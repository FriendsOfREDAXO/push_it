(function() {
  'use strict';
  
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
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      throw new Error('Web Push wird von diesem Browser nicht unterstützt');
    }
    
    // Service Worker registrieren oder vorhandenen abrufen
    let reg = await navigator.serviceWorker.getRegistration('/assets/addons/pushi_it/sw.js');
    if (!reg) {
      reg = await navigator.serviceWorker.register('/assets/addons/pushi_it/sw.js', {
        scope: '/assets/addons/pushi_it/'
      });
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
  }
  
  async function requestNotificationPermission() {
    if (Notification.permission === 'granted') {
      return true;
    }
    
    if (Notification.permission === 'denied') {
      const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
      const isChrome = /chrome/i.test(navigator.userAgent);
      const isFirefox = /firefox/i.test(navigator.userAgent);
      
      let instructions = 'Benachrichtigungen wurden blockiert. Bitte aktivieren Sie diese in den Browser-Einstellungen:\n\n';
      
      if (isSafari) {
        instructions += '🔧 Safari:\n' +
          '1. Klicken Sie auf das Schloss-Symbol in der Adressleiste\n' +
          '2. Wählen Sie "Einstellungen für diese Website"\n' +
          '3. Setzen Sie "Benachrichtigungen" auf "Erlauben"\n\n' +
          'Alternativ: Safari → Einstellungen → Websites → Benachrichtigungen';
      } else if (isChrome) {
        instructions += '🔧 Chrome:\n' +
          '1. Klicken Sie auf das Schloss-Symbol in der Adressleiste\n' +
          '2. Aktivieren Sie "Benachrichtigungen"\n' +
          '3. Laden Sie die Seite neu\n\n' +
          'Alternativ: Chrome → Einstellungen → Datenschutz → Website-Einstellungen → Benachrichtigungen';
      } else if (isFirefox) {
        instructions += '🔧 Firefox:\n' +
          '1. Klicken Sie auf das Schild-Symbol in der Adressleiste\n' +
          '2. Aktivieren Sie "Benachrichtigungen"\n' +
          '3. Laden Sie die Seite neu\n\n' +
          'Alternativ: Firefox → Einstellungen → Datenschutz → Berechtigungen → Benachrichtigungen';
      } else {
        instructions += '🔧 Browser-Einstellungen:\n' +
          '1. Suchen Sie nach "Benachrichtigungen" oder "Notifications"\n' +
          '2. Fügen Sie diese Domain zur Erlaubt-Liste hinzu\n' +
          '3. Laden Sie die Seite neu';
      }
      
      throw new Error(instructions);
    }
    
    // Nur bei Benutzeraktion anfordern
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      throw new Error('Berechtigung für Benachrichtigungen verweigert. Bitte versuchen Sie es erneut oder prüfen Sie die Browser-Einstellungen.');
    }
    
    return true;
  }
  
  async function subscribe(userType = 'frontend', topics = '') {
    const reg = await ensureServiceWorker();
    await requestNotificationPermission();
    
    const publicKey = window.PushiItPublicKey || '';
    if (!publicKey) {
      throw new Error('VAPID Public Key nicht konfiguriert (window.PushiItPublicKey)');
    }
    
    // VAPID Key validieren
    if (publicKey.length < 80) {
      throw new Error('VAPID Public Key scheint ungültig zu sein (zu kurz)');
    }
    
    try {
      const subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey)
      });
      
      const params = new URLSearchParams({
        'rex-api-call': 'pushi_it_subscribe',
        user_type: userType
      });
      
      if (topics) {
        params.append('topics', topics);
      }
      
      // User-ID für Backend-Subscriptions hinzufügen
      if (userType === 'backend' && window.PushiItUserId) {
        params.append('user_id', window.PushiItUserId);
      }
      
      const response = await fetch('/index.php?' + params.toString(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(subscription)
      });
      
      if (!response.ok) {
        throw new Error('Server-Fehler: ' + response.status);
      }
      
      const result = await response.json();
      if (!result.success) {
        throw new Error('Subscription fehlgeschlagen: ' + (result.error || 'Unbekannter Fehler'));
      }
      
      return result;
      
    } catch (error) {
      console.error('Subscription error:', error);
      throw error;
    }
  }
  
  async function unsubscribe() {
    try {
      const reg = await navigator.serviceWorker.getRegistration('/assets/addons/pushi_it/');
      const subscription = await reg?.pushManager.getSubscription();
      
      if (!subscription) {
        return { success: true, info: 'no_subscription' };
      }
      
      const response = await fetch('/index.php?rex-api-call=pushi_it_unsubscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ endpoint: subscription.endpoint })
      });
      
      if (!response.ok) {
        throw new Error('Server-Fehler: ' + response.status);
      }
      
      const result = await response.json();
      if (!result.success) {
        throw new Error('Unsubscribe fehlgeschlagen: ' + (result.error || 'Unbekannter Fehler'));
      }
      
      await subscription.unsubscribe();
      return result;
      
    } catch (error) {
      console.error('Unsubscribe error:', error);
      throw error;
    }
  }
  
  async function getSubscriptionStatus() {
    try {
      const reg = await navigator.serviceWorker.getRegistration('/assets/addons/pushi_it/');
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
  window.PushiIt = {
    subscribe: function(userType = 'frontend', topics = '') {
      return subscribe(userType, topics)
        .then(result => {
          console.log('Push-Benachrichtigungen aktiviert:', result);
          return result;
        })
        .catch(error => {
          console.error('Fehler beim Aktivieren:', error);
          throw error;
        });
    },
    
    unsubscribe: function() {
      return unsubscribe()
        .then(result => {
          console.log('Push-Benachrichtigungen deaktiviert:', result);
          return result;
        })
        .catch(error => {
          console.error('Fehler beim Deaktivieren:', error);
          throw error;
        });
    },
    
    getStatus: getSubscriptionStatus,
    
    // Einfache UI-Funktionen
    requestFrontend: function(topics = '') {
      const topicsToUse = topics || window.PushiItTopics || '';
      subscribe('frontend', topicsToUse)
        .then(() => alert('Frontend-Benachrichtigungen aktiviert!'))
        .catch(err => alert('Fehler: ' + err.message));
    },
    
    requestBackend: function(topics = '') {
      const topicsToUse = topics || window.PushiItTopics || '';
      subscribe('backend', topicsToUse)
        .then(() => alert('Backend-Benachrichtigungen aktiviert!'))
        .catch(err => alert('Fehler: ' + err.message));
    },
    
    disable: function() {
      unsubscribe()
        .then(() => alert('Benachrichtigungen deaktiviert.'))
        .catch(err => alert('Fehler: ' + err.message));
    }
  };
  
})();
