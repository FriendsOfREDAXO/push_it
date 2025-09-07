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
if (!$addon->hasConfig('backend_token')) $addon->setConfig('backend_token', '');
if (!$addon->hasConfig('backend_enabled')) $addon->setConfig('backend_enabled', true);
if (!$addon->hasConfig('frontend_enabled')) $addon->setConfig('frontend_enabled', true);
if (!$addon->hasConfig('admin_notifications')) $addon->setConfig('admin_notifications', true);

// Berechtigungen registrieren
if (rex::isBackend()) {
    rex_perm::register('push_it[]', 'PushIt - Grundberechtigung');
}



rex_api_function::register('push_it_subscribe', \FriendsOfREDAXO\PushIt\Api\Subscribe::class);
rex_api_function::register('push_it_unsubscribe', \FriendsOfREDAXO\PushIt\Api\Unsubscribe::class);

// Backend Assets hinzufügen wenn Backend aktiviert ist
if (rex::isBackend() && $addon->getConfig('backend_enabled')) {
    // Sprache ermitteln
    $lang = rex::getUser() ? rex::getUser()->getLanguage() : 'de';
    $supportedLangs = ['de', 'en'];
    if (!in_array($lang, $supportedLangs)) {
        $lang = 'de'; // Fallback auf Deutsch
    }
    
    // Sprachdatei laden
    $langFile = $addon->getAssetsUrl("lang/{$lang}.js");
    rex_view::addJsFile($langFile);
    
    // Sowohl frontend.js als auch backend.js laden für vollständige Funktionalität
    rex_view::addJsFile($addon->getAssetsUrl('frontend.js'));
    rex_view::addJsFile($addon->getAssetsUrl('backend.js'));
    
    // Sprache für JavaScript setzen
    rex_view::setJsProperty('push_it_language', $lang);
    
    // Public Key und Backend-Token für Backend verfügbar machen
    $publicKey = $addon->getConfig('publicKey');
    $backendToken = $addon->getConfig('backend_token');
    
    if ($publicKey) {
        rex_view::setJsProperty('push_it_public_key', $publicKey);
        rex_view::setJsProperty('push_it_backend_enabled', true);
        
        // Backend-Token nur übertragen wenn er existiert
        if ($backendToken) {
            rex_view::setJsProperty('push_it_backend_token', $backendToken);
        }
        
        // User-ID für Backend-Subscriptions übertragen
        $user = rex::getUser();
        if ($user) {
            rex_view::setJsProperty('push_it_user_id', $user->getId());
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
