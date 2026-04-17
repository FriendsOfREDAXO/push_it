# PushIt - Praktische Beispiele & Implementierung

Hier finden Sie praktische Implementierungsbeispiele, detaillierte Anleitungen und Use Cases für das PushIt AddOn.

## 📋 **Inhaltsverzeichnis**

1. [Frontend Integration](#frontend-integration)
2. [Backend/PHP Integration](#backendphp-integration)
3. [System Error Monitoring](#system-error-monitoring)
4. [iOS & PWA Setup](#ios--pwa-setup)
5. [Framework-neutrale UI-Komponente](#framework-neutrale-ui-komponente)
6. [REDAXO-spezifische Integration](#redaxo-spezifische-integration)
7. [Erweiterte Beispiele](#erweiterte-beispiele)
8. [Debugging und Troubleshooting](#debugging-und-troubleshooting)

## 🚀 **Frontend Integration**

### Basis-Implementation

```html
<!DOCTYPE html>
<html>
<head>
    <!-- PWA Manifest für iOS Support -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body>
    <!-- Buttons für Nutzer -->
    <button onclick="PushIt.requestFrontend()">Benachrichtigungen aktivieren</button>
    <button onclick="PushIt.disable()">Benachrichtigungen deaktivieren</button>
    <button onclick="checkStatus()">Status prüfen</button>

    <!-- JavaScript wird automatisch eingebunden wenn Frontend aktiviert -->
    <script>
    // Einfache Aktivierung
    function enableNotifications() {
        PushIt.subscribe('frontend', 'news,updates').then(result => {
            if (result.success) {
                alert('Benachrichtigungen aktiviert!');
            } else {
                console.error('Fehler:', result.error);
            }
        });
    }

    // Status prüfen
    function checkStatus() {
        PushIt.getStatus().then(status => {
            console.log('Subscription Status:', status);
            document.getElementById('status').textContent = 
                status.isSubscribed ? 'Aktiviert' : 'Deaktiviert';
        });
    }

    // Event Listener für Push-Nachrichten
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            console.log('Push Message received:', event.data);
        });
    }
    </script>
</body>
</html>
```

### iOS-kompatible Implementation

**Wichtig**: iOS Safari erfordert spezielle Behandlung und PWA-Setup.

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- PWA Manifest - ZWINGEND für iOS Push Notifications -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- iOS-spezifische Meta-Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Meine App">
    <link rel="apple-touch-icon" href="/assets/addons/push_it/icon-192.png">
    
    <!-- WICHTIG: Sprache setzen BEVOR JavaScript geladen wird -->
    <script>
    <?php
    // Automatische Spracherkennung
    $lang = rex_clang::getCurrent()->getCode();
    $supportedLangs = ['de', 'en'];
    if (!in_array($lang, $supportedLangs)) {
        $lang = 'de';
    }
    echo "window.PushItLanguage = '{$lang}';";
    ?>
    </script>
    
    <!-- Sprachdatei MUSS vor frontend.js geladen werden -->
    <script src="/assets/addons/push_it/lang/<?php echo $lang; ?>.js"></script>
    
    <!-- Push-It Frontend JavaScript -->
    <script src="/assets/addons/push_it/frontend.js"></script>
    
    <!-- Konfiguration nach JavaScript-Einbindung -->
    <script>
    window.PushItPublicKey = '<?php echo rex_addon::get('push_it')->getConfig('publicKey'); ?>';
    window.PushItTopics = 'news,updates,breaking';
    </script>
</head>
<body>
    <!-- iOS-optimierte UI -->
    <div class="notification-controls">
        <h3>📱 Push-Benachrichtigungen</h3>
        <button onclick="requestNotifications()" class="btn-primary">
            🔔 Benachrichtigungen aktivieren
        </button>
        <button onclick="disableNotifications()" class="btn-secondary">
            🔕 Deaktivieren
        </button>
        <div id="status-display" class="status-info"></div>
    </div>

    <!-- iOS Installation Hinweis -->
    <div id="ios-install-prompt" style="display: none;" class="ios-prompt">
        <h4>📲 Installation für iOS</h4>
        <p>Für Push-Benachrichtigungen auf iOS:</p>
        <ol>
            <li>Tippen Sie auf das <strong>Teilen-Symbol</strong> (□↗)</li>
            <li>Wählen Sie <strong>"Zum Home-Bildschirm"</strong></li>
            <li>Bestätigen Sie mit <strong>"Hinzufügen"</strong></li>
            <li>Öffnen Sie die App vom Home-Bildschirm</li>
        </ol>
    </div>

    <script>
    // iOS-spezifische Funktionen
    function requestNotifications() {
        // iOS-Check und PWA-Erkennung
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isInStandaloneMode = window.matchMedia('(display-mode: standalone)').matches;
        
        if (isIOS && !isInStandaloneMode) {
            document.getElementById('ios-install-prompt').style.display = 'block';
            return;
        }
        
        // Standard Push-Request
        PushIt.subscribe('frontend', window.PushItTopics).then(result => {
            updateStatus(result.success ? 'Aktiviert ✅' : 'Fehler ❌');
            if (!result.success) {
                console.error('Push-Fehler:', result.error);
            }
        }).catch(error => {
            console.error('Subscribe Error:', error);
            updateStatus('Fehler beim Aktivieren ❌');
        });
    }
    
    function disableNotifications() {
        PushIt.disable().then(result => {
            updateStatus('Deaktiviert 🔕');
        });
    }
    
    function updateStatus(message) {
        document.getElementById('status-display').textContent = message;
    }
    
    // Status beim Laden prüfen
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            PushIt.getStatus().then(status => {
                updateStatus(status.isSubscribed ? 'Aktiviert ✅' : 'Verfügbar 🔔');
            });
        }, 1000);
    });
    
    // Service Worker Message Handling
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.type === 'push-received') {
                console.log('Push empfangen:', event.data.payload);
            }
        });
    }
    </script>
    
    <!-- CSS für besseres iOS-Design -->
    <style>
    .notification-controls {
        padding: 20px;
        text-align: center;
        background: #f8f9fa;
        border-radius: 12px;
        margin: 20px;
    }
    
    .btn-primary, .btn-secondary {
        padding: 12px 24px;
        margin: 8px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        display: inline-block;
    }
    
    .btn-primary {
        background: #007AFF;
        color: white;
    }
    
    .btn-secondary {
        background: #8E8E93;
        color: white;
    }
    
    .ios-prompt {
        background: #FFF3CD;
        border: 1px solid #FFEAA7;
        border-radius: 8px;
        padding: 16px;
        margin: 20px;
    }
    
    .status-info {
        margin-top: 12px;
        font-weight: bold;
        padding: 8px;
        border-radius: 4px;
        background: #E3F2FD;
    }
    
    /* iOS Safari spezifische Styles */
    @supports (-webkit-touch-callout: none) {
        .btn-primary, .btn-secondary {
            -webkit-appearance: none;
            appearance: none;
        }
    }
    </style>
