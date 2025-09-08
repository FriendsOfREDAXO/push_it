# PushIt - API Documentation

Vollständige API-Referenz für das PushIt AddOn.

## � **Inhaltsverzeichnis**

1. [PHP Service Classes](#php-service-classes)
2. [REST API Endpoints](#rest-api-endpoints)
3. [JavaScript Frontend API](#javascript-frontend-api)
4. [Extension Points](#extension-points)
5. [Configuration Options](#configuration-options)
6. [Advanced Features](#advanced-features)
7. [Error Handling](#error-handling)
8. [Performance & Limits](#performance--limits)
- [Configuration API](#configuration-api)

---

## 🌐 JavaScript Frontend API

### PushIt Object

Das globale `PushIt` Objekt ist der Haupteinstiegspunkt für Frontend-Funktionalität.

#### Methods

##### `PushIt.subscribe(userType, topics)`

Abonniert Push-Benachrichtigungen für den angegebenen Benutzertyp und Topics.

```javascript
await PushIt.subscribe(userType, topics);
```

**Parameter:**
- `userType` (string): `'frontend'` oder `'backend'`
- `topics` (string): Komma-getrennte Liste von Topics (z.B. `'news,offers,updates'`)

**Returns:** `Promise<Object>`

**Example:**
```javascript
try {
    const result = await PushIt.subscribe('frontend', 'news,offers');
    console.log('Subscription successful:', result);
} catch (error) {
    console.error('Subscription failed:', error.message);
}
```

---

##### `PushIt.unsubscribe()`

Deaktiviert Push-Benachrichtigungen für den aktuellen Browser.

```javascript
await PushIt.unsubscribe();
```

**Returns:** `Promise<Object>`

**Example:**
```javascript
try {
    await PushIt.unsubscribe();
    console.log('Successfully unsubscribed');
} catch (error) {
    console.error('Unsubscribe failed:', error);
}
```

---

##### `PushIt.getStatus()`

Ruft den aktuellen Subscription-Status ab.

```javascript
const status = await PushIt.getStatus();
```

**Returns:** `Promise<Object>`
```javascript
{
    isSubscribed: boolean,
    subscription: PushSubscription|null,
    error?: string
}
```

**Example:**
```javascript
const status = await PushIt.getStatus();
if (status.isSubscribed) {
    console.log('Notifications are active');
} else {
    console.log('Notifications not active');
}
```

---

##### `PushIt.requestFrontend(topics)`

Vereinfachte Methode zum Aktivieren von Frontend-Benachrichtigungen mit Alert-Feedback.

```javascript
await PushIt.requestFrontend(topics);
```

**Parameter:**
- `topics` (string, optional): Topics, Standard: `window.PushItTopics`

---

##### `PushIt.requestBackend(topics)`

Vereinfachte Methode zum Aktivieren von Backend-Benachrichtigungen mit Alert-Feedback.

```javascript
await PushIt.requestBackend(topics);
```

**Parameter:**
- `topics` (string, optional): Topics, Standard: `window.PushItTopics`

---

##### `PushIt.disable()`

Vereinfachte Methode zum Deaktivieren mit Alert-Feedback.

```javascript
await PushIt.disable();
```

---

### PushIt.i18n Object

Internationalisierungs-API für mehrsprachige Anwendungen.

##### `PushIt.i18n.get(key, replacements)`

Ruft übersetzten Text ab.

```javascript
const text = PushIt.i18n.get(key, replacements);
```

**Parameter:**
- `key` (string): Übersetzungsschlüssel
- `replacements` (object, optional): Platzhalter-Ersetzungen

**Example:**
```javascript
const message = PushIt.i18n.get('error.permission_denied');
const error = PushIt.i18n.get('error.server_error', { status: 404 });
```

##### `PushIt.i18n.loadLanguage(lang)`

Lädt eine Sprachdatei dynamisch.

```javascript
await PushIt.i18n.loadLanguage('en');
```

---

## 🔧 JavaScript Backend API

### PushItBackend Object

Spezielle API für Backend-Administratoren.

##### `PushItBackend.sendTestNotification()`

Sendet eine Test-Benachrichtigung.

```javascript
PushItBackend.sendTestNotification();
```

##### `PushItBackend.resetBackendAsk()`

Setzt den "Backend gefragt" Status zurück.

```javascript
PushItBackend.resetBackendAsk();
```

### Debug Functions

##### `PushItDebug()`

Zeigt Debug-Informationen in der Console.

```javascript
PushItDebug();
```

##### `PushItTest()`

Forciert die Anzeige des Notification-Banners.

```javascript
PushItTest();
```

##### `PushItReset()`

Setzt den Subscription-Status zurück.

```javascript
PushItReset();
```

---

## 🏗️ PHP Service Classes

### SendManager

Klasse zum Versenden von Push-Benachrichtigungen.

```php
use FriendsOfREDAXO\PushIt\Service\SendManager;

$sendManager = new SendManager();
```

#### Methods

##### `sendNotification(array $data): array`

Sendet eine Push-Benachrichtigung.

```php
public function sendNotification(array $data): array
```

**Parameter:**
```php
$data = [
    'title' => 'Notification Title',
    'body' => 'Notification message',
    'icon' => '/path/to/icon.png',
    'url' => 'https://example.com/target',
    'topics' => ['news', 'updates'],
    'user_types' => ['frontend', 'backend'],
    'user_ids' => [1, 2, 3], // optional: specific users
    'ttl' => 3600, // optional: Time-to-live in seconds
    'priority' => 'high', // optional: 'normal', 'high'
    'data' => ['custom' => 'data'] // optional: custom data
];

$result = $sendManager->sendNotification($data);
```

**Returns:**
```php
[
    'success' => true,
    'sent_count' => 42,
    'failed_count' => 0,
    'message' => 'Successfully sent to 42 recipients',
    'details' => [
        'frontend' => 35,
        'backend' => 7
    ]
]
```

**Example:**
```php
try {
    $result = $sendManager->sendNotification([
        'title' => 'Neue Nachricht',
        'body' => 'Sie haben eine neue Nachricht erhalten',
        'icon' => rex_url::assets('addons/push_it/icon.png'),
        'url' => rex_url::frontend(),
        'topics' => ['news'],
        'user_types' => ['frontend']
    ]);
    
    if ($result['success']) {
        rex_logger::logInfo('push_it', 'Notification sent', $result);
    }
} catch (Exception $e) {
    rex_logger::logError('push_it', 'Send failed: ' . $e->getMessage());
}
```

---

##### `sendQuickNotification(string $type, array $options = []): array`

Sendet vordefinierte Quick-Benachrichtigungen.

```php
public function sendQuickNotification(string $type, array $options = []): array
```

**Parameter:**
- `$type`: `'critical_error'`, `'system_warning'`, `'system_info'`
- `$options`: Zusätzliche Optionen

**Example:**
```php
$result = $sendManager->sendQuickNotification('critical_error', [
    'message' => 'Database connection failed',
    'url' => rex_url::backendPage('system/log')
]);
```

---

### SubscriptionManager

Verwaltet Push-Subscriptions.

```php
use FriendsOfREDAXO\PushIt\Service\SubscriptionManager;

$subscriptionManager = new SubscriptionManager();
```

#### Methods

##### `getAllSubscriptions(bool $activeOnly = true): array`

Ruft alle Subscriptions ab.

```php
public function getAllSubscriptions(bool $activeOnly = true): array
```

**Returns:** Array von Subscription-Objekten

---

##### `getSubscriptionsByUserType(string $userType): array`

Ruft Subscriptions nach Benutzertyp ab.

```php
public function getSubscriptionsByUserType(string $userType): array
```

**Parameter:**
- `$userType`: `'frontend'` oder `'backend'`

---

##### `deleteSubscription(int $id): bool`

Löscht eine Subscription.

```php
public function deleteSubscription(int $id): bool
```

---

##### `getSubscriptionStats(): array`

Ruft Subscription-Statistiken ab.

```php
public function getSubscriptionStats(): array
```

**Returns:**
```php
[
    'total' => 150,
    'frontend' => 120,
    'backend' => 30,
    'active' => 145,
    'inactive' => 5
]
```

---

### SettingsManager

Verwaltet AddOn-Einstellungen.

```php
use FriendsOfREDAXO\PushIt\Service\SettingsManager;

$settingsManager = new SettingsManager();
```

#### Methods

##### `validateVapidKeys(string $publicKey, string $privateKey): array`

Validiert VAPID-Schlüssel.

```php
public function validateVapidKeys(string $publicKey, string $privateKey): array
```

**Returns:**
```php
[
    'valid' => true,
    'public_valid' => true,
    'private_valid' => true,
    'error' => null
]
```

---

##### `getAvailableTopics(): array`

Ruft verfügbare Topics ab.

```php
public function getAvailableTopics(): array
```

---

##### `filterTopicsForUserType(array $topics, string $userType): array`

Filtert Topics nach Benutzertyp (berücksichtigt Backend-Only Topics).

```php
public function filterTopicsForUserType(array $topics, string $userType): array
```

---

### BackendNotificationManager

Verwaltet Backend-Benachrichtigungen.

```php
use FriendsOfREDAXO\PushIt\Service\BackendNotificationManager;

$backendManager = new BackendNotificationManager();
```

#### Methods

##### `sendBackendNotification(string $title, string $message, string $priority = 'normal'): array`

Sendet Backend-Benachrichtigung.

```php
public function sendBackendNotification(string $title, string $message, string $priority = 'normal'): array
```

---

##### `getBackendUsers(): array`

Ruft alle Backend-Benutzer mit Subscriptions ab.

```php
public function getBackendUsers(): array
```

---

## 🔌 REST API Endpoints

### POST `/index.php?rex-api-call=push_it_subscribe`

Erstellt eine neue Push-Subscription.

**Request Body:**
```json
{
    "endpoint": "https://fcm.googleapis.com/fcm/send/...",
    "keys": {
        "p256dh": "...",
        "auth": "..."
    }
}
```

**Query Parameters:**
- `user_type`: `frontend` oder `backend`
- `topics`: Komma-getrennte Topics
- `backend_token`: Token für Backend-Subscriptions
- `user_id`: User-ID für Backend-Subscriptions

**Response:**
```json
{
    "success": true,
    "message": "Subscription successful",
    "subscription_id": 123
}
```

---

### POST `/index.php?rex-api-call=push_it_unsubscribe`

Entfernt eine Push-Subscription.

**Request Body:**
```json
{
    "endpoint": "https://fcm.googleapis.com/fcm/send/..."
}
```

**Response:**
```json
{
    "success": true,
    "message": "Unsubscription successful"
}
```

---

### POST `/index.php?rex-api-call=push_it_test`

Sendet Test-Benachrichtigung (nur Backend).

**Response:**
```json
{
    "success": true,
    "message": "Test notification sent",
    "sent_count": 5
}
```

---

## 📡 Event System

### PHP Events

#### `PUSH_IT_NOTIFICATION_SENT`

Wird ausgelöst nach dem Versenden einer Benachrichtigung.

```php
rex_extension::register('PUSH_IT_NOTIFICATION_SENT', function(rex_extension_point $ep) {
    $result = $ep->getParam('result');
    $data = $ep->getParam('data');
    
    // Custom logging
    error_log("Push notification sent to {$result['sent_count']} users");
});
```

#### `PUSH_IT_SUBSCRIPTION_CREATED`

Wird ausgelöst bei neuer Subscription.

```php
rex_extension::register('PUSH_IT_SUBSCRIPTION_CREATED', function(rex_extension_point $ep) {
    $subscription = $ep->getParam('subscription');
    
    // Welcome notification
    if ($subscription['user_type'] === 'frontend') {
        $sendManager = new SendManager();
        $sendManager->sendNotification([
            'title' => 'Willkommen!',
            'body' => 'Benachrichtigungen wurden aktiviert',
            'user_ids' => [$subscription['user_id']]
        ]);
    }
});
```

#### `PUSH_IT_SUBSCRIPTION_DELETED`

Wird ausgelöst beim Löschen einer Subscription.

```php
rex_extension::register('PUSH_IT_SUBSCRIPTION_DELETED', function(rex_extension_point $ep) {
    $subscriptionId = $ep->getParam('subscription_id');
    // Cleanup logic
});
```

### JavaScript Events

#### `pushit:subscribed`

Wird ausgelöst nach erfolgreicher Subscription.

```javascript
document.addEventListener('pushit:subscribed', function(event) {
    console.log('Subscription details:', event.detail);
    
    // Analytics tracking
    gtag('event', 'push_subscription', {
        'event_category': 'engagement'
    });
});
```

#### `pushit:unsubscribed`

Wird ausgelöst nach Deabonnierung.

```javascript
document.addEventListener('pushit:unsubscribed', function(event) {
    console.log('Unsubscribed:', event.detail);
});
```

#### `pushit:error`

Wird bei Fehlern ausgelöst.

```javascript
document.addEventListener('pushit:error', function(event) {
    console.error('Push error:', event.detail);
});
```

---

## ⚙️ Configuration API

### REDAXO Config

Zugriff auf AddOn-Konfiguration über `rex_config`.

```php
// VAPID Keys
$publicKey = rex_config::get('push_it', 'vapid_public_key');
$privateKey = rex_config::get('push_it', 'vapid_private_key');

// Features
$frontendEnabled = rex_config::get('push_it', 'frontend_enabled', false);
$backendEnabled = rex_config::get('push_it', 'backend_enabled', false);

// Topics
$defaultTopics = rex_config::get('push_it', 'default_topics', 'news,updates');
$backendOnlyTopics = rex_config::get('push_it', 'backend_only_topics', []);

// Notification settings
$defaultIcon = rex_config::get('push_it', 'default_icon');
$defaultTTL = rex_config::get('push_it', 'default_ttl', 3600);
```

### JavaScript Configuration

Global verfügbare Konfigurationsvariablen.

```javascript
// Automatisch verfügbar im Frontend
window.PushItPublicKey      // VAPID Public Key
window.PushItTopics         // Default Topics
window.PushItLanguage       // Current Language

// Backend-spezifisch
window.rex.push_it_backend_enabled    // Backend enabled
window.rex.push_it_backend_token      // Backend token
window.rex.push_it_user_id            // Current user ID
```

---

## 🔒 Security Considerations

### Topic Security

Backend-Only Topics sind automatisch vor Frontend-Zugriff geschützt:

```php
// In SettingsManager
public function filterTopicsForUserType(array $topics, string $userType): array
{
    if ($userType === 'frontend') {
        $backendOnlyTopics = rex_config::get('push_it', 'backend_only_topics', []);
        $topics = array_diff($topics, $backendOnlyTopics);
    }
    return $topics;
}
```

### Backend Authentication

Backend-Subscriptions erfordern gültigen Backend-Token:

```php
// Token-Validierung in Subscribe API
if ($userType === 'backend') {
    $token = rex_request('backend_token', 'string');
    if (!$this->validateBackendToken($token)) {
        throw new Exception('Invalid backend token');
    }
}
```

### Rate Limiting

Implementieren Sie Rate Limiting für API-Endpunkte:

```php
rex_extension::register('REX_API_CALL', function(rex_extension_point $ep) {
    if ($ep->getParam('subject') === 'push_it_subscribe') {
        // Rate limiting logic
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "push_it_rate_limit_$ip";
        
        $attempts = rex_config::get('temp', $key, 0);
        if ($attempts > 10) {
            throw new rex_api_exception('Rate limit exceeded', 429);
        }
        
        rex_config::set('temp', $key, $attempts + 1);
    }
});
```

---

## 📚 Class Reference

### Exception Classes

```php
// Custom Exceptions
FriendsOfREDAXO\PushIt\Exception\ConfigurationException
FriendsOfREDAXO\PushIt\Exception\ValidationException
FriendsOfREDAXO\PushIt\Exception\SendException
FriendsOfREDAXO\PushIt\Exception\SubscriptionException
```

### Data Transfer Objects

```php
// Notification DTO
class NotificationData {
    public string $title;
    public string $body;
    public ?string $icon = null;
    public ?string $url = null;
    public array $topics = [];
    public array $userTypes = ['frontend'];
    public array $userIds = [];
    public int $ttl = 3600;
    public string $priority = 'normal';
    public array $data = [];
}
```

### Utility Classes

```php
// VAPID Helper
FriendsOfREDAXO\PushIt\Util\VapidHelper::generateKeys(): array
FriendsOfREDAXO\PushIt\Util\VapidHelper::validateKey(string $key): bool

// URL Helper
FriendsOfREDAXO\PushIt\Util\UrlHelper::isValidUrl(string $url): bool
FriendsOfREDAXO\PushIt\Util\UrlHelper::makeAbsolute(string $url): string
```

Diese API-Referenz bietet eine vollständige Übersicht über alle verfügbaren Funktionen des Push-It AddOns.
