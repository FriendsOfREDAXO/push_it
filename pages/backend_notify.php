<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;
use FriendsOfREDAXO\PushIt\Service\SecurityService;

$addon = rex_addon::get('push_it');

// Admin-Berechtigung prüfen
$isAdmin = rex::getUser()->isAdmin();

// Sicherstellen dass PushIt JS verfügbar ist
$publicKey = $addon->getConfig('publicKey');
$currentUser = rex::getUser();
$userId = $currentUser ? $currentUser->getId() : null;
$nonce = SecurityService::getCurrentNonce();

if ($publicKey) {
    echo '<script src="' . $addon->getAssetsUrl('frontend.js') . '"></script>';
    echo '<script nonce="' . rex_escape($nonce) . '">
        window.PushItPublicKey = ' . json_encode($publicKey) . ';
        window.PushItUserToken = "' . SecurityService::generateUserToken($userId, true) . '";
    </script>';
} else {
    echo rex_view::warning('
        <h4>VAPID-Schlüssel fehlen</h4>
        <p>Bitte generieren Sie zuerst VAPID-Schlüssel in den <a href="' . rex_url::backendPage('push_it') . '">Einstellungen</a>, um Push-Benachrichtigungen verwenden zu können.</p>
    ');
}

echo '<div class="alert alert-info">
    <h4><i class="rex-icon fa-info-circle"></i> Backend-Benachrichtigungen</h4>
    <p>Hier können Sie Backend-Benachrichtigungen für Ihr Konto aktivieren.' . ($isAdmin ? ' Als Administrator haben Sie zusätzlich Zugriff auf System-Benachrichtigungen.' : '') . '</p>
</div>';

// Nur anzeigen wenn VAPID-Schlüssel vorhanden sind
if ($publicKey) {

// Backend Subscription Button
$backendSubContent = '
<div class="well">
    <h4>Backend-Benachrichtigungen aktivieren</h4>
    <p>Aktivieren Sie Push-Benachrichtigungen für Ihr Backend-Konto:</p>
    <button class="btn btn-success" data-backend-subscribe="' . rex_escape($isAdmin ? 'system,admin,critical,editorial' : 'editorial') . '">
        <i class="rex-icon fa-bell"></i> Backend-Benachrichtigungen aktivieren
    </button>
    <button class="btn btn-default" data-status-check="true">
        <i class="rex-icon fa-info"></i> Status prüfen
    </button>
    <button class="btn btn-warning" data-push-disable="true">
        <i class="rex-icon fa-bell-slash"></i> Deaktivieren
    </button>
    <br><br>
    <button class="btn btn-xs btn-default" data-push-reset="true">
        <i class="rex-icon fa-refresh"></i> Abfrage zurücksetzen
    </button>
    <small class="help-block">Zurücksetzen: Sie werden beim nächsten Seitenaufruf wieder gefragt, ob Sie Backend-Benachrichtigungen aktivieren möchten.</small>
    
    ' . ($isAdmin ? '' : '
    <div class="alert alert-info" style="margin-top: 15px;">
        <strong>Redakteur-Benachrichtigungen:</strong> Sie erhalten Benachrichtigungen zu redaktionellen Inhalten und Updates.
    </div>
    ') . '
    
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
            1. Klicken Sie auf das <strong>Schild-Symbol</strong> in der Adressleiste<br>
            2. Aktivieren Sie <strong>"Benachrichtigungen"</strong><br>
            3. Laden Sie die Seite neu<br><br>
            
            <em>Alternative: Browser-Einstellungen → Datenschutz/Websites → Benachrichtigungen → Diese Domain erlauben</em>
        </div>
    </details>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Backend-Subscription', false);
$fragment->setVar('body', $backendSubContent, false);
echo $fragment->parse('core/page/section.php');

// Admin-only: Schnelle System-Benachrichtigungen
if ($isAdmin) {
$quickNotifyContent = '
<div class="row">
    <div class="col-md-4">
        <button class="btn btn-danger btn-block" data-quick-notify="critical" data-title="Kritischer Systemfehler" data-body="Ein kritischer Fehler wurde im System erkannt. Sofortige Überprüfung erforderlich.">
            <i class="rex-icon fa-exclamation-triangle"></i><br>
            Kritischer Fehler
        </button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-warning btn-block" data-quick-notify="warning" data-title="System-Warnung" data-body="Eine Warnung wurde im System registriert. Bitte prüfen Sie die Logs.">
            <i class="rex-icon fa-warning"></i><br>
            System-Warnung
        </button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-info btn-block" data-quick-notify="info" data-title="System-Information" data-body="Eine wichtige Information über den Systemstatus.">
            <i class="rex-icon fa-info-circle"></i><br>
            Information
        </button>
    </div>
</div>';
$fragment2 = new rex_fragment();
$fragment2->setVar('title', 'Schnelle System-Benachrichtigungen', false);
$fragment2->setVar('body', $quickNotifyContent, false);
echo $fragment2->parse('core/page/section.php');

// JavaScript für Event Delegation
echo '<script nonce="' . rex_escape($nonce) . '">
// Event delegation für alle Push It Buttons
document.addEventListener("DOMContentLoaded", function() {
    document.addEventListener("click", function(e) {
        // Backend subscribe button
        if (e.target.hasAttribute("data-backend-subscribe")) {
            e.preventDefault();
            const topics = e.target.getAttribute("data-backend-subscribe");
            if (window.PushIt) {
                PushIt.requestBackend(topics);
            }
        }
        
        // Status check button
        if (e.target.hasAttribute("data-status-check")) {
            e.preventDefault();
            if (window.PushIt) {
                PushIt.getStatus().then(s => alert(s.isSubscribed ? "Aktiv" : "Nicht aktiv"));
            }
        }
        
        // Disable button
        if (e.target.hasAttribute("data-push-disable")) {
            e.preventDefault();
            if (window.PushIt) {
                PushIt.disable();
            }
        }
        
        // Reset button
        if (e.target.hasAttribute("data-push-reset")) {
            e.preventDefault();
            if (window.PushItReset) {
                PushItReset();
            }
        }
        
        // Quick notification buttons
        if (e.target.hasAttribute("data-quick-notify")) {
            e.preventDefault();
            const type = e.target.getAttribute("data-quick-notify");
            const title = e.target.getAttribute("data-title");
            const body = e.target.getAttribute("data-body");
            sendQuickNotification(type, title, body);
        }
    });
});

function sendQuickNotification(type, title, body) {
    if (confirm("Schnelle " + type.toUpperCase() + "-Benachrichtigung senden?\\n\\n" + title + "\\n" + body)) {
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
                alert("✅ " + type.toUpperCase() + "-Benachrichtigung wurde erfolgreich gesendet!");
            } else {
                alert("❌ Fehler beim Senden der Benachrichtigung!");
            }
        }).catch(error => {
            console.error("Quick notification error:", error);
            alert("❌ Fehler beim Senden der Benachrichtigung: " + error.message);
        });
    }
}
</script>';