</body>
</html>
```

## 🔧 **Backend/PHP Integration**

### NotificationService Basics

```php
<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

// Service initialisieren
$service = new NotificationService();

// Einfache Nachricht an alle
$service->sendToAllUsers(
    'Willkommen!',
    'Herzlich willkommen auf unserer Website',
    'https://example.com/welcome'
);

// Mit erweiterten Optionen
$options = [
    'icon' => '/media/notification-icon.png',
    'badge' => '/media/badge.png',
    'image' => '/media/hero-image.jpg',
    'tag' => 'welcome-series',
    'requireInteraction' => true,
    'actions' => [
        [
            'action' => 'view',
            'title' => 'Jetzt ansehen',
            'icon' => '/media/view-icon.png'
        ],
        [
            'action' => 'later',
            'title' => 'Später',
            'icon' => '/media/later-icon.png'
        ]
    ]
];

$service->sendNotification(
    'Neuer Artikel verfügbar',
    'Ein spannender neuer Artikel wartet auf Sie',
    'https://example.com/artikel/123',
    'frontend',  // Zielgruppe: 'frontend', 'backend' oder 'all'
    ['news'],    // Topics (optional)
    $options
);
?>
```

### Zielgruppen-spezifische Nachrichten

```php
<?php
use FriendsOfREDAXO\PushIt\Service\NotificationService;

$service = new NotificationService();

// Nur an Frontend-Benutzer
$service->sendToFrontendUsers(
    'Newsletter Update',
    'Neue Ausgabe verfügbar',
    '/newsletter/aktuell',
    ['newsletter', 'updates']
);

// Nur an Backend-Benutzer (Admins)
$service->sendToBackendUsers(
    'System Wartung',
    'Geplante Wartung heute um 22:00 Uhr',
    '/redaxo/index.php?page=system',
    ['system', 'maintenance']
);

// An alle Abonnenten mit bestimmten Topics (Frontend + Backend)
$service->sendToAllUsers(
    'Eilmeldung',
    'Wichtige Nachricht für alle Abonnenten',
    '/news/breaking',
    ['breaking-news', 'urgent'] // Nur Abonnenten dieser Topics werden benachrichtigt
);

// An einen spezifischen Backend-Benutzer (nur Backend-User haben User-IDs)
$service->sendToUser(
    123, // REDAXO Backend User-ID
    'Admin-Benachrichtigung',
    'Hallo Admin, wichtige System-Info!',
    '/redaxo/index.php?page=system',
    ['admin', 'system'] // Optional: Topics-Filter
);
?>
```

### E-Commerce Beispiele

```php
<?php
// Neue Bestellung
function notifyNewOrder($orderId, $customerName) {
    $service = new NotificationService();
    
    $service->sendToBackendUsers(
        "💰 Neue Bestellung #$orderId",
        "Bestellung von $customerName eingegangen",
        "/redaxo/index.php?page=shop/orders&order_id=$orderId",
        ['orders', 'ecommerce']
    );
}

