# PushIt - Web Push Notifications für REDAXO 5

 Web Push Notifications in REDAXO 5, das sowohl Frontend- als auch Backend-Benachrichtigungen unterstützt.

## **Key Features**

### **Web Push Notifications** 
- **Cross-Browser Support** für Chrome, Firefox, Edge, Safari (iOS 16.4+)
- **Frontend & Backend** Unterstützung mit separaten Subscription-Typen
- **Topic-basierte Subscriptions** für gezielte Benachrichtigungen
- **Rich Notifications** mit Bildern, Actions und benutzerdefinierten Daten

### **Advanced Features**
- **iOS PWA Support** mit automatischen Installationsanleitungen
- **Mehrsprachiges Frontend** (DE/EN) mit browser-spezifischen Hilfen
- **REST API** für externe Integrationen und Drittsysteme
- **Umfassendes Admin-Interface** mit Subscription-Verwaltung und Historie

### **System Error Monitoring** (PHPMailer Error-Mail Ersatz)
- **Automatische Fehlerüberwachung** mit sofortigen Push-Benachrichtigungen
- **Realtime & Cronjob Modi** für flexible Überwachungsstrategien  
- **Domain & URL-Tracking** zeigt genau wo Fehler aufgetreten sind
- **Ersetzt rex_mailer::errorMail()** mit modernen Push-Notifications

## ⚡ **Quick Start**

### Installation
1. AddOn über den REDAXO Package Manager installieren
2. AddOn aktivieren
3. Zu **AddOns → PushIt → Einstellungen** gehen
4. VAPID-Keys werden automatisch generiert
5. Subject (E-Mail/Domain) eingeben
6. Frontend/Backend nach Bedarf aktivieren

### Frontend Integration
```html
<!-- JavaScript manuell in Template einbinden -->
<script src="/assets/addons/push_it/frontend.js"></script>
<script>
// VAPID Public Key setzen
window.PushItPublicKey = '<?php echo rex_addon::get('push_it')->getConfig('publicKey'); ?>';
</script>

<!-- Button für User-Interaktion erforderlich -->
<button onclick="enableNotifications()">🔔 Benachrichtigungen aktivieren</button>
<button onclick="disableNotifications()">🔕 Deaktivieren</button>

<script>
// Nur bei User-Klick möglich!
function enableNotifications() {
    PushIt.subscribe('frontend', 'news,updates').then(result => {
        if (result.success) {
            alert('Benachrichtigungen aktiviert!');
        }
    }).catch(error => {
        console.error('Fehler:', error);
    });
}

function disableNotifications() {
    PushIt.disable().then(() => {
        alert('Benachrichtigungen deaktiviert');
    });
}

// Status prüfen (das geht ohne User-Interaktion)
PushIt.getStatus().then(status => {
    console.log('Subscribed:', status.isSubscribed);
});
</script>
```

### Benachrichtigungen senden (PHP)
```php
<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

$service = new NotificationService();

// An alle Empfänger
$service->sendToAllUsers('Titel', 'Nachricht', 'https://example.com');

// Nur an Frontend-Benutzer
$service->sendToFrontendUsers('News Update', 'Neuer Artikel verfügbar', '/news');

// Nur an Backend-Benutzer (Admins)
$service->sendToBackendUsers('System Info', 'Wartung abgeschlossen', '/redaxo');

// An einen spezifischen Backend-Benutzer (nur Backend-User haben User-IDs)
$service->sendToUser(123, 'Admin-Nachricht', 'Hallo Admin, wichtige Info!', '/redaxo/index.php?page=system');

// Nach Topics filtern (Frontend-Benutzer)
$service->sendToFrontendUsers('Breaking News', 'Eilmeldung', '/breaking', ['news', 'updates']);
?>
```

## � **PHP API-Referenz**

### NotificationService Methoden

```php
<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;
$service = new NotificationService();

// 🌐 An alle Benutzer senden
$service->sendToAllUsers(
    string $title,           // Benachrichtigungstitel
    string $body,            // Nachrichtentext
    string $url = '',        // Optional: Link-URL
    array $topics = [],      // Optional: Topic-Filter
    array $options = []      // Optional: Erweiterte Optionen
): array;

// 👥 An Frontend-Benutzer senden
$service->sendToFrontendUsers(
    string $title,
    string $body,
    string $url = '',
    array $topics = [],
    array $options = []
): array;

// 🔧 An Backend-Benutzer senden
$service->sendToBackendUsers(
    string $title,
    string $body,
    string $url = '',
    array $topics = [],
    array $options = []
): array;

// 👤 An spezifischen Backend-Benutzer senden
// Hinweis: Nur für Backend-User mit REDAXO User-ID
$service->sendToUser(
    int $userId,             // REDAXO Backend User-ID
    string $title,
    string $body,
    string $url = '',
    array $topics = [],
    array $options = []
): array;
?>
```

