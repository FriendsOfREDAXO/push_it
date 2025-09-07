<?php
namespace FriendsOfREDAXO\PushIt\Service;

use rex_sql;
use rex_fragment;
use rex_url;
use rex_addon;
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
            <h4><i class="rex-icon fa-info-circle"></i> Backend-Benachrichtigungen</h4>
            <p>Hier können Sie Backend-Benachrichtigungen für Ihr Konto aktivieren.' . 
            ($isAdmin ? ' Als Administrator haben Sie zusätzlich Zugriff auf System-Benachrichtigungen.' : '') . '</p>
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
            <h4>Backend-Benachrichtigungen aktivieren</h4>
            <p>Aktivieren Sie Push-Benachrichtigungen für Ihr Backend-Konto:</p>
            <button class="btn btn-success" id="pushit-subscribe-backend">
                <i class="rex-icon fa-bell"></i> Backend-Benachrichtigungen aktivieren
            </button>
            <button class="btn btn-default" id="pushit-status-check">
                <i class="rex-icon fa-info"></i> Status prüfen
            </button>
            <button class="btn btn-warning" id="pushit-disable">
                <i class="rex-icon fa-bell-slash"></i> Deaktivieren
            </button>
            <br><br>
            <button class="btn btn-xs btn-default" id="pushit-reset">
                <i class="rex-icon fa-refresh"></i> Abfrage zurücksetzen
            </button>
            <small class="help-block">Zurücksetzen: Sie werden beim nächsten Seitenaufruf wieder gefragt, ob Sie Backend-Benachrichtigungen aktivieren möchten.</small>
            
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
                <strong>Redakteur-Benachrichtigungen:</strong> Sie erhalten Benachrichtigungen zu redaktionellen Inhalten und Updates.
            </div>';
        }
        
        $content .= '
            <hr>
            <details>
                <summary><strong>Benachrichtigungen blockiert? Hilfe für Browser-Einstellungen</strong></summary>
                <div class="help-block" style="margin-top: 10px;">
                    <strong>🔧 Safari:</strong><br>
                    1. Klicken Sie auf das <strong>Schloss-Symbol</strong> in der Adressleiste<br>
                    2. Wählen Sie <strong>"Einstellungen für diese Website"</strong><br>
                    3. Setzen Sie <strong>"Benachrichtigungen" auf "Erlauben"</strong><br>
                    4. Laden Sie die Seite neu<br><br>
                    
                    <strong>🔧 Chrome:</strong><br>
                    1. Klicken Sie auf das <strong>Schloss-Symbol</strong> in der Adressleiste<br>
                    2. Aktivieren Sie <strong>"Benachrichtigungen"</strong><br>
                    3. Laden Sie die Seite neu<br><br>
                    
                    <strong>🔧 Firefox:</strong><br>
                    1. Klicken Sie auf das <strong>Schloss-Symbol</strong> in der Adressleiste<br>
                    2. Wählen Sie <strong>"Berechtigung bearbeiten"</strong><br>
                    3. Setzen Sie <strong>"Desktop-Benachrichtigungen" auf "Erlauben"</strong><br>
                    4. Laden Sie die Seite neu
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
                    Kritischer Fehler
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-warning btn-block" id="quick-warning">
                    <i class="rex-icon fa-warning"></i><br>
                    System-Warnung
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-info btn-block" id="quick-info">
                    <i class="rex-icon fa-info-circle"></i><br>
                    Information
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
        $adminNotificationsEnabled = $this->addon->getConfig('enableAdminNotifications', false);
        
        return '
        <p>Die folgenden Ereignisse lösen automatisch Benachrichtigungen an Backend-Nutzer aus:</p>
        <ul class="list-group">
            <li class="list-group-item">
                <strong>System-Fehler:</strong> Bei kritischen PHP-Fehlern oder Exceptions
                <span class="pull-right">
                    <span class="label label-' . ($adminNotificationsEnabled ? 'success' : 'default') . '">
                        ' . ($adminNotificationsEnabled ? 'Aktiviert' : 'Deaktiviert') . '
                    </span>
                </span>
            </li>
            <li class="list-group-item">
                <strong>AddOn-Änderungen:</strong> Installation/Deinstallation von AddOns
                <span class="pull-right">
                    <span class="label label-' . ($adminNotificationsEnabled ? 'success' : 'default') . '">
                        ' . ($adminNotificationsEnabled ? 'Aktiviert' : 'Deaktiviert') . '
                    </span>
                </span>
            </li>
        </ul>

        <p><a href="' . rex_url::backendPage('push_it') . '" class="btn btn-default">
            <i class="rex-icon fa-cog"></i> Einstellungen ändern
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
                    <p>Backend-Subscriptions</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-success">' . $stats['active_backend'] . '</h3>
                    <p>Aktive Subscriptions</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-primary">' . $stats['editorial_subscribers'] . '</h3>
                    <p>Editorial-Topic</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-warning">' . ($stats['system_subscribers'] + $stats['admin_subscribers'] + $stats['critical_subscribers']) . '</h3>
                    <p>Admin-Topics</p>
                </div>
            </div>';
            
            if ($isAdmin) {
                $content .= '
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-3 text-center">
                        <small class="text-muted">System: ' . $stats['system_subscribers'] . '</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Admin: ' . $stats['admin_subscribers'] . '</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Critical: ' . $stats['critical_subscribers'] . '</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Editorial: ' . $stats['editorial_subscribers'] . '</small>
                    </div>
                </div>';
            }
            
            return $content;
            
        } catch (\Exception $e) {
            return '<div class="alert alert-warning">
                <h4>Statistiken temporär nicht verfügbar</h4>
                <p>Die Backend-Statistiken können momentan nicht geladen werden.</p>
            </div>';
        }
    }
    
    /**
     * Rendert eine Warnung wenn VAPID-Schlüssel fehlen
     */
    public function renderVapidWarning(): string
    {
        return \rex_view::warning('
            <h4>VAPID-Schlüssel fehlen</h4>
            <p>Um Backend-Benachrichtigungen zu verwenden, müssen erst VAPID-Schlüssel generiert werden.</p>
            <p><a href="' . rex_url::backendPage('push_it') . '" class="btn btn-primary">
                <i class="rex-icon fa-key"></i> VAPID-Schlüssel generieren
            </a></p>
        ');
    }
}
