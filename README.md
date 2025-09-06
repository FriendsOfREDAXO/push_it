# PushIt - Web Push Notifications für REDAXO 5

Ein leistungsstarkes AddOn für Web Push Notifications in REDAXO 5, das sowohl Frontend- als auch Backend-Benachrichtigungen unterstützt.

## Features

- 📱 **Web Push Notifications** für moderne Browser (Chrome, Firefox, Edge, Safari)
- 🎯 **Frontend & Backend** Unterstützung
- 🔔 **Topic-basierte Subscriptions** für gezielte Benachrichtigungen
- 🛡️ **VAPID-Authentifizierung** für sichere Push-Services
- 📊 **Admin-Interface** mit Subscription-Verwaltung und Versendungshistorie
- 🔧 **REST API** für externe Integrationen
- 📱 **Bilder-Support** (Icon, Badge, Hero-Images)
- 🎨 **Responsive Design** für alle Geräte
- ⚡ **PHP 8.3 optimiert** mit strict typing

## Installation

1. AddOn über den REDAXO Package Manager installieren oder ZIP-Datei hochladen
2. AddOn aktivieren
3. VAPID-Keys in den Einstellungen konfigurieren
4. Service Worker und JavaScript werden automatisch eingebunden

## Konfiguration

### VAPID-Keys generieren

```bash
# Mit OpenSSL
openssl ecparam -genkey -name prime256v1 -out private_key.pem
openssl ec -in private_key.pem -pubout -out public_key.pem

# Oder online Generator verwenden
# https://web-push-codelab.glitch.me/
```

### Einstellungen

Gehen Sie zu **AddOns → PushIt → Einstellungen** und konfigurieren Sie:

- **VAPID Public Key**: Öffentlicher Schlüssel für Browser-Authentifizierung
- **VAPID Private Key**: Privater Schlüssel für Server-Authentifizierung  
- **Subject**: E-Mail-Adresse oder Domain für VAPID
- **Frontend aktiviert**: Push-Notifications für Website-Besucher
- **Backend aktiviert**: Push-Notifications für REDAXO-Benutzer
- **Admin-Benachrichtigungen**: Automatische System-Notifications

## Verwendung

### Frontend Integration

```html
<!-- Automatisch eingebunden wenn Frontend aktiviert -->
<script>
// Einfache Aktivierung
PushIt.requestFrontend('news,updates');

// Mit Topics
PushIt.subscribe('frontend', 'breaking-news,sports');

// Status prüfen
PushIt.getStatus().then(status => {
    console.log('Subscribed:', status.isSubscribed);
});

// Deaktivieren
PushIt.disable();
</script>
```

### Backend Integration

```php
<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

$service = new NotificationService();

// Einfache Benachrichtigung
$service->sendToAll(
    'Titel der Benachrichtigung',
    'Text der Nachricht',
    'https://example.com/link'
);

// An Frontend-User
$service->sendToFrontendUsers(
    'News Update',
    'Neue Artikel verfügbar',
    '/news',
    ['news', 'updates'] // Topics
);

// An Backend-User
$service->sendToBackendUsers(
    'System Update',
    'Wartung abgeschlossen',
    '/redaxo/index.php?page=system'
);

// Mit Bildern
$service->sendNotification(
    'Titel',
    'Text',
    'https://example.com',
    ['icon' => '/media/icon.png', 'badge' => '/media/badge.png']
);
```

### REST API

```bash
# Subscription erstellen
curl -X POST "https://domain.com/redaxo/index.php?rex-api-call=push_it_subscribe&user_type=frontend&topics=news" \
  -H "Content-Type: application/json" \
  -d '{"endpoint":"...","keys":{"p256dh":"...","auth":"..."}}'

# Subscription löschen  
curl -X POST "https://domain.com/redaxo/index.php?rex-api-call=push_it_unsubscribe" \
  -H "Content-Type: application/json" \
  -d '{"endpoint":"..."}'
```

## Topics

Organisieren Sie Benachrichtigungen mit Topics:

- `news` - Allgemeine Nachrichten
- `updates` - System-Updates
- `admin` - Admin-Benachrichtigungen
- `critical` - Kritische Meldungen
- `marketing` - Marketing-Nachrichten