### Erweiterte Optionen (options)

```php
$options = [
    'icon' => '/media/notification-icon.png',  // Icon-URL
    'badge' => '/media/badge.png',             // Badge-Icon
    'image' => '/media/hero-image.jpg',        // Großes Bild
    'tag' => 'unique-notification-id',         // Eindeutige ID (ersetzt vorherige)
    'silent' => false,                         // Stumme Benachrichtigung
    'requireInteraction' => true,              // Bleibt bis Benutzerinteraktion
    'renotify' => true,                        // Erneut benachrichtigen bei gleichem Tag
    'vibrate' => [200, 100, 200],             // Vibrationsmuster (Mobile)
    'actions' => [                             // Action-Buttons
        [
            'action' => 'view',
            'title' => 'Ansehen',
            'icon' => '/media/view-icon.png'
        ],
        [
            'action' => 'later',
            'title' => 'Später'
        ]
    ]
];
```

### Return-Werte

Alle Methoden geben ein Array zurück:

```php
[
    'success' => true,       // bool: Erfolgreich versendet
    'sent' => 15,           // int: Anzahl erfolgreich versendeter Nachrichten
    'failed' => 2,          // int: Anzahl fehlgeschlagener Sendungen
    'total' => 17           // int: Gesamtanzahl der Subscriptions
]
```

### Topics für Zielgruppen-Segmentierung

```php
// Beispiel Topics
$topics = [
    'news',           // Nachrichten
    'updates',        // System-Updates  
    'orders',         // Bestellungen
    'premium',        // Premium-Kunden
    'admin',          // Admin-Nachrichten (Backend-Only)
    'system',         // System-Meldungen (Backend-Only)
    'critical'        // Kritische Meldungen (Backend-Only)
];

// Verwendung
$service->sendToFrontendUsers(
    'Newsletter Update',
    'Neue Ausgabe verfügbar',
    '/newsletter',
    ['news', 'updates']  // Nur an User mit diesen Topics
);
```

## **System Error Monitoring Setup**

Das Error Monitoring ersetzt `rex_mailer::errorMail()` mit modernen Push-Notifications:

### Konfiguration
- **AddOns → PushIt → Einstellungen**
- **Error Monitoring**: Aktivieren
- **Monitoring Modus**: `realtime` (sofort) oder `cronjob` (geplant)

### Cronjob einrichten (Empfohlen)
1. **AddOns → Cronjob → Hinzufügen**
2. **Push-It System Monitoring** auswählen
3. Intervall konfigurieren (z.B. alle 15 Minuten)

## **iOS & PWA Support**

**Wichtig**: Für iOS Safari ist eine `manifest.json` erforderlich:

```json
{
    "name": "Meine Website",
    "display": "minimal-ui",
    "start_url": "/",
    "icons": [
        {
            "src": "/assets/addons/push_it/icon-192.png",
            "sizes": "192x192",
            "type": "image/png"
        }
    ]
}
```

```html
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
```

## 🌍 **Browser-Kompatibilität**

| Browser | Desktop | Mobile | Bemerkungen |
|---------|---------|---------|-------------|
| Chrome  | ✅ 50+  | ✅ 50+  | Vollständig |
| Firefox | ✅ 44+  | ✅ 48+  | Vollständig |
| Edge    | ✅ 17+  | ✅ 17+  | Vollständig |
| Safari  | ✅ 16+  | ✅ 16.4+| Erfordert PWA |

## **Weiterführende Dokumentation**

- **[📚 EXAMPLES.md](EXAMPLES.md)** - Praktische Implementierungsbeispiele
- **[📖 API.md](API.md)** - Vollständige API-Referenz
- **[AddOns → PushIt → Hilfe](help)** - Backend-Dokumentation

## **Use Cases**

- **E-Commerce**: Neue Bestellungen, Warenkorb-Erinnerungen
- **News & Content**: Breaking News, neue Artikel
- **System-Monitoring**: Fehlerbenachrichtigungen, Server-Alerts
- **Community**: Forum-Updates, Event-Erinnerungen

## **Lizenz & Support**

- **Lizenz**: MIT License
- **Support**: [GitHub Issues](https://github.com/FriendsOfREDAXO/push_it/issues)
- **Developer**: [Thomas Skerbis](https://github.com/skerbis) - Friends Of REDAXO

---

**PushIt** - Moderne Web Push Notifications für REDAXO 5 🚀
