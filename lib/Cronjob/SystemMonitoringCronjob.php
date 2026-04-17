<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Cronjob;

use rex_cronjob;
use rex_i18n;
use rex_addon;
use FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor;

/**
 * Cronjob für Push-It System Monitoring
 * Überwacht System-Fehler und AddOn-Updates via Cronjob statt RESPONSE_SHUTDOWN
 */
class SystemMonitoringCronjob extends rex_cronjob
{

    /**
     * Führt das System Monitoring aus
     */
    public function execute(): bool
    {
        $addon = rex_addon::get('push_it');
        $monitoringType = $this->getParam('monitoring_type', 'full');

        try {
            $executed = [];

            // Error Monitoring
            if (($monitoringType === 'full' || $monitoringType === 'errors_only')
                && $addon->getConfig('error_monitoring_enabled', false)) {

                $monitor = new SystemErrorMonitor();
                $monitor->checkAndSendErrorNotifications();
                $executed[] = 'Error Monitoring';
            }

            // Update Monitoring
            if (($monitoringType === 'full' || $monitoringType === 'updates_only')
                && $addon->getConfig('admin_notifications', false)) {

                $this->checkForUpdates();
                $executed[] = 'Update Monitoring';
            }

            // Statistiken aktualisieren
            $this->updateCronjobStats();

            if ($executed === []) {
                $this->setMessage('Kein Monitoring aktiviert oder ausgewählt');
                return true;
            }

            $this->setMessage('Ausgeführt: ' . implode(', ', $executed));
            return true;

        } catch (\Exception $e) {
            $this->setMessage('Fehler beim System Monitoring: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Prüft auf AddOn-Updates (aus SystemErrorMonitor extrahiert)
     */
    private function checkForUpdates(): void
    {
        $addon = rex_addon::get('push_it');

        // Nur alle 30 Minuten prüfen um Performance zu schonen
        $lastCheck = (int) $addon->getConfig('last_update_check', 0);
        if (time() - $lastCheck < 1800) { // 30 Minuten
            return;
        }

        $addon->setConfig('last_update_check', time());

        // Log-Datei auf Update-Nachrichten prüfen
        $logFile = \rex_path::log('system.log');
        if (!file_exists($logFile) || !filesize($logFile)) {
            return;
        }

        $lastLogSize = (int) $addon->getConfig('last_log_size', 0);
        $currentLogSize = filesize($logFile);

        // Nur neue Log-Einträge prüfen
        if ($currentLogSize === false || $currentLogSize <= $lastLogSize) {
            return;
        }

        $newContent = $this->readLogTail($logFile, $lastLogSize, $currentLogSize);
        if ($newContent === '') {
            return;
        }

        $addon->setConfig('last_log_size', $currentLogSize);

        // Nach Update-Meldungen suchen
        if (preg_match('/AddOn\s+(\w+)\s+updated\s+from\s+([\d\.]+)\s+to\s+version\s+([\d\.]+)/i', $newContent, $matches)) {
            $addonName = $matches[1];
            $oldVersion = $matches[2];
            $newVersion = $matches[3];
            
            // Prüfen ob bereits für diese Version benachrichtigt wurde
            $notifiedUpdates = json_decode($addon->getConfig('notified_updates', '{}'), true) ?? [];
            $updateKey = $addonName . '_' . $newVersion;

            if (!isset($notifiedUpdates[$updateKey])) {
                $service = new \FriendsOfREDAXO\PushIt\Service\NotificationService();

                $title = rex_i18n::msg('pushit_addon_updated_title', $addonName);
                $body = rex_i18n::msg('pushit_addon_updated_body', $addonName, $oldVersion, $newVersion);

                $service->sendToBackendUsers($title, $body, '/redaxo/index.php?page=packages');

                // Als benachrichtigt markieren
                $notifiedUpdates[$updateKey] = time();
                $addon->setConfig('notified_updates', json_encode($notifiedUpdates));
            }
        }

        // Nach verfügbaren Updates suchen (nur wenn Install-AddOn verfügbar ist)
        if (\rex_addon::exists('install') && \rex_addon::get('install')->isAvailable()) {
            try {
                $lastUpdateNotification = (int) $addon->getConfig('last_available_update_check', 0);
                // Nur alle 6 Stunden nach verfügbaren Updates schauen
                if (time() - $lastUpdateNotification > 21600) {
                    $availableUpdates = \rex_install_packages::getUpdatePackages();
                    $availableCount = count($availableUpdates);

                    $lastKnownCount = (int) $addon->getConfig('last_known_update_count', 0);

                    if ($availableCount > 0 && $availableCount !== $lastKnownCount) {
                        $service = new \FriendsOfREDAXO\PushIt\Service\NotificationService();

                        $title = rex_i18n::msg('pushit_addon_updates_available_title', $availableCount);
                        $body = rex_i18n::msg('pushit_addon_updates_available_body', $availableCount);

                        $service->sendToBackendUsers($title, $body, '/redaxo/index.php?page=packages/update');

                        $addon->setConfig('last_known_update_count', $availableCount);
                    }

                    $addon->setConfig('last_available_update_check', time());
                }
            } catch (\Exception $e) {
                // Fehler beim Prüfen von Updates ignorieren
            }
        }
    }

    /**
     * Liest das Log-Tail ab einem bestimmten Offset
     */
    private function readLogTail(string $logFile, int $fromOffset, int $fileSize): string
    {
        $handle = fopen($logFile, 'r');
        if ($handle === false) {
            return '';
        }

        try {
            if ($fromOffset > 0) {
                fseek($handle, $fromOffset);
                $length = $fileSize - $fromOffset;
            } else {
                // Beim ersten Lauf nur die letzten 10 KB einlesen
                $length = min($fileSize, 10240);
                fseek($handle, $fileSize - $length);
            }

            if ($length <= 0) {
                return '';
            }

            $content = fread($handle, $length);
            return is_string($content) ? $content : '';
        } finally {
            fclose($handle);
        }
    }

    /**
     * Aktualisiert Cronjob-Statistiken
     */
    private function updateCronjobStats(): void
    {
        $addon = rex_addon::get('push_it');
        $stats = json_decode($addon->getConfig('cronjob_stats', '{}'), true) ?? [];
        $stats['last_run'] = time();
        $stats['total_runs'] = ($stats['total_runs'] ?? 0) + 1;

        $addon->setConfig('cronjob_stats', json_encode($stats));
    }

    /**
     * Liefert verfügbare Umgebungen (für Cronjob-Konfiguration)
     */
    public function getTypeName(): string
    {
        return rex_i18n::msg('pushit_cronjob_system_monitoring');
    }

    /**
     * Liefert Parameter-Felder für das Cronjob-Formular
     */
    public function getParamFields(): array
    {
        return []; // Vereinfacht - Parameter können später erweitert werden
    }

    /**
     * Liefert Cronjob-Statistiken für das Dashboard
     *
     * @return array{last_run: int, total_runs: int, is_configured: bool}
     */
    public static function getCronjobStats(): array
    {
        $addon = \rex_addon::get('push_it');
        $stats = json_decode($addon->getConfig('cronjob_stats', '{}'), true) ?? [];

        return [
            'last_run' => (int) ($stats['last_run'] ?? 0),
            'total_runs' => (int) ($stats['total_runs'] ?? 0),
            'is_configured' => self::isCronjobConfigured(),
        ];
    }

    /**
     * Prüft ob ein Push-It Cronjob konfiguriert ist
     */
    public static function isCronjobConfigured(): bool
    {
        if (!\rex_addon::exists('cronjob') || !\rex_addon::get('cronjob')->isAvailable()) {
            return false;
        }

        $sql = \rex_sql::factory();
        $sql->setQuery("
            SELECT COUNT(*) as count 
            FROM rex_cronjob 
            WHERE type = ? 
            AND status = 1
        ", [self::class]);
        
        return (int) $sql->getValue('count') > 0;
    }
}