// Warenkorb-Erinnerung (nur für Backend-User mit bekannter User-ID)
function sendCartReminder($userId, $cartItems) {
    $service = new NotificationService();
    
    $message = count($cartItems) . " Artikel warten in Ihrem Warenkorb";
    
    $service->sendToUser(
        $userId,
        '🛒 Vergessene Artikel',
        $message,
        '/shop/cart',
        [], // Topics (leer = alle Topics des Users)
        [
            'tag' => 'cart-reminder',
            'icon' => '/media/cart-icon.png',
            'actions' => [
                ['action' => 'checkout', 'title' => 'Zur Kasse'],
                ['action' => 'later', 'title' => 'Später']
            ]
        ]
    );
}

// Admin-Benachrichtigung an spezifischen Backend-User
function notifyAdmin($adminUserId, $message, $urgency = 'normal') {
    $service = new NotificationService();
    
    $icons = [
        'critical' => '�',
        'warning' => '⚠️', 
        'normal' => '�'
    ];
    
    $service->sendToUser(
        $adminUserId, // REDAXO Backend User-ID
        $icons[$urgency] . ' System-Benachrichtigung',
        $message,
        '/redaxo/index.php?page=system',
        ['admin', 'system'],
        [
            'tag' => 'admin-notification',
            'icon' => '/media/admin-icon.png',
            'requireInteraction' => $urgency === 'critical'
        ]
    );
}

// Hinweis: Frontend-User haben keine User-IDs in REDAXO
// Für Frontend-User nutzen Sie Topics oder andere Filter:
function notifyFrontendCustomers($topic, $title, $message) {
    $service = new NotificationService();
    
    $service->sendToFrontendUsers(
        $title,
        $message,
        '/news',
        [$topic] // z.B. 'customers', 'premium', 'newsletter'
    );
}

// Lager-Warnung
function notifyLowStock($productId, $stock) {
    if ($stock <= 5) {
        $service = new NotificationService();
        
        $service->sendToBackendUsers(
            '⚠️ Niedriger Lagerbestand',
            "Produkt #$productId hat nur noch $stock Stück",
            "/redaxo/index.php?page=shop/products&id=$productId",
            ['admin', 'inventory']
        );
    }
}
?>
```

## 🚨 **System Error Monitoring**

Der Cronjob `SystemMonitoringCronjob` ist bereits im AddOn enthalten und wird automatisch registriert. Sie müssen ihn **nicht** selbst implementieren – er muss nur im REDAXO-Backend unter **Cronjobs** eingerichtet werden.

### Cronjob einrichten

1. REDAXO-Backend → **AddOns → Cronjob**
2. Neuen Cronjob anlegen: Typ **„Push-It System Monitoring"** wählen
3. Ausführungsintervall setzen (empfohlen: alle 15–60 Minuten)
4. Cronjob aktivieren und speichern

Der Cronjob erledigt automatisch:
- Prüft das REDAXO-Systemlog auf neue Fehler
- Sendet Push-Benachrichtigungen bei konfigurierten Fehlerschwellen
- Prüft auf verfügbare AddOn-Updates (falls das `install`-AddOn aktiv ist)

### Cronjob-Status abfragen

```php
<?php
use FriendsOfREDAXO\PushIt\Cronjob\SystemMonitoringCronjob;

// Ist ein aktiver Push-It Cronjob konfiguriert?
$isConfigured = SystemMonitoringCronjob::isCronjobConfigured();

// Letzte Ausführungsstatistiken abfragen
$stats = SystemMonitoringCronjob::getCronjobStats();
// $stats enthält:
// [
//   'last_run'      => int (Unix-Timestamp der letzten Ausführung),
//   'total_runs'    => int (Anzahl Ausführungen gesamt),
//   'is_configured' => bool
// ]

if ($stats['last_run'] > 0) {
    echo 'Letzter Cronjob-Lauf: ' . date('d.m.Y H:i', $stats['last_run']);
    echo ' (gesamt ' . $stats['total_runs'] . ' Läufe)';
}
?>
```

### Realtime-Monitoring (Alternative zum Cronjob)

```php
<?php
// In boot.php – bei jedem Request prüfen (nur wenn Cronjob nicht genutzt wird)
$addon = rex_addon::get('push_it');

if ($addon->getConfig('error_monitoring_enabled') && 
    $addon->getConfig('monitoring_mode') === 'realtime') {
    
    rex_extension::register('RESPONSE_SHUTDOWN', static function(): void {
        $monitor = new \FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor();
        $monitor->checkAndSendErrorNotifications();
    }, rex_extension::LATE);
}
?>
```

### Monitoring-Einstellungen programmatisch anpassen

```php
<?php
use FriendsOfREDAXO\PushIt\Service\SystemErrorMonitor;

