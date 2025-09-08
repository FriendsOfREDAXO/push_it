# Push-It System Monitoring - Cronjob Integration

## Problem
Push-It überwacht System-Fehler und AddOn-Updates über `RESPONSE_SHUTDOWN` Events. Diese werden jedoch nur ausgelöst, wenn Website-Requests stattfinden. Bei Websites mit wenig Traffic können wichtige Benachrichtigungen dadurch verzögert werden.

## Lösung: Cronjob Integration

### 1. Automatischer Cronjob (wenn Cronjob-AddOn installiert ist)

Falls das REDAXO Cronjob-AddOn verfügbar ist, können Sie einen Cronjob einrichten:

**Cronjob-Einstellungen:**
- **Name:** Push-It System Monitoring
- **Beschreibung:** Überwacht System-Fehler und AddOn-Updates
- **PHP-Code:**
```php
\FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor::cronCheck();
```
- **Intervall:** Alle 15-30 Minuten
- **Status:** Aktiv

### 2. System-Cronjob (Linux/macOS)

Alternativ können Sie einen System-Cronjob einrichten:

```bash
# Crontab bearbeiten
crontab -e

# Alle 15 Minuten Push-It Monitoring ausführen
*/15 * * * * /usr/bin/php /pfad/zu/redaxo/bin/console push-it:monitor >/dev/null 2>&1
```

### 3. Console-Command (für manuelle Ausführung)

```bash
# Von REDAXO-Root-Verzeichnis aus:
php bin/console push-it:monitor
```

## Was wird überwacht?

### Error Monitoring
- Überwacht `redaxo/data/log/system.log` auf neue Fehler und Exceptions
- Sendet Push-Benachrichtigungen an Backend-Benutzer mit "system" oder "admin" Topics
- Duplikats-Schutz: Gleiche Fehler werden nur einmal im konfigurierten Intervall gemeldet

### Update Monitoring
- Erkennt durchgeführte AddOn-Updates in system.log
- Prüft verfügbare Updates über Install-AddOn API
- Benachrichtigt nur bei Änderungen (neue Updates oder durchgeführte Updates)

## Konfiguration

Alle Einstellungen können im Push-It Backend konfiguriert werden:

- **Error Monitoring aktivieren/deaktivieren**
- **Monitoring-Intervall:** 5 Minuten bis 6 Stunden
- **Admin-Benachrichtigungen aktivieren/deaktivieren**
- **Custom Icons** für Error- und Update-Benachrichtigungen

## Performance

- **Log-Scanning:** Nur neue Log-Einträge werden gescannt
- **Zeitlimits:** Verschiedene Prüfungen haben unterschiedliche Intervalle
- **Exception-Handling:** Fehler beim Monitoring unterbrechen nicht die Website

## Troubleshooting

### Benachrichtigungen kommen nicht an
1. Push-It Error Monitoring aktiviert? ✓
2. Backend-Benutzer haben "system" oder "admin" Topics abonniert? ✓
3. VAPID-Keys konfiguriert? ✓
4. Cronjob läuft regelmäßig? ✓

### Performance-Probleme
- Monitoring-Intervall erhöhen
- Log-Rotation für system.log einrichten
- Nur wichtige Topics abonnieren

### Debugging
```php
// Debug-Informationen abrufen
$monitor = new \FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor();
$status = $monitor->getErrorMonitoringStatus();
var_dump($status);
```
