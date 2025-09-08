<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Service;

use LimitIterator;
use rex;
use rex_addon;
use rex_formatter;
use rex_i18n;
use rex_log_entry;
use rex_log_file;
use rex_path;
use rex_sql;
use rex_url;
use rex_logger;
use IntlDateFormatter;

/**
 * System Error Monitor für Push-It
 * Überwacht system.log auf Fehler und sendet Push-Nachrichten
 * Basiert auf PHPMailer errorMail Funktionalität
 */
class SystemErrorMonitor
{
    private rex_addon $addon;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->addon = rex_addon::get('push_it');
        $this->notificationService = new NotificationService();
    }

    /**
     * Überwacht system.log auf Fehler und sendet Push-Benachrichtigungen
     * Diese Methode wird über RESPONSE_SHUTDOWN Event aufgerufen
     */
    public static function errorPush(): void
    {
        $monitor = new self();
        $monitor->checkAndSendErrorNotifications();
    }

    /**
     * Alternative Methode für Cronjob-basierte Überwachung
     * Kann verwendet werden wenn wenig Website-Traffic vorhanden ist
     */
    public static function cronCheck(): void
    {
        $addon = rex_addon::get('push_it');
        
        // Nur ausführen wenn Error Monitoring aktiviert ist
        if (!$addon->getConfig('error_monitoring_enabled', false)) {
            return;
        }
        
        $monitor = new self();
        $monitor->checkAndSendErrorNotifications(true); // true = Cronjob-Modus
        
        // Auch Update-Benachrichtigungen prüfen wenn aktiviert
        if ($addon->getConfig('admin_notifications', false)) {
            // Update-Prüfung aus boot.php hier einbauen
            $monitor->checkForAddonUpdates();
        }
    }

    /**
     * Hauptlogik für Fehlerüberwachung und -benachrichtigung
     */
    public function checkAndSendErrorNotifications(bool $isCronjobMode = false): void
    {
        // Prüfen ob Error Monitoring aktiviert ist
        if (!$this->isErrorMonitoringEnabled()) {
            return;
        }

        // Cronjob-Modus: Keine Zeit-basierten Checks - läuft nach eigenem Schedule
        if (!$isCronjobMode) {
            // Zeitbasierte Kontrolle: Nur alle 5 Minuten prüfen um Performance zu schonen
            $lastErrorCheck = (int) $this->addon->getConfig('last_error_check_time', 0);
            if (time() - $lastErrorCheck < 300) { // 5 Minuten
                return;
            }
            
            $this->addon->setConfig('last_error_check_time', time());
        }

        // Debug-Log (nur in Debug-Modus)
        if (rex::isDebugMode()) {
            $mode = $isCronjobMode ? 'Cronjob' : 'Realtime';
            rex_logger::factory()->info('Push-It Error Monitor (' . $mode . '): Starte Fehlerprüfung');
        }

        $logFile = rex_path::log('system.log');
        $lastSendTime = (int) $this->addon->getConfig('last_error_push_time', 0);

        // Prüfen ob Log-Datei Inhalt hat
        if (!file_exists($logFile) || !filesize($logFile)) {
            if (rex::isDebugMode()) {
                rex_logger::factory()->info('Push-It Error Monitor: Log-Datei nicht vorhanden oder leer');
            }
            return;
        }

        // Verwende rex_log_file wie im PHPMailer-System
        $file = rex_log_file::factory($logFile);
        $newErrors = [];
        $newErrorCount = 0;
        $maxErrors = 10; // Maximum Anzahl Fehler für Push-Notification

        // Debug: Aktuelle Log-Größe
        if (rex::isDebugMode()) {
            rex_logger::factory()->info('Push-It Error Monitor: Log-Datei Größe: ' . filesize($logFile) . ' Bytes');
            rex_logger::factory()->info('Push-It Error Monitor: Letzte Push-Zeit: ' . date('Y-m-d H:i:s', $lastSendTime));
        }

        /** @var rex_log_entry $entry */
        foreach (new LimitIterator($file, 0, 100) as $entry) { // Mehr Einträge prüfen
            $data = $entry->getData();
            $type = $data[0];
            $message = $data[1];
            $entryFile = $data[2] ?? '';
            $line = $data[3] ?? '';
            $url = $data[4] ?? '';
            $timestamp = $entry->getTimestamp();

            // Nur Fehler und Exceptions berücksichtigen, die NACH dem letzten Push aufgetreten sind
            if ((false !== stripos($type, 'error') || false !== stripos($type, 'exception')) 
                && $timestamp > $lastSendTime) {
                
                $newErrors[] = [
                    'time' => rex_formatter::intlDateTime($timestamp, [IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM]),
                    'type' => $type,
                    'message' => substr($message, 0, 100), // Kürzen für Push-Notification
                    'file' => basename($entryFile),
                    'line' => $line,
                    'url' => $url,
                    'timestamp' => $timestamp
                ];
                
                ++$newErrorCount;
                
                if ($newErrorCount >= $maxErrors) {
                    break;
                }
            }
        }

        // Debug: Gefundene neue Fehler
        if (rex::isDebugMode()) {
            rex_logger::factory()->info('Push-It Error Monitor: ' . $newErrorCount . ' neue Fehler seit letztem Push gefunden');
        }

        // Wenn keine neuen Fehler gefunden wurden, beenden
        if (empty($newErrors)) {
            if (rex::isDebugMode()) {
                rex_logger::factory()->info('Push-It Error Monitor: Keine neuen Fehler gefunden');
            }
            return;
        }

        // Im Cronjob-Modus: Keine zeitbasierten Beschränkungen
        if (!$isCronjobMode) {
            // Zusätzlicher Schutz: Mindestens 5 Minuten zwischen Push-Benachrichtigungen
            $timeSinceLastSend = time() - $lastSendTime;
            if ($timeSinceLastSend < 300) { // 5 Minuten absolutes Minimum
                if (rex::isDebugMode()) {
                    rex_logger::factory()->info('Push-It Error Monitor: Zu früh für neue Benachrichtigung (< 5 Min)');
                }
                return;
            }
        }

        // Debug-Log
        if (rex::isDebugMode()) {
            $mode = $isCronjobMode ? 'Cronjob' : 'Realtime';
            rex_logger::factory()->info('Push-It Error Monitor (' . $mode . '): Sende Benachrichtigung für ' . $newErrorCount . ' neue Fehler');
        }

        // Push-Benachrichtigung senden
        $this->sendErrorNotification($newErrors, $newErrorCount);

        // Konfiguration bei erfolgreichem Versand aktualisieren
        $this->addon->setConfig('last_error_push_time', time());
    }

    /**
     * Sendet Push-Benachrichtigung für System-Fehler
     */
    private function sendErrorNotification(array $errorMessages, int $errorCount): void
    {
        $serverName = rex::getServerName();
        $domain = rex::getServer();
        
        // URL-Informationen aus dem neuesten Fehler extrahieren
        $latestError = $errorMessages[0];
        $errorUrl = $latestError['url'] ?? '';
        $errorFile = $latestError['file'] ?? '';
        
        // Domain und URL-Info für die Nachricht aufbereiten
        $locationInfo = '';
        if (!empty($errorUrl)) {
            $parsedUrl = parse_url($errorUrl);
            $urlDomain = $parsedUrl['host'] ?? $domain;
            $urlPath = $parsedUrl['path'] ?? '';
            
            if ($urlDomain !== $domain) {
                $locationInfo = " (Domain: {$urlDomain})";
            }
            
            if (!empty($urlPath) && $urlPath !== '/') {
                $locationInfo .= " → {$urlPath}";
            }
        } elseif (!empty($errorFile)) {
            $locationInfo = " → {$errorFile}";
        }
        
        // Titel und Nachricht mit Domain/URL-Informationen erstellen
        if ($errorCount === 1) {
            $title = "System-Fehler auf {$serverName}";
            $message = $latestError['type'] . ': ' . $latestError['message'];
            if ($locationInfo) {
                $message .= $locationInfo;
            }
        } else {
            $title = "{$errorCount} System-Fehler auf {$serverName}";
            $message = "Mehrere Fehler aufgetreten. Letzter: " . $latestError['message'];
            if ($locationInfo) {
                $message .= $locationInfo;
            }
        }

        // Icon für Fehler-Benachrichtigungen
        $icon = $this->getErrorIcon();

        // Erweiterte Daten für Push-Benachrichtigung
        $pushData = [
            'title' => $title,
            'body' => $message,
            'icon' => $icon,
            'badge' => $icon,
            'tag' => 'system-error-' . time(), // Eindeutiger Tag
            'data' => [
                'type' => 'system_error',
                'server' => $serverName,
                'domain' => $domain,
                'error_count' => $errorCount,
                'timestamp' => time(),
                'error_url' => $errorUrl,
                'error_file' => $errorFile,
                'url' => rex_url::backendPage('system/log')
            ],
            'actions' => [
                [
                    'action' => 'view_log',
                    'title' => 'Log anzeigen'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Schließen'
                ]
            ]
        ];

        // An alle Backend-Benutzer mit 'system' Topic senden
        $this->sendToSystemSubscribers($pushData);
    }

    /**
     * Sendet Push-Benachrichtigung an alle Backend-Benutzer mit System-Topic
     */
    private function sendToSystemSubscribers(array $pushData): void
    {
        // Nutze NotificationService für das eigentliche Senden
        $this->notificationService->sendToBackendUsers(
            $pushData['title'],
            $pushData['body'],
            $pushData['data']['url'] ?? '',
            ['system', 'admin'], // Topics für System-Benachrichtigungen
            [
                'icon' => $pushData['icon'],
                'badge' => $pushData['badge'],
                'tag' => $pushData['tag'],
                'data' => $pushData['data'],
                'actions' => $pushData['actions']
            ]
        );
    }

    /**
     * Prüft ob Error Monitoring aktiviert ist
     */
    private function isErrorMonitoringEnabled(): bool
    {
        return (bool) $this->addon->getConfig('error_monitoring_enabled', false);
    }

    /**
     * Liefert Icon für Fehler-Benachrichtigungen
     */
    private function getErrorIcon(): string
    {
        $defaultIcon = $this->addon->getConfig('default_icon', '');
        
        // Spezifisches Error-Icon falls konfiguriert, sonst Default-Icon
        $errorIcon = $this->addon->getConfig('error_icon', '');
        
        if (!empty($errorIcon)) {
            return rex_url::media($errorIcon);
        }
        
        if (!empty($defaultIcon)) {
            return rex_url::media($defaultIcon);
        }
        
        // Fallback auf Standard REDAXO Icon
        return rex_url::assets('core/redaxo_logo.svg');
    }

    /**
     * Konfiguriert Error Monitoring Intervall (in Sekunden)
     */
    public function setErrorMonitoringInterval(int $seconds): void
    {
        $this->addon->setConfig('error_monitoring_interval', $seconds);
    }

    /**
     * Aktiviert/Deaktiviert Error Monitoring
     */
    public function setErrorMonitoringEnabled(bool $enabled): void
    {
        $this->addon->setConfig('error_monitoring_enabled', $enabled);
    }

    /**
     * Setzt spezifisches Icon für Error-Benachrichtigungen
     */
    public function setErrorIcon(string $mediaFile): void
    {
        $this->addon->setConfig('error_icon', $mediaFile);
    }

    /**
     * Liefert aktuellen Status des Error Monitoring
     */
    public function getErrorMonitoringStatus(): array
    {
        return [
            'enabled' => $this->isErrorMonitoringEnabled(),
            'interval' => (int) $this->addon->getConfig('error_monitoring_interval', 3600),
            'last_check' => (int) $this->addon->getConfig('last_error_push_time', 0),
            'error_icon' => $this->addon->getConfig('error_icon', ''),
            'subscriber_count' => $this->getSystemSubscriberCount()
        ];
    }

    /**
     * Zählt Backend-Benutzer mit System-Benachrichtigungen
     */
    private function getSystemSubscriberCount(): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT COUNT(*) as count 
            FROM rex_push_it_subscriptions 
            WHERE active = 1 
            AND user_type = 'backend' 
            AND (topics LIKE '%system%' OR topics LIKE '%admin%')
        ");
        
        return (int) $sql->getValue('count');
    }

    /**
     * Prüft auf AddOn-Updates (für Cronjob-Verwendung)
     */
    private function checkForAddonUpdates(): void
    {
        // Nur alle 30 Minuten prüfen um Performance zu schonen
        $lastCheck = (int) $this->addon->getConfig('last_update_check', 0);
        if (time() - $lastCheck < 1800) { // 30 Minuten
            return;
        }
        
        $this->addon->setConfig('last_update_check', time());
        
        // Log-Datei auf Update-Nachrichten prüfen
        $logFile = rex_path::log('system.log');
        if (!file_exists($logFile) || !filesize($logFile)) {
            return;
        }
        
        $lastLogSize = (int) $this->addon->getConfig('last_log_size', 0);
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
        
        $this->addon->setConfig('last_log_size', $currentLogSize);
        
        // Nach Update-Meldungen suchen
        if (preg_match('/AddOn\s+(\w+)\s+updated\s+from\s+([\d\.]+)\s+to\s+version\s+([\d\.]+)/i', $newContent, $matches)) {
            $addonName = $matches[1];
            $oldVersion = $matches[2];
            $newVersion = $matches[3];
            
            // Prüfen ob bereits für diese Version benachrichtigt wurde
            $notifiedUpdates = json_decode($this->addon->getConfig('notified_updates', '{}'), true);
            $updateKey = $addonName . '_' . $newVersion;
            
            if (!isset($notifiedUpdates[$updateKey])) {
                $title = "AddOn Update: {$addonName}";
                $message = "AddOn '{$addonName}' wurde von Version {$oldVersion} auf {$newVersion} aktualisiert.";
                
                $pushData = [
                    'title' => $title,
                    'body' => $message,
                    'icon' => $this->getErrorIcon(),
                    'badge' => $this->getErrorIcon(),
                    'tag' => 'addon-update-' . $updateKey,
                    'data' => [
                        'type' => 'addon_update',
                        'addon' => $addonName,
                        'old_version' => $oldVersion,
                        'new_version' => $newVersion,
                        'timestamp' => time(),
                        'url' => rex_url::backendPage('packages')
                    ]
                ];
                
                $this->sendToSystemSubscribers($pushData);
                
                // Als benachrichtigt markieren
                $notifiedUpdates[$updateKey] = time();
                $this->addon->setConfig('notified_updates', json_encode($notifiedUpdates));
            }
        }
        
        // Nach verfügbaren Updates suchen (nur wenn Install-AddOn verfügbar ist)
        if (rex_addon::exists('install') && rex_addon::get('install')->isAvailable()) {
            try {
                $lastUpdateNotification = (int) $this->addon->getConfig('last_available_update_check', 0);
                // Nur alle 6 Stunden nach verfügbaren Updates schauen
                if (time() - $lastUpdateNotification > 21600) {
                    $availableUpdates = \rex_install_packages::getUpdatePackages();
                    $availableCount = count($availableUpdates);
                    
                    $lastKnownCount = (int) $this->addon->getConfig('last_known_update_count', 0);
                    
                    if ($availableCount > 0 && $availableCount !== $lastKnownCount) {
                        if ($availableCount === 1) {
                            $title = "1 AddOn-Update verfügbar";
                            $message = "Es ist ein Update für ein AddOn verfügbar.";
                        } else {
                            $title = "{$availableCount} AddOn-Updates verfügbar";
                            $message = "Es sind {$availableCount} Updates für AddOns verfügbar.";
                        }
                        
                        $pushData = [
                            'title' => $title,
                            'body' => $message,
                            'icon' => $this->getErrorIcon(),
                            'badge' => $this->getErrorIcon(),
                            'tag' => 'updates-available-' . $availableCount,
                            'data' => [
                                'type' => 'updates_available',
                                'count' => $availableCount,
                                'timestamp' => time(),
                                'url' => rex_url::backendPage('packages/update')
                            ]
                        ];
                        
                        $this->sendToSystemSubscribers($pushData);
                        
                        $this->addon->setConfig('last_known_update_count', $availableCount);
                    }
                    
                    $this->addon->setConfig('last_available_update_check', time());
                }
            } catch (\Exception $e) {
                // Fehler beim Prüfen von Updates ignorieren
                rex_logger::logException($e);
            }
        }
    }
}