$monitor = new SystemErrorMonitor();

// Aktuellen Status abfragen
$status = $monitor->getErrorMonitoringStatus();
// $status enthält u. a.:
// [
//   'enabled'     => bool,
//   'interval'    => int (Sekunden zwischen Checks),
//   'last_check'  => int (Unix-Timestamp),
//   'error_count' => int,
// ]

if ($status['enabled']) {
    echo 'Monitoring aktiv, letzter Check: ' . date('H:i:s', $status['last_check'] ?? 0);
}

// Einstellungen ändern
$monitor->setErrorMonitoringEnabled(true);
$monitor->setErrorMonitoringInterval(300); // Alle 5 Minuten prüfen
$monitor->setErrorIcon('custom-error-icon.png'); // Dateiname aus dem Mediapool
?>
```

## 📱 **iOS & PWA Setup**

### Manifest.json erstellen

**Wichtig**: Für Push-Benachrichtigungen auf iOS Safari ist eine `manifest.json` Datei **zwingend erforderlich**.

```json
{
    "name": "Meine REDAXO Website",
    "short_name": "REDAXO Site", 
    "description": "Website mit Push-Benachrichtigungen",
    "start_url": "/",
    "display": "minimal-ui",
    "background_color": "#ffffff",
    "theme_color": "#d62d20",
    "orientation": "portrait-primary",
    "scope": "/",
    "icons": [
        {
            "src": "/assets/addons/push_it/icon-192.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose": "any maskable"
        },
        {
            "src": "/assets/addons/push_it/icon-512.png",
            "sizes": "512x512", 
            "type": "image/png",
            "purpose": "any maskable"
        }
    ],
    "categories": ["news", "business"],
    "lang": "de-DE",
    "prefer_related_applications": false
}
```

### REDAXO Template Integration

```php
<?php
// manifest.php - Dynamische Manifest-Generierung
header('Content-Type: application/manifest+json; charset=utf-8');

