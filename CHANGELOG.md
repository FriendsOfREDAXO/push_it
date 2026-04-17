# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] – 2026-04-17

### Added
- **Setup-Wizard** auf der Übersichtsseite: geführte 3-Schritte-Ersteinrichtung (VAPID-Keys → Backend-Token → Service Worker) mit Fortschrittsanzeige
- **Dashboard** auf der Übersichtsseite: Gesamtstatistik (Abonnenten gesamt / aktiv / Backend / Frontend) mit Direktlinks zu Versand, Abonnements, Verlauf und Einstellungen
- Hinweis auf fehlende WebPush-Bibliothek mit Installationsanleitung
- Neue i18n-Keys für Dashboard, Setup-Wizard, Error-Monitoring-Status und Intervall-Einheiten (DE/EN)
- Neue JS-Datei `assets/admin-backend-notify.js` für das Backend-Benachrichtigungs-Panel

### Changed
- **NotificationService**: Refaktorierung mit extrahierten Hilfsmethoden `createWebPush()`, `buildPayload()` und `dispatchToSubscriptions()` – eliminiert ~100 Zeilen doppelten Code
- **NotificationService**: Alle `error_log()`-Aufrufe durch `rex_logger` hinter `rex::isDebugMode()`-Guard ersetzt
- **Subscribe.php API**: Alle `$_SERVER`-Zugriffe durch `rex_request::server()` ersetzt (REDAXO-Konvention)
- **BackendNotificationManager**: Alle Inline-`<script>`-Blöcke aus `renderBackendSubscriptionPanel()` und `renderQuickNotificationPanel()` entfernt – JS liegt jetzt in `admin-backend-notify.js`; dynamische Werte werden über `data-*`-Attribute übergeben
- **BackendNotificationManager**: Alle hardcodierten deutschen Strings in `renderErrorMonitoringInfo()` durch `rex_i18n::msg()` ersetzt
- **SubscriptionManager**: `getSubscriptionStats()` liefert jetzt aggregierte Schlüssel `total`, `active`, `frontend`, `backend` (vorher nur per-Type-Arrays, was zu undefiniertem Zugriff im Dashboard führte)
- `pages/backend_notify.php` bindet `admin-backend-notify.js` via `rex_view::addJsFile()` ein
- PHP-Mindestversion auf `^8.2` angehoben (war `^8.0`)
- `private $addon` → `private rex_addon $addon` (typisierte Properties)

### Removed
- Leere/tote Dateien gelöscht: `lib/Service/AdminNotificationService.php`, `lib/Service/TokenService.php`, `pages/tokens.php`, `pages/backend_notify_new.php`, `pages/backend_notify_clean.php`
- Alle `empty()`-Aufrufe entfernt; ersetzt durch strikte Vergleiche (`=== []`, `!== []`)

### Dependencies
- `minishlink/web-push` von `^9.0` auf `^10.0` aktualisiert (API-kompatibel)
- `brick/math` von `0.13.1` auf `0.17.0` aktualisiert
- `guzzlehttp/psr7` von `2.8.0` auf `2.9.0` aktualisiert
- `spomky-labs/pki-framework` von `1.3.0` auf `1.4.2` aktualisiert
- `web-token/jwt-library` von `4.0.6` auf `4.1.6` aktualisiert
- `symfony/polyfill-php83` neu hinzugefügt

---

## [1.0.0-beta4] – 2025

- Initialer Release mit Frontend- und Backend-Push-Notifications
- VAPID-Key-Verwaltung im Backend
- Topic-basierte Subscriptions
- System Error Monitoring (Realtime & Cronjob)
- REST API für externe Integrationen
- iOS PWA Support
- Mehrsprachiges Interface (DE/EN)
