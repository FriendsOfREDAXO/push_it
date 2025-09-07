<?php
use FriendsOfREDAXO\PushIt\Service\BackendNotificationManager;

$addon = rex_addon::get('push_it');

// Admin-Berechtigung prüfen
$isAdmin = rex::getUser()->isAdmin();

// BackendNotificationManager initialisieren
$backendManager = new BackendNotificationManager();

// VAPID-Schlüssel prüfen
if (!$backendManager->hasVapidKeys()) {
    echo $backendManager->renderVapidWarning();
    return;
}

// JavaScript für PushIt laden
echo $backendManager->renderJavaScript();

// Info-Panel anzeigen
echo $backendManager->renderInfoPanel($isAdmin);

// Backend Subscription Panel
$fragment = new rex_fragment();
$fragment->setVar('title', 'Backend-Subscription', false);
$fragment->setVar('body', $backendManager->renderBackendSubscriptionPanel($isAdmin), false);
echo $fragment->parse('core/page/section.php');

// Admin-only: Schnelle System-Benachrichtigungen
if ($isAdmin) {
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', 'Schnelle System-Benachrichtigungen', false);
    $fragment2->setVar('body', $backendManager->renderQuickNotificationPanel(), false);
    echo $fragment2->parse('core/page/section.php');

    // Automatische Benachrichtigungen konfigurieren (Admin-only)
    $fragment3 = new rex_fragment();
    $fragment3->setVar('title', 'Automatische Benachrichtigungen', false);
    $fragment3->setVar('body', $backendManager->renderAutoNotificationPanel(), false);
    echo $fragment3->parse('core/page/section.php');
}

// Backend Subscription Statistik
$fragment4 = new rex_fragment();
$fragment4->setVar('title', 'Backend-Subscription Statistik', false);
$fragment4->setVar('body', $backendManager->renderStatisticsPanel($isAdmin), false);
echo $fragment4->parse('core/page/section.php');
