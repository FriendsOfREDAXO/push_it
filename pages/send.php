<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

$addon = rex_addon::get('push_it');

// Berechtigung prüfen
if (!rex::getUser()->hasPerm('push_it[]')) {
    echo rex_view::error('Sie haben keine Berechtigung für diesen Bereich.');
    return;
}

// Prüfen ob User Admin ist (für erweiterte Features)
$isAdmin = rex::getUser()->isAdmin();

// PushIt JavaScript für Test-Funktion laden
$publicKey = $addon->getConfig('publicKey');
if ($publicKey) {
    echo '<script src="' . $addon->getAssetsUrl('frontend.js') . '"></script>';
    echo '<script>window.PushItPublicKey = ' . json_encode($publicKey) . ';</script>';
}

$title = rex_request('title', 'string');
$body = rex_request('body', 'string');
$url = rex_request('url', 'string');
$userType = rex_request('user_type', 'string', 'frontend');
$topics = rex_request('topics', 'string');
$icon = rex_request('icon', 'string');
$badge = rex_request('badge', 'string');
$image = rex_request('image', 'string');
$doSend = rex_request('send', 'bool');

if ($doSend && $title && $body) {
    try {
        $service = new NotificationService();
        $topicsArray = array_filter(array_map('trim', explode(',', $topics)));
        
        // Erweiterte Optionen sammeln - nur für Admins
        $options = [];
        if ($isAdmin) {
            if ($icon) $options['icon'] = $icon;
            if ($badge) $options['badge'] = $badge;
            if ($image) $options['image'] = $image;
        } elseif ($icon || $badge || $image) {
            echo rex_view::warning('Bilder können nur von Administratoren versendet werden. Nachricht wird ohne Bilder gesendet.');
        }
        
        $result = $service->sendNotification($title, $body, $url, $userType, $topicsArray, $options);
        
        if ($result['success']) {
            echo rex_view::success(sprintf(
                'Benachrichtigung wurde erfolgreich gesendet! Gesendet: %d, Fehler: %d, Gesamt: %d',
                $result['sent'],
                $result['errors'],
                $result['total']
            ));
        } else {
            echo rex_view::warning($result['message']);
        }
    } catch (Exception $e) {
        echo rex_view::error('Fehler beim Senden: ' . $e->getMessage());
    }
}