$manifestData = [
    'name' => rex::getServerName() . ' - ' . rex_config::get('website', 'title', 'REDAXO Website'),
    'short_name' => rex_config::get('website', 'short_title', rex::getServerName()),
    'description' => rex_config::get('website', 'description', 'Website mit Push-Benachrichtigungen'),
    'start_url' => rex_url::frontend(),
    'scope' => rex_url::frontend(),
    'display' => 'minimal-ui',
    'background_color' => rex_config::get('website', 'background_color', '#ffffff'),
    'theme_color' => rex_config::get('website', 'theme_color', '#d62d20'),
    'orientation' => 'portrait-primary',
    'lang' => rex_clang::getCurrent()->getCode() . '-' . strtoupper(rex_clang::getCurrent()->getCode()),
    'icons' => [
        [
            'src' => rex_url::assets('addons/push_it/icon-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => rex_url::assets('addons/push_it/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png', 
            'purpose' => 'any maskable'
        ]
    ],
    'categories' => rex_config::get('website', 'categories', ['business']),
    'prefer_related_applications' => false
];

echo json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
```

### HTML Template Integration

```html
<!DOCTYPE html>
<html lang="<?php echo rex_clang::getCurrent()->getCode(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.php">
    
    <!-- iOS-spezifische Meta-Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo rex_config::get('website', 'short_title', rex::getServerName()); ?>">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/addons/push_it/icon-180.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/assets/addons/push_it/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/assets/addons/push_it/icon-512.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/addons/push_it/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/addons/push_it/favicon-16x16.png">
    
    <!-- Windows Tiles -->
    <meta name="msapplication-TileColor" content="#d62d20">
    <meta name="msapplication-TileImage" content="/assets/addons/push_it/ms-icon-144x144.png">
    
    <!-- Theme Colors -->
    <meta name="theme-color" content="#d62d20">
    <meta name="msapplication-navbutton-color" content="#d62d20">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <title><?php echo rex_config::get('website', 'title', 'REDAXO Website'); ?></title>
</head>
<body>
    <!-- Ihr Content -->
    
    <!-- iOS Installation Prompt -->
    <div id="ios-install-banner" style="display: none;" class="ios-install-prompt">
        <div class="install-content">
            <div class="install-icon">📱</div>
            <div class="install-text">
                <h4>App installieren</h4>
                <p>Für Push-Benachrichtigungen installieren Sie diese Website als App</p>
            </div>
            <div class="install-actions">
                <button onclick="showIOSInstructions()">Anleitung</button>
                <button onclick="dismissInstallPrompt()">×</button>
            </div>
        </div>
    </div>
    
    <!-- iOS Installations-Anleitung -->
    <div id="ios-instructions" style="display: none;" class="modal-overlay">
        <div class="modal-content">
            <h3>📲 Installation auf iOS</h3>
            <ol class="install-steps">
                <li>Tippen Sie auf das <strong>Teilen-Symbol</strong> <span class="icon">□↗</span> unten in Safari</li>
                <li>Scrollen Sie nach unten und wählen Sie <strong>"Zum Home-Bildschirm"</strong></li>
                <li>Bestätigen Sie mit <strong>"Hinzufügen"</strong></li>
                <li>Die App erscheint auf Ihrem Home-Bildschirm</li>
                <li>Öffnen Sie die App vom Home-Bildschirm für Push-Benachrichtigungen</li>
            </ol>
            <button onclick="closeIOSInstructions()" class="btn-primary">Verstanden</button>
        </div>
    </div>

    <script>
    // iOS PWA Installation Detection
    document.addEventListener('DOMContentLoaded', function() {
        // Prüfen ob iOS und nicht bereits als PWA geöffnet
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isInStandaloneMode = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        
        if (isIOS && !isInStandaloneMode) {
            // Prüfen ob Banner bereits geschlossen wurde
            if (!localStorage.getItem('ios-install-dismissed')) {
                setTimeout(() => {
                    document.getElementById('ios-install-banner').style.display = 'block';
                }, 3000); // Nach 3 Sekunden anzeigen
            }
        }
        
        // Browser PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Eigenen Install-Button anzeigen
            showInstallButton();
        });
    });
    
    function showIOSInstructions() {
        document.getElementById('ios-instructions').style.display = 'flex';
    }
    
    function closeIOSInstructions() {
        document.getElementById('ios-instructions').style.display = 'none';
    }
    
    function dismissInstallPrompt() {
        document.getElementById('ios-install-banner').style.display = 'none';
        localStorage.setItem('ios-install-dismissed', 'true');
    }
    
    // PWA Install für andere Browser
    function showInstallButton() {
        // Custom Install Button anzeigen
        const installBtn = document.createElement('button');
        installBtn.textContent = '📱 App installieren';
        installBtn.onclick = installPWA;
        installBtn.className = 'pwa-install-btn';
        document.body.appendChild(installBtn);
    }
    
    function installPWA() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('PWA installation accepted');
                }
                deferredPrompt = null;
            });
        }
    }
    </script>
    
    <style>
    .ios-install-prompt {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        z-index: 1000;
        animation: slideUp 0.3s ease-out;
    }
    
    .install-content {
        display: flex;
        align-items: center;
        max-width: 600px;
        margin: 0 auto;
        gap: 16px;
    }
    
    .install-icon {
        font-size: 32px;
        line-height: 1;
    }
    
    .install-text h4 {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: 600;
    }
    
    .install-text p {
        margin: 0;
        font-size: 14px;
        opacity: 0.9;
    }
    
    .install-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
    }
    
    .install-actions button {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .install-actions button:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 20px;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 400px;
        width: 100%;
        text-align: center;
    }
    
    .install-steps {
        text-align: left;
        padding-left: 20px;
        line-height: 1.6;
    }
    
    .install-steps .icon {
        font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 16px;
    }
    
    .btn-primary {
        background: #007AFF;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 16px;
    }
    
    .pwa-install-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #007AFF;
        color: white;
        border: none;
        padding: 12px 16px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0,122,255,0.3);
        z-index: 1000;
    }
    
    @keyframes slideUp {
        from {
            transform: translateY(100%);
        }
        to {
            transform: translateY(0);
        }
    }
    
    /* Responsive */
    @media (max-width: 600px) {
        .install-content {
            flex-direction: column;
            text-align: center;
            gap: 12px;
        }
        
        .install-actions {
            margin-left: 0;
        }
    }
    </style>
</body>
</html>
```

### Service Worker Anpassungen für iOS

```javascript
// In service-worker.js - iOS-spezifische Optimierungen
self.addEventListener('push', function(event) {
    const data = event.data ? event.data.json() : {};
    
    // iOS-optimierte Notification-Optionen
    const options = {
        body: data.body,
        icon: data.icon || '/assets/addons/push_it/icon-192.png',
        badge: data.badge || '/assets/addons/push_it/badge-72.png',
        image: data.image,
        tag: data.tag || 'push-notification',
        data: {
            url: data.url,
            actions: data.actions || []
        },
        // iOS-spezifische Optionen
        requireInteraction: data.requireInteraction || false,
        silent: data.silent || false,
        timestamp: Date.now(),
        // iOS-Fallback für Actions
        actions: self.navigator.userAgent.includes('iPhone') ? [] : (data.actions || [])
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'Neue Nachricht', options)
    );
});

// iOS-spezifisches Click-Handling
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    const notificationData = event.notification.data;
    
    if (event.action) {
        // Action Button geklickt
        handleNotificationAction(event.action, notificationData);
    } else {
        // Notification selbst geklickt
        if (notificationData.url) {
            event.waitUntil(
                clients.openWindow(notificationData.url)
            );
        }
    }
});

