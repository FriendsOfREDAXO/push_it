<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Api;

use rex_api_function;
use rex_logger;
use rex_request;
use rex_response;
use rex_sql;
use rex;
use rex_addon;
use FriendsOfREDAXO\PushIt\Service\SettingsManager;

/**
 * API-Endpunkt für Push-Notification Subscriptions
 * 
 * @package FriendsOfREDAXO\PushIt\Api
 */
class Subscribe extends rex_api_function
{
    protected $published = true;
    private const MAX_INPUT_BYTES = 16384;
    private const MAX_ENDPOINT_LENGTH = 1000;
    private const MAX_KEY_LENGTH = 255;
    private const MAX_TOPICS_LENGTH = 255;
    
    /**
     * CSRF-Schutz deaktivieren für externe Aufrufe
     * 
     * @return bool
     */
    public function requiresCsrfProtection(): bool 
    { 
        return false; 
    }
    
    /**
     * Führt die Subscription aus
     * 
     * @return void
     */
    public function execute(): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::setHeader('Content-Type', 'application/json; charset=utf-8');

        if (rex_request_method() !== 'post') {
            $this->sendJson(['success' => false, 'error' => 'method_not_allowed'], 405);
        }
        
        try {
            // JSON-Input validieren
            $input = file_get_contents('php://input');
            if ($input === false || $input === '') {
                $this->sendJson(['success' => false, 'error' => 'no_input_data'], 400);
            }

            if (strlen($input) > self::MAX_INPUT_BYTES) {
                $this->sendJson(['success' => false, 'error' => 'payload_too_large'], 413);
            }
            
            $data = json_decode($input, true);
            if (!is_array($data) || !isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
                $this->sendJson(['success' => false, 'error' => 'invalid_subscription_data'], 400);
            }

            if (!$this->isValidSubscriptionPayload($data)) {
                $this->sendJson(['success' => false, 'error' => 'invalid_subscription_payload'], 400);
            }
            
            // Topics aus Query-Parametern und validieren
            $topics = $this->normalizeTopics(trim(rex_request('topics', 'string', '')));
            
            // SettingsManager für Topic-Validierung
            $settingsManager = new SettingsManager();
            
            // User-Type validieren mit Token-basierter Backend-Authentifizierung
            $userType = rex_request('user_type', 'string', 'frontend');
            $backendToken = rex_request('backend_token', 'string', '');
            
            // Backend-Token aus AddOn-Konfiguration laden
            $addon = rex_addon::get('push_it');
            $validBackendToken = $addon->getConfig('backend_token');
            
            // Sicherheitsvalidierung für Backend-Subscriptions
            if ($userType === 'backend') {
                if (!$validBackendToken || $backendToken !== $validBackendToken) {
                    if (rex::isDebugMode()) {
                        rex_logger::logError(E_USER_WARNING, sprintf(
                            'SECURITY WARNING: Invalid backend token attempt from IP %s, provided token: %s',
                            rex_request::server('REMOTE_ADDR', 'string', 'unknown'),
                            $backendToken ? substr($backendToken, 0, 8) . '...' : 'none'
                        ), __FILE__, __LINE__);
                    }

                    $this->sendJson([
                        'success' => false,
                        'error' => 'invalid_backend_token',
                        'message' => 'Ungültiger Backend-Token',
                    ], 403);
                }
                
                // Backend-Subscription mit gültigem Token
                $currentUser = rex::getUser();
                
                // User-ID aus Parameter oder aktuellem User ermitteln
                $userId = null;
                $requestedUserId = rex_request('user_id', 'int', 0);
                if ($requestedUserId > 0) {
                    $userId = $requestedUserId;
                } elseif ($currentUser) {
                    $userId = $currentUser->getId();
                }
                
                // Zusätzliche Sicherheitsprüfung: User-ID muss mit aktuellem User übereinstimmen
                if ($currentUser && $userId && $userId !== $currentUser->getId()) {
                    if (rex::isDebugMode()) {
                        rex_logger::logError(E_USER_WARNING, sprintf(
                            'SECURITY WARNING: User ID mismatch from IP %s, provided: %d, current: %d',
                            rex_request::server('REMOTE_ADDR', 'string', 'unknown'),
                            $userId,
                            $currentUser->getId()
                        ), __FILE__, __LINE__);
                    }

                    // Verwende immer die ID des aktuell eingeloggten Users
                    $userId = $currentUser->getId();
                }
                
            } else {
                // Frontend-Subscription (kein Token erforderlich)
                $userType = 'frontend';
                $userId = null;
            }
            
            if (!in_array($userType, ['backend', 'frontend'], true)) {
                $userType = 'frontend';
            }
            
            // Topics für den User-Type filtern (entfernt Backend-Only Topics für Frontend)
            $topics = $settingsManager->filterTopicsForUserType($topics, $userType);
            
            // Browser-Informationen sammeln
            $ua     = rex_request::server('HTTP_USER_AGENT', 'string', '');
            $lang   = rex_request::server('HTTP_ACCEPT_LANGUAGE', 'string', '');
            $domain = rex_request::server('HTTP_HOST', 'string', '');
            
            // Subscription in Datenbank speichern
            $this->saveSubscription($data, $userType, $userId, $topics, $ua, $lang, $domain);
            
            // Erfolgs-Response
            $this->sendJson([
                'success' => true, 
                'user_type' => $userType,
                'user_id' => $userId,
                'timestamp' => time()
            ]);
            
        } catch (\Throwable $e) {
            rex_logger::logException($e);
            $this->sendJson(['success' => false, 'error' => 'server_error'], 500);
        }
    }
    
    /**
     * Speichert Subscription in der Datenbank
     * 
     * @param array{endpoint: string, keys: array{p256dh: string, auth: string}} $data
     * @param string $userType
     * @param int|null $userId
     * @param string $topics
     * @param string $ua
     * @param string $lang
     * @param string $domain
     * @return void
     */
    private function saveSubscription(array $data, string $userType, ?int $userId, string $topics, string $ua, string $lang, string $domain): void
    {
        $sql = rex_sql::factory();
        $table = rex::getTable('push_it_subscriptions');
        
        // Prüfen ob Subscription bereits existiert (basierend auf endpoint - da UNIQUE constraint)
        $sql->setQuery(
            'SELECT id, user_type, user_id, topics FROM ' . $table . ' WHERE endpoint = ?',
            [$data['endpoint']]
        );
        
        if ($sql->getRows() > 0) {
            // Existierende Subscription gefunden
            $existingUserType = $sql->getValue('user_type');
            $existingUserId = $sql->getValue('user_id');
            $existingTopics = $sql->getValue('topics') ?: '';
            
            // Entscheiden ob update oder error
            if ($userType === 'backend' && $existingUserType === 'frontend') {
                // Frontend->Backend: Upgrade zur Backend-Subscription
                $newTopics = $this->mergeTopics($existingTopics, $topics);
                $sql->setQuery("
                    UPDATE " . $table . "
                    SET active = 1, user_type = ?, user_id = ?, topics = ?, 
                        ua = ?, lang = ?, domain = ?, updated = NOW(), last_error = NULL
                    WHERE endpoint = ?
                ", [
                    'backend', // Backend hat Priorität
                    $userId,
                    $newTopics,
                    $ua,
                    $lang,
                    $domain,
                    $data['endpoint']
                ]);
            } elseif ($userType === 'frontend' && $existingUserType === 'backend') {
                // Backend->Frontend: Topics zu bestehender Backend-Subscription hinzufügen
                $newTopics = $this->mergeTopics($existingTopics, $topics);
                $sql->setQuery("
                    UPDATE " . $table . "
                    SET active = 1, topics = ?, ua = ?, lang = ?, domain = ?, updated = NOW(), last_error = NULL
                    WHERE endpoint = ?
                ", [
                    $newTopics,
                    $ua,
                    $lang,
                    $domain,
                    $data['endpoint']
                ]);
            } else {
                // Gleicher Typ: Standard-Update
                $newTopics = $this->mergeTopics($existingTopics, $topics);
                $updateUserId = ($userType === 'backend') ? $userId : $existingUserId;
                
                $sql->setQuery("
                    UPDATE " . $table . "
                    SET active = 1, user_id = ?, topics = ?, ua = ?, lang = ?, domain = ?, updated = NOW(), last_error = NULL
                    WHERE endpoint = ?
                ", [
                    $updateUserId,
                    $newTopics,
                    $ua,
                    $lang,
                    $domain,
                    $data['endpoint']
                ]);
            }
        } else {
            // Neue Subscription erstellen
            $sql->setQuery("
                INSERT INTO " . $table . "
                (user_id, user_type, endpoint, p256dh, auth, topics, ua, lang, domain, active, created, updated)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ", [
                $userId,
                $userType,
                $data['endpoint'],
                $data['keys']['p256dh'],
                $data['keys']['auth'],
                $topics,
                $ua,
                $lang,
                $domain
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isValidSubscriptionPayload(array $data): bool
    {
        $endpoint = $data['endpoint'] ?? '';
        $p256dh = $data['keys']['p256dh'] ?? '';
        $auth = $data['keys']['auth'] ?? '';

        if (!is_string($endpoint) || trim($endpoint) === '') {
            return false;
        }

        $endpoint = trim($endpoint);
        if (strlen($endpoint) > self::MAX_ENDPOINT_LENGTH || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        if (!is_string($p256dh) || $p256dh === '' || strlen($p256dh) > self::MAX_KEY_LENGTH) {
            return false;
        }

        if (!is_string($auth) || $auth === '' || strlen($auth) > self::MAX_KEY_LENGTH) {
            return false;
        }

        return true;
    }
    
    /**
     * Führt Topics intelligent zusammen
     * 
     * @param string $existing Bestehende Topics
     * @param string $new Neue Topics
     * @return string Kombinierte Topics
     */
    private function mergeTopics(string $existing, string $new): string
    {
        $existingArray = array_filter(explode(',', $existing));
        $newArray = array_filter(explode(',', $new));
        
        // Zusammenführen und Duplikate entfernen
        $merged = array_unique(array_merge($existingArray, $newArray));
        
        return implode(',', array_map('trim', $merged));
    }

    private function normalizeTopics(string $topics): string
    {
        if ($topics === '') {
            return '';
        }

        $parts = array_filter(array_map('trim', explode(',', $topics)));
        $cleaned = [];

        foreach ($parts as $topic) {
            if (preg_match('/^[a-z0-9_-]{1,40}$/i', $topic) === 1) {
                $cleaned[] = $topic;
            }
        }

        $value = implode(',', array_unique($cleaned));
        if (strlen($value) > self::MAX_TOPICS_LENGTH) {
            $value = substr($value, 0, self::MAX_TOPICS_LENGTH);
            $value = rtrim($value, ',');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendJson(array $data, int $statusCode = 200): void
    {
        rex_response::setStatus($statusCode);
        rex_response::sendJson($data);
        exit;
    }
}
