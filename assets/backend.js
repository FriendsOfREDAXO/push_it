// Backend-spezifisches JavaScript für Push It
(function() {
  'use strict';
  
  // Warten bis DOM und REDAXO-spezifische Objekte geladen sind
  document.addEventListener('DOMContentLoaded', function() {
    
    // Backend-Benachrichtigungen automatisch aktivieren wenn konfiguriert
    if (window.rex && window.rex.push_it_backend_enabled && window.rex.push_it_public_key) {
      // Public Key setzen (sowohl für PushIt als auch PushItPublicKey)
      window.PushItPublicKey = window.rex.push_it_public_key;
      
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
      // Prüfen ob bereits eine Subscription existiert
      const status = await window.PushIt.getStatus();
      
      // Prüfen ob der Benutzer bereits geantwortet hat (localStorage)
      const hasAnswered = localStorage.getItem('push_it_backend_asked');
      
      if (!status.isSubscribed && !hasAnswered) {
        // Zeige eine Info-Nachricht anstatt automatisch zu fragen
        showBackendNotificationPrompt();
      }
    } catch (error) {
      console.warn('Backend Subscription Check failed:', error);
    }
  }
  
  function showBackendNotificationPrompt() {
    // Info-Banner in das Message-Container einfügen
    const messageContainer = document.getElementById('rex-message-container');
    if (messageContainer) {
      const promptHtml = `
        <div class="alert alert-info alert-dismissible fade show" role="alert">
          <h4><i class="rex-icon fa-bell"></i> Backend-Benachrichtigungen</h4>
          <p>Möchten Sie Push-Benachrichtigungen für Systemereignisse aktivieren?</p>
          <button type="button" class="btn btn-success btn-sm" onclick="activateBackendNotifications()">
            <i class="rex-icon fa-bell"></i> Aktivieren
          </button>
          <button type="button" class="btn btn-default btn-sm" onclick="declineBackendNotifications()">
            Nein, danke
          </button>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close" onclick="declineBackendNotifications()">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      `;
      
      messageContainer.innerHTML = promptHtml;
    }
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
    // Versuche Notification-Button in Backend-Header zu integrieren
    const headerActions = document.querySelector('#rex-js-nav-top .navbar-nav');
    if (headerActions) {
      const listItem = document.createElement('li');
      listItem.className = 'nav-item dropdown';
      
      listItem.innerHTML = `
        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" title="Push-Benachrichtigungen">
          <i class="rex-icon fa-bell"></i>
          <span class="sr-only">Push-Benachrichtigungen</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-right">
          <li><a href="#" onclick="PushIt.requestBackend('system,admin'); return false;" class="dropdown-item">
            <i class="rex-icon fa-bell-o"></i> Backend aktivieren
          </a></li>
          <li><a href="#" onclick="PushIt.disable(); return false;" class="dropdown-item">
            <i class="rex-icon fa-bell-slash"></i> Deaktivieren
          </a></li>
          <li role="separator" class="dropdown-divider"></li>
          <li><a href="index.php?page=push_it" class="dropdown-item">
            <i class="rex-icon fa-cog"></i> Einstellungen
          </a></li>
        </ul>
      `;
      
      headerActions.appendChild(listItem);
    }
  }
  
  function showBackendMessage(message, type = 'info') {
    // REDAXO-spezifische Nachrichtenanzeige
    const messageContainer = document.getElementById('rex-message-container');
    if (messageContainer) {
      const alertClass = type === 'success' ? 'alert-success' : 
                        type === 'error' ? 'alert-danger' : 'alert-info';
      
      const messageHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
          ${message}
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      `;
      
      messageContainer.innerHTML = messageHtml;
      
      // Auto-hide nach 5 Sekunden
      setTimeout(() => {
        const alert = messageContainer.querySelector('.alert');
        if (alert) {
          alert.classList.remove('show');
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
    alert('Backend-Subscription Status wurde zurückgesetzt.');
    // Banner erneut anzeigen
    showBackendNotificationPrompt();
  };
  
})();