function handleNotificationAction(action, data) {
    switch(action) {
        case 'view':
            if (data.url) {
                clients.openWindow(data.url);
            }
            break;
        case 'dismiss':
            // Notification wird automatisch geschlossen
            break;
        default:
            if (data.url) {
                clients.openWindow(data.url);
            }
    }
}
```

## 🎨 Framework-neutrale UI-Komponente

### Universelle Notification Bar

```html
<!-- CSS-Variablen für einfache Anpassung -->
<style>
:root {
    --pushit-primary-color: #007bff;
    --pushit-success-color: #28a745;
    --pushit-danger-color: #dc3545;
    --pushit-warning-color: #ffc107;
    --pushit-light-color: #f8f9fa;
    --pushit-dark-color: #343a40;
    --pushit-border-radius: 8px;
    --pushit-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --pushit-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --pushit-transition: all 0.3s ease;
}

.pushit-notification-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, var(--pushit-primary-color), #0056b3);
    color: white;
    padding: 12px 20px;
    font-family: var(--pushit-font-family);
    font-size: 14px;
    text-align: center;
    box-shadow: var(--pushit-shadow);
    z-index: 10000;
    transform: translateY(-100%);
    transition: var(--pushit-transition);
}

.pushit-notification-bar.show {
    transform: translateY(0);
}

.pushit-notification-bar.success {
    background: linear-gradient(135deg, var(--pushit-success-color), #1e7e34);
}

.pushit-notification-bar.error {
    background: linear-gradient(135deg, var(--pushit-danger-color), #c82333);
}

.pushit-notification-bar.warning {
    background: linear-gradient(135deg, var(--pushit-warning-color), #e0a800);
    color: var(--pushit-dark-color);
}

.pushit-bar-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    max-width: 1200px;
    margin: 0 auto;
}

.pushit-bar-icon {
    font-size: 18px;
}

.pushit-bar-text {
    flex: 1;
    max-width: 600px;
}

.pushit-bar-actions {
    display: flex;
    gap: 8px;
}

.pushit-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 6px 12px;
    border-radius: var(--pushit-border-radius);
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: var(--pushit-transition);
    text-decoration: none;
    display: inline-block;
}

.pushit-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.pushit-btn.primary {
    background: white;
    color: var(--pushit-primary-color);
}

.pushit-btn.primary:hover {
    background: var(--pushit-light-color);
}

.pushit-close {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    margin-left: 10px;
    opacity: 0.8;
    transition: var(--pushit-transition);
}

.pushit-close:hover {
    opacity: 1;
    transform: scale(1.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .pushit-notification-bar {
        position: relative;
        transform: none;
        padding: 15px;
    }
    
    .pushit-bar-content {
        flex-direction: column;
        gap: 10px;
    }
    
    .pushit-bar-actions {
        width: 100%;
        justify-content: center;
    }
}

/* iOS-spezifische Anpassungen */
@supports (-webkit-touch-callout: none) {
    .pushit-notification-bar {
        padding-top: env(safe-area-inset-top, 12px);
    }
}
</style>

<!-- HTML-Struktur -->
<div id="pushit-notification-bar" class="pushit-notification-bar">
    <div class="pushit-bar-content">
        <span class="pushit-bar-icon">🔔</span>
        <div class="pushit-bar-text">
            <span id="pushit-bar-message">Möchten Sie Benachrichtigungen aktivieren?</span>
        </div>
        <div class="pushit-bar-actions">
            <button class="pushit-btn primary" onclick="PushItUI.allow()">
                Ja, aktivieren
            </button>
            <button class="pushit-btn" onclick="PushItUI.deny()">
                Nein, danke
            </button>
        </div>
        <button class="pushit-close" onclick="PushItUI.hide()">&times;</button>
    </div>
</div>
```

### JavaScript UI-Controller

```javascript
// Universeller UI-Controller für Push-Benachrichtigungen
window.PushItUI = (function() {
    'use strict';
    
    const CONFIG = {
        storageKey: 'pushit_response',
        autoShowDelay: 2000,
        hideDelay: 5000,
        defaultTopics: 'news,updates'
    };
    
    let isVisible = false;
    let barElement = null;
    
    function init() {
        barElement = document.getElementById('pushit-notification-bar');
        
        // Prüfen ob bereits geantwortet wurde
        const userResponse = localStorage.getItem(CONFIG.storageKey);
        if (!userResponse) {
            // Nach kurzer Verzögerung anzeigen
            setTimeout(show, CONFIG.autoShowDelay);
        }
        
        // Push-It Status prüfen wenn verfügbar
        if (window.PushIt) {
            checkPushStatus();
        }
    }
    
    async function checkPushStatus() {
        try {
            const status = await PushIt.getStatus();
            if (status.isSubscribed) {
                // Bereits abonniert - nicht anzeigen
                return;
            }
        } catch (error) {
            console.log('Push status check failed:', error);
        }
    }
    
    function show(type = 'default', message = null, autoHide = false) {
        if (!barElement) return;
        
        // Typ-spezifische Klassen
        barElement.className = 'pushit-notification-bar show';
        if (type !== 'default') {
            barElement.classList.add(type);
        }
        
        // Message aktualisieren wenn angegeben
        if (message) {
            const messageEl = document.getElementById('pushit-bar-message');
            if (messageEl) messageEl.textContent = message;
        }
        
        isVisible = true;
        
        // Auto-Hide für Nachrichten
        if (autoHide) {
            setTimeout(hide, CONFIG.hideDelay);
        }
        
        // Body Padding für fixed Bar
        if (barElement.style.position === 'fixed') {
            document.body.style.paddingTop = barElement.offsetHeight + 'px';
        }
    }
    
    function hide() {
        if (!barElement) return;
        
        barElement.classList.remove('show');
        isVisible = false;
        
        // Body Padding zurücksetzen
        document.body.style.paddingTop = '';
        
        setTimeout(() => {
            if (barElement) {
                barElement.className = 'pushit-notification-bar';
            }
        }, 300);
    }
    
    async function allow() {
        try {
            // iOS-Check
            if (isIOS() && !isInStandaloneMode()) {
                showIOSInstructions();
                return;
            }
            
            localStorage.setItem(CONFIG.storageKey, 'allowed');
            
            await PushIt.subscribe('frontend', CONFIG.defaultTopics);
            
            showMessage('success', '✅ Benachrichtigungen wurden aktiviert!', true);
            
        } catch (error) {
            console.error('Push subscription failed:', error);
            
            // SSL-Fehler spezielle Behandlung
            if (error.message.includes('Service Worker') || 
                error.message.includes('SSL') ||
                error.message.includes('certificate')) {
                showMessage('error', '❌ SSL-Zertifikat Problem. Bitte vertrauen Sie dem Zertifikat.', false);
            } else {
                showMessage('error', '❌ Fehler: ' + error.message, true);
            }
        }
    }
    
    function deny() {
        localStorage.setItem(CONFIG.storageKey, 'denied');
        hide();
    }
    
    function showMessage(type, message, autoHide = true) {
        hide();
        setTimeout(() => {
            show(type, message, autoHide);
        }, 100);
    }
    
    function showIOSInstructions() {
        const message = '📱 Für iOS: Fügen Sie diese Seite zum Home-Bildschirm hinzu (Teilen → "Zum Home-Bildschirm")';
        showMessage('warning', message, false);
    }
    
    // iOS-Hilfsfunktionen
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent);
    }
    
    function isInStandaloneMode() {
        return window.navigator.standalone === true;
    }
    
    // Öffentliche API
    return {
        init: init,
        show: show,
        hide: hide,
        allow: allow,
        deny: deny,
        showMessage: showMessage,
        
        // Utility-Funktionen
        reset: function() {
            localStorage.removeItem(CONFIG.storageKey);
            show();
        },
        
        configure: function(options) {
            Object.assign(CONFIG, options);
        },
        
        isVisible: function() {
            return isVisible;
        }
    };
})();

