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

        $content = '
        <div class="well">
            <h4>' . rex_i18n::msg('pushit_activate_backend_notifications') . '</h4>
            <p>' . rex_i18n::msg('pushit_activate_push_notifications') . '</p>
            <button class="btn btn-success" id="pushit-subscribe-backend" data-topics="' . rex_escape($topics) . '">
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
            <small class="help-block">' . rex_i18n::msg('pushit_reset_query_info') . '</small>';

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
        return '
        <div id="pushit-quick-notifications"
             data-send-url="' . rex_escape(rex_url::backendPage('push_it/send')) . '"
             data-system-url="' . rex_escape(rex_url::backendPage('system')) . '">
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
        </div>';
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

    public function renderErrorMonitoringInfo(): string
    {
        if (!class_exists('\\FriendsOfREDAXO\\PushIt\\Service\\SystemErrorMonitor')) {
            return '';
        }

        $errorMonitor = new \FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor();
        $status = $errorMonitor->getErrorMonitoringStatus();

        $statusClass = $status['enabled'] ? 'success' : 'info';
        $statusIcon  = $status['enabled'] ? 'fa-check-circle' : 'fa-info-circle';
        $statusText  = $status['enabled'] ? rex_i18n::msg('pushit_enabled') : rex_i18n::msg('pushit_disabled');

        // Monitoring-Modus ermitteln
        $monitoringMode = $this->addon->getConfig('monitoring_mode', 'realtime');
        $monitoringModeText = $monitoringMode === 'cronjob'
            ? rex_i18n::msg('pushit_mode_cronjob')
            : rex_i18n::msg('pushit_mode_realtime');

        $intervalText = '';
        if ($status['interval'] >= 3600) {
            $intervalText = ($status['interval'] / 3600) . ' ' . rex_i18n::msg('pushit_hours');
        } elseif ($status['interval'] >= 60) {
            $intervalText = ($status['interval'] / 60) . ' ' . rex_i18n::msg('pushit_minutes');
        } else {
            $intervalText = $status['interval'] . ' ' . rex_i18n::msg('pushit_seconds');
        }

        $lastCheckText = $status['last_check'] > 0
            ? date('d.m.Y H:i:s', $status['last_check'])
            : rex_i18n::msg('pushit_never');

        // Cronjob-Status prüfen
        $cronjobStats = '';
        $cronStats    = null;
        if ($monitoringMode === 'cronjob') {
            $cronStats         = \FriendsOfREDAXO\PushIt\Cronjob\SystemMonitoringCronjob::getCronjobStats();
            $cronjobConfigured = $cronStats['is_configured'];
            $lastRun           = $cronStats['last_run'] > 0
                ? date('d.m.Y H:i:s', $cronStats['last_run'])
                : rex_i18n::msg('pushit_never');

            if (!$cronjobConfigured) {
                $statusClass = 'warning';
                $statusIcon  = 'fa-warning';
            }

            $cronjobStats = '<br><strong>' . rex_i18n::msg('pushit_monitor_cronjob_configured') . ':</strong> '
                . ($cronjobConfigured ? rex_i18n::msg('pushit_yes') : rex_i18n::msg('pushit_no'))
                . '<br><strong>' . rex_i18n::msg('pushit_monitor_last_cronjob') . ':</strong> ' . $lastRun;
        }

        $errorIconText = $status['error_icon']
            ? rex_i18n::msg('pushit_configured')
            : rex_i18n::msg('pushit_use_default');

        $infoText = $status['enabled']
            ? rex_i18n::msg($monitoringMode === 'cronjob' ? 'pushit_monitor_info_cronjob' : 'pushit_monitor_info_realtime')
            : null;

        $noCronjobHint = '';
        if ($monitoringMode === 'cronjob' && $cronStats !== null && !$cronStats['is_configured']) {
            $noCronjobHint = '<br><strong class="text-warning">&#9888; ' . rex_i18n::msg('pushit_monitor_no_cronjob_warning') . '</strong> '
                . '<a href="/redaxo/index.php?page=cronjob">' . rex_i18n::msg('pushit_monitor_no_cronjob_setup') . '</a>';
        }

        return '
        <div class="panel panel-' . $statusClass . '">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="rex-icon ' . $statusIcon . '"></i>
                    ' . rex_i18n::msg('pushit_monitor_system_error_monitoring') . ': ' . $statusText . '
                </h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>' . rex_i18n::msg('pushit_monitor_status') . ':</strong> ' . $statusText . '<br>
                        <strong>' . rex_i18n::msg('pushit_monitor_mode') . ':</strong> ' . $monitoringModeText . '<br>
                        <strong>' . rex_i18n::msg('pushit_monitor_interval') . ':</strong> ' . $intervalText . '<br>
                        <strong>' . rex_i18n::msg('pushit_monitor_last_check') . ':</strong> ' . $lastCheckText . $cronjobStats . '
                    </div>
                    <div class="col-sm-6">
                        <strong>' . rex_i18n::msg('pushit_monitor_subscribers') . ':</strong> ' . $status['subscriber_count'] . ' ' . rex_i18n::msg('pushit_monitor_backend_users') . '<br>
                        <strong>' . rex_i18n::msg('pushit_monitor_error_icon') . ':</strong> ' . $errorIconText . '
                    </div>
                </div>
                ' . ($status['enabled']
                    ? '<div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0;">
                    <small>
                        <i class="rex-icon fa-info-circle"></i>
                        ' . rex_escape($infoText ?? '') . $noCronjobHint . '
                    </small>
                </div>'
                    : '<div class="alert alert-warning" style="margin-top: 15px; margin-bottom: 0;">
                    <small>
                        <i class="rex-icon fa-warning"></i>
                        ' . rex_i18n::msg('pushit_monitor_disabled_info') . '
                        <a href="' . rex_url::backendPage('push_it') . '">' . rex_i18n::msg('pushit_settings') . '</a>.
                    </small>
                </div>') . '
            </div>
        </div>';
    }
}
