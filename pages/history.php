<?php
use FriendsOfREDAXO\PushIt\Service\HistoryManager;

$addon = rex_addon::get('push_it');

// Berechtigung prüfen
if (!rex::getUser()->hasPerm('push_it[]')) {
    echo rex_view::error(rex_i18n::msg('pushit_no_permission_send'));
    return;
}

// HistoryManager initialisieren
$historyManager = new HistoryManager();

// Aktionen verarbeiten
$action = rex_request('action', 'string');
$id = rex_request('id', 'int');

if ($action) {
    if ($action === 'delete_all' || $action === 'delete_filtered') {
        // Für Lösch-Aktionen keine ID erforderlich
        $result = $historyManager->processAction($action, 0);
    } elseif ($id > 0) {
        // Für andere Aktionen ID erforderlich
        $result = $historyManager->processAction($action, $id);
    } else {
        $result = ['success' => false, 'message' => 'Ungültige Aktion oder fehlende ID'];
    }
    
    if (isset($result)) {
        if ($result['success']) {
            echo rex_view::success($result['message']);
        } else {
            echo rex_view::error($result['message']);
        }
        
        // Bei Delete-Actions redirect
        if (in_array($action, ['delete', 'delete_all', 'delete_filtered']) && $result['success']) {
            echo '<script>window.location.href = "' . rex_url::backendPage('push_it/history') . '";</script>';
        }
    }
}

// Filter-Parameter sammeln
$filters = [
    'user_type' => rex_request('filter_user_type', 'string', ''),
    'topics' => rex_request('filter_topics', 'string', ''),
    'date' => rex_request('filter_date', 'string', '')
];

$limit = rex_request('limit', 'int', 20);
$offset = rex_request('offset', 'int', 0);

// Statistiken anzeigen
$statsFragment = new rex_fragment();
$statsFragment->setVar('title', rex_i18n::msg('pushit_history_statistics_title'), false);
$statsFragment->setVar('body', $historyManager->renderStatisticsPanel(), false);
echo $statsFragment->parse('core/page/section.php');

// Nachrichten laden und anzeigen
$notificationsData = $historyManager->getNotifications($filters, $limit, $offset);

$tableFragment = new rex_fragment();
$tableFragment->setVar('title', rex_i18n::msg('pushit_history_messages_title') . ' (' . $notificationsData['total'] . ')', false);
$tableFragment->setVar('body', $historyManager->renderNotificationsTable(
    $notificationsData['notifications'],
    $notificationsData['total'],
    $limit,
    $offset,
    $filters
), false);
echo $tableFragment->parse('core/page/section.php');