// Auto-Init
document.addEventListener('DOMContentLoaded', PushItUI.init);
```

## 🔧 REDAXO-spezifische Integration

### Template-Integration

```php
<?php
// Im Template oder Modul
$pushItConfig = [
    'publicKey' => rex_config::get('push_it', 'vapid_public_key'),
    'enabled' => rex_config::get('push_it', 'frontend_enabled'),
    'topics' => rex_config::get('push_it', 'default_topics', 'news,updates'),
    'language' => rex_clang::getCurrentId() == 1 ? 'de' : 'en'
];
?>

<script>
// REDAXO-Konfiguration global verfügbar machen
window.PushItPublicKey = <?= json_encode($pushItConfig['publicKey']) ?>;
window.PushItTopics = <?= json_encode($pushItConfig['topics']) ?>;
window.PushItLanguage = <?= json_encode($pushItConfig['language']) ?>;

// Push-It konfigurieren
document.addEventListener('DOMContentLoaded', function() {
    if (<?= $pushItConfig['enabled'] ? 'true' : 'false' ?>) {
        PushItUI.configure({
            defaultTopics: <?= json_encode($pushItConfig['topics']) ?>,
            autoShowDelay: 3000
        });
    }
});
</script>
```

### Backend-Integration für Entwickler

```php
<?php
// Benachrichtigung aus REDAXO heraus senden
use FriendsOfREDAXO\PushIt\Service\SendManager;

// In einem AddOn oder Modul
$sendManager = new SendManager();

