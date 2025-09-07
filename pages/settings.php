<?php
use FriendsOfREDAXO\PushIt\Service\SettingsManager;

$addon = rex_addon::get('push_it');

// Nur Admins haben Zugriff
if (!rex::getUser() || !rex::getUser()->isAdmin()) {
    echo rex_view::error('Keine Berechtigung für diese Seite.');
    return;
}

// SettingsManager initialisieren
$settingsManager = new SettingsManager();

// Request-Parameter
$doSave = rex_request('save', 'bool');
$doGenerate = rex_request('generate', 'bool');
$doGenerateToken = rex_request('generate_token', 'bool');

// Actions verarbeiten
if ($doSave) {
    $formData = [
        'subject' => rex_request('subject', 'string'),
        'publicKey' => rex_request('publicKey', 'string'),
        'privateKey' => rex_request('privateKey', 'string'),
        'backend_token' => rex_request('backend_token', 'string'),
        'backend_enabled' => rex_request('backend_enabled', 'bool'),
        'frontend_enabled' => rex_request('frontend_enabled', 'bool'),
        'admin_notifications' => rex_request('admin_notifications', 'bool'),
        'backend_only_topics' => rex_request('backend_only_topics', 'string')
    ];
    
    if ($settingsManager->saveSettings($formData)) {
        echo rex_view::success('Einstellungen wurden gespeichert.');
    } else {
        echo rex_view::error('Fehler beim Speichern der Einstellungen.');
    }
}

if ($doGenerate) {
    $result = $settingsManager->generateVapidKeys();
    
    if ($result['success']) {
        echo rex_view::success($result['message']);
    } else {
        echo rex_view::warning($result['message']);
    }
}

if ($doGenerateToken) {
    $result = $settingsManager->generateBackendToken();
    
    if ($result['success']) {
        echo rex_view::success($result['message']);
    } else {
        echo rex_view::error($result['message']);
    }
}

// Aktuelle Einstellungen laden
$settings = $settingsManager->getSettings();

// Konfigurations-Status anzeigen
$statusFragment = new rex_fragment();
$statusFragment->setVar('title', 'Status', false);
$statusFragment->setVar('body', $settingsManager->renderConfigStatus(), false);
echo $statusFragment->parse('core/page/section.php');

// Einstellungsformular anzeigen
$formFragment = new rex_fragment();
$formFragment->setVar('title', 'Push-It Einstellungen', false);
$formFragment->setVar('body', $settingsManager->renderSettingsForm($settings), false);
echo $formFragment->parse('core/page/section.php');

// Topic-Sicherheitsinfo anzeigen
$securityFragment = new rex_fragment();
$securityFragment->setVar('title', 'Topic-Sicherheit', false);
$securityFragment->setVar('body', $settingsManager->renderTopicSecurityInfo(), false);
echo $securityFragment->parse('core/page/section.php');

// Frontend-Integration-Snippet
if ($settings['publicKey'] && $settings['frontend_enabled']) {
    $frontendSnippet = '<!-- Push It Frontend Integration -->
<script src="/assets/addons/push_it/frontend.js"></script>
<script type="text/javascript" nonce="<?=rex_response::getNonce()?>">
// PushIt Konfiguration
window.PushItPublicKey = \'' . rex_escape($settings['publicKey'], 'js') . '\';
window.PushItLanguage = \'de\'; // oder \'en\' für Englisch

// Sprachdateien werden automatisch geladen - keine Inline-Übersetzungen mehr nötig

// Optional: Topics für Frontend-Nutzer
window.PushItTopics = \'news,updates\';
</script>

<!-- Buttons für Nutzer -->
<button onclick="PushIt.requestFrontend()">Benachrichtigungen aktivieren</button>
<button onclick="PushIt.disable()">Benachrichtigungen deaktivieren</button>';
    
    $content2 = '<p>Fügen Sie diesen Code in Ihr Frontend-Template ein:</p><pre>' . rex_escape(trim($frontendSnippet)) . '</pre>';
    
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', 'Frontend-Integration', false);
    $fragment2->setVar('body', $content2, false);
    echo $fragment2->parse('core/page/section.php');
}

// Backend-Integration Info
if ($settings['publicKey'] && $settings['backend_enabled']) {
    $backendInfo = '<p>Backend-Benachrichtigungen sind aktiviert. Backend-Nutzer werden automatisch gefragt, ob sie Benachrichtigungen erhalten möchten.</p>
    <p>Das Backend-JavaScript wird automatisch geladen und ein Benachrichtigungsbutton wird in der Navigation hinzugefügt.</p>';
    
    if ($settings['backend_token']) {
        $backendInfo .= '<div class="alert alert-success">
            <h4><i class="rex-icon fa-check"></i> Backend-Token aktiv</h4>
            <p>Backend-Subscriptions werden über den konfigurierten Token authentifiziert.</p>
            <p><code>' . substr($settings['backend_token'], 0, 16) . '...</code> (Token-Vorschau)</p>
        </div>';
    } else {
        $backendInfo .= '<div class="alert alert-warning">
            <h4><i class="rex-icon fa-warning"></i> Kein Backend-Token</h4>
            <p>Bitte generieren Sie einen Backend-Token für sichere Backend-Subscriptions.</p>
        </div>';
    }
    
    $fragment3 = new rex_fragment();
    $fragment3->setVar('title', 'Backend-Integration', false);
    $fragment3->setVar('body', $backendInfo, false);
    echo $fragment3->parse('core/page/section.php');
}

// Status-Warnungen
if (!$settings['publicKey']) {
    echo rex_view::info('Bitte generieren Sie zuerst VAPID-Schlüssel, um Push-Benachrichtigungen zu verwenden.');
}

if ($settings['backend_enabled'] && !$settings['backend_token']) {
    echo rex_view::warning('Backend-Benachrichtigungen sind aktiviert, aber es wurde noch kein Backend-Token generiert. Klicken Sie auf "Neu generieren" um einen sicheren Token zu erstellen.');
}
