<?php

declare(strict_types=1);

namespace FriendsOfREDAXO\PushIt\Service;

use rex_sql;
use rex;

/**
 * Security Service für Push It
 * 
 * @package FriendsOfREDAXO\PushIt\Service
 */
class SecurityService
{
    /**
     * Generiert einen sicheren Token für einen Benutzer
     * 
     * @param int $userId
     * @param bool $isBackendUser Ist der Benutzer ein Backend-User? (Token läuft nicht ab)
     * @return string
     */
    public static function generateUserToken(int $userId, bool $isBackendUser = false): string
    {
        // Kombiniere User-ID mit einem sicheren Zufallswert
        $randomBytes = random_bytes(16);
        $timestamp = time();
        $siteSalt = rex::getProperty('instname', 'pushit'); // Unique per installation
        
        // Erstelle einen Hash aus User-ID, Timestamp, Zufallsbytes und Site-Salt
        $data = $userId . '|' . $timestamp . '|' . bin2hex($randomBytes) . '|' . $siteSalt;
        $token = hash('sha256', $data);
        
        // Speichere Token in der Datenbank für spätere Validierung
        self::storeUserToken($userId, $token, $timestamp, $isBackendUser);
        
        return $token;
    }
    
    /**
     * Validiert einen Benutzer-Token
     * 
     * @param string $token
     * @param int $userId
     * @return bool
     */
    public static function validateUserToken(string $token, int $userId): bool
    {
        if (empty($token) || $userId <= 0) {
            return false;
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery(
            "SELECT id, created FROM rex_push_it_user_tokens 
             WHERE user_id = ? AND token = ? AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created DESC LIMIT 1",
            [$userId, $token]
        );
        
        return $sql->getRows() > 0;
    }
    
    /**
     * Holt die User-ID für einen gültigen Token
     * 
     * @param string $token
     * @return int|null
     */
    public static function getUserIdFromToken(string $token): ?int
    {
        if (empty($token)) {
            return null;
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery(
            "SELECT user_id FROM rex_push_it_user_tokens 
             WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created DESC LIMIT 1",
            [$token]
        );
        
        if ($sql->getRows() > 0) {
            return (int) $sql->getValue('user_id');
        }
        
        return null;
    }
    
    /**
     * Speichert einen Token in der Datenbank
     * 
     * @param int $userId
     * @param string $token
     * @param int $timestamp
     * @param bool $isBackendUser Backend-User Tokens laufen nicht ab
     * @return void
     */
    private static function storeUserToken(int $userId, string $token, int $timestamp, bool $isBackendUser = false): void
    {
        $sql = rex_sql::factory();
        
        // Alte Tokens für diesen User löschen (max. 5 Tokens pro User)
        $sql->setQuery(
            "DELETE FROM rex_push_it_user_tokens 
             WHERE user_id = ? AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM rex_push_it_user_tokens 
                     WHERE user_id = ? 
                     ORDER BY created DESC LIMIT 4
                 ) AS t
             )",
            [$userId, $userId]
        );
        
        // Neuen Token speichern
        if ($isBackendUser) {
            // Backend-User Tokens laufen nicht ab
            $sql->setQuery(
                "INSERT INTO rex_push_it_user_tokens (user_id, token, created, expires_at)
                 VALUES (?, ?, NOW(), NULL)",
                [$userId, $token]
            );
        } else {
            // Frontend-User Tokens laufen nach 30 Tagen ab
            $expiresAt = date('Y-m-d H:i:s', $timestamp + (30 * 24 * 60 * 60));
            $sql->setQuery(
                "INSERT INTO rex_push_it_user_tokens (user_id, token, created, expires_at)
                 VALUES (?, ?, NOW(), ?)",
                [$userId, $token, $expiresAt]
            );
        }
    }
    
    /**
     * Bereinigt abgelaufene Tokens
     * 
     * @return int Anzahl gelöschte Tokens
     */
    public static function cleanupExpiredTokens(): int
    {
        $sql = rex_sql::factory();
        $sql->setQuery("DELETE FROM rex_push_it_user_tokens WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        return $sql->getAffectedRows();
    }
    
    /**
     * Löscht alle Tokens für einen bestimmten Benutzer
     * (z.B. wenn der Benutzer gelöscht wird)
     * 
     * @param int $userId
     * @return int Anzahl gelöschte Tokens
     */
    public static function deleteUserTokens(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery("DELETE FROM rex_push_it_user_tokens WHERE user_id = ?", [$userId]);
        return $sql->getAffectedRows();
    }
    
    /**
     * Generiert eine sichere Nonce für CSP
     * 
     * @return string
     */
    public static function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }
    
    /**
     * Holt die aktuelle Nonce aus den rex properties
     * 
     * @return string
     */
    public static function getCurrentNonce(): string
    {
        // Nonce aus rex properties holen oder neue generieren
        $nonce = rex_view::getJsProperty('push_it_nonce');
        if (!$nonce) {
            $nonce = self::generateNonce();
            rex_view::setJsProperty('push_it_nonce', $nonce);
        }
        return $nonce;
    }
}