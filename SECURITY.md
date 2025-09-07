# Push It Security Guide

## Security Features

Das Push It AddOn implementiert verschiedene Sicherheitsmaßnahmen zum Schutz vor Angriffen:

### 1. Backend-Benutzer Authentifizierung

**Problem:** Ursprünglich konnten Backend-Subscriptions durch Manipulation der URL-Parameter erstellt werden.

**Lösung:** 
- Nur authentifizierte Backend-Benutzer können Backend-Subscriptions erstellen
- Benutzer können nur Subscriptions für ihren eigenen Account erstellen
- Administratoren können Subscriptions für andere Benutzer verwalten
- Validierung der Benutzer-IDs gegen die tatsächlich angemeldeten Benutzer

### 2. Sichere User-Tokens

**Problem:** Sequential user IDs waren leicht zu erraten (1, 2, 3...).

**Lösung:**
- Kryptographisch sichere SHA-256 Tokens
- Tokens kombinieren User-ID, Timestamp, Zufallsbytes und Site-Salt
- Automatisches Ablaufen nach 30 Tagen
- Automatische Bereinigung abgelaufener Tokens

```php
// Beispiel Token-Generierung
$token = hash('sha256', $userId . '|' . $timestamp . '|' . $randomBytes . '|' . $siteSalt);
```

### 3. CSP Nonce-Schutz

**Problem:** Inline JavaScript ohne Nonce-Schutz war anfällig für XSS-Angriffe.

**Lösung:**
- Alle Inline-Scripts verwenden CSP Nonces
- Event Delegation statt onclick-Handler
- Sichere Script-Einbindung in allen Backend-Seiten

```php
// Beispiel Nonce-Verwendung
$nonce = SecurityService::generateNonce();
echo '<script nonce="' . rex_escape($nonce) . '">...</script>';
```

### 4. API-Sicherheit

**Subscribe API** (`/index.php?rex-api-call=push_it_subscribe`):

**Validierungen:**
- Backend-Zugriff nur für authentifizierte Benutzer
- Token-basierte Authentifizierung
- Validierung der JSON-Subscription-Daten
- Überprüfung der Benutzerberechtigungen

**Fehlerbehandlung:**
- `400 Bad Request`: Ungültige Daten oder Token
- `401 Unauthorized`: Nicht authentifiziert
- `403 Forbidden`: Keine Berechtigung
- `500 Internal Server Error`: Server-Fehler

### 5. Token-Management

**SecurityService-Klasse:**
```php
// Token generieren
$token = SecurityService::generateUserToken($userId);

// Token validieren
$isValid = SecurityService::validateUserToken($token, $userId);

// User-ID aus Token ermitteln
$userId = SecurityService::getUserIdFromToken($token);

// CSP Nonce generieren
$nonce = SecurityService::generateNonce();
```

**Token-Bereinigung:**
- Automatische Bereinigung alle 24 Stunden
- Maximal 5 Tokens pro Benutzer
- Alte Tokens werden automatisch gelöscht

### 6. Datenbank-Sicherheit

**Neue Tabelle: `rex_push_it_user_tokens`**
```sql
CREATE TABLE `rex_push_it_user_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `created` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`)
);
```

## Migration von unsicheren Implementierungen

### Vor den Sicherheits-Updates:
```javascript
// UNSICHER: Direkte user_id Manipulation möglich
params.append('user_id', window.PushItUserId);
```

### Nach den Sicherheits-Updates:
```javascript
// SICHER: Kryptographisch sicherer Token
params.append('user_token', window.PushItUserToken);
```

## Best Practices

1. **Immer CSP Nonces verwenden** für Inline-JavaScript
2. **Token-basierte Authentifizierung** für Backend-Operationen  
3. **Event Delegation** statt direkter onclick-Handler
4. **Regelmäßige Token-Bereinigung** für Performance
5. **Validierung aller Eingaben** in API-Endpunkten

## Upgrade-Hinweise

Bei einem Update von einer älteren Version:
1. Die `update.php` erstellt automatisch die neue Token-Tabelle
2. Bestehende Subscriptions bleiben funktionsfähig
3. Neue Sicherheitsfeatures werden automatisch aktiviert
4. Keine manuellen Schritte erforderlich

## Sicherheits-Konfiguration

### CSP Header (empfohlen):
```
Content-Security-Policy: script-src 'self' 'nonce-[GENERATED_NONCE]';
```

### VAPID-Schlüssel:
- Regelmäßige Rotation alle 6-12 Monate
- Sichere Speicherung der Private Keys
- Verwendung starker Zufallsgeneratoren

## Monitoring

Überwachen Sie folgende Metriken:
- Anzahl abgelaufener Tokens (täglich bereinigt)
- Fehlgeschlagene Authentifizierungsversuche
- Ungewöhnliche Subscription-Aktivitäten
- API-Fehlercodes (401, 403, 400)

Das System protokolliert Sicherheitsereignisse im PHP Error Log.