<?php
namespace FriendsOfREDAXO\PushIt\Service;

use rex_sql;
use rex_fragment;
use rex_url;
use rex_addon;
use rex_i18n;
use rex;

class BackendNotificationManager
{
    private $addon;
    
    public function __construct()
    {
        $this->addon = rex_addon::get('push_it');
    }
    
    /**
     * Prüft ob VAPID-Schlüssel verfügbar sind
     */
    public function hasVapidKeys(): bool
    {
        return !empty($this->addon->getConfig('publicKey'));
    }
    
    /**
     * Rendert den PushIt JavaScript-Code
     */
    public function renderJavaScript(): string
    {
        $publicKey = $this->addon->getConfig('publicKey');
        $currentUser = rex::getUser();
        $userId = $currentUser ? $currentUser->getId() : null;
        
        return '<script src="' . $this->addon->getAssetsUrl('frontend.js') . '"></script>
        <script src="' . $this->addon->getAssetsUrl('backend.js') . '"></script>
        <script type="text/javascript" nonce="' . \rex_response::getNonce() . '">
            window.PushItPublicKey = ' . json_encode($publicKey) . ';
            window.PushItUserId = ' . json_encode($userId) . ';
        </script>';
    }
    
    /**
     * Rendert das Info-Panel
     */
    public function renderInfoPanel(bool $isAdmin): string
    {
        return '<div class="alert alert-info">
            <h4><i class="rex-icon fa-info-circle"></i> ' . rex_i18n::msg('pushit_backend_notifications_title') . '</h4>
            <p>' . rex_i18n::msg('pushit_backend_notifications_info') . 
            ($isAdmin ? ' ' . rex_i18n::msg('pushit_admin_additional_access') : '') . '</p>
        </div>';
    }
    
    /**
     * Rendert das Backend-Subscription Panel
     */
    public function renderBackendSubscriptionPanel(bool $isAdmin): string
    {
        $topics = $isAdmin ? 'system,admin,critical,editorial' : 'editorial';
        $nonce = \rex_response::getNonce();
        
        $content = '
        <div class="well">
            <h4>' . rex_i18n::msg('pushit_activate_backend_notifications') . '</h4>
            <p>' . rex_i18n::msg('pushit_activate_push_notifications') . '</p>
            <button class="btn btn-success" id="pushit-subscribe-backend">
                <i class="rex-icon fa-bell"></i> ' . rex_i18n::msg('pushit_activate_backend_notifications_button') . '
            </button>
            <button class="btn btn-default" id="pushit-status-check">
                <i class="rex-icon fa-info"></i> ' . rex_i18n::msg('pushit_status_check') . '
            </button>
            <button class="btn btn-warning" id="pushit-disable">
                <i class="rex-icon fa-bell-slash"></i> ' . rex_i18n::msg('pushit_deactivate') . '
            </button>
            <br><br>
            <button class="btn btn-xs btn-default" id="pushit-reset">
                <i class="rex-icon fa-refresh"></i> ' . rex_i18n::msg('pushit_reset_query') . '
            </button>
            <small class="help-block">' . rex_i18n::msg('pushit_reset_query_info') . '</small>
            
            <script type="text/javascript" nonce="' . $nonce . '">
                document.getElementById("pushit-subscribe-backend").addEventListener("click", function() {
                    PushIt.requestBackend("' . $topics . '");
                });
                document.getElementById("pushit-status-check").addEventListener("click", function() {
                    PushIt.getStatus().then(s => alert(s.isSubscribed ? PushIt.i18n.get("status_active") : PushIt.i18n.get("status_inactive")));
                });
                document.getElementById("pushit-disable").addEventListener("click", function() {
                    PushIt.disable();
                });
                document.getElementById("pushit-reset").addEventListener("click", function() {
                    PushItReset();
                });
            </script>';

        if (!$isAdmin) {
            $content .= '
            <div class="alert alert-info" style="margin-top: 15px;">
                <strong>' . rex_i18n::msg('pushit_editor_notifications') . ':</strong> ' . rex_i18n::msg('pushit_editor_notifications_info') . '
            </div>';
        }
        
        $content .= '
            <hr>
            <details>
                <summary><strong>' . rex_i18n::msg('pushit_notifications_blocked_help_title') . '</strong></summary>
                <div class="help-block" style="margin-top: 10px;">
                    <strong>🔧 Safari:</strong><br>
                    ' . rex_i18n::msg('pushit_safari_step1') . '<br>
                    ' . rex_i18n::msg('pushit_safari_step2') . '<br>
                    ' . rex_i18n::msg('pushit_safari_step3') . '<br>
                    ' . rex_i18n::msg('pushit_safari_step4') . '<br><br>
                    
                    <strong>🔧 Chrome:</strong><br>
                    ' . rex_i18n::msg('pushit_chrome_step1') . '<br>
                    ' . rex_i18n::msg('pushit_chrome_step2') . '<br>
                    ' . rex_i18n::msg('pushit_chrome_step3') . '<br><br>
                    
                    <strong>🔧 Firefox:</strong><br>
                    ' . rex_i18n::msg('pushit_firefox_step1') . '<br>
                    ' . rex_i18n::msg('pushit_firefox_step2') . '<br>
                    ' . rex_i18n::msg('pushit_firefox_step3') . '<br>
                    ' . rex_i18n::msg('pushit_firefox_step4') . '
                </div>
            </details>
        </div>';
        
        return $content;
    }
    
