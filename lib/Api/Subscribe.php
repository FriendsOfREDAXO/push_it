<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Api;

use rex_api_function;
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
        // Content-Type für JSON-Response setzen
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // JSON-Input validieren
            $input = file_get_contents('php://input');
            if ($input === false || $input === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'no_input_data']);
                exit;
            }
            
            $data = json_decode($input, true);
            if (!is_array($data) || !isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'invalid_subscription_data']);
                exit;
            }
            
            // Topics aus GET-Parameter und validieren
            $topics = '';
            if (isset($_GET['topics']) && is_string($_GET['topics'])) {
                $topics = trim($_GET['topics']);
            }
            
            // SettingsManager für Topic-Validierung
            $settingsManager = new SettingsManager();
            
            // User-Type validieren mit Token-basierter Backend-Authentifizierung
            $userType = $_GET['user_type'] ?? 'frontend';
            $backendToken = $_GET['backend_token'] ?? '';
            
            // Backend-Token aus AddOn-Konfiguration laden
            $addon = rex_addon::get('push_it');
            $validBackendToken = $addon->getConfig('backend_token');
            
            // Sicherheitsvalidierung für Backend-Subscriptions
            if ($userType === 'backend') {
                if (!$validBackendToken || $backendToken !== $validBackendToken) {
                    // Ungültiger oder fehlender Backend-Token
                    error_log(sprintf(
                        'SECURITY WARNING: Invalid backend token attempt from IP %s, provided token: %s',
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $backendToken ? substr($backendToken, 0, 8) . '...' : 'none'
                    ));
                    
                    http_response_code(403);
                    echo json_encode([
                        'success' => false, 
                        'error' => 'invalid_backend_token',
                        'message' => 'Ungültiger Backend-Token'
                    ]);
                    exit;
                }
                
                // Backend-Subscription mit gültigem Token
                $currentUser = rex::getUser();
                
                // User-ID aus Parameter oder aktuellem User ermitteln
                $userId = null;
                if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                    $userId = (int)$_GET['user_id'];
                } elseif ($currentUser) {
                    $userId = $currentUser->getId();
                }
                
                // Zusätzliche Sicherheitsprüfung: User-ID muss mit aktuellem User übereinstimmen
                if ($currentUser && $userId && $userId !== $currentUser->getId()) {
                    error_log(sprintf(
                        'SECURITY WARNING: User ID mismatch from IP %s, provided: %d, current: %d',
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $userId,
                        $currentUser->getId()
                    ));
                    
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
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            
            // Subscription in Datenbank speichern
            $this->saveSubscription($data, $userType, $userId, $topics, $ua, $lang, $domain);
            
            // Erfolgs-Response
            echo json_encode([
                'success' => true, 
                'user_type' => $userType,
                'user_id' => $userId,
                'timestamp' => time()
            ]);
            exit;
            
        } catch (\Throwable $e) {
            error_log('PushIt Subscribe Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'server_error']);
            exit;
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
        
        // Prüfen ob Subscription bereits existiert (basierend auf endpoint - da UNIQUE constraint)
        $sql->setQuery(
            "SELECT id, user_type, user_id, topics FROM rex_push_it_subscriptions WHERE endpoint = ?",
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
                    UPDATE rex_push_it_subscriptions 
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
                    UPDATE rex_push_it_subscriptions 
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
                    UPDATE rex_push_it_subscriptions 
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
                INSERT INTO rex_push_it_subscriptions 
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
}
