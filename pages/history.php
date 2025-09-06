<?php
$addon = rex_addon::get('pushi_it');

// Aktionen zuerst verarbeiten
$action = rex_request('action', 'string');
$id = rex_request('id', 'int');

if ($action && $id > 0) {
    switch ($action) {
        case 'resend':
            // Nachricht erneut senden
            $resendSql = rex_sql::factory();
            $resendSql->setQuery("SELECT * FROM rex_pushi_it_notifications WHERE id = ?", [$id]);
            
            if ($resendSql->getRows() > 0) {
                try {
                    $service = new FriendsOfREDAXO\PushIt\Service\NotificationService();
                    $topics = $resendSql->getValue('topics') ? explode(',', $resendSql->getValue('topics')) : [];
                    
                    $result = $service->sendNotification(
                        $resendSql->getValue('title'),
                        $resendSql->getValue('body'),
                        $resendSql->getValue('url'),
                        $resendSql->getValue('user_type'),
                        $topics
                    );
                    
                    echo rex_view::success(sprintf(
                        'Nachricht wurde erneut gesendet! Empfänger: %d, Fehler: %d',
                        $result['sent'],
                        $result['errors']
                    ));
                    
                } catch (Exception $e) {
                    echo rex_view::error('Fehler beim erneuten Senden: ' . $e->getMessage());
                }
            } else {
                echo rex_view::error('Nachricht nicht gefunden');
            }
            break;
            
        case 'delete':
            // Nachricht aus Historie löschen
            if ($_GET['action'] === 'delete') {
            $id = rex_get('id', 'int');
            if ($id) {
                $sql = rex_sql::factory();
                $sql->setQuery('DELETE FROM ' . rex::getTable('pushi_it_notifications') . ' WHERE id = ?', [$id]);
                echo '<div class="alert alert-success">Benachrichtigung wurde gelöscht.</div>';
            }
            echo '<script>window.location.href = "' . rex_url::backendPage('pushi_it/history') . '";</script>';
        }
            break;
    }
}

// Filter-Parameter
$filterUserType = rex_request('filter_user_type', 'string', '');
$filterTopics = rex_request('filter_topics', 'string', '');
$filterDate = rex_request('filter_date', 'string', '');
$limit = rex_request('limit', 'int', 20);
$offset = rex_request('offset', 'int', 0);

// Gesendete Nachrichten aus Datenbank abrufen
$whereConditions = [];
$params = [];

if ($filterUserType && $filterUserType !== 'all') {
    $whereConditions[] = "user_type = ?";
    $params[] = $filterUserType;
}

if ($filterTopics) {
    $whereConditions[] = "topics LIKE ?";
    $params[] = '%' . $filterTopics . '%';
}