// Automatische Benachrichtigungen konfigurieren (Admin-only)
$autoNotifyContent = '
<p>Die folgenden Ereignisse lösen automatisch Benachrichtigungen an Backend-Nutzer aus:</p>
<ul class="list-group">
    <li class="list-group-item">
        <strong>System-Fehler:</strong> Bei kritischen PHP-Fehlern oder Exceptions
        <span class="pull-right">
            <span class="label label-' . ($addon->getConfig('admin_notifications') ? 'success' : 'default') . '">
                ' . ($addon->getConfig('admin_notifications') ? 'Aktiviert' : 'Deaktiviert') . '
            </span>
        </span>
    </li>
    <li class="list-group-item">
        <strong>AddOn-Änderungen:</strong> Installation/Deinstallation von AddOns
        <span class="pull-right">
            <span class="label label-' . ($addon->getConfig('admin_notifications') ? 'success' : 'default') . '">
                ' . ($addon->getConfig('admin_notifications') ? 'Aktiviert' : 'Deaktiviert') . '
            </span>
        </span>
    </li>
</ul>

<p><a href="' . rex_url::backendPage('push_it') . '" class="btn btn-default">
    <i class="rex-icon fa-cog"></i> Einstellungen ändern
</a></p>';

$fragment3 = new rex_fragment();
$fragment3->setVar('title', 'Automatische Benachrichtigungen', false);
$fragment3->setVar('body', $autoNotifyContent, false);
echo $fragment3->parse('core/page/section.php');

