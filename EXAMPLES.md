# Push-It AddOn - Praxisbeispiele

Diese Dokumentation zeigt praktische Anwendungsbeispiele für das Push-It AddOn mit verschiedenen Szenarien und Plattformen.

## 📱 Grundlegende Integration

### Frontend JavaScript Integration

```javascript
// Einfache Frontend-Integration
<script>
// Push-Benachrichtigungen für Frontend-Benutzer aktivieren
async function enableNotifications() {
    try {
        await PushIt.subscribe('frontend', 'news,offers');
        alert('Benachrichtigungen wurden aktiviert!');
    } catch (error) {
        console.error('Fehler:', error.message);
        alert('Fehler beim Aktivieren: ' + error.message);
    }
}

// Status prüfen
async function checkStatus() {
    const status = await PushIt.getStatus();
    console.log('Notification Status:', status.isSubscribed);
}
</script>
```

### Backend Integration

```javascript
// Backend-Benachrichtigungen für Administratoren
<script>
async function enableBackendNotifications() {
    try {
        await PushIt.subscribe('backend', 'system,admin,critical');
        alert('Backend-Benachrichtigungen aktiviert!');
    } catch (error) {
        alert('Fehler: ' + error.message);
    }
}
</script>
```

## 🍎 iOS Safari Integration

iOS Safari erfordert spezielle Behandlung, da Push-Nachrichten nur in Web-Apps funktionieren, die zum Homescreen hinzugefügt wurden.

### iOS-kompatible Implementierung

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Meine App">
    <link rel="apple-touch-icon" href="/assets/addons/push_it/icon.png">
    <link rel="manifest" href="/manifest.json">
</head>
<body>
    <div id="ios-install-prompt" class="ios-prompt" style="display: none;">
        <div class="ios-prompt-content">
            <h3>📱 Installation für iOS</h3>
            <p>Für Push-Benachrichtigungen auf iOS:</p>
            <ol>
                <li>Tippen Sie auf das <strong>Teilen-Symbol</strong> <span style="font-size: 1.2em;">⬆️</span></li>
                <li>Wählen Sie <strong>"Zum Home-Bildschirm"</strong></li>
                <li>Bestätigen Sie mit <strong>"Hinzufügen"</strong></li>
                <li>Öffnen Sie die App vom Home-Bildschirm</li>
                <li>Aktivieren Sie dann die Benachrichtigungen</li>
            </ol>
            <button onclick="hideIOSPrompt()">Verstanden</button>
        </div>
    </div>

    <div id="notification-controls">
        <button id="enable-notifications" onclick="enableNotifications()">
            🔔 Benachrichtigungen aktivieren
        </button>
        <button id="disable-notifications" onclick="disableNotifications()">
            🔕 Benachrichtigungen deaktivieren
        </button>
    </div>

    <script>
        // iOS-Erkennung und Installation
        function isIOS() {
            return /iPad|iPhone|iPod/.test(navigator.userAgent);
        }
        
        function isInStandaloneMode() {
            return window.navigator.standalone === true;
        }
        
        function isInstallable() {
            return isIOS() && !isInStandaloneMode();
        }
        
        // iOS-Installationsaufforderung anzeigen
        if (isInstallable()) {
            document.getElementById('ios-install-prompt').style.display = 'block';
            document.getElementById('notification-controls').style.display = 'none';
        }
        
        function hideIOSPrompt() {
            document.getElementById('ios-install-prompt').style.display = 'none';
            document.getElementById('notification-controls').style.display = 'block';
        }
        
        // Benachrichtigungen aktivieren
        async function enableNotifications() {
            // Prüfen ob iOS und nicht installiert
            if (isInstallable()) {
                alert('Bitte installieren Sie die App zuerst auf Ihrem Home-Bildschirm.');
                return;
            }
            
            try {
                await PushIt.subscribe('frontend', 'news,updates');
                alert('✅ Benachrichtigungen wurden aktiviert!');
                updateButtonStates();
            } catch (error) {
                console.error('Push-Fehler:', error);
                alert('❌ Fehler: ' + error.message);
            }
        }
        
        async function disableNotifications() {
            try {
                await PushIt.unsubscribe();
                alert('✅ Benachrichtigungen wurden deaktiviert!');
                updateButtonStates();
            } catch (error) {
                alert('❌ Fehler: ' + error.message);
            }
        }
        
        // Button-Status aktualisieren
        async function updateButtonStates() {
            const status = await PushIt.getStatus();
            const enableBtn = document.getElementById('enable-notifications');
            const disableBtn = document.getElementById('disable-notifications');
            
            if (status.isSubscribed) {
                enableBtn.style.display = 'none';
                disableBtn.style.display = 'inline-block';
            } else {
                enableBtn.style.display = 'inline-block';
                disableBtn.style.display = 'none';
            }
        }
        
        // Initial laden
        document.addEventListener('DOMContentLoaded', updateButtonStates);
    </script>
</body>
</html>
```

### Web App Manifest (manifest.json)

```json
{
    "name": "Meine Push-App",
    "short_name": "PushApp",
    "description": "App mit Push-Benachrichtigungen",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#007bff",
    "icons": [
        {
            "src": "/assets/addons/push_it/icon.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/assets/addons/push_it/icon.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
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