if ($filterDate) {
    $whereConditions[] = "DATE(created) = ?";
    $params[] = $filterDate;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Gesamt-Anzahl für Pagination
$sqlCount = rex_sql::factory();
$sqlCount->setQuery("SELECT COUNT(*) as total FROM rex_pushi_it_notifications $whereClause", $params);
$totalNotifications = $sqlCount->getValue('total');

// Nachrichten mit Pagination abrufen
$sql = rex_sql::factory();
$sql->setQuery("
    SELECT * FROM rex_pushi_it_notifications 
    $whereClause
    ORDER BY created DESC 
    LIMIT $limit OFFSET $offset
", $params);

$notifications = [];
for ($i = 0; $i < $sql->getRows(); $i++) {
    $notifications[] = [
        'id' => $sql->getValue('id'),
        'title' => $sql->getValue('title'),
        'body' => $sql->getValue('body'),
        'url' => $sql->getValue('url'),
        'topics' => $sql->getValue('topics'),
        'user_type' => $sql->getValue('user_type'),
        'sent_to' => $sql->getValue('sent_to'),
        'delivery_errors' => $sql->getValue('delivery_errors'),
        'created_by' => $sql->getValue('created_by'),
        'created' => $sql->getValue('created')
    ];
    $sql->next();
}

// Statistiken
$sqlStats = rex_sql::factory();
$sqlStats->setQuery("
    SELECT 
        COUNT(*) as total_notifications,
        SUM(sent_to) as total_sent,
        SUM(delivery_errors) as total_errors,
        AVG(sent_to) as avg_recipients
    FROM rex_pushi_it_notifications
");

$stats = [];
for ($i = 0; $i < $sqlStats->getRows(); $i++) {
    $avgRecipients = $sqlStats->getValue('avg_recipients');
    $stats = [
        'total_notifications' => (int) ($sqlStats->getValue('total_notifications') ?? 0),
        'total_sent' => (int) ($sqlStats->getValue('total_sent') ?? 0),
        'total_errors' => (int) ($sqlStats->getValue('total_errors') ?? 0),
        'avg_recipients' => $avgRecipients !== null ? round((float) $avgRecipients, 1) : 0
    ];
    break;
}

// Fallback-Werte
$stats = array_merge([
    'total_notifications' => 0,
    'total_sent' => 0,
    'total_errors' => 0,
    'avg_recipients' => 0
], $stats);

// Filter-Formular
$filterForm = '
<form method="get" class="form-inline" style="margin-bottom: 20px;">
    <input type="hidden" name="page" value="pushi_it/history">
    
    <div class="form-group">
        <label for="filter_user_type">User-Typ:</label>
        <select name="filter_user_type" id="filter_user_type" class="form-control">
            <option value="">Alle</option>
            <option value="backend"' . ($filterUserType === 'backend' ? ' selected' : '') . '>Backend</option>
            <option value="frontend"' . ($filterUserType === 'frontend' ? ' selected' : '') . '>Frontend</option>
        </select>
    </div>
    
    <div class="form-group">
        <label for="filter_topics">Topics:</label>
        <input type="text" name="filter_topics" id="filter_topics" class="form-control" 
               placeholder="z.B. system, news" value="' . rex_escape($filterTopics) . '">
    </div>
    
    <div class="form-group">
        <label for="filter_date">Datum:</label>
        <input type="date" name="filter_date" id="filter_date" class="form-control" 
               value="' . rex_escape($filterDate) . '">
    </div>
    
    <div class="form-group">
        <label for="limit">Anzahl:</label>
        <select name="limit" id="limit" class="form-control">
            <option value="10"' . ($limit === 10 ? ' selected' : '') . '>10</option>
            <option value="20"' . ($limit === 20 ? ' selected' : '') . '>20</option>
            <option value="50"' . ($limit === 50 ? ' selected' : '') . '>50</option>
            <option value="100"' . ($limit === 100 ? ' selected' : '') . '>100</option>
        </select>
    </div>
    
    <button type="submit" class="btn btn-primary">
        <i class="rex-icon fa-search"></i> Filtern
    </button>
    
    <a href="' . rex_url::backendPage('pushi_it/history') . '" class="btn btn-default">
        <i class="rex-icon fa-refresh"></i> Zurücksetzen
    </a>
</form>';

// Statistiken anzeigen
$statsContent = '
<div class="row">
    <div class="col-md-3 text-center">
        <h3>' . $stats['total_notifications'] . '</h3>
        <p>Gesendete Nachrichten</p>
    </div>
    <div class="col-md-3 text-center">
        <h3 class="text-success">' . $stats['total_sent'] . '</h3>
        <p>Empfänger erreicht</p>
    </div>
    <div class="col-md-3 text-center">
        <h3 class="text-danger">' . $stats['total_errors'] . '</h3>
        <p>Zustellfehler</p>
    </div>
    <div class="col-md-3 text-center">
        <h3 class="text-info">' . $stats['avg_recipients'] . '</h3>
        <p>⌀ Empfänger pro Nachricht</p>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Versand-Statistiken', false);
$fragment->setVar('body', $statsContent, false);
echo $fragment->parse('core/page/section.php');

// Filter und Tabelle
if (!empty($notifications)) {
    
    $tableContent = $filterForm;
    
    // Pagination Info
    $pageInfo = '
    <div class="alert alert-info">
        <strong>Zeige ' . count($notifications) . ' von ' . $totalNotifications . ' Nachrichten</strong>
        ' . ($totalNotifications > $limit ? '(Seite ' . (floor($offset / $limit) + 1) . ' von ' . ceil($totalNotifications / $limit) . ')' : '') . '
    </div>';
    
    $tableContent .= $pageInfo;
    
    $tableContent .= '
    <div class="table-responsive">
    <table class="table table-striped table-hover" id="notificationTable">
        <thead>
            <tr>
                <th width="5%" class="sortable" data-sort="id">
                    ID <i class="fa fa-sort"></i>
                </th>
                <th width="25%" class="sortable" data-sort="title">
                    Titel & Nachricht <i class="fa fa-sort"></i>
                </th>
                <th width="12%" class="sortable" data-sort="topics">
                    Topics <i class="fa fa-sort"></i>
                </th>
                <th width="8%" class="sortable" data-sort="type">
                    Typ <i class="fa fa-sort"></i>
                </th>
                <th width="12%" class="sortable" data-sort="sent">
                    Versand <i class="fa fa-sort"></i>
                </th>
                <th width="18%" class="sortable" data-sort="date">
                    Gesendet am <i class="fa fa-sort"></i>
                </th>
                <th width="10%" class="sortable" data-sort="user">
                    Benutzer <i class="fa fa-sort"></i>
                </th>
                <th width="10%">Aktionen</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($notifications as $notification) {
        // Sichere Array-Zugriffe mit Fallbacks
        $notificationId = $notification['id'] ?? 0;
        $title = $notification['title'] ?? '';
        $body = $notification['body'] ?? '';
        $url = $notification['url'] ?? '';
        $topics = $notification['topics'] ?? '';
        $userType = $notification['user_type'] ?? 'frontend';
        $sentTo = $notification['sent_to'] ?? 0;
        $errors = $notification['delivery_errors'] ?? 0;
        $created = $notification['created'] ?? '';
        $createdById = $notification['created_by'] ?? null;
        
        $createdBy = '-';
        if ($createdById) {
            $userSql = rex_sql::factory();
            $userSql->setQuery("SELECT name, login FROM rex_user WHERE id = ?", [$createdById]);
            if ($userSql->getRows() > 0) {
                $createdBy = $userSql->getValue('name') ?: $userSql->getValue('login');
            }
        }
        
        // Body kürzen für Anzeige
        $shortBody = strlen($body) > 100 ? 
            substr($body, 0, 100) . '...' : 
            $body;
        
        $tableContent .= '
        <tr data-id="' . $notificationId . '" 
            data-title="' . rex_escape($title) . '"
            data-topics="' . rex_escape($topics) . '"
            data-type="' . $userType . '"
            data-sent="' . $sentTo . '"
            data-date="' . ($created ? strtotime($created) : 0) . '"
            data-user="' . rex_escape($createdBy) . '">
            <td><span class="label label-default">#' . $notificationId . '</span></td>
            <td>
                <strong>' . rex_escape($title) . '</strong><br>
                <small class="text-muted">' . rex_escape($shortBody) . '</small>
                ' . ($url ? '<br><a href="' . rex_escape($url) . '" target="_blank" class="text-primary"><i class="rex-icon fa-external-link"></i></a>' : '') . '
            </td>
            <td>
                ' . ($topics ? 
                    '<span class="label label-info">' . str_replace(',', '</span> <span class="label label-info">', rex_escape($topics)) . '</span>' 
                    : '<span class="text-muted">-</span>') . '
            </td>
            <td>
                <span class="label label-' . ($userType === 'backend' ? 'primary' : 'default') . '">
                    ' . ucfirst($userType) . '
                </span>
            </td>
            <td>
                <span class="label label-' . ($errors > 0 ? 'warning' : 'success') . '">
                    ' . $sentTo . ' gesendet
                </span>
                ' . ($errors > 0 ? 
                    '<br><span class="label label-danger">' . $errors . ' Fehler</span>' : '') . '
                <br><small>' . ($sentTo > 0 ? round(($sentTo / ($sentTo + $errors)) * 100, 1) : 0) . '% Erfolg</small>
            </td>
            <td>
                <strong>' . ($created ? date('d.m.Y', strtotime($created)) : '-') . '</strong><br>
                <small class="text-muted">' . ($created ? date('H:i:s', strtotime($created)) : '-') . ' Uhr</small>
            </td>
            <td>
                <small>' . rex_escape($createdBy) . '</small>
            </td>
            <td>
                <div class="btn-group btn-group-xs">
                    <a href="' . rex_url::backendPage('pushi_it/history', ['action' => 'resend', 'id' => $notificationId]) . '" 
                       class="btn btn-primary" title="Erneut senden"
                       onclick="return confirm(\'Nachricht erneut senden?\')">
                        <i class="rex-icon fa-repeat"></i>
                    </a>
                    <a href="' . rex_url::backendPage('pushi_it/history', ['action' => 'delete', 'id' => $notificationId]) . '" 
                       class="btn btn-danger" title="Löschen"
                       onclick="return confirm(\'Nachricht aus Historie löschen?\')">
                        <i class="rex-icon fa-trash"></i>
                    </a>
                </div>
            </td>
        </tr>';
    }
    
    $tableContent .= '
        </tbody>
    </table>
    </div>';
    
    // Pagination
    if ($totalNotifications > $limit) {
        $tableContent .= '<nav><ul class="pagination">';
        
        $totalPages = ceil($totalNotifications / $limit);
        $currentPage = floor($offset / $limit) + 1;
        
        // Vorherige Seite
        if ($currentPage > 1) {
            $prevOffset = ($currentPage - 2) * $limit;
            $tableContent .= '<li><a href="' . rex_url::backendPage('pushi_it/history', array_merge($_GET, ['offset' => $prevOffset])) . '">&laquo; Vorherige</a></li>';
        }
        
        // Seitenzahlen
        for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
            $pageOffset = ($i - 1) * $limit;
            $active = $i === $currentPage ? ' class="active"' : '';
            $tableContent .= '<li' . $active . '><a href="' . rex_url::backendPage('pushi_it/history', array_merge($_GET, ['offset' => $pageOffset])) . '">' . $i . '</a></li>';
        }
        
        // Nächste Seite
        if ($currentPage < $totalPages) {
            $nextOffset = $currentPage * $limit;
            $tableContent .= '<li><a href="' . rex_url::backendPage('pushi_it/history', array_merge($_GET, ['offset' => $nextOffset])) . '">Nächste &raquo;</a></li>';
        }
        
        $tableContent .= '</ul></nav>';
    }
    
    // JavaScript für Tabellen-Sortierung
    $tableContent .= '
    <style>
    .sortable {
        user-select: none;
        position: relative;
    }
    .sortable:hover {
        background-color: #f5f5f5;
    }
    .sortable i {
        margin-left: 5px;
        opacity: 0.6;
    }
    .sortable:hover i {
        opacity: 1;
    }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const table = document.getElementById("notificationTable");
        const headers = table.querySelectorAll("th.sortable");
        let currentSort = { column: "date", direction: "desc" };
        
        // Initial nach Datum sortieren (neueste zuerst)
        sortTable("date", "desc");
        
        headers.forEach(header => {
            header.style.cursor = "pointer";
            header.addEventListener("click", function() {
                const sortBy = this.getAttribute("data-sort");
                const direction = currentSort.column === sortBy && currentSort.direction === "asc" ? "desc" : "asc";
                sortTable(sortBy, direction);
                updateSortIcons(this, direction);
                currentSort = { column: sortBy, direction: direction };
            });
        });
        
        function sortTable(column, direction) {
            const tbody = table.querySelector("tbody");
            const rows = Array.from(tbody.querySelectorAll("tr"));
            
            rows.sort((a, b) => {
                let aVal = a.getAttribute("data-" + column) || "";
                let bVal = b.getAttribute("data-" + column) || "";
                
                // Für numerische Werte
                if (column === "id" || column === "sent" || column === "date") {
                    aVal = parseInt(aVal) || 0;
                    bVal = parseInt(bVal) || 0;
                }
                
                if (direction === "asc") {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
            
            // Zeilen neu einfügen
            rows.forEach(row => tbody.appendChild(row));
        }
        
        function updateSortIcons(activeHeader, direction) {
            // Alle Icons zurücksetzen
            headers.forEach(h => {
                const icon = h.querySelector("i");
                icon.className = "fa fa-sort";
            });
            
            // Aktives Icon setzen
            const activeIcon = activeHeader.querySelector("i");
            activeIcon.className = direction === "asc" ? "fa fa-sort-up" : "fa fa-sort-down";
        }
        
        // Initial icon für Datum setzen
        const dateHeader = table.querySelector(\'th[data-sort="date"]\');
        if (dateHeader) {
            updateSortIcons(dateHeader, "desc");
        }
    });
    </script>';
    
    $fragment2 = new rex_fragment();
    $fragment2->setVar('title', 'Gesendete Nachrichten (' . $totalNotifications . ')', false);
    $fragment2->setVar('body', $tableContent, false);
    echo $fragment2->parse('core/page/section.php');

} else {
    $noDataContent = $filterForm . '
    <div class="alert alert-info text-center">
        <h4><i class="rex-icon fa-info-circle"></i> Keine Nachrichten gefunden</h4>
        <p>Es wurden noch keine Push-Nachrichten gesendet oder Ihre Filter-Kriterien treffen nicht zu.</p>
        <a href="' . rex_url::backendPage('pushi_it/send') . '" class="btn btn-primary">
            <i class="rex-icon fa-paper-plane"></i> Erste Nachricht senden
        </a>
    </div>';
    
    $fragment3 = new rex_fragment();
    $fragment3->setVar('title', 'Gesendete Nachrichten', false);
    $fragment3->setVar('body', $noDataContent, false);
    echo $fragment3->parse('core/page/section.php');
}
