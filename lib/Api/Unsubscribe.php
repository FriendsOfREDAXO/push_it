<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Api;

use rex_api_function;
use rex_api_result;
use rex_sql;
use rex_response;
use rex_i18n;
use rex_request;
use rex;

/**
 * API-Endpunkt zum Abmelden von Push-Benachrichtigungen
 * 
 * @package FriendsOfREDAXO\PushIt\Api
 */
class Unsubscribe extends rex_api_function
{
    protected $published = true;
    private const MAX_INPUT_BYTES = 4096;
    private const MAX_ENDPOINT_LENGTH = 1000;

    public function requiresCsrfProtection(): bool
    {
        return false;
    }
    
    /**
     * Führt die Abmeldung von Push-Benachrichtigungen aus
     * 
    * @return rex_api_result
     */
    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();
        // Content-Type für JSON-Response setzen
        rex_response::setHeader('Content-Type', 'application/json; charset=utf-8');

        if (rex_request_method() !== 'post') {
            $this->sendErrorResponse('method_not_allowed', 405);
        }
        
        try {
            // Request-Daten validieren
            $requestData = $this->getValidatedRequestData();
            
            if ($requestData === null) {
                $this->sendErrorResponse('Ungültige Request-Daten', 400);
                return new rex_api_result(false);
            }
            
            // Subscription in Datenbank suchen und deaktivieren
            $subscriptionId = $this->findAndDeactivateSubscription($requestData['endpoint']);
            
            if ($subscriptionId === null) {
                $this->sendErrorResponse('Subscription nicht gefunden', 404);
                return new rex_api_result(false);
            }
            
            // Erfolgsmeldung senden
            $this->sendSuccessResponse($subscriptionId);
            
        } catch (\Throwable $e) {
            $this->sendErrorResponse(rex_i18n::msg('pushit_server_error'), 500);
        }

        return new rex_api_result(true);
    }
    
    /**
     * Validiert und extrahiert die Request-Daten
     * 
     * @return array{endpoint: string}|null
     */
    private function getValidatedRequestData(): ?array
    {
        // Content-Type prüfen
        $contentType = rex_request::server('CONTENT_TYPE', 'string', '');
        if (!str_contains($contentType, 'application/json')) {
            return null;
        }
        
        // JSON-Body parsen
        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return null;
        }

        if (strlen($input) > self::MAX_INPUT_BYTES) {
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

        if (strlen(trim($endpoint)) > self::MAX_ENDPOINT_LENGTH) {
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
        $table = rex::getTable('push_it_subscriptions');
        
        // Subscription suchen
        $sql->setQuery(
            'SELECT id FROM ' . $table . ' WHERE endpoint = ? AND active = 1',
            [$endpoint]
        );
        
        if ($sql->getRows() === 0) {
            return null;
        }
        
        $subscriptionId = (int) $sql->getValue('id');
        
        // Subscription deaktivieren (nicht löschen für History)
        $updateSql = rex_sql::factory();
        $updateSql->setQuery(
            'UPDATE ' . $table . '
             SET active = 0, updated = NOW(), last_error = NULL 
             WHERE id = ?',
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

        $this->sendJson($response, 200);
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
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ];

        $this->sendJson($response, $httpCode);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendJson(array $data, int $statusCode): void
    {
        rex_response::setStatus((string) $statusCode);
        rex_response::sendJson($data);
        exit;
    }
}
