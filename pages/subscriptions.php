<?php
use FriendsOfREDAXO\PushIt\Service\SubscriptionManager;

$addon = rex_addon::get('push_it');

// Admin-Berechtigung prüfen
$isAdmin = rex::getUser()->isAdmin();

// SubscriptionManager initialisieren
$subscriptionManager = new SubscriptionManager();
$csrfToken = rex_csrf_token::factory('push_it_subscriptions_actions');
$csrfParams = $csrfToken->getUrlParams();
$csrfName = (string) array_key_first($csrfParams);
$csrfValue = $csrfName !== '' ? (string) ($csrfParams[$csrfName] ?? '') : '';

// Löschfunktion - nur für Admins
$action = rex_request('action', 'string');
$id = rex_request('id', 'int');

if ($action === 'delete' && $id > 0) {
    if (rex_request_method() !== 'post' || !$csrfToken->isValid()) {
        echo rex_view::error('CSRF-Fehler beim Löschen der Subscription.');
    } elseif (!$isAdmin) {
        echo rex_view::error(rex_i18n::msg('pushit_no_permission_delete'));
    } else {
        if ($subscriptionManager->deleteSubscription($id)) {
            echo rex_view::success(rex_i18n::msg('pushit_subscription_deleted'));
        } else {
            echo rex_view::error(rex_i18n::msg('pushit_subscription_delete_error'));
        }

        rex_response::sendRedirect(rex_url::currentBackendPage());
    }
}

// Repair-Funktion für Backend-Subscriptions ohne User-ID
if ($action === 'repair' && $isAdmin) {
    if (rex_request_method() !== 'post' || !$csrfToken->isValid()) {
        echo rex_view::error('CSRF-Fehler bei der Reparatur der Subscriptions.');
    } else {
        $currentUser = rex::getUser();
        if ($currentUser) {
            $repairedCount = $subscriptionManager->repairBackendSubscriptionsWithoutUserId($currentUser->getId());
            if ($repairedCount > 0) {
                echo rex_view::success(rex_i18n::msg('pushit_backend_subscriptions_repaired', '', $repairedCount));
            } else {
                echo rex_view::info(rex_i18n::msg('pushit_no_backend_subscriptions_found'));
            }

            rex_response::sendRedirect(rex_url::currentBackendPage());
        }
    }
}

// Daten laden
$subscriptions = $subscriptionManager->getAllSubscriptions();
$stats = $subscriptionManager->getSubscriptionStats();

// Statistiken anzeigen
$statsContent = $subscriptionManager->renderStatsHtml($stats);

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('pushit_subscription_statistics_title'), false);
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
            <h4><i class="rex-icon fa-exclamation-triangle"></i> ' . rex_i18n::msg('pushit_backend_subscriptions_without_user_id') . '</h4>
            <p>' . rex_i18n::msg('pushit_backend_subscriptions_found', $backendSubscriptionsWithoutUserId) . '</p>
            <form method="post" action="' . rex_escape(rex_url::currentBackendPage()) . '" style="display:inline;" onsubmit="return confirm(\'' . rex_i18n::msg('pushit_repair_subscriptions_confirm', rex::getUser()->getId()) . '\')">
                <input type="hidden" name="page" value="' . rex_escape(rex_be_controller::getCurrentPage()) . '">
                <input type="hidden" name="action" value="repair">
                <input type="hidden" name="' . rex_escape($csrfName) . '" value="' . rex_escape($csrfValue) . '">
                <button type="submit" class="btn btn-warning">
                    <i class="rex-icon fa-wrench"></i> ' . rex_i18n::msg('pushit_repair_button') . '
                </button>
            </form>
        </div>';
        
        $fragment = new rex_fragment();
        $fragment->setVar('title', rex_i18n::msg('pushit_maintenance_title'), false);
        $fragment->setVar('body', $repairContent, false);
        echo $fragment->parse('core/page/section.php');
    }
}

// Tabelle mit allen Subscriptions
if (!empty($subscriptions)) {
    $tableContent = $subscriptionManager->renderTableHtml($subscriptions, $isAdmin, $csrfName, $csrfValue);
    
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', rex_i18n::msg('pushit_all_subscriptions_title') . ' (' . count($subscriptions) . ')', false);
    $fragment2->setVar('body', $tableContent, false);
    echo $fragment2->parse('core/page/section.php');
    
} else {
    echo rex_view::info(rex_i18n::msg('pushit_no_subscriptions'));
}
