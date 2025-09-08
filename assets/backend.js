// Backend-spezifisches JavaScript für Push It
(function() {
  'use strict';
  
  // i18n-System für Backend-Texte mit dynamischem Laden
  let translationsLoaded = false;
  let currentLang = 'de';
  
  function t(key, replacements = {}) {
    // Fallback-Übersetzungen direkt im JavaScript
    const fallbackTranslations = {
      de: {
        'status_active': 'Benachrichtigungen sind aktiv',
        'status_inactive': 'Benachrichtigungen sind nicht aktiv',
        'quick_notification_confirm_prefix': 'Möchten Sie eine',
        'quick_notification_confirm_suffix': 'Benachrichtigung senden',
        'notification_sent_success': 'Benachrichtigung erfolgreich gesendet!',
        'notification_sent_error': 'Fehler beim Senden der Benachrichtigung.',
        'network_error': 'Netzwerk-Fehler',
        'critical_error_title': 'Kritischer System-Fehler',
        'critical_error_message': 'Ein kritischer Fehler wurde erkannt und muss sofort behoben werden.',
        'system_warning_title': 'System-Warnung',
        'system_warning_message': 'Eine System-Warnung wurde ausgelöst.',
        'system_info_title': 'System-Information',
        'system_info_message': 'Neue System-Information verfügbar.',
        'backend.notifications_activated': 'Backend-Benachrichtigungen wurden aktiviert!',
        'backend.activation_error': 'Fehler beim Aktivieren der Benachrichtigungen: {message}',
        'backend.test_sent': 'Test-Benachrichtigung wurde gesendet!',
        'backend.test_error': 'Fehler beim Senden der Test-Benachrichtigung!',
        'backend.status_reset': 'Backend-Subscription Status wurde zurückgesetzt.',
        'backend.notifications_title': 'Backend-Benachrichtigungen',
        'backend.notifications_prompt': 'Möchten Sie Push-Benachrichtigungen für Systemereignisse aktivieren?',
        'backend.activate_button': 'Aktivieren',
        'backend.decline_button': 'Nein, danke',
        'backend.activate_backend': 'Backend aktivieren',
        'backend.deactivate_button': 'Deaktivieren', 
        'backend.settings_button': 'Einstellungen'
      },
      en: {
        'status_active': 'Notifications are active',
        'status_inactive': 'Notifications are not active',
        'quick_notification_confirm_prefix': 'Do you want to send a',
        'quick_notification_confirm_suffix': 'notification',
        'notification_sent_success': 'Notification sent successfully!',
        'notification_sent_error': 'Error sending notification.',
        'network_error': 'Network error',
        'critical_error_title': 'Critical System Error',
        'critical_error_message': 'A critical error has been detected and needs immediate attention.',
        'system_warning_title': 'System Warning',
        'system_warning_message': 'A system warning has been triggered.',
        'system_info_title': 'System Information',
        'system_info_message': 'New system information available.',
        'backend.notifications_activated': 'Backend notifications have been activated!',
        'backend.activation_error': 'Error activating notifications: {message}',
        'backend.test_sent': 'Test notification has been sent!',
        'backend.test_error': 'Error sending test notification!',
        'backend.status_reset': 'Backend subscription status has been reset.',
        'backend.notifications_title': 'Backend Notifications',
        'backend.notifications_prompt': 'Would you like to activate push notifications for system events?',
        'backend.activate_button': 'Activate',
        'backend.decline_button': 'No, thanks',
        'backend.activate_backend': 'Activate Backend',
        'backend.deactivate_button': 'Deactivate',
        'backend.settings_button': 'Settings'
      }
    };
    
    // Verwende geladene Übersetzungen oder Fallback
    let translations = {};
    if (window.PushItLang && window.PushItLang[currentLang]) {
      translations = window.PushItLang[currentLang];
    } else {
      translations = fallbackTranslations[currentLang] || fallbackTranslations.de || {};
    }
    
    let text = translations[key] || key;
    
    // Platzhalter ersetzen {variable} mit Werten
    for (const [placeholder, value] of Object.entries(replacements)) {
      text = text.replace(new RegExp(`\\{${placeholder}\\}`, 'g'), value);
    }
    
    return text;
  }
  
  // Initialisiere PushIt-Objekt mit i18n sofort verfügbar
  window.PushIt = window.PushIt || {};
  
  // i18n-System sofort verfügbar machen
  window.PushIt.i18n = {
    get: function(key, replacements = {}) {
      return t(key, replacements);
    },
    
    loadLanguage: loadTranslations
  };
  
  // Sprache sofort laden (nicht auf DOMContentLoaded warten)
  loadTranslations();
  
  // Sprache ermitteln
  function detectLanguage() {
    if (window.rex && window.rex.push_it_language) {
      return window.rex.push_it_language;
    }
    if (window.PushItLanguage) {
      return window.PushItLanguage;
    }
    // Fallback: HTML lang attribute prüfen
    const htmlLang = document.documentElement.lang;
    if (htmlLang) {
      const lang = htmlLang.split('-')[0];
      if (['de', 'en'].includes(lang)) {
        return lang;
      }
    }
    // Fallback auf Browser-Sprache
    const browserLang = navigator.language.split('-')[0];
    return ['de', 'en'].includes(browserLang) ? browserLang : 'de';
  }
  
  // Sprachdatei dynamisch laden
  async function loadTranslations(lang = null) {
    if (!lang) {
      lang = detectLanguage();
    }
    
    // Bereits geladen oder aktuell geladen wird
    if (translationsLoaded && currentLang === lang) {
      return;
    }
    
    try {
      // Prüfen ob bereits inline geladen (Fallback)
      if (window.PushItLang && window.PushItLang[lang]) {
        currentLang = lang;
        translationsLoaded = true;
        return;
      }
      
      // Dynamisch laden via Script-Tag (besser für Browser-Caching)
      const script = document.createElement('script');
      script.src = '/assets/addons/push_it/lang/' + lang + '.js';
      
      await new Promise((resolve, reject) => {
        script.onload = () => {
          currentLang = lang;
          translationsLoaded = true;
          resolve();
        };
        script.onerror = () => {
          // Fallback auf Deutsch wenn Sprache nicht verfügbar
          if (lang !== 'de') {
            loadTranslations('de').then(resolve).catch(reject);
          } else {
            reject(new Error('Could not load language file: ' + lang));
          }
        };
        document.head.appendChild(script);
      });
      
    } catch (error) {
      console.warn('Could not load translations for', lang, '- using fallback');
      currentLang = 'de';
      translationsLoaded = true;
    }
  }

  // Prüfen ob Benutzer eingeloggt ist (nicht auf Login-Seite)
  function isUserLoggedIn() {
    // Prüfe auf Login-Seite (verschiedene mögliche URLs)
    const isLoginPage = window.location.href.includes('page=login') || 
                       window.location.href.includes('rex_login') ||
                       document.body.classList.contains('rex-page-login') ||
                       document.querySelector('.rex-page-login') !== null ||
                       document.querySelector('#rex-form-login') !== null;
    
    if (isLoginPage) {
      console.log('PushIt: On login page - not showing notifications');
      return false;
    }
    
    // Prüfe auf rex-User-Objekt oder Backend-Indikatoren
    const hasUserSession = window.rex && (window.rex.user_id || window.rex.user || window.rex.push_it_user_id);
    const hasBackendElements = document.querySelector('#rex-js-nav-top') !== null ||
                              document.querySelector('.rex-page-header') !== null ||
                              document.querySelector('.rex-page-main') !== null;
    
    const isLoggedIn = hasUserSession || hasBackendElements;
    console.log('PushIt: User logged in check:', { hasUserSession, hasBackendElements, isLoggedIn });
    
    return isLoggedIn;
  }

  // Warten bis DOM und REDAXO-spezifische Objekte geladen sind
  document.addEventListener('DOMContentLoaded', async function() {
    // Sprache laden
    await loadTranslations();
    
    // Nur wenn Benutzer eingeloggt ist
    if (!isUserLoggedIn()) {
      console.log('PushIt: User not logged in or on login page - skipping backend notifications');
      return;
    }
    
    // Backend-Benachrichtigungen automatisch aktivieren wenn konfiguriert
    if (window.rex && window.rex.push_it_backend_enabled && window.rex.push_it_public_key) {
      // Public Key setzen (sowohl für PushIt als auch PushItPublicKey)
      window.PushItPublicKey = window.rex.push_it_public_key;
      
      // Backend-Token setzen falls verfügbar
      if (window.rex.push_it_backend_token) {
        window.PushItBackendToken = window.rex.push_it_backend_token;
      }
      
      // User-ID setzen falls verfügbar
      if (window.rex.push_it_user_id) {
        window.PushItUserId = window.rex.push_it_user_id;
      }
      
      // Warten bis PushIt verfügbar ist
      waitForPushIt().then(() => {
        checkBackendSubscription();
      });
    }
    
    // Notification-Button in Backend-Header hinzufügen
    addBackendNotificationButton();
  });
  
  function waitForPushIt() {
    return new Promise((resolve) => {
      if (window.PushIt) {
        resolve();
        return;
      }
      
      const checkPushIt = () => {
        if (window.PushIt) {
          resolve();
        } else {
          setTimeout(checkPushIt, 100);
        }
      };
      
      checkPushIt();
    });
  }
  
  async function checkBackendSubscription() {
    try {
      console.log('PushIt: Checking backend subscription status...');
      
      // Prüfen ob bereits eine Subscription existiert
      const status = await window.PushIt.getStatus();
      console.log('PushIt: Subscription status:', status);
      
      // Prüfen ob der Benutzer bereits geantwortet hat (localStorage)
      const hasAnswered = localStorage.getItem('push_it_backend_asked');
      console.log('PushIt: User has answered before:', hasAnswered);
      
      if (!status.isSubscribed && !hasAnswered) {
        console.log('PushIt: Showing backend notification prompt...');
        // Zeige eine Info-Nachricht anstatt automatisch zu fragen
        showBackendNotificationPrompt();
      } else {
        console.log('PushIt: Not showing prompt - already subscribed or answered');
      }
    } catch (error) {
      console.warn('Backend Subscription Check failed:', error);
    }
  }
  
  function showBackendNotificationPrompt() {
    // Info-Banner in das Message-Container einfügen (Bootstrap 3 kompatibel)
    let messageContainer = document.getElementById('rex-message-container');
    
    // Fallback: Erstelle Container falls nicht vorhanden
    if (!messageContainer) {
      const mainContent = document.querySelector('.rex-page-main') || document.querySelector('main') || document.body;
      messageContainer = document.createElement('div');
      messageContainer.id = 'rex-message-container';
      messageContainer.style.margin = '20px 0';
      mainContent.insertBefore(messageContainer, mainContent.firstChild);
    }
    
    const promptHtml = `
      <div class="alert alert-info alert-dismissible" role="alert" style="margin: 15px; display: block !important; visibility: visible !important;">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" onclick="declineBackendNotifications()">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4><i class="rex-icon fa-bell"></i> ${t('backend.notifications_title')}</h4>
        <p>${t('backend.notifications_prompt')}</p>
        <div class="btn-group" role="group" style="margin-top: 10px;">
          <button type="button" class="btn btn-success btn-sm" onclick="activateBackendNotifications()">
            <i class="rex-icon fa-bell"></i> ${t('backend.activate_button')}
          </button>
          <button type="button" class="btn btn-default btn-sm" onclick="declineBackendNotifications()">
            ${t('backend.decline_button')}
          </button>
        </div>
      </div>
    `;
    
    messageContainer.innerHTML = promptHtml;
  }
  
  // Globale Funktionen für die Buttons
  window.activateBackendNotifications = async function() {
    try {
      await window.PushIt.subscribe('backend', 'system,admin');
      localStorage.setItem('push_it_backend_asked', 'accepted');
      showBackendMessage(t('backend.notifications_activated'), 'success');
    } catch (error) {
      console.error('Backend subscription error:', error);
      
      // Spezielle Behandlung für SSL-Probleme
      let errorMessage = error.message;
      if (error.message.includes('Service Worker') || 
          error.message.includes('fetch') ||
          error.message.includes('SSL') ||
          error.message.includes(t('error.serviceworker_ssl'))) {
        
        // Ausführliche SSL-Fehlermeldung anzeigen
        showBackendMessage(errorMessage, 'error');
        return;
      }
      
      // Normale Fehlermeldung
      showBackendMessage(t('backend.activation_error', { message: errorMessage }), 'error');
    }
  };
  
  window.declineBackendNotifications = function() {
    localStorage.setItem('push_it_backend_asked', 'declined');
    // Alert schließen
    const alert = document.querySelector('#rex-message-container .alert');
    if (alert) {
      alert.remove();
    }
  };
  
  function addBackendNotificationButton() {
    // Versuche Notification-Button in Backend-Header zu integrieren (Bootstrap 3)
    const headerActions = document.querySelector('#rex-js-nav-top .navbar-nav');
    if (headerActions) {
      const listItem = document.createElement('li');
      listItem.className = 'dropdown';
      
      listItem.innerHTML = `
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="${t('backend.notifications_title')}">
          <i class="rex-icon fa-bell"></i>
          <span class="sr-only">${t('backend.notifications_title')}</span>
          <span class="caret"></span>
        </a>
        <ul class="dropdown-menu">
          <li><a href="#" onclick="PushIt.requestBackend('system,admin'); return false;">
            <i class="rex-icon fa-bell-o"></i> ${t('backend.activate_backend')}
          </a></li>
          <li><a href="#" onclick="PushIt.disable(); return false;">
            <i class="rex-icon fa-bell-slash"></i> ${t('backend.deactivate_button')}
          </a></li>
          <li class="divider"></li>
          <li><a href="index.php?page=push_it">
            <i class="rex-icon fa-cog"></i> ${t('backend.settings_button')}
          </a></li>
        </ul>
      `;
      
      headerActions.appendChild(listItem);
    }
  }
  
  function showBackendMessage(message, type = 'info') {
    // REDAXO-spezifische Nachrichtenanzeige (Bootstrap 3 kompatibel)
    const messageContainer = document.getElementById('rex-message-container');
    if (messageContainer) {
      const alertClass = type === 'success' ? 'alert-success' : 
                        type === 'error' ? 'alert-danger' : 'alert-info';
      
      // Längere Nachrichten in einem erweiterbaren Format anzeigen
      const isLongMessage = message.length > 200;
      const messageContent = isLongMessage ? 
        `<strong>SSL-Zertifikat Problem:</strong><br><pre style="white-space: pre-wrap; margin-top: 10px; background: rgba(0,0,0,0.1); padding: 10px; border-radius: 3px; font-size: 12px;">${message}</pre>` :
        message;
      
      const messageHtml = `
        <div class="alert ${alertClass} alert-dismissible" role="alert" style="max-width: 800px; margin: 15px auto;">
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          ${messageContent}
        </div>
      `;
      
      messageContainer.innerHTML = messageHtml;
      
      // Auto-hide nach längerer Zeit für Fehlermeldungen
      const hideDelay = type === 'error' ? 15000 : 5000;
      setTimeout(() => {
        const alert = messageContainer.querySelector('.alert');
        if (alert) {
          // Bootstrap 3 fade out
          alert.classList.add('fade');
          setTimeout(() => alert.remove(), 150);
        }
      }, hideDelay);
    }
  }
  
  // Test-Benachrichtigung senden (für Admin-Bereich)
  window.PushItBackend = {
    sendTestNotification: function() {
      fetch('/index.php?page=push_it&func=test_notification', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'rex-api-call=push_it_test'
      }).then(response => {
        if (response.ok) {
          showBackendMessage(t('backend.test_sent'), 'success');
        } else {
          showBackendMessage(t('backend.test_error'), 'error');
        }
      }).catch(error => {
        console.error('Test notification error:', error);
        showBackendMessage(t('backend.test_error'), 'error');
      });
    },
    
    // Reset-Funktion um erneut nach Backend-Subscription zu fragen
    resetBackendAsk: function() {
      localStorage.removeItem('push_it_backend_asked');
      checkBackendSubscription();
    }
  };
  
  // Zusätzliche Funktionen für localStorage-Reset
  window.PushItReset = function() {
    localStorage.removeItem('push_it_backend_asked');
    console.log('PushIt: Backend ask status reset');
    alert(t('backend.status_reset'));
    // Banner erneut anzeigen
    setTimeout(() => {
      showBackendNotificationPrompt();
    }, 500);
  };
  
    // Test-Funktion um Banner zu forcieren
  window.PushItTest = function() {
    if (!isUserLoggedIn()) {
      console.log('PushIt: Cannot show prompt - user not logged in');
      alert('Test nur im eingeloggten Backend möglich!');
      return;
    }
    console.log('PushIt: Forcing notification prompt for testing');
    showBackendNotificationPrompt();
  };
  
  // Reset-Funktion
  window.PushItReset = function() {
    if (!isUserLoggedIn()) {
      console.log('PushIt: Cannot reset - user not logged in');
      alert('Reset nur im eingeloggten Backend möglich!');
      return;
    }
    localStorage.removeItem('push_it_backend_asked');
    console.log('PushIt: Backend ask status reset');
    alert(t('backend.status_reset'));
    // Banner erneut anzeigen
    setTimeout(() => {
      showBackendNotificationPrompt();
    }, 500);
  };
  
  // Debug-Funktion um aktuellen Status zu prüfen
  window.PushItDebug = function() {
    console.log('PushIt Debug Info:');
    console.log('- User logged in:', isUserLoggedIn());
    console.log('- LocalStorage asked:', localStorage.getItem('push_it_backend_asked'));
    console.log('- Backend enabled:', window.rex && window.rex.push_it_backend_enabled);
    console.log('- Public key:', window.rex && window.rex.push_it_public_key ? 'Set' : 'Not set');
    console.log('- PushIt available:', typeof window.PushIt !== 'undefined');
    
    if (window.PushIt) {
      window.PushIt.getStatus().then(status => {
        console.log('- Subscription status:', status);
      });
    }
  };
  
})();