    /**
     * Rendert die Quick-Notification Buttons (nur für Admins)
     */
    public function renderQuickNotificationPanel(): string
    {
        $nonce = \rex_response::getNonce();
        
        return '
        <div class="row">
            <div class="col-md-4">
                <button class="btn btn-danger btn-block" id="quick-critical">
                    <i class="rex-icon fa-exclamation-triangle"></i><br>
                    ' . rex_i18n::msg('pushit_critical_error') . '
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-warning btn-block" id="quick-warning">
                    <i class="rex-icon fa-warning"></i><br>
                    ' . rex_i18n::msg('pushit_system_warning') . '
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-info btn-block" id="quick-info">
                    <i class="rex-icon fa-info-circle"></i><br>
                    ' . rex_i18n::msg('pushit_information') . '
                </button>
            </div>
        </div>

        <script type="text/javascript" nonce="' . $nonce . '">
        function sendQuickNotification(type, title, body) {
            if (confirm(PushIt.i18n.get("quick_notification_confirm_prefix") + " " + type.toUpperCase() + "-" + PushIt.i18n.get("quick_notification_confirm_suffix") + "?" + String.fromCharCode(10) + String.fromCharCode(10) + title + String.fromCharCode(10) + body)) {
                const urlParams = new URLSearchParams({
                    title: title,
                    body: body,
                    url: "' . rex_url::backendPage('system') . '",
                    user_type: "backend",
                    topics: "system,admin," + type,
                    send: "1"
                });
                
                fetch("' . rex_url::backendPage('push_it/send') . '", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: urlParams
                }).then(response => {
                    if (response.ok) {
                        alert(PushIt.i18n.get("notification_sent_success"));
                    } else {
                        alert(PushIt.i18n.get("notification_sent_error"));
                    }
                }).catch(error => {
                    alert(PushIt.i18n.get("network_error") + ": " + error.message);
                });
            }
        }
        
        document.getElementById("quick-critical").addEventListener("click", function() {
            sendQuickNotification("critical", PushIt.i18n.get("critical_error_title"), PushIt.i18n.get("critical_error_message"));
        });
        
        document.getElementById("quick-warning").addEventListener("click", function() {
            sendQuickNotification("warning", PushIt.i18n.get("system_warning_title"), PushIt.i18n.get("system_warning_message"));
        });
        
