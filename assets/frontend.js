(function() {
  'use strict';
  
  // i18n-System für Frontend-Texte mit dynamischem Laden
  let translationsLoaded = false;
  let currentLang = 'de';
  
  // Sprache ermitteln
  function detectLanguage() {
    if (window.rex && window.rex.push_it_language) {
      return window.rex.push_it_language;
    }
    if (window.PushItLanguage) {
      return window.PushItLanguage;
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
  
  function t(key, replacements = {}) {
    const translations = window.PushItLang && window.PushItLang[currentLang] ? window.PushItLang[currentLang] : {};
    
    let text = translations[key] || key;
    
    // Platzhalter ersetzen {variable} mit Werten
    for (const [placeholder, value] of Object.entries(replacements)) {
      text = text.replace(new RegExp(`\\{${placeholder}\\}`, 'g'), value);
    }
    
    return text;
  }
  
  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }
  
  async function ensureServiceWorker() {
    await loadTranslations(); // Sprache laden
    
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      throw new Error(t('error.browser_not_supported'));
    }
    
    try {
      // Service Worker registrieren oder vorhandenen abrufen
      let reg = await navigator.serviceWorker.getRegistration('/assets/addons/push_it/sw.js');
      if (!reg) {
        try {
          reg = await navigator.serviceWorker.register('/assets/addons/push_it/sw.js', {
            scope: '/assets/addons/push_it/'
          });
        } catch (registerError) {
          // Spezifische Behandlung für SSL/Fetch-Probleme
          if (registerError.message.includes('fetch') || 
              registerError.message.includes('An unknown error occurred when fetching the script') ||
              registerError.message.includes('Load failed') ||
              registerError.message.includes('net::ERR_CERT')) {
            
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            const isChrome = /chrome/i.test(navigator.userAgent);
            const isFirefox = /firefox/i.test(navigator.userAgent);
            
            let sslInstructions = t('instructions.ssl_certificate') + '\n\n';
            
            if (isChrome) {
              sslInstructions += t('instructions.ssl_chrome');
            } else if (isFirefox) {
              sslInstructions += t('instructions.ssl_firefox');
            } else if (isSafari) {
              sslInstructions += t('instructions.ssl_safari');
            } else {
              sslInstructions += t('instructions.ssl_general');
            }
            
            sslInstructions += '\n\n' + t('instructions.ssl_general');
            
            throw new Error(t('error.serviceworker_ssl') + '\n\n' + sslInstructions);
          }
          
          // Andere Service Worker Registrierungsfehler
          console.error('Service Worker registration error:', registerError);
          throw new Error(t('error.serviceworker_register') + ': ' + registerError.message);
        }
      }
      
      // Warten bis Service Worker aktiv ist
      if (reg.installing) {
        await new Promise(resolve => {
          reg.installing.addEventListener('statechange', function() {
            if (this.state === 'activated') resolve();
          });
        });
      } else if (!reg.active) {
        // Kurz warten für bereits installierte SW
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      
      return reg;
      
    } catch (error) {
      // Falls der Fehler bereits behandelt wurde, weiterwerfen
      if (error.message.includes(t('error.serviceworker_ssl')) || 
          error.message.includes(t('instructions.ssl_certificate'))) {
        throw error;
      }
      
      // Allgemeine Service Worker Fehler
      console.error('Service Worker error:', error);
      throw new Error(t('error.serviceworker_register') + ': ' + error.message);
    }
  }
  
  async function requestNotificationPermission() {
    await loadTranslations(); // Sprache laden
    
    if (Notification.permission === 'granted') {
      return true;
    }
    
    if (Notification.permission === 'denied') {
      const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
      const isChrome = /chrome/i.test(navigator.userAgent);
      const isFirefox = /firefox/i.test(navigator.userAgent);
      
      let instructions = t('instructions.notifications_blocked');
      
      if (isSafari) {
        instructions += t('instructions.safari');
      } else if (isChrome) {
        instructions += t('instructions.chrome');
      } else if (isFirefox) {
        instructions += t('instructions.firefox');
      } else {
        instructions += t('instructions.generic');
      }
      
      throw new Error(instructions);
    }
    
    // Nur bei Benutzeraktion anfordern
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      throw new Error(t('error.permission_denied'));
    }
    
    return true;
  }
  
  async function subscribe(userType = 'frontend', topics = '') {
    const reg = await ensureServiceWorker();
    await requestNotificationPermission();
    
    const publicKey = window.PushItPublicKey || '';
    if (!publicKey) {
      throw new Error(t('error.vapid_not_configured'));
    }
    
    // VAPID Key validieren
    if (publicKey.length < 80) {
      throw new Error(t('error.vapid_invalid'));
    }
    
    try {
      const subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey)
      });
      
      const params = new URLSearchParams({
        'rex-api-call': 'push_it_subscribe',
        user_type: userType
      });
      
      if (topics) {
        params.append('topics', topics);
      }
      
      // Backend-Token für Backend-Subscriptions hinzufügen
      if (userType === 'backend') {
        let backendToken = null;
        
        // Zuerst aus rex-Objekt versuchen
        if (window.rex && window.rex.push_it_backend_token) {
          backendToken = window.rex.push_it_backend_token;
        }
        // Fallback auf alte Variable
        else if (window.PushItBackendToken) {
          backendToken = window.PushItBackendToken;
        }
        
        if (backendToken) {
          params.append('backend_token', backendToken);
        }
      }
      
      // User-ID für Backend-Subscriptions hinzufügen
      if (userType === 'backend') {
        let userId = null;
        
        // Zuerst aus rex-Objekt versuchen
        if (window.rex && window.rex.push_it_user_id) {
          userId = window.rex.push_it_user_id;
        }
        // Fallback auf alte Variable
        else if (window.PushItUserId) {
          userId = window.PushItUserId;
        }
        
        if (userId) {
          params.append('user_id', userId);
        }
      }
      
      const response = await fetch('/index.php?' + params.toString(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(subscription)
      });
      
      if (!response.ok) {
        throw new Error(t('error.server_error', { status: response.status }));
      }
      
      const result = await response.json();
      if (!result.success) {
        throw new Error(t('error.subscription_failed', { error: result.error || t('error.unknown_error') }));
      }
      
      return result;
      
    } catch (error) {
      console.error(t('log.subscription_error'), error);
      throw error;
    }
  }
  
  async function unsubscribe() {
    try {
      const reg = await navigator.serviceWorker.getRegistration('/assets/addons/push_it/');
      const subscription = await reg?.pushManager.getSubscription();
      
      if (!subscription) {
        return { success: true, info: 'no_subscription' };
      }
      
      const response = await fetch('/index.php?rex-api-call=push_it_unsubscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ endpoint: subscription.endpoint })
      });
      
      if (!response.ok) {
        throw new Error(t('error.server_error', { status: response.status }));
      }
      
      const result = await response.json();
      if (!result.success) {
        throw new Error(t('error.unsubscribe_failed', { error: result.error || t('error.unknown_error') }));
      }
      
      await subscription.unsubscribe();
      return result;
      
    } catch (error) {
      console.error(t('log.unsubscribe_error'), error);
      throw error;
    }
  }
  
  async function getSubscriptionStatus() {
    try {
      const reg = await navigator.serviceWorker.getRegistration('/assets/addons/push_it/');
      const subscription = await reg?.pushManager.getSubscription();
      return {
        isSubscribed: !!subscription,
        subscription: subscription
      };
    } catch (error) {
      return {
        isSubscribed: false,
        error: error.message
      };
    }
  }
  
  // Öffentliche API
  window.PushIt = {
    subscribe: async function(userType = 'frontend', topics = '') {
      try {
        await loadTranslations(); // Sprache laden
        const result = await subscribe(userType, topics);
        console.log(t('success.push_notifications_activated'), result);
        return result;
      } catch (error) {
        console.error(t('log.activate_error'), error);
        throw error;
      }
    },
    
    unsubscribe: async function() {
      try {
        await loadTranslations(); // Sprache laden
        const result = await unsubscribe();
        console.log(t('success.push_notifications_deactivated'), result);
        return result;
      } catch (error) {
        console.error(t('log.deactivate_error'), error);
        throw error;
      }
    },
    
    getStatus: getSubscriptionStatus,
    
    // Einfache UI-Funktionen
    requestFrontend: async function(topics = '') {
      await loadTranslations(); // Sprache laden
      const topicsToUse = topics || window.PushItTopics || '';
      try {
        await subscribe('frontend', topicsToUse);
        alert(t('success.frontend_notifications_activated'));
      } catch (err) {
        alert(t('error.generic', { message: err.message }));
      }
    },
    
    requestBackend: async function(topics = '') {
      await loadTranslations(); // Sprache laden
      const topicsToUse = topics || window.PushItTopics || '';
      try {
        await subscribe('backend', topicsToUse);
        alert(t('success.backend_notifications_activated'));
      } catch (err) {
        alert(t('error.generic', { message: err.message }));
      }
    },
    
    disable: async function() {
      await loadTranslations(); // Sprache laden
      try {
        await unsubscribe();
        alert(t('success.notifications_disabled'));
      } catch (err) {
        alert(t('error.generic', { message: err.message }));
      }
    },
    
    // i18n-System öffentlich verfügbar machen
    i18n: {
      get: function(key, replacements = {}) {
        return t(key, replacements);
      },
      
      loadLanguage: loadTranslations
    }
  };
  
})();
