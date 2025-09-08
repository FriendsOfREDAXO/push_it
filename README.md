# PushIt - Web Push Notifications für REDAXO 5

Ein leistungsstarkes AddOn für Web Push Notifications in REDAXO 5, das sowohl Frontend- als auch Backend-Benachrichtigungen unterstützt.

## 🚀 **Key Features**

### 🚨 **System Error Monitoring** (PHPMailer Ersatz)
- **Automatische Fehlerüberwachung** mit sofortigen Push-Benachrichtigungen
- **Realtime & Cronjob Modi** für flexible Überwachungsstrategien  
- **Domain & URL-Tracking** zeigt genau wo Fehler aufgetreten sind
- **Ersetzt rex_mailer::errorMail()** mit modernen Push-Notifications

### 📱 **Web Push Notifications** 
- **Cross-Browser Support** für Chrome, Firefox, Edge, Safari (iOS 16.4+)
- **Frontend & Backend** Unterstützung mit separaten Subscription-Typen
- **Topic-basierte Subscriptions** für gezielte Benachrichtigungen
- **Rich Notifications** mit Bildern, Actions und benutzerdefinierten Daten

### 🎯 **Advanced Features**
- **iOS PWA Support** mit automatischen Installationsanleitungen
- **Mehrsprachiges Frontend** (DE/EN) mit browser-spezifischen Hilfen
- **REST API** für externe Integrationen und Drittsysteme
- **Umfassendes Admin-Interface** mit Subscription-Verwaltung und Historie

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
<!-- Automatisch eingebunden wenn Frontend aktiviert -->
<script>
// Einfache Aktivierung
PushIt.subscribe('frontend', 'news,updates');

// Status prüfen
PushIt.getStatus().then(status => {
    console.log('Subscribed:', status.isSubscribed);
});
</script>
```

### Backend Integration
```php
<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

$service = new NotificationService();
$service->sendToAll(
    'Neue Nachricht',
    'Ein neuer Artikel ist verfügbar',
    'https://example.com/artikel'
);
?>
```

## 🛠️ **System Error Monitoring Setup**

Das Error Monitoring ersetzt `rex_mailer::errorMail()` mit modernen Push-Notifications:

### Konfiguration
- **AddOns → PushIt → Einstellungen**
- **Error Monitoring**: Aktivieren
- **Monitoring Modus**: `realtime` (sofort) oder `cronjob` (geplant)

### Cronjob einrichten (Empfohlen)
1. **AddOns → Cronjob → Hinzufügen**
2. **Push-It System Monitoring** auswählen
3. Intervall konfigurieren (z.B. alle 15 Minuten)

## 📱 **iOS & PWA Support**

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

## 🔗 **Weiterführende Dokumentation**

- **[📚 EXAMPLES.md](EXAMPLES.md)** - Praktische Implementierungsbeispiele
- **[📖 API.md](API.md)** - Vollständige API-Referenz
- **[AddOns → PushIt → Hilfe](help)** - Backend-Dokumentation

## 🎯 **Use Cases**

- **E-Commerce**: Neue Bestellungen, Warenkorb-Erinnerungen
- **News & Content**: Breaking News, neue Artikel
- **System-Monitoring**: Fehlerbenachrichtigungen, Server-Alerts
- **Community**: Forum-Updates, Event-Erinnerungen

## 📄 **Lizenz & Support**

- **Lizenz**: MIT License
- **Support**: [GitHub Issues](https://github.com/FriendsOfREDAXO/push_it/issues)
- **Developer**: [Thomas Skerbis](https://github.com/skerbis) - Friends Of REDAXO

---

**PushIt** - Moderne Web Push Notifications für REDAXO 5 🚀
