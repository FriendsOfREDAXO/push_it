<?php
namespace FriendsOfREDAXO\PushIt\Service;

use FriendsOfREDAXO\PushIt\Service\NotificationService;
use rex_sql;
use rex_escape;
use rex_url;
use rex;
use rex_i18n;

class HistoryManager
{
    /**
     * Verarbeitet Aktionen (resend, delete)
     */
    public function processAction(string $action, int $id): array
    {
        if ($action === 'resend' && $id > 0) {
            return $this->resendNotification($id);
        }
        
        if ($action === 'delete' && $id > 0) {
            return $this->deleteNotification($id);
        }
        
        return ['success' => false, 'message' => rex_i18n::msg('pushit_invalid_action')];
    }
    
    /**
     * Sendet eine Nachricht erneut
     */
    private function resendNotification(int $id): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery("SELECT * FROM rex_push_it_notifications WHERE id = ?", [$id]);
        
        if ($sql->getRows() === 0) {
            return ['success' => false, 'message' => rex_i18n::msg('pushit_message_not_found')];
        }
        
        try {
            $service = new NotificationService();
            $topics = $sql->getValue('topics') ? explode(',', $sql->getValue('topics')) : [];
            
            $result = $service->sendNotification(
                $sql->getValue('title'),
                $sql->getValue('body'),
                $sql->getValue('url'),
                $sql->getValue('user_type'),
                $topics
            );
            
            return [
                'success' => true,
                'message' => sprintf(
                    rex_i18n::msg('pushit_message_resent_success'),
                    $result['sent'],
                    $result['errors']
                )
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => sprintf(rex_i18n::msg('pushit_resend_error'), $e->getMessage())];
        }
    }
    
    /**
     * Löscht eine Nachricht aus der Historie
     */
    private function deleteNotification(int $id): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM rex_push_it_notifications WHERE id = ?', [$id]);
        
        return ['success' => true, 'message' => rex_i18n::msg('pushit_notification_deleted_success')];
    }
    
    /**
     * Lädt gefilterte Nachrichten mit Pagination
     */
    public function getNotifications(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['user_type']) && $filters['user_type'] !== 'all') {
            $whereConditions[] = "user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        if (!empty($filters['topics'])) {
            $whereConditions[] = "topics LIKE ?";
            $params[] = '%' . $filters['topics'] . '%';
        }
        
        if (!empty($filters['date'])) {
            $whereConditions[] = "DATE(created) = ?";
            $params[] = $filters['date'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Gesamt-Anzahl für Pagination
        $sqlCount = rex_sql::factory();
        $sqlCount->setQuery("SELECT COUNT(*) as total FROM rex_push_it_notifications $whereClause", $params);
        $totalNotifications = $sqlCount->getValue('total');
        
        // Nachrichten mit Pagination abrufen
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT * FROM rex_push_it_notifications 
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
        
        return [
            'notifications' => $notifications,
            'total' => $totalNotifications,
            'count' => count($notifications)
        ];
    }
    
    /**
     * Lädt Versand-Statistiken
     */
    public function getStatistics(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT 
                COUNT(*) as total_notifications,
                SUM(sent_to) as total_sent,
                SUM(delivery_errors) as total_errors,
                AVG(sent_to) as avg_recipients
            FROM rex_push_it_notifications
        ");
        
        if ($sql->getRows() === 0) {
            return [
                'total_notifications' => 0,
                'total_sent' => 0,
                'total_errors' => 0,
                'avg_recipients' => 0
            ];
        }
        
        $avgRecipients = $sql->getValue('avg_recipients');
        
        return [
            'total_notifications' => (int) ($sql->getValue('total_notifications') ?? 0),
            'total_sent' => (int) ($sql->getValue('total_sent') ?? 0),
            'total_errors' => (int) ($sql->getValue('total_errors') ?? 0),
            'avg_recipients' => $avgRecipients !== null ? round((float) $avgRecipients, 1) : 0
        ];
    }
    
    /**
     * Rendert das Filter-Formular
     */
    public function renderFilterForm(array $filters = [], int $limit = 20): string
    {
        return '
        <form method="get" class="form-inline" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="push_it/history">
            
            <div class="form-group">
                <label for="filter_user_type">' . rex_i18n::msg('pushit_user_type_filter') . '</label>
                <select name="filter_user_type" id="filter_user_type" class="form-control">
                    <option value="">' . rex_i18n::msg('pushit_all_filter') . '</option>
                    <option value="backend"' . (($filters['user_type'] ?? '') === 'backend' ? ' selected' : '') . '>' . rex_i18n::msg('pushit_user_type_backend') . '</option>
                    <option value="frontend"' . (($filters['user_type'] ?? '') === 'frontend' ? ' selected' : '') . '>' . rex_i18n::msg('pushit_user_type_frontend') . '</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="filter_topics">' . rex_i18n::msg('pushit_topics_filter') . '</label>
                <input type="text" name="filter_topics" id="filter_topics" class="form-control" 
                       placeholder="' . rex_i18n::msg('pushit_topics_placeholder') . '" value="' . rex_escape($filters['topics'] ?? '') . '">
            </div>
            
            <div class="form-group">
                <label for="filter_date">' . rex_i18n::msg('pushit_date_filter') . '</label>
                <input type="date" name="filter_date" id="filter_date" class="form-control" 
                       value="' . rex_escape($filters['date'] ?? '') . '">
            </div>
            
            <div class="form-group">
                <label for="limit">' . rex_i18n::msg('pushit_count_filter') . '</label>
                <select name="limit" id="limit" class="form-control">
                    <option value="10"' . ($limit === 10 ? ' selected' : '') . '>10</option>
                    <option value="20"' . ($limit === 20 ? ' selected' : '') . '>20</option>
                    <option value="50"' . ($limit === 50 ? ' selected' : '') . '>50</option>
                    <option value="100"' . ($limit === 100 ? ' selected' : '') . '>100</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="rex-icon fa-search"></i> ' . rex_i18n::msg('pushit_filter_button') . '
            </button>
            
            <a href="' . rex_url::backendPage('push_it/history') . '" class="btn btn-default">
                <i class="rex-icon fa-refresh"></i> ' . rex_i18n::msg('pushit_reset_filter_button') . '
            </a>
        </form>';
    }
    
    /**
     * Rendert das Statistiken-Panel
     */
    public function renderStatisticsPanel(): string
    {
        $stats = $this->getStatistics();
        
        return '
        <div class="row">
            <div class="col-md-3 text-center">
                <h3>' . $stats['total_notifications'] . '</h3>
                <p>' . rex_i18n::msg('pushit_sent_messages') . '</p>
            </div>
            <div class="col-md-3 text-center">
                <h3 class="text-success">' . $stats['total_sent'] . '</h3>
                <p>' . rex_i18n::msg('pushit_recipients_reached') . '</p>
            </div>
            <div class="col-md-3 text-center">
                <h3 class="text-danger">' . $stats['total_errors'] . '</h3>
                <p>' . rex_i18n::msg('pushit_delivery_errors') . '</p>
            </div>
            <div class="col-md-3 text-center">
                <h3 class="text-info">' . $stats['avg_recipients'] . '</h3>
                <p>' . rex_i18n::msg('pushit_avg_recipients_per_message') . '</p>
            </div>
        </div>';
    }
    
    /**
     * Rendert die Nachrichten-Tabelle
     */
    public function renderNotificationsTable(array $notifications, int $totalNotifications, int $limit, int $offset, array $filters = []): string
    {
        if (empty($notifications)) {
            return $this->renderFilterForm($filters, $limit) . '
            <div class="alert alert-info text-center">
                <h4><i class="rex-icon fa-info-circle"></i> ' . rex_i18n::msg('pushit_no_messages_found') . '</h4>
                <p>' . rex_i18n::msg('pushit_no_messages_sent_yet') . '</p>
                <a href="' . rex_url::backendPage('push_it/send') . '" class="btn btn-primary">
                    <i class="rex-icon fa-paper-plane"></i> ' . rex_i18n::msg('pushit_send_first_message') . '
                </a>
            </div>';
        }
        
        $content = $this->renderFilterForm($filters, $limit);
        
        // Pagination Info
        $content .= '
        <div class="alert alert-info">
            <strong>' . sprintf(
                rex_i18n::msg('pushit_showing_messages'),
                count($notifications),
                $totalNotifications
            ) . '</strong>
            ' . ($totalNotifications > $limit ? sprintf(
                rex_i18n::msg('pushit_page_info'),
                (floor($offset / $limit) + 1),
                ceil($totalNotifications / $limit)
            ) : '') . '
        </div>';
        
        $content .= '
        <div class="table-responsive">
        <table class="table table-striped table-hover" id="notificationTable">
            <thead>
                <tr>
                    <th width="5%" class="sortable" data-sort="id">
                        ' . rex_i18n::msg('pushit_id') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="25%" class="sortable" data-sort="title">
                        ' . rex_i18n::msg('pushit_title_and_message') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="12%" class="sortable" data-sort="topics">
                        ' . rex_i18n::msg('pushit_topics') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="8%" class="sortable" data-sort="type">
                        ' . rex_i18n::msg('pushit_type_column') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="12%" class="sortable" data-sort="sent">
                        ' . rex_i18n::msg('pushit_delivery_column') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="18%" class="sortable" data-sort="date">
                        ' . rex_i18n::msg('pushit_sent_on') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="10%" class="sortable" data-sort="user">
                        ' . rex_i18n::msg('pushit_user_column') . ' <i class="fa fa-sort"></i>
                    </th>
                    <th width="10%">' . rex_i18n::msg('pushit_actions') . '</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($notifications as $notification) {
            $content .= $this->renderNotificationRow($notification);
        }
        
        $content .= '
            </tbody>
        </table>
        </div>';
        
        // Pagination
        if ($totalNotifications > $limit) {
            $content .= $this->renderPagination($totalNotifications, $limit, $offset, $filters);
        }
        
        // JavaScript für Sortierung
        $content .= $this->renderSortingJavaScript();
        
        return $content;
    }
    
    /**
     * Rendert eine Tabellenzeile für eine Nachricht
     */
    private function renderNotificationRow(array $notification): string
    {
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
        $shortBody = strlen($body) > 100 ? substr($body, 0, 100) . '...' : $body;
        
        return '
        <tr data-id="' . $notificationId . '" 
            data-title="' . rex_escape($title) . '"
            data-topics="' . rex_escape($topics) . '"
            data-type="' . $userType . '"
            data-sent="' . $sentTo . '"
            data-date="' . ($created ? $this->getGermanTimestamp($created) : 0) . '"
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
                    ' . $sentTo . ' ' . rex_i18n::msg('pushit_sent_status') . '
                </span>
                ' . ($errors > 0 ? 
                    '<br><span class="label label-danger">' . $errors . ' ' . rex_i18n::msg('pushit_error_count') . '</span>' : '') . '
                <br><small>' . ($sentTo > 0 ? round(($sentTo / ($sentTo + $errors)) * 100, 1) : 0) . rex_i18n::msg('pushit_success_rate') . '</small>
            </td>
            <td>
                <strong>' . ($created ? $this->formatGermanDate($created, 'd.m.Y') : '-') . '</strong><br>
                <small class="text-muted">' . ($created ? $this->formatGermanDate($created, 'H:i:s') : '-') . ' Uhr</small>
            </td>
            <td>
                <small>' . rex_escape($createdBy) . '</small>
            </td>
            <td>
                <div class="btn-group btn-group-xs">
                    <a href="' . rex_url::backendPage('push_it/history', ['action' => 'resend', 'id' => $notificationId]) . '" 
                       class="btn btn-primary" title="' . rex_i18n::msg('pushit_resend_button') . '"
                       onclick="return confirm(\'' . rex_i18n::msg('pushit_resend_confirm') . '\')">
                        <i class="rex-icon fa-repeat"></i>
                    </a>
                    <a href="' . rex_url::backendPage('push_it/history', ['action' => 'delete', 'id' => $notificationId]) . '" 
                       class="btn btn-danger" title="' . rex_i18n::msg('pushit_delete_button') . '"
                       onclick="return confirm(\'' . rex_i18n::msg('pushit_delete_confirm') . '\')">
                        <i class="rex-icon fa-trash"></i>
                    </a>
                </div>
            </td>
        </tr>';
    }
    
    /**
     * Rendert die Pagination
     */
    private function renderPagination(int $totalNotifications, int $limit, int $offset, array $filters): string
    {
        $totalPages = ceil($totalNotifications / $limit);
        $currentPage = floor($offset / $limit) + 1;
        
        $content = '<nav><ul class="pagination">';
        
        // Vorherige Seite
        if ($currentPage > 1) {
            $prevOffset = ($currentPage - 2) * $limit;
            $content .= '<li><a href="' . rex_url::backendPage('push_it/history', array_merge($filters, ['offset' => $prevOffset])) . '">&laquo; ' . rex_i18n::msg('pushit_previous_page') . '</a></li>';
        }
        
        // Seitenzahlen
        for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
            $pageOffset = ($i - 1) * $limit;
            $active = $i === $currentPage ? ' class="active"' : '';
            $content .= '<li' . $active . '><a href="' . rex_url::backendPage('push_it/history', array_merge($filters, ['offset' => $pageOffset])) . '">' . $i . '</a></li>';
        }
        
        // Nächste Seite
        if ($currentPage < $totalPages) {
            $nextOffset = $currentPage * $limit;
            $content .= '<li><a href="' . rex_url::backendPage('push_it/history', array_merge($filters, ['offset' => $nextOffset])) . '">' . rex_i18n::msg('pushit_next_page') . ' &raquo;</a></li>';
        }
        
        $content .= '</ul></nav>';
        
        return $content;
    }
    
    /**
     * Rendert JavaScript für Tabellen-Sortierung
     */
    private function renderSortingJavaScript(): string
    {
        return '
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
        <script type="text/javascript" nonce="' . \rex_response::getNonce() . '">
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
    }
    
    /**
     * Formatiert Datum/Zeit für deutsche Zeitzone
     * 
     * @param string $dateString
     * @param string $format
     * @return string
     */
    private function formatGermanDate(?string $dateString, string $format): string
    {
        if (empty($dateString)) {
            return '-';
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return '-';
        }
        
        // Deutsche Zeitzone verwenden
        $germanTimezone = new \DateTimeZone('Europe/Berlin');
        $dateTime = new \DateTime('@' . $timestamp);
        $dateTime->setTimezone($germanTimezone);
        
        return $dateTime->format($format);
    }
    
    /**
     * Gibt deutschen Timestamp für Sortierung zurück
     * 
     * @param string $dateString
     * @return int
     */
    private function getGermanTimestamp(?string $dateString): int
    {
        if (empty($dateString)) {
            return 0;
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return 0;
        }
        
        // Deutsche Zeitzone verwenden für konsistente Sortierung
        $germanTimezone = new \DateTimeZone('Europe/Berlin');
        $dateTime = new \DateTime('@' . $timestamp);
        $dateTime->setTimezone($germanTimezone);
        
        return $dateTime->getTimestamp();
    }
}