try {
    $result = $sendManager->sendNotification([
        'title' => 'Neue Nachricht',
        'body' => 'Eine neue Nachricht ist verfügbar',
        'icon' => '/assets/addons/push_it/icon.png',
        'url' => rex_url::frontend(),
        'topics' => ['news'],
        'user_types' => ['frontend'] // oder ['backend'] oder ['frontend', 'backend']
    ]);
    
    if ($result['success']) {
        echo "Benachrichtigung gesendet an " . $result['sent_count'] . " Empfänger";
    }
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
```

## 📊 Erweiterte Beispiele

### Custom Event Listener

```javascript
// Event-basierte Integration
document.addEventListener('pushit:subscribed', function(event) {
    console.log('Push notifications subscribed:', event.detail);
    // Analytics-Tracking
    gtag('event', 'push_subscription', {
        'event_category': 'engagement',
        'event_label': 'activated'
    });
});

document.addEventListener('pushit:unsubscribed', function(event) {
    console.log('Push notifications unsubscribed:', event.detail);
});

// Custom Events senden
PushIt.subscribe('frontend', 'news').then(result => {
    document.dispatchEvent(new CustomEvent('pushit:subscribed', {
        detail: result
    }));
});
```

### Progressive Enhancement

```javascript
// Progressive Enhancement für ältere Browser
(function() {
    'use strict';
    
    // Feature Detection
    function supportsPushNotifications() {
        return 'serviceWorker' in navigator && 
               'PushManager' in window && 
               'Notification' in window;
    }
    
    function enhanceWithPush() {
        if (!supportsPushNotifications()) {
            console.log('Push notifications not supported');
            return;
        }
        
        // Push-Features laden
        const script = document.createElement('script');
        script.src = '/assets/addons/push_it/frontend.js';
        script.onload = function() {
            // Push-UI initialisieren
            if (window.PushItUI) {
                PushItUI.init();
            }
        };
        document.head.appendChild(script);
    }
    
    // Lazy Loading
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceWithPush);
    } else {
        enhanceWithPush();
    }
})();
```

### A/B Testing Integration

```javascript
// A/B Testing für Notification-Strategien
const PushItAB = {
    variants: {
        'immediate': {
            delay: 0,
            message: 'Verpassen Sie keine Updates!'
        },
        'delayed': {
            delay: 5000,
            message: 'Bleiben Sie auf dem Laufenden'
        },
        'scroll': {
            trigger: 'scroll',
            threshold: 50,
            message: 'Interessiert? Aktivieren Sie Benachrichtigungen!'
        }
    },
    
    init() {
        const variant = this.getVariant();
        this.runVariant(variant);
    },
    
    getVariant() {
        // Gespeicherte Variante oder zufällige Auswahl
        let variant = localStorage.getItem('pushit_ab_variant');
        if (!variant) {
            const variants = Object.keys(this.variants);
            variant = variants[Math.floor(Math.random() * variants.length)];
            localStorage.setItem('pushit_ab_variant', variant);
        }
        return variant;
    },
    
    runVariant(variantName) {
        const variant = this.variants[variantName];
        
        if (variant.trigger === 'scroll') {
            this.setupScrollTrigger(variant);
        } else {
            setTimeout(() => {
                PushItUI.show('default', variant.message);
            }, variant.delay);
        }
    },
    
    setupScrollTrigger(variant) {
        let triggered = false;
        window.addEventListener('scroll', () => {
            if (triggered) return;
            
            const scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
            if (scrollPercent >= variant.threshold) {
                triggered = true;
                PushItUI.show('default', variant.message);
            }
        });
    }
};

// A/B Test starten
PushItAB.init();
```

## 🔍 Debugging und Troubleshooting

### Debug-Console

```javascript
// Debug-Funktionen für Entwicklung
window.PushItDebug = {
    async status() {
        const status = await PushIt.getStatus();
        console.table({
            'Subscribed': status.isSubscribed,
            'Browser Support': 'serviceWorker' in navigator,
            'Push Support': 'PushManager' in window,
            'Notification Support': 'Notification' in window,
            'Permission': Notification.permission,
            'iOS': /iPad|iPhone|iPod/.test(navigator.userAgent),
            'Standalone': window.navigator.standalone
        });
    },
    
    async testSubscription() {
        try {
            await PushIt.subscribe('frontend', 'test');
            console.log('✅ Subscription successful');
        } catch (error) {
            console.error('❌ Subscription failed:', error);
        }
    },
    
    clearStorage() {
        localStorage.removeItem('pushit_response');
        localStorage.removeItem('pushit_ab_variant');
        console.log('✅ Storage cleared');
    },
    
    showUI() {
        PushItUI.show();
    }
};

// Debug in Console verfügbar machen
console.log('Push-It Debug available: PushItDebug.status(), PushItDebug.testSubscription()');
```

Diese Beispiele zeigen die vielseitigen Einsatzmöglichkeiten des Push-It AddOns mit besonderem Fokus auf iOS-Kompatibilität und framework-neutrale Implementierung.
