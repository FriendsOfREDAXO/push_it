# PushIt - API Documentation

Vollständige API-Referenz für das PushIt AddOn **v1.0.0**.


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

Orchestriert Formulardaten des Backend-Sendeformulars und delegiert an `NotificationService`.

```php
use FriendsOfREDAXO\PushIt\Service\SendManager;

$sendManager = new SendManager();
```

#### Methods

##### `sendNotification(array $data, bool $isAdmin): array`

Sendet eine Push-Benachrichtigung anhand von Formulardaten.

```php
public function sendNotification(array $data, bool $isAdmin): array
```

**Parameter:**
```php
$data = [
    'title'     => 'Benachrichtigungstitel',   // required
    'body'      => 'Nachrichtentext',          // required
    'url'       => 'https://example.com',      // optional
    'user_type' => 'frontend',                 // 'frontend', 'backend' oder 'both'
    'topics'    => 'news,updates',             // optional: kommagetrennte Topics
    'icon'      => '/media/icon.png',          // optional
    'badge'     => '/media/badge.png',         // optional
    'image'     => '/media/hero.jpg',          // optional
];
```

**Returns:**
```php
[
    'success' => true,
    'message' => 'Benachrichtigung wurde an 15 Empfänger gesendet (2 Fehler).',
    'result'  => [
        'success' => true,
        'sent'    => 15,
        'failed'  => 2,
        'total'   => 17,
    ],
]
```

**Example:**
```php
$sendManager = new SendManager();
$result = $sendManager->sendNotification([
    'title'     => 'Neue Nachricht',
    'body'      => 'Sie haben eine neue Nachricht erhalten',
    'url'       => rex_url::frontend(),
    'user_type' => 'frontend',
    'topics'    => 'news',
], rex::getUser()->isAdmin());

if ($result['success']) {
    echo $result['message'];
}
```

---

##### `sendTestNotification(array $formData = []): array`

Sendet eine Test-Benachrichtigung an den aktuell eingeloggten Backend-User.

```php
public function sendTestNotification(array $formData = []): array
```

**Returns:** `['success' => bool, 'message' => string, 'result' => array|null]`

---

### NotificationService

Direkte Service-Klasse für einfaches Versenden von Push-Benachrichtigungen.

```php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

$service = new NotificationService();
```

#### Methods

##### `sendToAllUsers(string $title, string $body, string $url, array $topics, array $options): array`

Sendet Benachrichtigung an alle Benutzer.

```php
$result = $service->sendToAllUsers(
    'Titel der Benachrichtigung',
    'Nachrichtentext',
    'https://example.com/target',    // optional
    ['news', 'updates'],             // optional: Topic-Filter
    ['icon' => '/media/icon.png']    // optional: Erweiterte Optionen
);
```

##### `sendToFrontendUsers(string $title, string $body, string $url, array $topics, array $options): array`

Sendet Benachrichtigung nur an Frontend-Benutzer.

```php
$result = $service->sendToFrontendUsers(
    'News Update',
    'Neuer Artikel verfügbar',
    '/news/latest',
    ['news', 'articles']
);
```

##### `sendToBackendUsers(string $title, string $body, string $url, array $topics, array $options): array`

Sendet Benachrichtigung nur an Backend-Benutzer (Administratoren).

```php
$result = $service->sendToBackendUsers(
    'System Wartung',
    'Geplante Wartung heute um 22:00 Uhr',
    '/redaxo/index.php?page=system',
    ['system', 'maintenance']
);
```

##### `sendToUser(int $userId, string $title, string $body, string $url, array $topics, array $options): array`

Sendet Benachrichtigung an einen spezifischen Backend-Benutzer.

**Hinweis:** Funktioniert nur für Backend-User mit REDAXO User-ID. Frontend-User haben keine User-IDs.

```php
$result = $service->sendToUser(
    123,                             // REDAXO Backend User-ID
    'Admin-Benachrichtigung',
    'Wichtige System-Information',
    '/redaxo/index.php?page=system',
    ['admin', 'system'],             // optional: Topic-Filter
    [
        'icon' => '/media/admin-icon.png',
        'requireInteraction' => true
    ]
);
```

**Returns (alle Methoden):**
```php
[
    'success' => true,
    'sent' => 15,           // Anzahl erfolgreich versendeter Nachrichten
    'failed' => 2,          // Anzahl fehlgeschlagener Sendungen
    'total' => 17           // Gesamtanzahl der Subscriptions
]
```

