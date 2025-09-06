<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Api;

use rex_api_function;
use rex_sql;
use rex_response;

/**
 * API-Endpunkt zum Abmelden von Push-Benachrichtigungen
 * 
 * @package FriendsOfREDAXO\PushIt\Api
 */
class Unsubscribe extends rex_api_function
{
    protected $published = true;
    
    /**
     * Führt die Abmeldung von Push-Benachrichtigungen aus
     * 
     * @return void
     */
    public function execute(): void
    {
        // Content-Type für JSON-Response setzen
        rex_response::setHeader('Content-Type', 'application/json; charset=utf-8');
        
        try {
            // Request-Daten validieren
            $requestData = $this->getValidatedRequestData();
            
            if ($requestData === null) {
                $this->sendErrorResponse('Ungültige Request-Daten', 400);
                return;
            }
            
            // Subscription in Datenbank suchen und deaktivieren
            $subscriptionId = $this->findAndDeactivateSubscription($requestData['endpoint']);
            
            if ($subscriptionId === null) {
                $this->sendErrorResponse('Subscription nicht gefunden', 404);
                return;
            }
            
            // Erfolgsmeldung senden
            $this->sendSuccessResponse($subscriptionId);
            
        } catch (\Throwable $e) {
            $this->sendErrorResponse('Server-Fehler: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Validiert und extrahiert die Request-Daten
     * 
     * @return array{endpoint: string}|null
     */
    private function getValidatedRequestData(): ?array
    {
        // Content-Type prüfen
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            return null;
        }
        
        // JSON-Body parsen
        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return null;
        }
        
        $data = json_decode($input, true);
        if (!is_array($data)) {
            return null;
        }
        
        // Endpoint validieren
        $endpoint = $data['endpoint'] ?? '';
        if (!is_string($endpoint) || trim($endpoint) === '') {
            return null;
        }
        
        // Endpoint URL validieren
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return ['endpoint' => trim($endpoint)];
    }
    
    /**
     * Sucht Subscription und deaktiviert sie
     * 
     * @param string $endpoint
     * @return int|null Subscription-ID oder null wenn nicht gefunden
     */
    private function findAndDeactivateSubscription(string $endpoint): ?int
    {
        $sql = rex_sql::factory();
        
        // Subscription suchen
        $sql->setQuery(
            "SELECT id FROM rex_push_it_subscriptions WHERE endpoint = ? AND active = 1",
            [$endpoint]
        );
        
        if ($sql->getRows() === 0) {
            return null;
        }
        
        $subscriptionId = (int) $sql->getValue('id');
        
        // Subscription deaktivieren (nicht löschen für History)
        $updateSql = rex_sql::factory();
        $updateSql->setQuery(
            "UPDATE rex_push_it_subscriptions 
             SET active = 0, updated = NOW(), last_error = NULL 
             WHERE id = ?",
            [$subscriptionId]
        );
        
        return $subscriptionId;
    }
    
    /**
     * Sendet eine Erfolgs-Response
     * 
     * @param int $subscriptionId
     * @return void
     */
    private function sendSuccessResponse(int $subscriptionId): void
    {
        $response = [
            'success' => true,
            'message' => 'Successfully unsubscribed from push notifications',
            'subscription_id' => $subscriptionId,
            'timestamp' => time()
        ];
        
        echo json_encode($response, JSON_THROW_ON_ERROR);
        exit;
    }
    
    /**
     * Sendet eine Fehler-Response
     * 
     * @param string $message
     * @param int $httpCode
     * @return void
     */
    private function sendErrorResponse(string $message, int $httpCode): void
    {
        rex_response::setStatus($httpCode);
        
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ];
        
        echo json_encode($response, JSON_THROW_ON_ERROR);
        exit;
    }
}
