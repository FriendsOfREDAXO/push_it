<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Service;

use rex;
use rex_addon;
use rex_addon_interface;
use rex_logger;
use rex_sql;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class NotificationService
{
    private rex_addon_interface $addon;
    private ?bool $topicTableAvailable = null;

    public function __construct()
    {
        $this->addon = rex_addon::get('push_it');
    }

    /**
     * Sendet eine Benachrichtigung an alle Backend-Nutzer
     *
     * @param array<string> $topics
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendToBackendUsers(string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        return $this->sendNotification($title, $body, $url, 'backend', $topics, $options);
    }

    /**
     * Sendet eine Benachrichtigung an alle Frontend-Nutzer
     *
     * @param array<string> $topics
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendToFrontendUsers(string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        return $this->sendNotification($title, $body, $url, 'frontend', $topics, $options);
    }

    /**
     * Sendet eine Benachrichtigung an alle Nutzer
     *
     * @param array<string> $topics
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendToAllUsers(string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        return $this->sendNotification($title, $body, $url, 'both', $topics, $options);
    }

    /**
     * Sendet eine Benachrichtigung an einen spezifischen Backend-Benutzer.
     *
     * HINWEIS: Funktioniert nur für Backend-User mit REDAXO User-ID.
     * Frontend-User haben keine User-IDs – nutzen Sie stattdessen Topics.
     *
     * @param array<string> $topics
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '', array $topics = [], array $options = []): array
    {
        $webPush = $this->createWebPush();

        $subscriptions = $this->getSubscriptionsByUserId($userId, $topics);

        if ($subscriptions === []) {
            return [
                'success' => false,
                'message' => 'Keine aktiven Subscriptions für User ID: ' . $userId . ' und Topics: ' . implode(',', $topics),
                'sent'    => 0,
                'failed'  => 0,
                'total'   => 0,
            ];
        }

        $payloadJson = $this->buildPayload($title, $body, $url, $options);
        [$sent, $errors] = $this->dispatchToSubscriptions($webPush, $subscriptions, $payloadJson);

        $this->logNotification($title, $body, $url, 'user_' . $userId, implode(',', $topics), $sent, $errors, $options);

        return [
            'success' => true,
            'sent'    => $sent,
            'failed'  => $errors,
            'total'   => count($subscriptions),
        ];
    }

    /**
     * Hauptfunktion zum Senden von Benachrichtigungen.
     *
     * @param array<string> $topics
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function sendNotification(string $title, string $body, string $url = '', string $userType = 'frontend', array $topics = [], array $options = []): array
    {
        $webPush = $this->createWebPush();

        $subscriptions = $this->getSubscriptions($userType, $topics);

        if ($subscriptions === []) {
            return [
                'success' => false,
                'message' => 'Keine aktiven Subscriptions gefunden für User-Typ: ' . $userType . ' und Topics: ' . implode(',', $topics),
                'sent'    => 0,
                'failed'  => 0,
                'total'   => 0,
            ];
        }

        $payloadJson = $this->buildPayload($title, $body, $url, $options);
        [$sent, $errors] = $this->dispatchToSubscriptions($webPush, $subscriptions, $payloadJson);

        $this->logNotification($title, $body, $url, $userType, implode(',', $topics), $sent, $errors, $options);

        return [
            'success' => true,
            'sent'    => $sent,
            'failed'  => $errors,
            'total'   => count($subscriptions),
        ];
    }

    /**
     * Erzeugt eine WebPush-Instanz mit VAPID-Konfiguration.
     *
     * @throws \Exception
     */
    private function createWebPush(): WebPush
    {
        $publicKey  = (string) $this->addon->getConfig('publicKey', '');
        $privateKey = (string) $this->addon->getConfig('privateKey', '');
        $subject    = (string) $this->addon->getConfig('subject', '');

        if ($publicKey === '' || $privateKey === '') {
            throw new \Exception('VAPID-Schlüssel nicht konfiguriert');
        }

        return new WebPush([
            'VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    }

    /**
     * Baut das Payload-Array auf und gibt es JSON-kodiert zurück.
     *
     * @param array<string, mixed> $options
     */
    private function buildPayload(string $title, string $body, string $url, array $options): string
    {
        $defaultIcon = (string) $this->addon->getConfig('default_icon', '');
        $icon = $options['icon'] ?? ($defaultIcon !== '' ? $defaultIcon : '/assets/addons/push_it/icon.svg');

        $payload = [
            'title'     => $title,
            'body'      => $body,
            'url'       => $url,
            'icon'      => $icon,
            'timestamp' => time(),
        ];

        foreach (['badge', 'image', 'silent', 'tag', 'renotify'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        foreach (['vibrate', 'actions'] as $key) {
            if (isset($options[$key]) && is_array($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        return (string) json_encode($payload);
    }

    /**
     * Sendet Payload an eine Liste von Subscriptions.
     *
     * @param array<array<string, mixed>> $subscriptions
     * @return array{0: int, 1: int} [sent, errors]
     */
    private function dispatchToSubscriptions(WebPush $webPush, array $subscriptions, string $payloadJson): array
    {
        $sent   = 0;
        $errors = 0;

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => (string) $sub['endpoint'],
                    'keys'     => [
                        'p256dh' => (string) $sub['p256dh'],
                        'auth'   => (string) $sub['auth'],
                    ],
                ]);

                $result = $webPush->sendOneNotification($subscription, $payloadJson);

                if ($result->isSuccess()) {
                    $sent++;
                    $this->updateSubscriptionSuccess((int) $sub['id']);
                } else {
                    $errors++;
                    $reason = (string) $result->getReason();
                    $this->updateSubscriptionError((int) $sub['id'], $reason);

                    if ($this->shouldDeactivateSubscription($result, $reason)) {
                        $this->deactivateSubscription((int) $sub['id']);
                    }

                    if (rex::isDebugMode()) {
                        rex_logger::factory()->warning('PushIt: send failed for sub {id}: {reason}', [
                            'id'     => $sub['id'],
                            'reason' => $reason,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                $this->updateSubscriptionError((int) $sub['id'], $e->getMessage());
                if (rex::isDebugMode()) {
                    rex_logger::factory()->warning('PushIt: exception for sub {id}: {msg}', [
                        'id'  => $sub['id'],
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [$sent, $errors];
    }

    /**
     * Holt aktive Subscriptions aus der Datenbank.
     *
     * @param array<string> $topics
     * @return array<array<string, mixed>>
     */
    private function getSubscriptions(string $userType, array $topics = []): array
    {
        $sql = rex_sql::factory();
        $subscriptionTable = rex::getTable('push_it_subscriptions');
        $where = ['s.active = 1'];
        $params = [];

        // User Type Filter
        if ($userType === 'backend') {
            $where[] = "s.user_type = 'backend'";
        } elseif ($userType === 'frontend') {
            $where[] = "s.user_type = 'frontend'";
        }
        // bei 'both' keine Einschränkung

        $normalizedTopics = $this->normalizeTopics($topics);
        $query = 'SELECT DISTINCT s.id, s.endpoint, s.p256dh, s.auth FROM ' . $subscriptionTable . ' s';

        if ($normalizedTopics !== [] && $this->hasTopicTable()) {
            $topicTable = rex::getTable('push_it_subscription_topics');
            $query .= ' INNER JOIN ' . $topicTable . ' st ON st.subscription_id = s.id';

            $topicPlaceholders = implode(',', array_fill(0, count($normalizedTopics), '?'));
            $where[] = 'st.topic IN (' . $topicPlaceholders . ')';
            foreach ($normalizedTopics as $topic) {
                $params[] = $topic;
            }
        } elseif ($normalizedTopics !== []) {
            $topicConditions = [];
            foreach ($normalizedTopics as $topic) {
                $topicConditions[] = 'FIND_IN_SET(?, s.topics)';
                $params[] = $topic;
            }
            $where[] = '(' . implode(' OR ', $topicConditions) . ')';
        }

        $query .= ' WHERE ' . implode(' AND ', $where);

        $sql->setQuery($query, $params);

        $subscriptions = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $subscriptions[] = [
                'id'       => $sql->getValue('id'),
                'endpoint' => $sql->getValue('endpoint'),
                'p256dh'   => $sql->getValue('p256dh'),
                'auth'     => $sql->getValue('auth'),
            ];
            $sql->next();
        }

        return $subscriptions;
    }

    /**
     * Holt aktive Subscriptions für einen spezifischen Benutzer.
     *
     * @param array<string> $topics
     * @return array<array<string, mixed>>
     */
    private function getSubscriptionsByUserId(int $userId, array $topics = []): array
    {
        $sql = rex_sql::factory();
        $subscriptionTable = rex::getTable('push_it_subscriptions');

        $where  = ['s.active = 1', 's.user_id = ?', "s.user_type = 'backend'"];
        $params = [$userId];
        $normalizedTopics = $this->normalizeTopics($topics);
        $query = 'SELECT DISTINCT s.id, s.endpoint, s.p256dh, s.auth FROM ' . $subscriptionTable . ' s';

        if ($normalizedTopics !== [] && $this->hasTopicTable()) {
            $topicTable = rex::getTable('push_it_subscription_topics');
            $query .= ' INNER JOIN ' . $topicTable . ' st ON st.subscription_id = s.id';

            $topicPlaceholders = implode(',', array_fill(0, count($normalizedTopics), '?'));
            $where[] = 'st.topic IN (' . $topicPlaceholders . ')';
            foreach ($normalizedTopics as $topic) {
                $params[] = $topic;
            }
        } elseif ($normalizedTopics !== []) {
            $topicConditions = [];
            foreach ($normalizedTopics as $topic) {
                $topicConditions[] = 'FIND_IN_SET(?, s.topics)';
                $params[] = $topic;
            }
            $where[] = '(' . implode(' OR ', $topicConditions) . ')';
        }

        $query .= ' WHERE ' . implode(' AND ', $where);

        $sql->setQuery($query, $params);

        $subscriptions = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $subscriptions[] = [
                'id'       => $sql->getValue('id'),
                'endpoint' => $sql->getValue('endpoint'),
                'p256dh'   => $sql->getValue('p256dh'),
                'auth'     => $sql->getValue('auth'),
            ];
            $sql->next();
        }

        return $subscriptions;
    }

    /**
     * Aktualisiert Subscription bei erfolgreichem Versand.
     */
    private function updateSubscriptionSuccess(int $subscriptionId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('UPDATE ' . rex::getTable('push_it_subscriptions') . ' SET last_error = NULL, updated = NOW() WHERE id = ?', [$subscriptionId]);
    }

    /**
     * Aktualisiert Subscription bei Fehler.
     */
    private function updateSubscriptionError(int $subscriptionId, string $error): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('UPDATE ' . rex::getTable('push_it_subscriptions') . ' SET last_error = ?, updated = NOW() WHERE id = ?', [$error, $subscriptionId]);
    }

    /**
     * Markiert eine Subscription als inaktiv und räumt Topic-Zuordnungen auf.
     */
    private function deactivateSubscription(int $subscriptionId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'UPDATE ' . rex::getTable('push_it_subscriptions') . ' SET active = 0, updated = NOW() WHERE id = ?',
            [$subscriptionId]
        );

        if ($this->hasTopicTable()) {
            $topicSql = rex_sql::factory();
            $topicSql->setQuery(
                'DELETE FROM ' . rex::getTable('push_it_subscription_topics') . ' WHERE subscription_id = ?',
                [$subscriptionId]
            );
        }
    }

    /**
     * Erkennt abgelaufene/ungültige Push-Endpoints und markiert sie zum Deaktivieren.
     *
     * @param object $result Web-Push Resultatobjekt
     */
    private function shouldDeactivateSubscription(object $result, string $reason): bool
    {
        if (method_exists($result, 'isSubscriptionExpired') && $result->isSubscriptionExpired()) {
            return true;
        }

        $reasonLc = strtolower($reason);
        foreach ([' 404 ', ' 410 ', 'expired', 'unregistered', 'gone'] as $needle) {
            if (str_contains(' ' . $reasonLc . ' ', $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string> $topics
     * @return array<string>
     */
    private function normalizeTopics(array $topics): array
    {
        $cleaned = [];

        foreach ($topics as $topic) {
            $value = trim(strtolower($topic));
            if ($value !== '') {
                $cleaned[] = $value;
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function hasTopicTable(): bool
    {
        if ($this->topicTableAvailable !== null) {
            return $this->topicTableAvailable;
        }

        $sql = rex_sql::factory();
        $sql->setQuery('SHOW TABLES LIKE ?', [rex::getTable('push_it_subscription_topics')]);
        $this->topicTableAvailable = $sql->getRows() > 0;

        return $this->topicTableAvailable;
    }

    /**
     * Erstellt Log-Eintrag für gesendete Benachrichtigung.
     *
     * @param array<string, mixed> $options
     */
    private function logNotification(string $title, string $body, string $url, string $userType, string $topics, int $sent, int $errors, array $options = []): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            INSERT INTO rex_push_it_notifications (title, body, url, icon, badge, image, notification_options, topics, user_type, sent_to, delivery_errors, created_by, created)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ', [
            $title,
            $body,
            $url,
            $options['icon'] ?? '/assets/addons/push_it/icon.svg',
            $options['badge'] ?? null,
            $options['image'] ?? null,
            $options !== [] ? json_encode($options) : null,
            $topics,
            $userType,
            $sent,
            $errors,
            rex::getUser()?->getId() ?? null,
        ]);
    }
}
