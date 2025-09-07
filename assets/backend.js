// Backend-spezifisches JavaScript für Push It
(function() {
  'use strict';
  
  // Warten bis DOM und REDAXO-spezifische Objekte geladen sind
  document.addEventListener('DOMContentLoaded', function() {
    
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
        <h4><i class="rex-icon fa-bell"></i> Backend-Benachrichtigungen</h4>
        <p>Möchten Sie Push-Benachrichtigungen für Systemereignisse aktivieren?</p>
        <div class="btn-group" role="group" style="margin-top: 10px;">
          <button type="button" class="btn btn-success btn-sm" onclick="activateBackendNotifications()">
            <i class="rex-icon fa-bell"></i> Aktivieren
          </button>
          <button type="button" class="btn btn-default btn-sm" onclick="declineBackendNotifications()">
            Nein, danke
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
      showBackendMessage('Backend-Benachrichtigungen wurden aktiviert!', 'success');
    } catch (error) {
      console.error('Backend subscription error:', error);
      showBackendMessage('Fehler beim Aktivieren der Benachrichtigungen: ' + error.message, 'error');
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
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="Push-Benachrichtigungen">
          <i class="rex-icon fa-bell"></i>
          <span class="sr-only">Push-Benachrichtigungen</span>
          <span class="caret"></span>
        </a>
        <ul class="dropdown-menu">
          <li><a href="#" onclick="PushIt.requestBackend('system,admin'); return false;">
            <i class="rex-icon fa-bell-o"></i> Backend aktivieren
          </a></li>
          <li><a href="#" onclick="PushIt.disable(); return false;">
            <i class="rex-icon fa-bell-slash"></i> Deaktivieren
          </a></li>
          <li class="divider"></li>
          <li><a href="index.php?page=push_it">
            <i class="rex-icon fa-cog"></i> Einstellungen
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
      
      const messageHtml = `
        <div class="alert ${alertClass} alert-dismissible" role="alert">
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          ${message}
        </div>
      `;
      
      messageContainer.innerHTML = messageHtml;
      
      // Auto-hide nach 5 Sekunden
      setTimeout(() => {
        const alert = messageContainer.querySelector('.alert');
        if (alert) {
          // Bootstrap 3 fade out
          alert.classList.add('fade');
          setTimeout(() => alert.remove(), 150);
        }
      }, 5000);
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
          showBackendMessage('Test-Benachrichtigung wurde gesendet!', 'success');
        } else {
          showBackendMessage('Fehler beim Senden der Test-Benachrichtigung!', 'error');
        }
      }).catch(error => {
        console.error('Test notification error:', error);
        showBackendMessage('Fehler beim Senden der Test-Benachrichtigung!', 'error');
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
    alert('Backend-Subscription Status wurde zurückgesetzt.');
    // Banner erneut anzeigen
    setTimeout(() => {
      showBackendNotificationPrompt();
    }, 500);
  };
  
  // Test-Funktion um Banner zu forcieren
  window.PushItTest = function() {
    console.log('PushIt: Forcing notification prompt for testing');
    showBackendNotificationPrompt();
  };
  
  // Debug-Funktion um aktuellen Status zu prüfen
  window.PushItDebug = function() {
    console.log('PushIt Debug Info:');
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
