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

## System Error Monitoring

PushIt bietet automatische Überwachung von System-Fehlern mit Push-Benachrichtigungen. Diese Funktion sendet Ihnen sofortige Benachrichtigungen, wenn Fehler oder Exceptions in Ihrem REDAXO-System auftreten.

### Monitoring-Modi

Das Error Monitoring unterstützt zwei verschiedene Betriebsmodi:

#### 1. Realtime-Modus (Standard)
- **Aktivierung**: Beim Laden von Backend-Seiten
- **Trigger**: `RESPONSE_SHUTDOWN` Event bei jeder Backend-Anfrage
- **Zeitkontrolle**: Prüfung nur alle 5 Minuten (Performance-Schutz)
- **Mindestabstand**: 5 Minuten zwischen Push-Benachrichtigungen
- **Geeignet für**: Sofortige Benachrichtigung bei kritischen Fehlern

```php
// Konfiguration für Realtime-Modus
$addon->setConfig('monitoring_mode', 'realtime');
$addon->setConfig('error_monitoring_enabled', true);
```

#### 2. Cronjob-Modus (Empfohlen)
- **Aktivierung**: Über REDAXO Cronjob-System
- **Trigger**: Regelmäßige Ausführung nach festem Zeitplan
- **Zeitkontrolle**: Keine internen Beschränkungen
- **Mindestabstand**: Nur durch Cronjob-Intervall bestimmt
- **Geeignet für**: Planbare, regelmäßige Überwachung

```php
// Konfiguration für Cronjob-Modus
$addon->setConfig('monitoring_mode', 'cronjob');
$addon->setConfig('error_monitoring_enabled', true);

// Cronjob-Setup (automatisch registriert)
// AddOns → Cronjob → Neuer Cronjob → "Push-It System Monitoring"
```

### Konfiguration

In den **AddOn-Einstellungen** können Sie folgende Parameter konfigurieren:

- **Error Monitoring**: Ein-/Ausschalten der Fehlerüberwachung
- **Monitoring Modus**: `realtime` oder `cronjob`
- **Intervall**: Mindestabstand zwischen Benachrichtigungen (nur Realtime-Modus)

### Cronjob einrichten

1. Gehen Sie zu **AddOns → Cronjob → Liste**
2. Klicken Sie auf **Hinzufügen**
3. Wählen Sie **Push-It System Monitoring** aus der Liste
4. Konfigurieren Sie das gewünschte Intervall:
   - **Alle 5 Minuten**: Schnelle Reaktion auf Fehler
   - **Alle 15 Minuten**: Ausgewogene Überwachung
   - **Stündlich**: Für weniger kritische Systeme

```bash
# Cronjob-Beispiele
*/5 * * * *    # Alle 5 Minuten
*/15 * * * *   # Alle 15 Minuten  
0 * * * *      # Stündlich
0 */4 * * *    # Alle 4 Stunden
```

### Was wird überwacht

Das System überwacht automatisch:

- **PHP Errors**: Alle Arten von PHP-Fehlern
- **Exceptions**: Uncaught Exceptions und Fehler
- **Log-Einträge**: Aus der REDAXO `system.log`
- **Neue Fehler**: Nur Fehler seit der letzten Benachrichtigung

### Benachrichtigungs-Details

Push-Benachrichtigungen enthalten:

- **Fehleranzahl**: Anzahl neuer Fehler seit letzter Benachrichtigung
- **Fehlermeldung**: Kurze Beschreibung des letzten Fehlers
- **Zeitstempel**: Wann der Fehler aufgetreten ist
- **Direktlink**: Zum REDAXO System-Log

### Beispiel-Benachrichtigung

```
📋 3 System-Fehler auf example.com
Mehrere Fehler aufgetreten. Letzter: Call to undefined function func()

[Log anzeigen] [Schließen]
```

### Debug und Troubleshooting

Für die Fehlersuche steht eine versteckte Debug-Seite zur Verfügung:

**URL**: `/redaxo/index.php?page=push_it/debug`

Die Debug-Seite zeigt:
- Aktuelle Monitoring-Konfiguration
- Cronjob-Status und letzte Ausführung
- Letzte Log-Einträge mit [NEU] Markierung
- Test-Buttons für beide Modi
- Reset-Optionen für Zeitstempel

### Vorteile der Modi

| Feature | Realtime | Cronjob |
|---------|----------|---------|
| Reaktionszeit | Sofort (bei Backend-Nutzung) | Nach Cronjob-Intervall |
| Server-Performance | Minimaler Overhead | Geplante Last |
| Zeitkontrolle | 5-Min-Mindestabstand | Vollständig konfigurierbar |
| Unabhängigkeit | Erfordert Backend-Aktivität | Läuft eigenständig |
| Empfehlung | Entwicklung/Testing | Produktive Systeme |

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

### Frontend Integration mit Mehrsprachigkeit

Für mehrsprachige Websites mit benutzerdefinierten Fehlermeldungen und Anleitungen:

```html
<!-- Push-It Frontend Integration mit Sprachunterstützung -->
<script>
<?php
// Sprache ermitteln - REDAXO Clang verwenden
$lang = rex_clang::getCurrent()->getCode(); // z.B. 'de', 'en'
$supportedLangs = ['de', 'en'];
if (!in_array($lang, $supportedLangs)) {
    $lang = 'de'; // Fallback auf Deutsch
}
echo "window.PushItLanguage = '{$lang}';";
?>
</script>

<!-- Sprachdatei laden (WICHTIG: vor frontend.js) -->
<script src="/assets/addons/push_it/lang/<?php echo $lang; ?>.js"></script>

<!-- Frontend JavaScript -->
<script src="/assets/addons/push_it/frontend.js"></script>

<!-- Konfiguration -->
<script>
window.PushItPublicKey = '<?php echo rex_addon::get('push_it')->getConfig('publicKey'); ?>';
// Optional: Topics für Frontend-Nutzer
window.PushItTopics = 'news,updates';
</script>

<!-- Buttons für Nutzer -->
<button onclick="PushIt.requestFrontend()">Benachrichtigungen aktivieren</button>
<button onclick="PushIt.disable()">Benachrichtigungen deaktivieren</button>
```

#### Statische Sprache setzen

Falls keine dynamische Spracherkennung gewünscht ist:

```html
<!-- Statische Sprache -->
<script>
window.PushItLanguage = 'de'; // oder 'en' für Englisch
</script>
<script src="/assets/addons/push_it/lang/de.js"></script>
<script src="/assets/addons/push_it/frontend.js"></script>
```

#### Unterstützte Sprachen für Frontend

- **Deutsch (de)** - Alle Fehlermeldungen und Browser-Anleitungen
- **Englisch (en)** - Vollständige Übersetzung

#### Neue Sprachen hinzufügen

1. Erstelle neue Sprachdatei: `/assets/addons/push_it/lang/[code].js`
2. Kopiere Struktur von `de.js` oder `en.js`
3. Übersetze alle Schlüssel:

```javascript
window.PushItLang = window.PushItLang || {};
window.PushItLang.fr = {
    'error.browser_not_supported': 'Web Push n\'est pas supporté par ce navigateur',
    'success.notifications_activated': 'Notifications activées!',
    // ... weitere Übersetzungen
};
```

4. Füge Sprache zu unterstützten Sprachen hinzu:
```php
$supportedLangs = ['de', 'en', 'fr']; // Französisch hinzufügen
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

## Mehrsprachigkeit

Das AddOn bietet umfassende Mehrsprachigkeits-Unterstützung für Frontend-Benachrichtigungen:

### Automatische Browser-Anleitungen

Wenn Benachrichtigungen blockiert sind, zeigt das AddOn automatisch browser-spezifische Anleitungen in der gewählten Sprache:

- **Safari**: Schloss-Symbol → Website-Einstellungen → Benachrichtigungen
- **Chrome**: Schloss-Symbol → Benachrichtigungen aktivieren  
- **Firefox**: Schild-Symbol → Benachrichtigungen aktivieren
- **Generisch**: Fallback für andere Browser

### Übersetzte Meldungen

Alle Frontend-Nachrichten werden automatisch übersetzt:

- ✅ Fehlermeldungen (VAPID-Fehler, Server-Fehler, etc.)
- ✅ Erfolgs-Nachrichten (Aktivierung, Deaktivierung)
- ✅ Browser-spezifische Anleitungen
- ✅ Console-Log-Nachrichten
- ✅ Alert-Dialoge

### Backend-Spracherkennung

Die Sprache wird automatisch aus dem REDAXO-Backend erkannt:

```php
// Automatische Spracherkennung im Backend
$lang = rex::getUser() ? rex::getUser()->getLanguage() : 'de';

// Frontend: Clang-basierte Erkennung
$lang = rex_clang::getCurrent()->getCode();
```

### Sprachdatei-Struktur

```javascript
window.PushItLang = window.PushItLang || {};
window.PushItLang.de = {
    // Fehlermeldungen
    'error.browser_not_supported': 'Web Push wird von diesem Browser nicht unterstützt',
    'error.permission_denied': 'Berechtigung für Benachrichtigungen verweigert...',
    
    // Erfolgs-Nachrichten  
    'success.notifications_activated': 'Benachrichtigungen aktiviert!',
    
    // Browser-Anleitungen
    'instructions.safari': '🔧 Safari:\n1. Klicken Sie auf das Schloss-Symbol...',
    
    // Mit Platzhaltern
    'error.server_error': 'Server-Fehler: {status}',
    'backend.activation_error': 'Fehler beim Aktivieren: {message}'
};
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
rex_extension::register('PUSH_IT_BEFORE_SEND', function(rex_extension_point $ep) {
    $notification = $ep->getParam('notification');
    // Modify notification before sending
    return $notification;
});

rex_extension::register('PUSH_IT_AFTER_SEND', function(rex_extension_point $ep) {
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