**Example mit Error Handling:**
```php
try {
    $result = $service->sendToBackendUsers(
        'Kritischer Fehler',
        'Database connection failed',
        '/redaxo/index.php?page=system/log',
        ['system', 'critical']
    );
    
    if ($result['success'] && $result['sent'] > 0) {
        rex_logger::logInfo('push_it', "Sent to {$result['sent']} users");
    }
} catch (\Exception $e) {
    rex_logger::logError('push_it', 'Send failed: ' . $e->getMessage());
}
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
    'total'    => 150,   // Alle Subscriptions
    'active'   => 145,   // Aktive Subscriptions (gesamt)
    'frontend' => [
        'user_type'    => 'frontend',
        'total'        => 120,
        'active_count' => 118,
        'error_count'  => 2,
    ],
    'backend'  => [
        'user_type'    => 'backend',
        'total'        => 30,
        'active_count' => 27,
        'error_count'  => 0,
    ],
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

Rendert UI-Komponenten für das Backend-Benachrichtigungs-Panel und liefert Statistiken.

```php
use FriendsOfREDAXO\PushIt\Service\BackendNotificationManager;

$manager = new BackendNotificationManager();
```

#### Methods

| Methode | Beschreibung |
|---------|--------------|
| `hasVapidKeys(): bool` | Prüft ob VAPID-Schlüssel konfiguriert sind |
| `renderJavaScript(): string` | Gibt `<script>`-Tags für frontend.js / backend.js zurück |
| `renderInfoPanel(bool $isAdmin): string` | Info-Panel mit Hinweistext |
| `renderBackendSubscriptionPanel(bool $isAdmin): string` | Panel mit Aktivieren/Deaktivieren-Buttons |
| `renderQuickNotificationPanel(): string` | Schnell-Versand-Buttons (Admin only) |
| `renderAutomaticNotificationsInfo(bool $isAdmin): string` | Automatische Events-Info |
| `getBackendStatistics(): array` | Statistiken für Backend-Subscriptions |
| `renderStatisticsPanel(bool $isAdmin): string` | Statistik-Panel HTML |
| `renderVapidWarning(): string` | Warnung bei fehlenden VAPID-Keys |
| `renderErrorMonitoringInfo(): string` | Status-Panel für SystemErrorMonitor |

**Beispiel `getBackendStatistics()`:**
```php
$stats = $manager->getBackendStatistics();
// [
//   'total_backend'        => 30,
//   'active_backend'       => 27,
//   'system_subscribers'   => 15,
//   'admin_subscribers'    => 12,
//   'editorial_subscribers'=> 20,
//   'critical_subscribers' => 8,
// ]
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

## � Security Considerations

### REDAXO Config

Zugriff auf AddOn-Konfiguration über `rex_addon::get('push_it')->getConfig()`.

```php
$addon = rex_addon::get('push_it');

// VAPID Keys
$publicKey  = $addon->getConfig('publicKey');
$privateKey = $addon->getConfig('privateKey');
$subject    = $addon->getConfig('subject');

// Features
$frontendEnabled = $addon->getConfig('frontend_enabled', false);
$backendEnabled  = $addon->getConfig('backend_enabled', false);

// Backend-Token
$backendToken = $addon->getConfig('backend_token');

// Topics
$defaultTopics     = $addon->getConfig('default_topics', 'news,updates');
$backendOnlyTopics = $addon->getConfig('backend_only_topics', []);

// Error Monitoring
$monitoringEnabled = $addon->getConfig('error_monitoring_enabled', false);
$monitoringMode    = $addon->getConfig('monitoring_mode', 'realtime'); // 'realtime' | 'cronjob'
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
public function filterTopicsForUserType(string $topics, string $userType): string
{
    if ($userType === 'frontend') {
        $backendOnlyTopics = rex_addon::get('push_it')->getConfig('backend_only_topics', []);
        $topicsArray = array_diff(explode(',', $topics), $backendOnlyTopics);
        return implode(',', $topicsArray);
    }
    return $topics;
}
```

### Backend Authentication

Backend-Subscriptions erfordern einen gültigen Backend-Token, der in den Einstellungen generiert wird:

```php
// Token prüfen (Subscribe API)
$validToken = rex_addon::get('push_it')->getConfig('backend_token');
if ($backendToken !== $validToken) {
    // 403 Forbidden
}
```

---

Diese API-Referenz dokumentiert alle öffentlich verwendbaren Klassen und Methoden des PushIt AddOns v1.0.0.
