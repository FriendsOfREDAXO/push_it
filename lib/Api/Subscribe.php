<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Api;

use rex_api_function;
use rex_sql;
use rex;
use FriendsOfREDAXO\PushIt\Service\SecurityService;

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
            
            // Topics aus GET-Parameter
            $topics = '';
            if (isset($_GET['topics']) && is_string($_GET['topics'])) {
                $topics = trim($_GET['topics']);
            }
            
            // User-Type validieren
            $userType = $_GET['user_type'] ?? 'frontend';
            if (!in_array($userType, ['backend', 'frontend'], true)) {
                $userType = 'frontend';
            }
            
            // User-ID für Backend-Subscriptions
            $userId = null;
            if ($userType === 'backend') {
                // Backend-Subscriptions nur für authentifizierte Benutzer
                if (!rex::isBackend()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'backend_access_denied']);
                    exit;
                }
                
                $currentUser = rex::getUser();
                if (!$currentUser) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'authentication_required']);
                    exit;
                }
                
                // Prüfen ob ein sicherer Token verwendet wird
                $userToken = $_GET['user_token'] ?? '';
                if (!empty($userToken)) {
                    // Token-basierte Authentifizierung
                    $tokenUserId = SecurityService::getUserIdFromToken($userToken);
                    if (!$tokenUserId) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'invalid_user_token']);
                        exit;
                    }
                    
                    // Prüfen ob der authentifizierte Benutzer berechtigt ist
                    $actualUserId = $currentUser->getId();
                    if ($tokenUserId !== $actualUserId && !$currentUser->isAdmin()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'token_user_mismatch']);
                        exit;
                    }
                    
                    $userId = $tokenUserId;
                } else {
                    // Fallback für direkte User-ID (nur für den eigenen Account)
                    $requestedUserId = !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null;
                    $actualUserId = $currentUser->getId();
                    
                    if ($requestedUserId && $requestedUserId !== $actualUserId) {
                        // Prüfen ob User Admin ist und andere Benutzer anmelden darf
                        if (!$currentUser->isAdmin()) {
                            http_response_code(403);
                            echo json_encode(['success' => false, 'error' => 'insufficient_permissions']);
                            exit;
                        }
                        // Prüfen ob die angeforderte User-ID tatsächlich existiert
                        $targetUser = rex_user::get($requestedUserId);
                        if (!$targetUser) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'invalid_user_id']);
                            exit;
                        }
                        $userId = $requestedUserId;
                    } else {
                        $userId = $actualUserId;
                    }
                }
            }
            
            // Browser-Informationen sammeln
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            
            // Subscription in Datenbank speichern
            $this->saveSubscription($data, $userType, $userId, $topics, $ua, $lang, $domain);
            
            // Erfolgs-Response
            $responseData = [
                'success' => true, 
                'user_type' => $userType,
                'timestamp' => time()
            ];
            
            // Für Backend-Subscriptions einen sicheren Token generieren (läuft nicht ab)
            if ($userType === 'backend' && $userId) {
                $responseData['user_token'] = SecurityService::generateUserToken($userId, true);
                $responseData['user_id'] = $userId; // Für Debugging/Logging
            }
            
            echo json_encode($responseData);
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
        
        // Prüfen ob Subscription bereits existiert
        $sql->setQuery(
            "SELECT id FROM rex_push_it_subscriptions WHERE endpoint = ?",
            [$data['endpoint']]
        );
        
        if ($sql->getRows() > 0) {
            // Update existierende Subscription
            $sql->setQuery("
                UPDATE rex_push_it_subscriptions 
                SET active = 1, user_type = ?, user_id = ?, topics = ?, 
                    ua = ?, lang = ?, domain = ?, updated = NOW(), last_error = NULL
                WHERE endpoint = ?
            ", [
                $userType,
                $userId,
                $topics,
                $ua,
                $lang,
                $domain,
                $data['endpoint']
            ]);
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
}
