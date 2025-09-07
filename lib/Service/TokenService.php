<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Service;

use rex_sql;
use rex;

/**
 * Token-Service für Backend-User-Authentifizierung
 * 
 * @package FriendsOfREDAXO\PushIt\Service
 */
class TokenService
{
    /**
     * Generiert einen neuen Auth-Token für einen Backend-User
     * 
     * @param int $userId User-ID
     * @param bool $isAdmin Ist der User Admin?
     * @param int $validDays Gültigkeit in Tagen (default: 365)
     * @return string Generated token
     */
    public static function generateToken(int $userId, bool $isAdmin = false, int $validDays = 365): string
    {
        // Alten Token des Users deaktivieren
        self::deactivateUserTokens($userId);
        
        // Kryptographisch sicheren Token generieren (64 Zeichen)
        $token = bin2hex(random_bytes(32));
        
        // Token in Datenbank speichern
        $sql = rex_sql::factory();
        $sql->setQuery("
            INSERT INTO rex_push_it_user_tokens 
            (user_id, token, is_admin, active, created, expires)
            VALUES (?, ?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))
        ", [
            $userId,
            $token,
            $isAdmin ? 1 : 0,
            $validDays
        ]);
        
        return $token;
    }
    
    /**
     * Validiert einen Auth-Token
     * 
     * @param string $token
     * @return array|null ['user_id' => int, 'is_admin' => bool] oder null wenn ungültig
     */
    public static function validateToken(string $token): ?array
    {
        if (empty($token) || strlen($token) !== 64) {
            return null;
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT user_id, is_admin 
            FROM rex_push_it_user_tokens 
            WHERE token = ? AND active = 1 AND expires > NOW()
        ", [$token]);
        
        if ($sql->getRows() === 0) {
            return null;
        }
        
        // Last-used Zeitstempel aktualisieren
        self::updateLastUsed($token);
        
        return [
            'user_id' => (int) $sql->getValue('user_id'),
            'is_admin' => (bool) $sql->getValue('is_admin')
        ];
    }
    
    /**
     * Deaktiviert alle Tokens eines Users
     * 
     * @param int $userId
     */
    public static function deactivateUserTokens(int $userId): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            UPDATE rex_push_it_user_tokens 
            SET active = 0 
            WHERE user_id = ?
        ", [$userId]);
    }
    
    /**
     * Deaktiviert einen spezifischen Token
     * 
     * @param string $token
     */
    public static function deactivateToken(string $token): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            UPDATE rex_push_it_user_tokens 
            SET active = 0 
            WHERE token = ?
        ", [$token]);
    }
    
    /**
     * Holt den aktuellen Token für einen User
     * 
     * @param int $userId
     * @return string|null
     */
    public static function getUserToken(int $userId): ?string
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT token 
            FROM rex_push_it_user_tokens 
            WHERE user_id = ? AND active = 1 AND expires > NOW()
            ORDER BY created DESC 
            LIMIT 1
        ", [$userId]);
        
        if ($sql->getRows() === 0) {
            return null;
        }
        
        return $sql->getValue('token');
    }
    
    /**
     * Holt alle aktiven Tokens (für Admin-Übersicht)
     * 
     * @return array
     */
    public static function getAllActiveTokens(): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            SELECT 
                t.*, 
                u.name as user_name, 
                u.login,
                CONCAT(LEFT(t.token, 8), '...') as token_preview
            FROM rex_push_it_user_tokens t 
            LEFT JOIN rex_user u ON t.user_id = u.id 
            WHERE t.active = 1 AND t.expires > NOW()
            ORDER BY t.created DESC
        ");
        
        $tokens = [];
        for ($i = 0; $i < $sql->getRows(); $i++) {
            $tokens[] = [
                'id' => $sql->getValue('id'),
                'user_id' => $sql->getValue('user_id'),
                'user_name' => $sql->getValue('user_name') ?: $sql->getValue('login'),
                'token_preview' => $sql->getValue('token_preview'),
                'is_admin' => (bool) $sql->getValue('is_admin'),
                'created' => $sql->getValue('created'),
                'expires' => $sql->getValue('expires'),
                'last_used' => $sql->getValue('last_used')
            ];
            $sql->next();
        }
        
        return $tokens;
    }
    
    /**
     * Aktualisiert den last_used Zeitstempel
     * 
     * @param string $token
     */
    private static function updateLastUsed(string $token): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            UPDATE rex_push_it_user_tokens 
            SET last_used = NOW() 
            WHERE token = ?
        ", [$token]);
    }
    
    /**
     * Bereinigt abgelaufene Tokens
     */
    public static function cleanupExpiredTokens(): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery("
            DELETE FROM rex_push_it_user_tokens 
            WHERE expires < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
}
