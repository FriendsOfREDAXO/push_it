<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Service;

use rex_addon;
use rex_sql;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationService
{
    private $addon;
    
    public function __construct()
    {
        $this->addon = rex_addon::get('push_it');
    }
    
    /**
     * Sendet eine Benachrichtigung an alle Backend-Nutzer
     */
    public function sendToBackendUsers(string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        return $this->sendNotification($title, $body, $url, 'backend', $topics, $options);
    }
    
    /**
     * Sendet eine Benachrichtigung an alle Frontend-Nutzer
     */
    public function sendToFrontendUsers(string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        return $this->sendNotification($title, $body, $url, 'frontend', $topics, $options);
    }
    
    /**
     * Sendet eine Benachrichtigung an alle Nutzer
     */
    public function sendToAllUsers(string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        return $this->sendNotification($title, $body, $url, 'both', $topics, $options);
    }
    
    /**
     * Sendet eine Benachrichtigung an einen spezifischen Backend-Benutzer
     * 
     * HINWEIS: Funktioniert nur für Backend-User mit REDAXO User-ID.
     * Frontend-User haben keine User-IDs - nutzen Sie stattdessen Topics.
     * 
     * @param int $userId REDAXO Backend User-ID
     * @param string $title
     * @param string $body
     * @param string $url
     * @param array $topics
     * @param array $options
     * @return array
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        $publicKey = $this->addon->getConfig('publicKey');
        $privateKey = $this->addon->getConfig('privateKey');
        $subject = $this->addon->getConfig('subject');
        
        if (!$publicKey || !$privateKey) {
            throw new \Exception('VAPID-Schlüssel nicht konfiguriert');
        }
        
        // WebPush-Instanz erstellen
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]
        ]);
        
        // Subscriptions für spezifischen User abrufen
        $subscriptions = $this->getSubscriptionsByUserId($userId, $topics);
        
        if (empty($subscriptions)) {
            return [
                'success' => false,
                'message' => 'Keine aktiven Subscriptions für User ID: ' . $userId . ' und Topics: ' . implode(',', $topics),
                'sent' => 0,
                'failed' => 0,
                'total' => 0
            ];
        }
        
        // Payload erstellen
        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => $options['icon'] ?? '/assets/addons/push_it/icon.svg',
            'timestamp' => time()
        ];
        
        // Erweiterte Optionen hinzufügen
        if (isset($options['badge'])) {
            $payload['badge'] = $options['badge'];
        }
        
        if (isset($options['image'])) {
            $payload['image'] = $options['image'];
        }
        
        if (isset($options['silent'])) {
            $payload['silent'] = $options['silent'];
        }
        
        if (isset($options['tag'])) {
            $payload['tag'] = $options['tag'];
        }
        
        if (isset($options['renotify'])) {
            $payload['renotify'] = $options['renotify'];
        }
        
        if (isset($options['vibrate']) && is_array($options['vibrate'])) {
            $payload['vibrate'] = $options['vibrate'];
        }
        
        if (isset($options['actions']) && is_array($options['actions'])) {
            $payload['actions'] = $options['actions'];
        }
        
        $payloadJson = json_encode($payload);
        
        $sent = 0;
        $errors = 0;
        
        // Nachrichten senden
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh'],
                        'auth' => $sub['auth']
                    ]
                ]);
                
                $result = $webPush->sendOneNotification($subscription, $payloadJson);
                
                if ($result->isSuccess()) {
                    $sent++;
                    $this->updateSubscriptionSuccess($sub['id']);
                } else {
                    $errors++;
                    $errorMsg = $result->getReason();
                    $this->updateSubscriptionError($sub['id'], $errorMsg);
                }
                
            } catch (\Exception $e) {
                $errors++;
                $this->updateSubscriptionError($sub['id'], $e->getMessage());
            }
        }
        
        // Log-Eintrag erstellen
        $this->logNotification($title, $body, $url, 'user_' . $userId, implode(',', $topics), $sent, $errors, $options);
        
        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $errors,
            'total' => count($subscriptions)
        ];
    }
    
    /**
     * Hauptfunktion zum Senden von Benachrichtigungen
     */
    public function sendNotification(string $title, string $body, string $url = '', string $userType = 'frontend', array $topics = [], array $options = []): array
    {
        $publicKey = $this->addon->getConfig('publicKey');
        $privateKey = $this->addon->getConfig('privateKey');
        $subject = $this->addon->getConfig('subject');
        
        if (!$publicKey || !$privateKey) {
            throw new \Exception('VAPID-Schlüssel nicht konfiguriert');
        }
        
        // WebPush-Instanz erstellen
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]
        ]);
        
        // Subscriptions aus Datenbank abrufen
        $subscriptions = $this->getSubscriptions($userType, $topics);
        
        // Debug-Ausgabe
        error_log('PushIt: Found ' . count($subscriptions) . ' subscriptions for userType: ' . $userType . ', topics: ' . implode(',', $topics));
        
        if (empty($subscriptions)) {
            return [
                'success' => false,
                'message' => 'Keine aktiven Subscriptions gefunden für User-Typ: ' . $userType . ' und Topics: ' . implode(',', $topics),
                'sent' => 0,
                'failed' => 0,
                'total' => 0
            ];
        }
        
        // Payload erstellen
        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => $options['icon'] ?? '/assets/addons/push_it/icon.svg',
            'timestamp' => time()
        ];
        
        // Erweiterte Optionen hinzufügen
        if (isset($options['badge'])) {
            $payload['badge'] = $options['badge'];
        }
        
        if (isset($options['image'])) {
            $payload['image'] = $options['image'];
        }
        
        if (isset($options['silent'])) {
            $payload['silent'] = $options['silent'];
        }
        
        if (isset($options['tag'])) {
            $payload['tag'] = $options['tag'];
        }
        
        if (isset($options['renotify'])) {
            $payload['renotify'] = $options['renotify'];
        }
        
        if (isset($options['vibrate']) && is_array($options['vibrate'])) {
            $payload['vibrate'] = $options['vibrate'];
        }
        
        if (isset($options['actions']) && is_array($options['actions'])) {
            $payload['actions'] = $options['actions'];
        }
        
        $payloadJson = json_encode($payload);
        
        $sent = 0;
        $errors = 0;
        
        // Nachrichten senden
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh'],
                        'auth' => $sub['auth']
                    ]
                ]);
                
                $result = $webPush->sendOneNotification($subscription, $payloadJson);
                
                error_log('PushIt: Sending to subscription ' . $sub['id'] . ', endpoint: ' . substr($sub['endpoint'], 0, 50) . '...');
                
                if ($result->isSuccess()) {
                    $sent++;
                    error_log('PushIt: Successfully sent to subscription ' . $sub['id']);
                    $this->updateSubscriptionSuccess($sub['id']);
                } else {
                    $errors++;
                    $errorMsg = $result->getReason();
                    error_log('PushIt: Failed to send to subscription ' . $sub['id'] . ': ' . $errorMsg);
                    $this->updateSubscriptionError($sub['id'], $errorMsg);
                }
                
            } catch (\Exception $e) {
                $errors++;
                error_log('PushIt: Exception sending to subscription ' . $sub['id'] . ': ' . $e->getMessage());
                $this->updateSubscriptionError($sub['id'], $e->getMessage());
            }
        }
        
        // Log-Eintrag erstellen
        $this->logNotification($title, $body, $url, $userType, implode(',', $topics), $sent, $errors, $options);
        
        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $errors,
            'total' => count($subscriptions)
        ];
    }
    
    /**
     * Holt aktive Subscriptions aus der Datenbank
     */
    private function getSubscriptions(string $userType, array $topics = []): array
    {
        $sql = rex_sql::factory();
        
        $where = ['active = 1'];
        $params = [];
        
        // User Type Filter
        if ($userType === 'backend') {
            $where[] = "user_type = 'backend'";
        } elseif ($userType === 'frontend') {
            $where[] = "user_type = 'frontend'";
        }
        // bei 'both' keine Einschränkung
        
        // Topics Filter
        if (!empty($topics)) {
            $topicConditions = [];
            foreach ($topics as $topic) {
                $topicConditions[] = "FIND_IN_SET(?, topics)";
                $params[] = trim($topic);
            }
            if (!empty($topicConditions)) {
                $where[] = '(' . implode(' OR ', $topicConditions) . ')';
            }
        }
        
        $query = "SELECT id, endpoint, p256dh, auth FROM rex_push_it_subscriptions WHERE " . implode(' AND ', $where);
        
        $sql->setQuery($query, $params);
        
        $subscriptions = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $subscriptions[] = [
                'id' => $sql->getValue('id'),
                'endpoint' => $sql->getValue('endpoint'),
                'p256dh' => $sql->getValue('p256dh'),
                'auth' => $sql->getValue('auth')
            ];
            $sql->next();
        }
        
        return $subscriptions;
    }
    
    /**
     * Holt aktive Subscriptions für einen spezifischen Benutzer
     */
    private function getSubscriptionsByUserId(int $userId, array $topics = []): array
    {
        $sql = rex_sql::factory();
        
        $where = ['active = 1', 'user_id = ?', "user_type = 'backend'"];
        $params = [$userId];
        
        // Topics Filter (nur wenn Topics angegeben sind)
        if (!empty($topics)) {
            $topicConditions = [];
            foreach ($topics as $topic) {
                $topicConditions[] = "FIND_IN_SET(?, topics)";
                $params[] = trim($topic);
            }
            if (!empty($topicConditions)) {
                $where[] = '(' . implode(' OR ', $topicConditions) . ')';
            }
        }
        
        $query = "SELECT id, endpoint, p256dh, auth FROM rex_push_it_subscriptions WHERE " . implode(' AND ', $where);
        
        $sql->setQuery($query, $params);
        
        $subscriptions = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $subscriptions[] = [
                'id' => $sql->getValue('id'),
                'endpoint' => $sql->getValue('endpoint'),
                'p256dh' => $sql->getValue('p256dh'),
                'auth' => $sql->getValue('auth')
            ];
            $sql->next();
        }
        
        return $subscriptions;
    }
    
    /**
     * Aktualisiert Subscription bei erfolgreichem Versand
     */
    private function updateSubscriptionSuccess(int $subscriptionId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("UPDATE rex_push_it_subscriptions SET last_error = NULL, updated = NOW() WHERE id = ?", [$subscriptionId]);
    }
    
    /**
     * Aktualisiert Subscription bei Fehler
     */
    private function updateSubscriptionError(int $subscriptionId, string $error): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("UPDATE rex_push_it_subscriptions SET last_error = ?, updated = NOW() WHERE id = ?", [$error, $subscriptionId]);
    }
    
    /**
     * Erstellt Log-Eintrag für gesendete Benachrichtigung
     */
    private function logNotification(string $title, string $body, string $url, string $userType, string $topics, int $sent, int $errors, array $options = []): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            INSERT INTO rex_push_it_notifications (title, body, url, icon, badge, image, notification_options, topics, user_type, sent_to, delivery_errors, created_by, created)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $title,
            $body,
            $url,
            $options['icon'] ?? '/assets/addons/push_it/icon.svg',
            $options['badge'] ?? null,
            $options['image'] ?? null,
            !empty($options) ? json_encode($options) : null,
            $topics,
            $userType,
            $sent,
            $errors,
            \rex::getUser() ? \rex::getUser()->getId() : null
        ]);
    }
}
