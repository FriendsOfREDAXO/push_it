<?php
$addon = rex_addon::get('push_it');

$subject = rex_request('subject', 'string');
$publicKey = rex_request('publicKey', 'string');
$privateKey = rex_request('privateKey', 'string');
$backendEnabled = rex_request('backend_enabled', 'bool');
$frontendEnabled = rex_request('frontend_enabled', 'bool');
$adminNotifications = rex_request('admin_notifications', 'bool');

$doSave = rex_request('save', 'bool');
$doGenerate = rex_request('generate', 'bool');

$libAvailable = class_exists('\\Minishlink\\WebPush\\VAPID');

if ($doSave && rex::isBackend() && rex::getUser() && rex::getUser()->isAdmin()) {
    $addon->setConfig('subject', $subject);
    $addon->setConfig('publicKey', $publicKey);
    $addon->setConfig('privateKey', $privateKey);
    $addon->setConfig('backend_enabled', $backendEnabled);
    $addon->setConfig('frontend_enabled', $frontendEnabled);
    $addon->setConfig('admin_notifications', $adminNotifications);
    
    echo rex_view::success('Einstellungen wurden gespeichert.');
}

if ($doGenerate) {
    if (!$libAvailable) {
        echo rex_view::warning('Composer-Abhängigkeiten fehlen. Bitte im AddOn-Verzeichnis "composer install" ausführen.');
    } else {
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            $addon->setConfig('publicKey', $keys['publicKey']);
            $addon->setConfig('privateKey', $keys['privateKey']);
            echo rex_view::success('VAPID-Schlüssel wurden erfolgreich generiert.');
        } catch (Exception $e) {
            echo rex_view::error('Fehler beim Generieren der VAPID-Schlüssel: ' . $e->getMessage());
        }
    }
}

// Aktuelle Werte laden
$subject = $addon->getConfig('subject');
$publicKey = $addon->getConfig('publicKey');
$privateKey = $addon->getConfig('privateKey');
$backendEnabled = $addon->getConfig('backend_enabled', true);
$frontendEnabled = $addon->getConfig('frontend_enabled', true);
$adminNotifications = $addon->getConfig('admin_notifications', true);

$content = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    <fieldset class="rex-form-col-1">
        <div class="rex-form-group form-group">
            <label class="control-label" for="subject">Subject (mailto: oder URL)</label>
            <input class="form-control" id="subject" name="subject" value="' . rex_escape($subject) . '" />
            <p class="help-block">Erforderlich für VAPID. Verwenden Sie eine mailto:-Adresse oder eine URL Ihrer Domain.</p>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="publicKey">VAPID Public Key</label>
            <textarea class="form-control" id="publicKey" name="publicKey" rows="3">' . rex_escape($publicKey) . '</textarea>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="privateKey">VAPID Private Key</label>
            <textarea class="form-control" id="privateKey" name="privateKey" rows="3">' . rex_escape($privateKey) . '</textarea>
        </div>
        
        <hr>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="backend_enabled" value="1" ' . ($backendEnabled ? 'checked' : '') . ' />
                    Backend-Benachrichtigungen aktivieren
                </label>
                <p class="help-block">Ermöglicht Push-Benachrichtigungen für Backend-Nutzer (Admins).</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="frontend_enabled" value="1" ' . ($frontendEnabled ? 'checked' : '') . ' />
                    Frontend-Benachrichtigungen aktivieren
                </label>
                <p class="help-block">Ermöglicht Push-Benachrichtigungen für Website-Besucher.</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="admin_notifications" value="1" ' . ($adminNotifications ? 'checked' : '') . ' />
                    Automatische Admin-Benachrichtigungen
                </label>
                <p class="help-block">Sendet automatisch Benachrichtigungen bei Systemereignissen an Backend-Nutzer.</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <button class="btn btn-primary" name="save" value="1">Speichern</button>
            <button class="btn btn-default" name="generate" value="1" type="submit">VAPID-Schlüssel generieren</button>
        </div>
    </fieldset>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'VAPID & Grundeinstellungen', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Frontend-Integration-Snippet
if ($publicKey && $frontendEnabled) {
    $frontendSnippet = '
<!-- Push It Frontend Integration -->
<script src="/assets/addons/push_it/frontend.js"></script>
<script>
window.PushItPublicKey = \'' . rex_escape($publicKey, 'js') . '\';
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
if ($publicKey && $backendEnabled) {
    $backendInfo = '<p>Backend-Benachrichtigungen sind aktiviert. Backend-Nutzer werden automatisch gefragt, ob sie Benachrichtigungen erhalten möchten.</p>
    <p>Das Backend-JavaScript wird automatisch geladen und ein Benachrichtigungsbutton wird in der Navigation hinzugefügt.</p>';
    
    $fragment3 = new rex_fragment();
    $fragment3->setVar('title', 'Backend-Integration', false);
    $fragment3->setVar('body', $backendInfo, false);
    echo $fragment3->parse('core/page/section.php');
}

// Status-Info
if (!$publicKey) {
    echo rex_view::info('Bitte generieren Sie zuerst VAPID-Schlüssel, um Push-Benachrichtigungen zu verwenden.');
}

if (!$libAvailable) {
    echo rex_view::warning('Die Composer-Abhängigkeiten sind nicht installiert. Führen Sie "composer install" im AddOn-Verzeichnis aus.');
}
