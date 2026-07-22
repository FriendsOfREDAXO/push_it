# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] – 2026-07-21

### Added
- Neue Pivot-Tabelle `rex_push_it_subscription_topics` für performante Topic-Zuordnungen (Subscription ↔ Topic).
- Idempotente Migration vorhandener CSV-Topics in die neue Topic-Tabelle beim Install/Update.

### Changed
- Service-Worker-Handling im Frontend vereinheitlicht: Registrierung und Lookup verwenden jetzt konsistent denselben Scope.
- Topic-Filter beim Versand auf JOIN-basierte Abfragen gegen die neue Topic-Tabelle umgestellt (mit Fallback auf Legacy-CSV, falls die Tabelle noch nicht existiert).
- Topic-Statistiken in Backend- und Monitoring-Bereichen auf die neue Topic-Tabelle umgestellt (mit Legacy-Fallback).
- Send-Seite für Redakteure verbessert: klarer Schnellstart-Hinweis, Schnellvorlagen, Zeichenzähler und verständlichere Formularführung.
- Panel-spezifische Assets für `push_it/send` werden zentral über `boot.php` geladen (inkl. robustem Seiten-Fallback über Request-Parameter).
- Backend-Opt-in-Hinweis platzsparend überarbeitet: kompakter Einzeiler statt großem Block.

### Fixed
- Zustellhygiene verbessert: abgelaufene/ungültige Endpoints werden bei Delivery-Fehlern automatisch deaktiviert.
- Monitoring-Dateizugriffe (Log-Lesen) robuster gemacht: sichere Stream-Guards für `fopen`/`fseek`/`fread` und Schutz bei leerem Inhalt.
- RexStan-Fehler vollständig bereinigt: nullable User-Zugriffe in Backend-Seiten abgesichert, API-Execute-Signaturen auf `rex_api_result` ausgerichtet, Array-Typen in Service-Klassen präzisiert und SQL-/Payload-Werte konsequent typisiert.
- History-Workflow stabilisiert: Wiederholversand nutzt korrekt typisierte Felder und konsistente Ergebnis-Keys (`failed` statt `errors`).
- Backend-Statistik und Monitoring-Services mit präziseren Property-/Return-Typen versehen, inklusive PSR-3-konformer Logger-Placeholders.
- Defekte Schaltflächen-Ziele im Backend korrigiert: „Einstellungen ändern“ verweist wieder auf `push_it/settings`.
- Schnellvorlagen auf der Send-Seite funktionieren jetzt zuverlässig durch gehärtete Initialisierung (`DOMContentLoaded`, natives `rex:ready`, jQuery `rex:ready`) und idempotente Bindung.
- Backend-Opt-in-Hinweis blockiert keine arbeitsintensiven Seiten mehr (`push_it/send`, `push_it/history`, `push_it/subscriptions`) und überschreibt nicht länger den gesamten Message-Container.

## [1.0.1] – 2026-05-28

### Security
- Backend-Seiten für mutierende Aktionen auf POST + CSRF umgestellt (Einstellungen, Versand, Subscriptions-Delete/Reparatur).
- Subscriptions-Aktionen im Backend von GET-Links auf sichere POST-Formulare mit CSRF-Token umgestellt.

### Changed
- `Subscribe` API gehärtet:
	- nur noch POST erlaubt
	- Request- und Payload-Limits ergänzt
	- strengere Validierung für Endpoint, Keys und Topics
	- Topics werden normalisiert und auf erlaubte Zeichen/Länge begrenzt
- `Unsubscribe` API gehärtet:
	- nur noch POST erlaubt
	- Input-Limit und Endpoint-Längenprüfung ergänzt
	- Response-Ausgabe auf konsistentes REDAXO-JSON-Handling umgestellt
- Redirects in der Subscriptions-Verwaltung auf serverseitige Redirects umgestellt (kein Inline-JS-Redirect mehr).
- Einbindung von `assets/admin-backend-notify.js` zentral in `boot.php` für `push_it/backend_notify` verlagert.
- Doppelte Script-Einbindung aus `pages/backend_notify.php` entfernt, um Race-Conditions zu vermeiden.
- `backend_notify` Panel-Buttons (`Aktivieren`, `Status prüfen`, `Deaktivieren`, `Abfrage zurücksetzen`) auf stabiles Event-Handling mit `rex:ready` + idempotenter Listener-Bindung umgestellt.
- Panel-Buttons explizit auf `type="button"` gesetzt, damit kein unbeabsichtigtes Submit-Verhalten auftritt.

### Fixed
- Zeitanzeige in `push_it/subscriptions` korrigiert: DB-Zeit wird für die Anzeige von UTC in die konfigurierte System-Zeitzone umgerechnet.
- `Status prüfen` im Backend-Panel liefert wieder sichtbares Feedback (Status/Fehler) statt stumm zu bleiben.

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

## [1.0.0-beta4] – 2025-09-12

### Fixed
- Installationsfehler behoben: MySQL `UNIQUE`-Index auf `TEXT`-Spalte `endpoint` schlug mit SQLSTATE 1170 fehl ([#13](https://github.com/FriendsOfREDAXO/push_it/issues/13), [#14](https://github.com/FriendsOfREDAXO/push_it/pull/14))

---

## [1.0.0-beta3] – 2025-09-08

### Added
- Gesendete Nachrichten können aus der Historie gelöscht werden

### Changed
- Nochmals verbesserter Test-Versand

---

## [1.0.0-beta2] – 2025-09-08

### Changed
- Verbesserter Test-Versand

---

## [1.0.0-beta1] – 2025-09-08

### Added
- Erster öffentlicher Beta-Release
- Web Push Notifications für REDAXO 5 (Frontend & Backend)
- Cross-Browser-Support: Chrome, Firefox, Edge, Safari (iOS 16.4+)
- Topic-basierte Subscriptions für gezielte Benachrichtigungen
- Rich Notifications mit Bildern, Actions und benutzerdefinierten Daten
- iOS PWA Support mit automatischen Installationsanleitungen
- Mehrsprachiges Frontend (DE/EN) mit Browser-spezifischen Hilfen
- VAPID-Key-Verwaltung im Backend
- REST API für externe Integrationen und Drittsysteme
- Umfassendes Admin-Interface mit Subscription-Verwaltung und Verlauf
- System Error Monitoring als Ersatz für `rex_mailer::errorMail()` (Realtime & Cronjob-Modi)
