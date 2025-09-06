<?php
$addon = rex_addon::get('pushi_it');

// Admin-Berechtigung prüfen
$isAdmin = rex::getUser()->isAdmin();

// Subscriptions aus Datenbank abrufen
$sql = rex_sql::factory();
$sql->setQuery("
    SELECT 
        id, user_id, user_type, endpoint, topics, ua, lang, domain, 
        created, updated, last_error, active,
        SUBSTRING(endpoint, 1, 50) as endpoint_short
    FROM rex_pushi_it_subscriptions 
    ORDER BY created DESC
");

$subscriptions = [];
for ($i = 0; $i < $sql->getRows(); $i++) {
    $subscriptions[] = [
        'id' => $sql->getValue('id'),
        'user_id' => $sql->getValue('user_id'),
        'user_type' => $sql->getValue('user_type'),
        'endpoint' => $sql->getValue('endpoint'),
        'endpoint_short' => $sql->getValue('endpoint_short'),
        'topics' => $sql->getValue('topics'),
        'ua' => $sql->getValue('ua'),
        'lang' => $sql->getValue('lang'),
        'domain' => $sql->getValue('domain'),
        'created' => $sql->getValue('created'),
        'updated' => $sql->getValue('updated'),
        'last_error' => $sql->getValue('last_error'),
        'active' => $sql->getValue('active')
    ];
    $sql->next();
}

// Statistiken
$sqlStats = rex_sql::factory();
$sqlStats->setQuery("
    SELECT 
        user_type,
        COUNT(*) as total,
        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN last_error IS NOT NULL THEN 1 ELSE 0 END) as error_count
    FROM rex_pushi_it_subscriptions 
    GROUP BY user_type
");

$stats = [];
for ($i = 0; $i < $sqlStats->getRows(); $i++) {
    $userType = $sqlStats->getValue('user_type');
    $stats[$userType] = [
        'user_type' => $userType,
        'total' => $sqlStats->getValue('total'),
        'active_count' => $sqlStats->getValue('active_count'),
        'error_count' => $sqlStats->getValue('error_count')
    ];
    $sqlStats->next();
}

// Statistik anzeigen
$statsContent = '<div class="row">';

foreach (['frontend', 'backend'] as $type) {
    $typeStats = $stats[$type] ?? ['total' => 0, 'active_count' => 0, 'error_count' => 0];
    $typeLabel = $type === 'frontend' ? 'Frontend' : 'Backend';
    
    $statsContent .= '
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">' . $typeLabel . ' Subscriptions</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-xs-4 text-center">
                        <h4>' . $typeStats['total'] . '</h4>
                        <small>Gesamt</small>
                    </div>
                    <div class="col-xs-4 text-center">
                        <h4 class="text-success">' . $typeStats['active_count'] . '</h4>
                        <small>Aktiv</small>
                    </div>
                    <div class="col-xs-4 text-center">
                        <h4 class="text-danger">' . $typeStats['error_count'] . '</h4>
                        <small>Fehler</small>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

$statsContent .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Subscription-Statistiken', false);
$fragment->setVar('body', $statsContent, false);
echo $fragment->parse('core/page/section.php');

// Tabelle mit allen Subscriptions
if (!empty($subscriptions)) {
    $tableContent = '
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Typ</th>
                <th>User ID</th>
                <th>Endpoint</th>
                <th>Topics</th>
                <th>Browser</th>
                <th>Erstellt</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($subscriptions as $subscription) {
        $statusClass = $subscription['active'] ? 'success' : 'danger';
        $statusText = $subscription['active'] ? 'Aktiv' : 'Inaktiv';
        
        if ($subscription['last_error']) {
            $statusClass = 'warning';
            $statusText = 'Fehler';
        }
        
        $userAgent = $subscription['ua'] ? substr($subscription['ua'], 0, 50) . '...' : '-';
        $topics = $subscription['topics'] ?: '-';
        $userId = $subscription['user_id'] ?: '-';
        
        $tableContent .= '
        <tr>
            <td>' . $subscription['id'] . '</td>
            <td>
                <span class="label label-' . ($subscription['user_type'] === 'backend' ? 'primary' : 'default') . '">
                    ' . ucfirst($subscription['user_type']) . '
                </span>
            </td>
            <td>' . $userId . '</td>
            <td>
                <small title="' . rex_escape($subscription['endpoint']) . '">
                    ' . rex_escape($subscription['endpoint_short']) . '...
                </small>
            </td>
            <td>' . rex_escape($topics) . '</td>
            <td><small>' . rex_escape($userAgent) . '</small></td>
            <td>' . rex_formatter::intlDate($subscription['created'], 'short') . '</td>
            <td>
                <span class="label label-' . $statusClass . '">' . $statusText . '</span>
                ' . ($subscription['last_error'] ? '<br><small class="text-muted" title="' . rex_escape($subscription['last_error']) . '">Fehler</small>' : '') . '
            </td>
            <td>
                ' . ($isAdmin ? '
                <a href="' . rex_url::currentBackendPage(['action' => 'delete', 'id' => $subscription['id']]) . '" 
                   class="btn btn-xs btn-danger" 
                   onclick="return confirm(\'Subscription wirklich löschen?\')">
                    <i class="rex-icon fa-trash"></i> Löschen
                </a>
                ' : '<span class="text-muted"><i class="rex-icon fa-lock"></i> Nur Admin</span>') . '
            </td>
        </tr>';
    }
    
    $tableContent .= '
        </tbody>
    </table>';
    
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', 'Alle Subscriptions (' . count($subscriptions) . ')', false);
    $fragment2->setVar('body', $tableContent, false);
    echo $fragment2->parse('core/page/section.php');
    
} else {
    echo rex_view::info('Noch keine Push-Subscriptions vorhanden.');
}

// Löschfunktion - nur für Admins
$action = rex_request('action', 'string');
$id = rex_request('id', 'int');

if ($action === 'delete' && $id > 0) {
    if (!$isAdmin) {
        echo rex_view::error('Keine Berechtigung zum Löschen von Subscriptions.');
    } else {
        $deleteSql = rex_sql::factory();
        $deleteSql->setQuery('DELETE FROM rex_pushi_it_subscriptions WHERE id = ?', [$id]);
        echo rex_view::success('Subscription wurde gelöscht.');
        
        // Reload um aktualisierte Daten zu zeigen
        echo '<script>window.location.href = "' . rex_url::currentBackendPage() . '";</script>';
    }
}
