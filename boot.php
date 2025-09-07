<?php
$addon = rex_addon::get('push_it');

// Composer Autoloader laden
if (file_exists($addon->getPath('vendor/autoload.php'))) {
    require_once $addon->getPath('vendor/autoload.php');
}

// Default-Konfiguration setzen
if (!$addon->hasConfig('subject')) $addon->setConfig('subject', 'mailto:info@example.com');
if (!$addon->hasConfig('publicKey')) $addon->setConfig('publicKey', '');
if (!$addon->hasConfig('privateKey')) $addon->setConfig('privateKey', '');
if (!$addon->hasConfig('backend_enabled')) $addon->setConfig('backend_enabled', true);
if (!$addon->hasConfig('frontend_enabled')) $addon->setConfig('frontend_enabled', true);
if (!$addon->hasConfig('admin_notifications')) $addon->setConfig('admin_notifications', true);

// Berechtigungen registrieren
if (rex::isBackend()) {
    rex_perm::register('push_it[]', 'PushIt - Grundberechtigung');
}

// API-Funktionen registrieren
// Sicherstellen dass die Klassen verfügbar sind
require_once $addon->getPath('lib/Api/Subscribe.php');
require_once $addon->getPath('lib/Api/Unsubscribe.php');
require_once $addon->getPath('lib/Service/SecurityService.php');

rex_api_function::register('push_it_subscribe', \FriendsOfREDAXO\PushIt\Api\Subscribe::class);
rex_api_function::register('push_it_unsubscribe', \FriendsOfREDAXO\PushIt\Api\Unsubscribe::class);

// Backend Assets hinzufügen wenn Backend aktiviert ist
if (rex::isBackend() && $addon->getConfig('backend_enabled')) {
    // CSP Nonce für Inline JavaScript generieren
    $nonce = \FriendsOfREDAXO\PushIt\Service\SecurityService::generateNonce();
    rex_view::setJsProperty('push_it_nonce', $nonce);
    
    // Sowohl frontend.js als auch backend.js laden für vollständige Funktionalität
    rex_view::addJsFile($addon->getAssetsUrl('frontend.js'));
    rex_view::addJsFile($addon->getAssetsUrl('backend.js'));
    
    // Public Key für Backend verfügbar machen
    $publicKey = $addon->getConfig('publicKey');
    if ($publicKey) {
        rex_view::setJsProperty('push_it_public_key', $publicKey);
        rex_view::setJsProperty('push_it_backend_enabled', true);
        
        // Für Backend-Benutzer: Sicheren Token generieren
        $currentUser = rex::getUser();
        if ($currentUser) {
            $secureToken = \FriendsOfREDAXO\PushIt\Service\SecurityService::generateUserToken($currentUser->getId());
            rex_view::setJsProperty('push_it_user_token', $secureToken);
        }
    }
}

// Extension Points für automatische Backend-Benachrichtigungen
if (rex::isBackend() && $addon->getConfig('admin_notifications')) {
    // System-Fehler abfangen
    rex_extension::register('SYSTEM_ERROR', function(rex_extension_point $ep) {
        $addon = rex_addon::get('push_it');
        $service = new \FriendsOfREDAXO\PushIt\Service\NotificationService();
        
        $title = 'System-Fehler aufgetreten';
        $body = 'Ein Fehler wurde im System registriert. Bitte prüfen Sie das Error-Log.';
        
        $service->sendToBackendUsers($title, $body, '/redaxo/index.php?page=system');
    });
    
    // Neue Addons installiert
    rex_extension::register('PACKAGES_INCLUDED', function(rex_extension_point $ep) {
        // Nur bei Änderungen benachrichtigen
        static $last_packages = null;
        $current_packages = array_keys(rex_addon::getRegisteredAddons());
        
        if ($last_packages !== null && $current_packages !== $last_packages) {
            $addon = rex_addon::get('push_it');
            $service = new \FriendsOfREDAXO\PushIt\Service\NotificationService();
            
            $title = 'AddOn-Änderung erkannt';
            $body = 'AddOns wurden installiert oder deinstalliert.';
            
            $service->sendToBackendUsers($title, $body, '/redaxo/index.php?page=packages');
        }
        
        $last_packages = $current_packages;
    });
}

// Token-Cleanup: Abgelaufene Tokens täglich bereinigen
if (rex::isBackend()) {
    $lastCleanup = $addon->getConfig('last_token_cleanup', 0);
    $now = time();
    // Cleanup alle 24 Stunden
    if ($now - $lastCleanup > 86400) {
        try {
            $deletedTokens = \FriendsOfREDAXO\PushIt\Service\SecurityService::cleanupExpiredTokens();
            $addon->setConfig('last_token_cleanup', $now);
            if ($deletedTokens > 0) {
                error_log("Push It: $deletedTokens abgelaufene Tokens bereinigt");
            }
        } catch (Exception $e) {
            error_log("Push It Token Cleanup Fehler: " . $e->getMessage());
        }
    }
}
