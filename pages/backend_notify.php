<?php
use FriendsOfREDAXO\PushIt\Service\BackendNotificationManager;

$addon = rex_addon::get('push_it');

// Admin-Berechtigung prüfen
$isAdmin = rex::getUser()->isAdmin();

// BackendNotificationManager initialisieren
$backendManager = new BackendNotificationManager();

// VAPID-Schlüssel prüfen und ggf. Warnung anzeigen
if (!$backendManager->hasVapidKeys()) {
    echo $backendManager->renderVapidWarning();
} else {
    // JavaScript für PushIt laden
    echo $backendManager->renderJavaScript();
}

// Info-Panel anzeigen
echo $backendManager->renderInfoPanel($isAdmin);

// Nur anzeigen wenn VAPID-Schlüssel vorhanden sind
if ($backendManager->hasVapidKeys()) {
    
    // Backend Subscription Panel
    $backendSubFragment = new rex_fragment();
    $backendSubFragment->setVar('title', rex_i18n::msg('backend_subscription_title'), false);
    $backendSubFragment->setVar('body', $backendManager->renderBackendSubscriptionPanel($isAdmin), false);
    echo $backendSubFragment->parse('core/page/section.php');
    
    // Admin-only: Schnelle System-Benachrichtigungen
    if ($isAdmin) {
        $quickNotifyFragment = new rex_fragment();
        $quickNotifyFragment->setVar('title', rex_i18n::msg('quick_notifications_title'), false);
        $quickNotifyFragment->setVar('body', $backendManager->renderQuickNotificationPanel(), false);
        echo $quickNotifyFragment->parse('core/page/section.php');
        
        // Automatische Benachrichtigungen konfigurieren (Admin-only)
        $autoNotifyFragment = new rex_fragment();
        $autoNotifyFragment->setVar('title', rex_i18n::msg('automatic_notifications_title'), false);
        $autoNotifyFragment->setVar('body', $backendManager->renderAutomaticNotificationsInfo($isAdmin), false);
        echo $autoNotifyFragment->parse('core/page/section.php');
    }
    
    // Backend Subscription Statistik
    $statsFragment = new rex_fragment();
    $statsFragment->setVar('title', rex_i18n::msg('backend_statistics_title'), false);
    $statsFragment->setVar('body', $backendManager->renderStatisticsPanel($isAdmin), false);
    echo $statsFragment->parse('core/page/section.php');
}
