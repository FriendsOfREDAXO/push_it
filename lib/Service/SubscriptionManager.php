<?php

namespace FriendsOfREDAXO\PushIt\Service;

use rex_sql;
use rex_user;
use rex_formatter;
use rex_escape;
use rex_i18n;
use Exception;

/**
 * Service-Klasse für die Verwaltung von Push-Subscriptions
 */
class SubscriptionManager
{
    /**
     * Ermittelt den Benutzernamen basierend auf der User-ID
     * 
     * @param int|null $userId
     * @return string
     */
    public function getUsernameById(?int $userId): string
    {
        if (!$userId) {
            return '-';
        }
        
        try {
            $user = rex_user::get($userId);
            if ($user) {
                return $user->getLogin();
            }
            
            return 'User #' . $userId . ' ' . rex_i18n::msg('pushit_user_deleted');
        } catch (Exception $e) {
            return 'User #' . $userId . ' ' . rex_i18n::msg('pushit_user_error');
        }
    }
    
    /**
     * Holt alle Subscriptions aus der Datenbank mit Username-Auflösung
     * 
     * @return array
     */
    public function getAllSubscriptions(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT 
                s.id, s.user_id, s.user_type, s.endpoint, s.topics, s.ua, s.lang, s.domain, 
                s.created, s.updated, s.last_error, s.active,
                SUBSTRING(s.endpoint, 1, 50) as endpoint_short,
                u.login as username
            FROM rex_push_it_subscriptions s
            LEFT JOIN rex_user u ON s.user_id = u.id
            ORDER BY s.created DESC
        ");
        
        $subscriptions = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $userId = $sql->getValue('user_id');
            $username = $sql->getValue('username');
            
            // Fallback wenn kein Username aus JOIN verfügbar
            if (!$username && $userId) {
                $username = $this->getUsernameById($userId);
            }
            
            $subscriptions[] = [
                'id' => $sql->getValue('id'),
                'user_id' => $userId,
                'username' => $username ?: '-',
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
        
        return $subscriptions;
    }
    
    /**
     * Holt Subscription-Statistiken gruppiert nach User-Type
     * 
     * @return array
     */
    public function getSubscriptionStats(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT 
                user_type,
                COUNT(*) as total,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN last_error IS NOT NULL THEN 1 ELSE 0 END) as error_count
            FROM rex_push_it_subscriptions 
            GROUP BY user_type
        ");
        
        $stats = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $userType = $sql->getValue('user_type');
            $stats[$userType] = [
                'user_type' => $userType,
                'total' => (int)$sql->getValue('total'),
                'active_count' => (int)$sql->getValue('active_count'),
                'error_count' => (int)$sql->getValue('error_count')
            ];
            $sql->next();
        }
        
