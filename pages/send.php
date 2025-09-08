<?php
use FriendsOfREDAXO\PushIt\Service\SendManager;

$addon = rex_addon::get('push_it');

// Berechtigung prüfen
if (!rex::getUser()->hasPerm('push_it[]')) {
    echo rex_view::error(rex_i18n::msg('pushit_no_permission_send'));
    return;
}

// Prüfen ob User Admin ist (für erweiterte Features)
$isAdmin = rex::getUser()->isAdmin();

// SendManager initialisieren
$sendManager = new SendManager();

// Request-Parameter sammeln
$formData = [
    'title' => rex_request('title', 'string'),
    'body' => rex_request('body', 'string'),
    'url' => rex_request('url', 'string'),
    'user_type' => rex_request('user_type', 'string', 'frontend'),
    'topics' => rex_request('topics', 'string'),
    'icon' => rex_request('icon', 'string'),
    'badge' => rex_request('badge', 'string'),
    'image' => rex_request('image', 'string')
];

$doSend = rex_request('send', 'bool');
$testMode = rex_request('test_mode', 'bool');

// Send-Action verarbeiten
if ($doSend) {
    if ($testMode) {
        // Testmodus: Nur an aktuellen Benutzer senden
        $result = $sendManager->sendTestNotification();
        
        if ($result['success']) {
            echo rex_view::success('✅ Test-Benachrichtigung erfolgreich gesendet!');
        } else {
            echo rex_view::error('❌ Fehler beim Senden der Test-Benachrichtigung: ' . $result['message']);
        }
    } else {
        // Normaler Versand
        $result = $sendManager->sendNotification($formData, $isAdmin);
        
        if ($result['success']) {
            echo rex_view::success($result['message']);
        } else {
            echo rex_view::error($result['message']);
        }
        
        // Warnung für Nicht-Admins bei Bildverwendung
        if (!$isAdmin && ($formData['icon'] || $formData['badge'] || $formData['image'])) {
            echo rex_view::warning(rex_i18n::msg('pushit_admin_images_only'));
        }
    }
}

// JavaScript für PushIt laden
echo $sendManager->renderJavaScript();

// Statistiken anzeigen
$statsFragment = new rex_fragment();
$statsFragment->setVar('title', rex_i18n::msg('pushit_current_subscribers_title'), false);
$statsFragment->setVar('body', $sendManager->renderStatisticsPanel(), false);
echo $statsFragment->parse('core/page/section.php');

// Preview anzeigen wenn Daten vorhanden
if (!empty($formData['title']) || !empty($formData['body'])) {
    $previewContent = $sendManager->renderPreviewPanel($formData);
    if ($previewContent) {
        $previewFragment = new rex_fragment();
        $previewFragment->setVar('title', rex_i18n::msg('pushit_preview_title'), false);
        $previewFragment->setVar('body', $previewContent, false);
        echo $previewFragment->parse('core/page/section.php');
    }
}

// Send-Formular anzeigen
$formFragment = new rex_fragment();
$formFragment->setVar('title', rex_i18n::msg('pushit_send_notification_title'), false);
$formFragment->setVar('body', $sendManager->renderSendForm($formData, $isAdmin), false);
echo $formFragment->parse('core/page/section.php');