// Subscription-Anzahl für Vorschau
$sql = rex_sql::factory();
$sql->setQuery("
    SELECT user_type, COUNT(*) as count 
    FROM rex_push_it_subscriptions 
    WHERE active = 1 
    GROUP BY user_type
");

$subscriptionCounts = [];
while ($sql->hasNext()) {
    $subscriptionCounts[$sql->getValue('user_type')] = $sql->getValue('count');
    $sql->next();
}

$frontendCount = $subscriptionCounts['frontend'] ?? 0;
$backendCount = $subscriptionCounts['backend'] ?? 0;

$content = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    <fieldset class="rex-form-col-1">
        <div class="rex-form-group form-group">
            <label class="control-label" for="title">Titel *</label>
            <input class="form-control" id="title" name="title" value="' . rex_escape($title) . '" required />
            <p class="help-block">Der Haupttitel der Benachrichtigung.</p>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="body">Nachricht *</label>
            <textarea class="form-control" id="body" name="body" rows="3" required>' . rex_escape($body) . '</textarea>
            <p class="help-block">Der Haupttext der Benachrichtigung.</p>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="url">URL (optional)</label>
            <input class="form-control" id="url" name="url" value="' . rex_escape($url) . '" placeholder="https://..." />
            <p class="help-block">URL die beim Klick auf die Benachrichtigung geöffnet wird.</p>
        </div>
        ';

// Bilder-Felder nur für Admins anzeigen
if ($isAdmin) {
    $content .= '
        <fieldset class="rex-form-col-1">
            <legend>Bilder & Icons (optional) - Nur für Administratoren</legend>
            
            <div class="rex-form-group form-group">
                <label class="control-label" for="icon">Icon</label>
                <input class="form-control" id="icon" name="icon" value="' . rex_escape($icon) . '" placeholder="/assets/addons/push_it/icon.png" />
                <p class="help-block"><strong>Icon-URL Formate:</strong><br>
                • <code>/media/icon.png</code> - Lokale Datei im REDAXO Media-Ordner<br>
                • <code>/assets/addons/push_it/icon.png</code> - AddOn-Assets<br>
                • <code>https://example.com/icon.png</code> - Externe URL (HTTPS erforderlich!)<br>
                <em>Empfohlen: 192x192px, PNG/JPG</em></p>
            </div>
            
            <div class="rex-form-group form-group">
                <label class="control-label" for="badge">Badge</label>
                <input class="form-control" id="badge" name="badge" value="' . rex_escape($badge) . '" placeholder="/media/badge.png" />
                <p class="help-block"><strong>Badge für Android Notification Bar:</strong><br>
                • Nur <strong>monochrome Icons</strong> (schwarz/weiß)<br>
                • Lokaler Pfad: <code>/media/badge.png</code> oder externe HTTPS-URL<br>
                <em>Empfohlen: 72x72px, monochrom</em></p>
            </div>
            
            <div class="rex-form-group form-group">
                <label class="control-label" for="image">Hero-Bild</label>
                <input class="form-control" id="image" name="image" value="' . rex_escape($image) . '" placeholder="/media/hero-image.jpg" />
                <p class="help-block"><strong>Großes Bild in der Benachrichtigung:</strong><br>
                • <code>/media/hero.jpg</code> - Lokale Mediendatei<br>
                • <code>https://cdn.example.com/image.jpg</code> - Externe HTTPS-URL<br>
                <em>Empfohlen: 360x240px (3:2 Verhältnis), JPG/PNG</em><br>
                <strong>⚠️ Browser-Kompatibilität:</strong> <span style="color: #d9534f;">macOS Safari zeigt KEINE Hero-Bilder!</span><br>
                ✅ Chrome, Firefox, Edge | ❌ Safari (macOS/iOS)</p>
            </div>
        </fieldset>
        ';
} else {
    $content .= '
        <div class="alert alert-info">
            <h4><i class="fa fa-info-circle"></i> Erweiterte Features</h4>
            <p><strong>Bilder und Icons</strong> können nur von Administratoren verwendet werden. Für einfache Text-Benachrichtigungen haben Sie bereits alle notwendigen Rechte.</p>
        </div>
        ';
}

$content .= '
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="user_type">Empfänger</label>
            <select class="form-control" id="user_type" name="user_type">
                <option value="frontend"' . ($userType === 'frontend' ? ' selected' : '') . '>Frontend-Nutzer (' . $frontendCount . ')</option>
                <option value="backend"' . ($userType === 'backend' ? ' selected' : '') . '>Backend-Nutzer (' . $backendCount . ')</option>
                <option value="both"' . ($userType === 'both' ? ' selected' : '') . '>Alle Nutzer (' . ($frontendCount + $backendCount) . ')</option>
            </select>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="topics">Topics (optional)</label>
            <input class="form-control" id="topics" name="topics" value="' . rex_escape($topics) . '" placeholder="news,updates,admin" />
            <p class="help-block">Komma-getrennte Liste von Topics. Nur Nutzer mit diesen Topics erhalten die Benachrichtigung.</p>
        </div>
        
        <div class="rex-form-group form-group">
            <button class="btn btn-primary" name="send" value="1" type="submit">
                <i class="rex-icon fa-paper-plane"></i> Benachrichtigung senden
            </button>
            <button class="btn btn-default" type="button" onclick="sendTestNotification()">
                <i class="rex-icon fa-flask"></i> Test senden
            </button>
        </div>
    </fieldset>
</form>

<script>
function sendTestNotification() {
    // Direkte Test-Benachrichtigung ohne PushIt JS
    if (confirm("Test-Benachrichtigung an alle Backend-User senden?")) {
        fetch("' . rex_url::currentBackendPage() . '", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                title: "🧪 Test-Benachrichtigung",
                body: "Dies ist eine Test-Nachricht von Push It. Wenn Sie diese Nachricht sehen, funktioniert das System korrekt!",
                url: "' . rex_url::backendPage('push_it') . '",
                user_type: "backend",
                topics: "test,admin",
                send: "1"
            })
        }).then(response => {
            if (response.ok) {
                alert("✅ Test-Benachrichtigung wurde erfolgreich gesendet!");
                window.location.reload();
            } else {
                alert("❌ Fehler beim Senden der Test-Benachrichtigung!");
            }
        }).catch(error => {
            alert("❌ Fehler: " + error.message);
        });
    }
}
</script>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Push-Benachrichtigung senden', false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Letzte gesendete Nachrichten anzeigen
$sql = rex_sql::factory();
$sql->setQuery("
    SELECT * FROM rex_push_it_notifications 
    ORDER BY created DESC 
    LIMIT 10
");

$recentNotifications = [];
while ($sql->hasNext()) {
    $recentNotifications[] = $sql->getRow();
    $sql->next();
}

if (!empty($recentNotifications)) {
    $historyContent = '
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Empfänger</th>
                <th>Topics</th>
                <th>Gesendet</th>
                <th>Fehler</th>
                <th>Erstellt</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($recentNotifications as $notification) {
        $title = $notification['title'] ?? '';
        $body = $notification['body'] ?? '';
        $userType = $notification['user_type'] ?? 'frontend';
        $topics = $notification['topics'] ?? '';
        $sentTo = $notification['sent_to'] ?? 0;
        $errors = $notification['delivery_errors'] ?? 0;
        $created = $notification['created'] ?? '';
        
        $historyContent .= '
        <tr>
            <td>
                <strong>' . rex_escape($title) . '</strong>
                ' . ($body ? '<br><small class="text-muted">' . rex_escape(substr($body, 0, 100)) . '...</small>' : '') . '
            </td>
            <td>
                <span class="label label-' . ($userType === 'backend' ? 'primary' : 'default') . '">
                    ' . ucfirst($userType) . '
                </span>
            </td>
            <td>' . rex_escape($topics ?: '-') . '</td>
            <td><span class="text-success">' . $sentTo . '</span></td>
            <td><span class="text-danger">' . $errors . '</span></td>
            <td>' . ($created ? rex_formatter::intlDateTime($created, 'short') : '-') . '</td>
        </tr>';
    }
    
    $historyContent .= '
        </tbody>
    </table>';
    
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', 'Letzte Benachrichtigungen', false);
    $fragment2->setVar('body', $historyContent, false);
    echo $fragment2->parse('core/page/section.php');
}