        return $stats;
    }
    
    /**
     * Formatiert die Benutzer-Anzeige für die Tabelle
     * 
     * @param array $subscription
     * @return string
     */
    public function formatUserDisplay(array $subscription): string
    {
        $userDisplay = $subscription['username'];
        
        if ($subscription['user_type'] === 'backend') {
            if ($subscription['user_id'] && $subscription['username'] !== '-') {
                $userDisplay .= ' <small class="text-muted">(ID: ' . $subscription['user_id'] . ')</small>';
            } elseif ($subscription['user_id'] && $subscription['username'] === '-') {
                $userDisplay = 'User #' . $subscription['user_id'] . ' <small class="text-warning">(gelöscht)</small>';
            } else {
                $userDisplay = '<span class="text-warning"><em>Backend-User (keine ID)</em></span>';
            }
        } elseif ($subscription['user_type'] === 'frontend') {
            $userDisplay = '<em class="text-muted">Frontend-User</em>';
        }
        
        return $userDisplay;
    }
    
    /**
     * Erstellt HTML für Subscription-Statistiken
     * 
     * @param array $stats
     * @return string
     */
    public function renderStatsHtml(array $stats): string
    {
        $html = '<div class="row">';
        
        foreach (['frontend', 'backend'] as $type) {
            $typeStats = $stats[$type] ?? ['total' => 0, 'active_count' => 0, 'error_count' => 0];
            $typeLabel = $type === 'frontend' ? rex_i18n::msg('pushit_frontend_subscriptions') : rex_i18n::msg('pushit_backend_subscriptions');
            
            $html .= '
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">' . $typeLabel . '</h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-4 text-center">
                                <h4>' . $typeStats['total'] . '</h4>
                                <small>' . rex_i18n::msg('pushit_total') . '</small>
                            </div>
                            <div class="col-xs-4 text-center">
                                <h4 class="text-success">' . $typeStats['active_count'] . '</h4>
                                <small>' . rex_i18n::msg('pushit_active') . '</small>
                            </div>
                            <div class="col-xs-4 text-center">
                                <h4 class="text-danger">' . $typeStats['error_count'] . '</h4>
                                <small>' . rex_i18n::msg('pushit_errors') . '</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Erstellt HTML für die Subscription-Tabelle
     * 
     * @param array $subscriptions
     * @param bool $isAdmin
     * @return string
     */
    public function renderTableHtml(array $subscriptions, bool $isAdmin): string
    {
        if (empty($subscriptions)) {
            return '';
        }
        
        $html = '
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>' . rex_i18n::msg('pushit_id') . '</th>
                    <th>' . rex_i18n::msg('pushit_type') . '</th>
                    <th>' . rex_i18n::msg('pushit_user') . '</th>
                    <th>' . rex_i18n::msg('pushit_endpoint') . '</th>
                    <th>' . rex_i18n::msg('pushit_topics') . '</th>
                    <th>' . rex_i18n::msg('pushit_browser') . '</th>
                    <th>' . rex_i18n::msg('pushit_created') . '</th>
                    <th>' . rex_i18n::msg('pushit_status') . '</th>
                    <th>' . rex_i18n::msg('pushit_actions') . '</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($subscriptions as $subscription) {
            $html .= $this->renderTableRow($subscription, $isAdmin);
        }
        
        $html .= '
            </tbody>
        </table>';
        
        return $html;
    }
    
    /**
     * Erstellt HTML für eine Tabellenzeile
     * 
     * @param array $subscription
     * @param bool $isAdmin
     * @return string
     */
    private function renderTableRow(array $subscription, bool $isAdmin): string
    {
        $statusClass = $subscription['active'] ? 'success' : 'danger';
        $statusText = $subscription['active'] ? rex_i18n::msg('pushit_active_status') : rex_i18n::msg('pushit_inactive_status');
        
        if ($subscription['last_error']) {
            $statusClass = 'warning';
            $statusText = rex_i18n::msg('pushit_error_status');
        }
        
        $userAgent = $subscription['ua'] ? substr($subscription['ua'], 0, 50) . '...' : '-';
        $topics = $subscription['topics'] ?: '-';
        $userDisplay = $this->formatUserDisplay($subscription);
        
        return '
        <tr>
            <td>' . $subscription['id'] . '</td>
            <td>
                <span class="label label-' . ($subscription['user_type'] === 'backend' ? 'primary' : 'default') . '">
                    ' . ucfirst($subscription['user_type']) . '
                </span>
            </td>
            <td>' . $userDisplay . '</td>
            <td>
                <small title="' . rex_escape($subscription['endpoint']) . '">
                    ' . rex_escape($subscription['endpoint_short']) . '...
                </small>
            </td>
            <td>' . rex_escape($topics) . '</td>
            <td><small>' . rex_escape($userAgent) . '</small></td>
            <td>' . $this->formatCreatedDate($subscription['created']) . '</td>
            <td>
                <span class="label label-' . $statusClass . '">' . $statusText . '</span>
                ' . ($subscription['last_error'] ? '<br><small class="text-muted" title="' . rex_escape($subscription['last_error']) . '">' . rex_i18n::msg('pushit_error_status') . '</small>' : '') . '
            </td>
            <td>' . $this->renderActionButtons($subscription, $isAdmin) . '</td>
        </tr>';
    }
    
    /**
     * Erstellt HTML für Aktions-Buttons
     * 
     * @param array $subscription
     * @param bool $isAdmin
     * @return string
     */
    private function renderActionButtons(array $subscription, bool $isAdmin): string
    {
        if (!$isAdmin) {
            return '<span class="text-muted"><i class="rex-icon fa-lock"></i> Nur Admin</span>';
        }
        
        return '
        <a href="' . \rex_url::currentBackendPage(['action' => 'delete', 'id' => $subscription['id']]) . '" 
           class="btn btn-xs btn-danger" 
           onclick="return confirm(\'Subscription wirklich löschen?\')">
            <i class="rex-icon fa-trash"></i> Löschen
        </a>';
    }
    
    /**
     * Formatiert das Erstellungsdatum
     * 
     * @param string $dateString
     * @return string
     */
    private function formatCreatedDate(?string $dateString): string
    {
        if (empty($dateString)) {
            return '<em>unbekannt</em>';
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return '<em>ungültig</em>';
        }
        
        return date('d.m.Y H:i', $timestamp);
    }
    
    /**
     * Löscht eine Subscription
     * 
     * @param int $id
     * @return bool
     */
    public function deleteSubscription(int $id): bool
    {
        try {
            $sql = rex_sql::factory();
            $sql->setQuery('DELETE FROM rex_push_it_subscriptions WHERE id = ?', [$id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Repariert Backend-Subscriptions ohne User-ID
     * 
     * @param int $currentUserId
     * @return int Anzahl reparierter Subscriptions
     */
    public function repairBackendSubscriptionsWithoutUserId(int $currentUserId): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            UPDATE rex_push_it_subscriptions 
            SET user_id = ? 
            WHERE user_type = "backend" AND user_id IS NULL
        ', [$currentUserId]);
        
        return $sql->getRows();
    }
}
