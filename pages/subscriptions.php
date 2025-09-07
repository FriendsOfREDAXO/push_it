<?php
use FriendsOfREDAXO\PushIt\Service\SubscriptionManager;

$addon = rex_addon::get('push_it');

// Admin-Berechtigung prüfen
$isAdmin = rex::getUser()->isAdmin();

// SubscriptionManager initialisieren
$subscriptionManager = new SubscriptionManager();

// Löschfunktion - nur für Admins
$action = rex_request('action', 'string');
$id = rex_request('id', 'int');

if ($action === 'delete' && $id > 0) {
    if (!$isAdmin) {
        echo rex_view::error('Keine Berechtigung zum Löschen von Subscriptions.');
    } else {
        if ($subscriptionManager->deleteSubscription($id)) {
            echo rex_view::success('Subscription wurde gelöscht.');
        } else {
            echo rex_view::error('Fehler beim Löschen der Subscription.');
        }
        
        // Reload um aktualisierte Daten zu zeigen
        echo '<script>window.location.href = "' . rex_url::currentBackendPage() . '";</script>';
    }
}

// Repair-Funktion für Backend-Subscriptions ohne User-ID
if ($action === 'repair' && $isAdmin) {
    $currentUser = rex::getUser();
    if ($currentUser) {
        $repairedCount = $subscriptionManager->repairBackendSubscriptionsWithoutUserId($currentUser->getId());
        if ($repairedCount > 0) {
            echo rex_view::success("$repairedCount Backend-Subscriptions wurden mit Ihrer User-ID verknüpft.");
        } else {
            echo rex_view::info('Keine Backend-Subscriptions ohne User-ID gefunden.');
        }
        
        // Reload um aktualisierte Daten zu zeigen
        echo '<script>window.location.href = "' . rex_url::currentBackendPage() . '";</script>';
    }
}

// Daten laden
$subscriptions = $subscriptionManager->getAllSubscriptions();
$stats = $subscriptionManager->getSubscriptionStats();

// Statistiken anzeigen
$statsContent = $subscriptionManager->renderStatsHtml($stats);

$fragment = new rex_fragment();
$fragment->setVar('title', 'Subscription-Statistiken', false);
$fragment->setVar('body', $statsContent, false);
echo $fragment->parse('core/page/section.php');

// Repair-Button für Admins anzeigen falls nötig
if ($isAdmin) {
    $backendSubscriptionsWithoutUserId = 0;
    foreach ($subscriptions as $subscription) {
        if ($subscription['user_type'] === 'backend' && !$subscription['user_id']) {
            $backendSubscriptionsWithoutUserId++;
        }
    }
    
    if ($backendSubscriptionsWithoutUserId > 0) {
        $repairContent = '
        <div class="alert alert-warning">
            <h4><i class="rex-icon fa-exclamation-triangle"></i> Backend-Subscriptions ohne User-ID</h4>
            <p>Es wurden ' . $backendSubscriptionsWithoutUserId . ' Backend-Subscriptions ohne zugeordnete User-ID gefunden.</p>
            <a href="' . rex_url::currentBackendPage(['action' => 'repair']) . '" 
               class="btn btn-warning"
               onclick="return confirm(\'Möchten Sie diese Subscriptions mit Ihrer User-ID (' . rex::getUser()->getId() . ') verknüpfen?\')">
                <i class="rex-icon fa-wrench"></i> Reparieren
            </a>
        </div>';
        
        $fragment = new rex_fragment();
        $fragment->setVar('title', 'Wartung', false);
        $fragment->setVar('body', $repairContent, false);
        echo $fragment->parse('core/page/section.php');
    }
}

// Tabelle mit allen Subscriptions
if (!empty($subscriptions)) {
    $tableContent = $subscriptionManager->renderTableHtml($subscriptions, $isAdmin);
    
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', 'Alle Subscriptions (' . count($subscriptions) . ')', false);
    $fragment2->setVar('body', $tableContent, false);
    echo $fragment2->parse('core/page/section.php');
    
} else {
    echo rex_view::info('Noch keine Push-Subscriptions vorhanden.');
}