```php
// Nur an News-Abonnenten
$service->sendToTopics(['news'], 'Neue Nachrichten', '...');

// An mehrere Topics
$service->sendToTopics(['news', 'updates'], 'Update verfügbar', '...');
```

## Bilder in Benachrichtigungen

```php
$options = [
    'icon' => '/media/notification-icon.png',    // 192x192px empfohlen
    'badge' => '/media/badge.png',               // 72x72px monochrom
    'image' => '/media/hero-image.jpg',          // Beliebige Größe
    'tag' => 'unique-id',                        // Gruppierung
    'requireInteraction' => true                 // Bleibt sichtbar
];

$service->sendNotification('Titel', 'Text', 'https://link.com', $options);
```

## Browser-Kompatibilität

| Browser | Desktop | Mobile | Service Worker |
|---------|---------|---------|----------------|
| Chrome  | ✅ 50+  | ✅ 50+  | ✅             |
| Firefox | ✅ 44+  | ✅ 48+  | ✅             |
| Edge    | ✅ 17+  | ✅ 17+  | ✅             |
| Safari  | ✅ 16+  | ✅ 16.4+| ⚠️ Eingeschränkt |

## Admin-Interface

### Subscriptions verwalten
- **AddOns → PushIt → Subscriptions**: Alle aktiven/inaktiven Subscriptions
- Filter nach User-Type, Topics, Browser
- Einzelne Subscriptions aktivieren/deaktivieren

### Benachrichtigungen senden  
- **AddOns → PushIt → Senden**: Manual Push Notifications
- Zielgruppe auswählen (Frontend/Backend/Topics)
- Vorschau und Test-Funktionen

### Versendungshistorie
- **AddOns → PushIt → Historie**: Alle gesendeten Notifications
- Sortierbar nach Datum, Status, Empfänger
- Export-Funktionen

## Troubleshooting

### Häufige Probleme

**Service Worker lädt nicht:**
```javascript
// Cache leeren
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(registrations => {
        registrations.forEach(registration => registration.unregister());
    });
}
```

**VAPID-Fehler:**
- Public/Private Key Paar korrekt?
- Subject-Feld konfiguriert?
- Keys im richtigen Format?

**Browser blockiert Notifications:**
```javascript
// Permission Status prüfen
console.log('Permission:', Notification.permission);

// Erneut anfordern
Notification.requestPermission();
```

### Debug-Modus

```php
// In boot.php temporär hinzufügen
if (rex::isDebugMode()) {
    rex_logger::logError('PushIt Debug', 'Your debug message');
}
```

## Entwicklung

### Lokale Entwicklung

```bash
# SSL für lokale Tests
mkcert localhost 127.0.0.1 ::1

# Service Worker Debugging
# Chrome: chrome://inspect/#service-workers
# Firefox: about:debugging#/runtime/this-firefox
```

### Extension Points

```php
// Custom Notification Handling
rex_extension::register('PUSHI_IT_BEFORE_SEND', function(rex_extension_point $ep) {
    $notification = $ep->getParam('notification');
    // Modify notification before sending
    return $notification;
});

rex_extension::register('PUSHI_IT_AFTER_SEND', function(rex_extension_point $ep) {
    $result = $ep->getParam('result');
    $notification = $ep->getParam('notification');
    // Log or process send result
});
```

## Performance

- Service Worker cached automatisch
- Subscriptions werden in MariaDB gespeichert
- Batch-Versendung für große Listen
- Rate Limiting für API-Calls

## Sicherheit

- CSRF-Schutz für Backend-Calls
- VAPID-Authentifizierung
- Validierung aller Input-Parameter
- Sichere Endpoint-Handhabung

## Lizenz

MIT License - Siehe LICENSE.md

## Support
- **Issues**: [GitHub Issues](https://github.com/FriendsOfREDAXO/push_it/issues)

## Credits

- **Lead Developer**: [Thomas Skerbis](https://github.com/skerbis)
- **Organization**: Friends Of REDAXO
- **Based on**: [Web Push Protocol](https://tools.ietf.org/html/rfc8030)
- **Library**: [Minishlink/WebPush](https://github.com/Minishlink/web-push)
---

**PushIt** - Moderne Web Push Notifications für REDAXO 5 🚀