        document.getElementById("quick-info").addEventListener("click", function() {
            sendQuickNotification("info", PushIt.i18n.get("system_info_title"), PushIt.i18n.get("system_info_message"));
        });
        </script>';
    }
    
    /**
     * Rendert automatische Benachrichtigung-Infos
     */
    public function renderAutomaticNotificationsInfo(bool $isAdmin): string
    {
        $adminNotificationsEnabled = $this->addon->getConfig('admin_notifications', false);
        
        return '
        <p>' . rex_i18n::msg('pushit_automatic_events_info') . '</p>
        <ul class="list-group">
            <li class="list-group-item">
                <strong>' . rex_i18n::msg('pushit_system_errors') . '</strong>
                <span class="pull-right">
                    <span class="label label-' . ($adminNotificationsEnabled ? 'success' : 'default') . '">
                        ' . ($adminNotificationsEnabled ? rex_i18n::msg('pushit_enabled') : rex_i18n::msg('pushit_disabled')) . '
                    </span>
                </span>
            </li>
            <li class="list-group-item">
                <strong>' . rex_i18n::msg('pushit_addon_changes') . '</strong>
                <span class="pull-right">
                    <span class="label label-' . ($adminNotificationsEnabled ? 'success' : 'default') . '">
                        ' . ($adminNotificationsEnabled ? rex_i18n::msg('pushit_enabled') : rex_i18n::msg('pushit_disabled')) . '
                    </span>
                </span>
            </li>
        </ul>

        <p><a href="' . rex_url::backendPage('push_it') . '" class="btn btn-default">
            <i class="rex-icon fa-cog"></i> ' . rex_i18n::msg('pushit_settings_change') . '
        </a></p>';
    }
    
    /**
     * Lädt Backend-Subscription Statistiken
     */
    public function getBackendStatistics(): array
    {
        try {
            $sql = rex_sql::factory();
            
            // Einfache Abfrage für Gesamt- und aktive Backend-Subscriptions
            $sql->setQuery("
                SELECT 
                    COUNT(*) as total_backend,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_backend
                FROM rex_push_it_subscriptions 
                WHERE user_type = 'backend'
            ");
            
            $totalBackend = 0;
            $activeBackend = 0;
            
            if ($sql->getRows() > 0) {
                $totalBackend = (int) $sql->getValue('total_backend');
                $activeBackend = (int) $sql->getValue('active_backend');
            }
            
            // Separate Abfragen für Topics um LIKE-Performance zu verbessern
            $systemCount = 0;
            $adminCount = 0;
            $editorialCount = 0;
            $criticalCount = 0;
            
            if ($totalBackend > 0) {
                $sql->setQuery("SELECT COUNT(*) as count FROM rex_push_it_subscriptions WHERE user_type = 'backend' AND active = 1 AND topics LIKE '%system%'");
                if ($sql->getRows() > 0) $systemCount = (int) $sql->getValue('count');
                
                $sql->setQuery("SELECT COUNT(*) as count FROM rex_push_it_subscriptions WHERE user_type = 'backend' AND active = 1 AND topics LIKE '%admin%'");
                if ($sql->getRows() > 0) $adminCount = (int) $sql->getValue('count');
                
                $sql->setQuery("SELECT COUNT(*) as count FROM rex_push_it_subscriptions WHERE user_type = 'backend' AND active = 1 AND topics LIKE '%editorial%'");
                if ($sql->getRows() > 0) $editorialCount = (int) $sql->getValue('count');
                
                $sql->setQuery("SELECT COUNT(*) as count FROM rex_push_it_subscriptions WHERE user_type = 'backend' AND active = 1 AND topics LIKE '%critical%'");
                if ($sql->getRows() > 0) $criticalCount = (int) $sql->getValue('count');
            }
            
            return [
                'total_backend' => $totalBackend,
                'active_backend' => $activeBackend,
                'system_subscribers' => $systemCount,
                'admin_subscribers' => $adminCount,
                'editorial_subscribers' => $editorialCount,
                'critical_subscribers' => $criticalCount
            ];
            
        } catch (\Exception $e) {
            // Fallback bei Fehlern
            return [
                'total_backend' => 0,
                'active_backend' => 0,
                'system_subscribers' => 0,
                'admin_subscribers' => 0,
                'editorial_subscribers' => 0,
                'critical_subscribers' => 0
            ];
        }
    }
    
    /**
     * Rendert das Statistiken-Panel
     */
    public function renderStatisticsPanel(bool $isAdmin): string
    {
        try {
            $stats = $this->getBackendStatistics();
            
            $content = '
            <div class="row">
                <div class="col-md-3 text-center">
                    <h3>' . $stats['total_backend'] . '</h3>
                    <p>' . rex_i18n::msg('pushit_backend_subscriptions') . '</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-success">' . $stats['active_backend'] . '</h3>
                    <p>' . rex_i18n::msg('pushit_active_subscriptions') . '</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-primary">' . $stats['editorial_subscribers'] . '</h3>
                    <p>' . rex_i18n::msg('pushit_editorial_topic') . '</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-warning">' . ($stats['system_subscribers'] + $stats['admin_subscribers'] + $stats['critical_subscribers']) . '</h3>
                    <p>' . rex_i18n::msg('pushit_admin_topics') . '</p>
                </div>
            </div>';
            
            if ($isAdmin) {
                $content .= '
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-3 text-center">
                        <small class="text-muted">' . rex_i18n::msg('pushit_system_topic') . ': ' . $stats['system_subscribers'] . '</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">' . rex_i18n::msg('pushit_admin_topic') . ': ' . $stats['admin_subscribers'] . '</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">' . rex_i18n::msg('pushit_critical_topic') . ': ' . $stats['critical_subscribers'] . '</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">' . rex_i18n::msg('pushit_editorial_topic') . ': ' . $stats['editorial_subscribers'] . '</small>
                    </div>
                </div>';
            }
            
            return $content;
            
        } catch (\Exception $e) {
            return '<div class="alert alert-warning">
                <h4>' . rex_i18n::msg('pushit_statistics_temporarily_unavailable') . '</h4>
                <p>' . rex_i18n::msg('pushit_backend_statistics_loading_error') . '</p>
            </div>';
        }
    }
    
    /**
     * Rendert eine Warnung wenn VAPID-Schlüssel fehlen
     */
    public function renderVapidWarning(): string
    {
        return \rex_view::warning('
            <h4>' . rex_i18n::msg('pushit_vapid_keys_missing') . '</h4>
            <p>' . rex_i18n::msg('pushit_vapid_keys_required') . '</p>
            <p><a href="' . rex_url::backendPage('push_it') . '" class="btn btn-primary">
                <i class="rex-icon fa-key"></i> ' . rex_i18n::msg('pushit_generate_vapid_keys') . '
            </a></p>
        ');
    }

    /**
     * Rendert Status-Informationen für System Error Monitoring
     */
    public function renderErrorMonitoringInfo(): string
    {
        if (!class_exists('\\FriendsOfREDAXO\\PushIt\\Service\\SystemErrorMonitor')) {
            return '';
        }

        $errorMonitor = new \FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor();
        $status = $errorMonitor->getErrorMonitoringStatus();

        $statusClass = $status['enabled'] ? 'success' : 'info';
        $statusIcon = $status['enabled'] ? 'fa-check-circle' : 'fa-info-circle';
        $statusText = $status['enabled'] ? 'Aktiviert' : 'Deaktiviert';

        // Monitoring-Modus ermitteln
        $monitoringMode = $this->addon->getConfig('monitoring_mode', 'realtime');
        $monitoringModeText = $monitoringMode === 'cronjob' ? 'Cronjob' : 'Echtzeit';

        $intervalText = '';
        if ($status['interval'] >= 3600) {
            $intervalText = ($status['interval'] / 3600) . ' Stunde(n)';
        } elseif ($status['interval'] >= 60) {
            $intervalText = ($status['interval'] / 60) . ' Minute(n)';
        } else {
            $intervalText = $status['interval'] . ' Sekunde(n)';
        }

        $lastCheckText = $status['last_check'] > 0 
            ? date('d.m.Y H:i:s', $status['last_check']) 
            : 'Noch nie';

        // Cronjob-Status prüfen
        $cronjobStats = '';
        if ($monitoringMode === 'cronjob') {
            $cronStats = \FriendsOfREDAXO\PushIt\Cronjob\SystemMonitoringCronjob::getCronjobStats();
            $cronjobConfigured = $cronStats['is_configured'];
            $lastRun = $cronStats['last_run'] > 0 ? date('d.m.Y H:i:s', $cronStats['last_run']) : 'Noch nie';
            
            if (!$cronjobConfigured) {
                $statusClass = 'warning';
                $statusIcon = 'fa-warning';
            }
            
            $cronjobStats = '<br><strong>Cronjob konfiguriert:</strong> ' . ($cronjobConfigured ? 'Ja' : 'Nein') . 
                           '<br><strong>Letzter Cronjob-Lauf:</strong> ' . $lastRun;
        }

        return '
        <div class="panel panel-' . $statusClass . '">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="rex-icon ' . $statusIcon . '"></i> 
                    System Error Monitoring: ' . $statusText . '
                </h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Status:</strong> ' . $statusText . '<br>
                        <strong>Modus:</strong> ' . $monitoringModeText . '<br>
                        <strong>Intervall:</strong> ' . $intervalText . '<br>
                        <strong>Letzte Prüfung:</strong> ' . $lastCheckText . $cronjobStats . '
                    </div>
                    <div class="col-sm-6">
                        <strong>Abonnenten:</strong> ' . $status['subscriber_count'] . ' Backend-Benutzer<br>
                        <strong>Error-Icon:</strong> ' . ($status['error_icon'] ? 'Konfiguriert' : 'Standard verwenden') . '
                    </div>
                </div>
                ' . ($status['enabled'] ? '
                <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0;">
                    <small>
                        <i class="rex-icon fa-info-circle"></i> 
                        Das System überwacht die system.log Datei ' . ($monitoringMode === 'cronjob' ? 'via Cronjob' : 'automatisch bei jedem Request') . ' auf Fehler und sendet 
                        Push-Benachrichtigungen an Backend-Benutzer mit "system" oder "admin" Topics.
                        ' . ($monitoringMode === 'cronjob' && !$cronStats['is_configured'] ? 
                            '<br><strong class="text-warning">⚠️ Kein Push-It Cronjob konfiguriert!</strong> 
                             <a href="/redaxo/index.php?page=cronjob">Cronjob einrichten</a>' : '') . '
                    </small>
                </div>
                ' : '
                <div class="alert alert-warning" style="margin-top: 15px; margin-bottom: 0;">
                    <small>
                        <i class="rex-icon fa-warning"></i> 
                        Error Monitoring ist deaktiviert. Aktivieren Sie es in den 
                        <a href="' . rex_url::backendPage('push_it') . '">Push-It Einstellungen</a>.
                    </small>
                </div>
                ') . '
            </div>
        </div>';
    }
}
