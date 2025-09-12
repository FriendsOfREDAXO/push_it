<?php
$addon = rex_addon::get('push_it');

// Berechtigungen registrieren
if (rex::isBackend()) {
    rex_perm::register('push_it[]');
    rex_perm::register('push_it[subscriptions]');
    rex_perm::register('push_it[send]');
}

// Default-Konfiguration setzen
if (!$addon->hasConfig('subject')) $addon->setConfig('subject', 'mailto:info@example.com');
if (!$addon->hasConfig('publicKey')) $addon->setConfig('publicKey', '');
if (!$addon->hasConfig('privateKey')) $addon->setConfig('privateKey', '');
if (!$addon->hasConfig('backend_token')) $addon->setConfig('backend_token', '');
if (!$addon->hasConfig('backend_enabled')) $addon->setConfig('backend_enabled', true);
if (!$addon->hasConfig('frontend_enabled')) $addon->setConfig('frontend_enabled', true);
if (!$addon->hasConfig('admin_notifications')) $addon->setConfig('admin_notifications', true);
if (!$addon->hasConfig('default_icon')) $addon->setConfig('default_icon', '');
// Error Monitoring Konfiguration
if (!$addon->hasConfig('error_monitoring_enabled')) $addon->setConfig('error_monitoring_enabled', false);
if (!$addon->hasConfig('error_monitoring_interval')) $addon->setConfig('error_monitoring_interval', 3600); // 1 Stunde
if (!$addon->hasConfig('error_icon')) $addon->setConfig('error_icon', '');
if (!$addon->hasConfig('monitoring_mode')) $addon->setConfig('monitoring_mode', 'realtime');


// Cronjob registrieren (falls Cronjob-AddOn verfügbar ist)
if (rex_addon::exists('cronjob') && rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FriendsOfREDAXO\PushIt\Cronjob\SystemMonitoringCronjob::class);
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

// Error Monitoring System - ähnlich wie PHPMailer
// WICHTIG: Läuft auch ohne Backend-Session, damit Fehler immer erkannt werden
// Nur im "realtime" Modus aktiv - bei "cronjob" Modus läuft alles über Cronjob
if ($addon->getConfig('error_monitoring_enabled') && $addon->getConfig('monitoring_mode', 'realtime') === 'realtime') {
    rex_extension::register('RESPONSE_SHUTDOWN', function(rex_extension_point $ep) {
        \FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor::errorPush();
    });
}

// Extension Points für automatische Backend-Benachrichtigungen
// WICHTIG: Admin-Benachrichtigungen laufen auch ohne Backend-Session
// Nur im "realtime" Modus aktiv
if ($addon->getConfig('admin_notifications') && $addon->getConfig('monitoring_mode', 'realtime') === 'realtime') {
    // System-Fehler abfangen - ENTFERNT, da wir SystemErrorMonitor haben
    
    // AddOn-Updates überwachen über System-Log
    // WICHTIG: Läuft auch bei Frontend-Requests, damit Updates immer erkannt werden
    rex_extension::register('RESPONSE_SHUTDOWN', function(rex_extension_point $ep) use ($addon) {
        // Nur alle 30 Minuten prüfen um Performance zu schonen
        $lastCheck = (int) $addon->getConfig('last_update_check', 0);
        if (time() - $lastCheck < 1800) { // 30 Minuten
            return;
        }
        
        $addon->setConfig('last_update_check', time());
        
        // Log-Datei auf Update-Nachrichten prüfen
        $logFile = rex_path::log('system.log');
        if (!file_exists($logFile) || !filesize($logFile)) {
            return;
        }
        
        $lastLogSize = (int) $addon->getConfig('last_log_size', 0);
        $currentLogSize = filesize($logFile);
        
        // Nur neue Log-Einträge prüfen
        if ($currentLogSize <= $lastLogSize) {
            return;
        }
        
        $newContent = '';
        if ($lastLogSize > 0) {
            $file = fopen($logFile, 'r');
            fseek($file, $lastLogSize);
            $newContent = fread($file, $currentLogSize - $lastLogSize);
            fclose($file);
        } else {
            // Bei erstem Lauf nur die letzten 10KB lesen
            $file = fopen($logFile, 'r');
            fseek($file, max(0, $currentLogSize - 10240));
            $newContent = fread($file, 10240);
            fclose($file);
        }
        
        $addon->setConfig('last_log_size', $currentLogSize);
        
        // Nach Update-Meldungen suchen
        if (preg_match('/AddOn\s+(\w+)\s+updated\s+from\s+([\d\.]+)\s+to\s+version\s+([\d\.]+)/i', $newContent, $matches)) {
            $addonName = $matches[1];
            $oldVersion = $matches[2];
            $newVersion = $matches[3];
            
            // Prüfen ob bereits für diese Version benachrichtigt wurde
            $notifiedUpdates = json_decode($addon->getConfig('notified_updates', '{}'), true);
            $updateKey = $addonName . '_' . $newVersion;
            
            if (!isset($notifiedUpdates[$updateKey])) {
                $service = new \FriendsOfREDAXO\PushIt\Service\NotificationService();
                
                $title = "AddOn Update: {$addonName}";
                $body = "AddOn '{$addonName}' wurde von Version {$oldVersion} auf {$newVersion} aktualisiert.";
                
                $service->sendToBackendUsers($title, $body, '/redaxo/index.php?page=packages');
                
                // Als benachrichtigt markieren
                $notifiedUpdates[$updateKey] = time();
                $addon->setConfig('notified_updates', json_encode($notifiedUpdates));
            }
        }
        
        // Nach verfügbaren Updates suchen (nur wenn Install-AddOn verfügbar ist)
        if (rex_addon::exists('install') && rex_addon::get('install')->isAvailable()) {
            try {
                $lastUpdateNotification = (int) $addon->getConfig('last_available_update_check', 0);
                // Nur alle 6 Stunden nach verfügbaren Updates schauen
                if (time() - $lastUpdateNotification > 21600) {
                    $availableUpdates = \rex_install_packages::getUpdatePackages();
                    $availableCount = count($availableUpdates);
                    
                    $lastKnownCount = (int) $addon->getConfig('last_known_update_count', 0);
                    
                    if ($availableCount > 0 && $availableCount !== $lastKnownCount) {
                        $service = new \FriendsOfREDAXO\PushIt\Service\NotificationService();
                        
                        if ($availableCount === 1) {
                            $title = "1 AddOn-Update verfügbar";
                            $body = "Es ist ein Update für ein AddOn verfügbar.";
                        } else {
                            $title = "{$availableCount} AddOn-Updates verfügbar";
                            $body = "Es sind {$availableCount} Updates für AddOns verfügbar.";
                        }
                        
                        $service->sendToBackendUsers($title, $body, '/redaxo/index.php?page=packages/update');
                        
                        $addon->setConfig('last_known_update_count', $availableCount);
                    }
                    
                    $addon->setConfig('last_available_update_check', time());
                }
            } catch (Exception $e) {
                // Fehler beim Prüfen von Updates ignorieren
            }
        }
    });
}