// Backend Subscription Statistik
$sql = rex_sql::factory();
$sql->setQuery("
    SELECT 
        COUNT(*) as total_backend,
        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_backend,
        SUM(CASE WHEN topics LIKE '%system%' THEN 1 ELSE 0 END) as system_subscribers,
        SUM(CASE WHEN topics LIKE '%admin%' THEN 1 ELSE 0 END) as admin_subscribers,
        SUM(CASE WHEN topics LIKE '%editorial%' THEN 1 ELSE 0 END) as editorial_subscribers,
        SUM(CASE WHEN topics LIKE '%critical%' THEN 1 ELSE 0 END) as critical_subscribers
    FROM rex_push_it_subscriptions 
    WHERE user_type = 'backend'
");

$backendStats = [];
for ($i = 0; $i < $sql->getRows(); $i++) {
    $backendStats = [
        'total_backend' => $sql->getValue('total_backend'),
        'active_backend' => $sql->getValue('active_backend'),
        'system_subscribers' => $sql->getValue('system_subscribers'),
        'admin_subscribers' => $sql->getValue('admin_subscribers'),
        'editorial_subscribers' => $sql->getValue('editorial_subscribers'),
        'critical_subscribers' => $sql->getValue('critical_subscribers')
    ];
    break; // Nur einen Eintrag
}

// Fallback-Werte falls keine Daten vorhanden
$backendStats = array_merge([
    'total_backend' => 0,
    'active_backend' => 0,
    'system_subscribers' => 0,
    'admin_subscribers' => 0,
    'editorial_subscribers' => 0,
    'critical_subscribers' => 0
], $backendStats);

$statsContent = '
<div class="row">
    <div class="col-md-3 text-center">
        <h3>' . $backendStats['total_backend'] . '</h3>
        <p>Backend-Subscriptions</p>
    </div>
    <div class="col-md-3 text-center">
        <h3 class="text-success">' . $backendStats['active_backend'] . '</h3>
        <p>Aktive Subscriptions</p>
    </div>
    <div class="col-md-3 text-center">
        <h3 class="text-primary">' . $backendStats['editorial_subscribers'] . '</h3>
        <p>Editorial-Topic</p>
    </div>
    <div class="col-md-3 text-center">
        <h3 class="text-warning">' . ($backendStats['system_subscribers'] + $backendStats['admin_subscribers'] + $backendStats['critical_subscribers']) . '</h3>
        <p>Admin-Topics</p>
    </div>
</div>' . ($isAdmin ? '
<div class="row" style="margin-top: 15px;">
    <div class="col-md-3 text-center">
        <small class="text-muted">System: ' . $backendStats['system_subscribers'] . '</small>
    </div>
    <div class="col-md-3 text-center">
        <small class="text-muted">Admin: ' . $backendStats['admin_subscribers'] . '</small>
    </div>
    <div class="col-md-3 text-center">
        <small class="text-muted">Critical: ' . $backendStats['critical_subscribers'] . '</small>
    </div>
    <div class="col-md-3 text-center">
        <small class="text-muted">Editorial: ' . $backendStats['editorial_subscribers'] . '</small>
    </div>
</div>' : '');

$fragment4 = new rex_fragment();
$fragment4->setVar('title', 'Backend-Subscription Statistik', false);
$fragment4->setVar('body', $statsContent, false);
echo $fragment4->parse('core/page/section.php');

} // Ende Admin-only Sektion

} // Schließende Klammer für if ($publicKey)
