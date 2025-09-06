self.addEventListener('push', event => {
  let data = {};
  try { 
    data = event.data ? event.data.json() : {}; 
  } catch(e) {
    console.error('Push data parsing failed:', e);
  }
  
  const title = data.title || 'Benachrichtigung';
  const options = {
    body: data.body || '',
    icon: data.icon || '/assets/addons/pushi_it/icon.png',
    badge: '/assets/addons/pushi_it/badge.png',
    data: { 
      url: data.url || '/',
      timestamp: data.timestamp || Date.now()
    },
    requireInteraction: true,
    actions: [
      {
        action: 'open',
        title: 'Öffnen',
        icon: '/assets/addons/pushi_it/action-open.png'
      },
      {
        action: 'dismiss',
        title: 'Schließen',
        icon: '/assets/addons/pushi_it/action-close.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'dismiss') {
    return; // Einfach schließen
  }
  
  const url = (event.notification && event.notification.data && event.notification.data.url) || '/';
  
  event.waitUntil(
    self.clients.matchAll({ 
      type: 'window', 
      includeUncontrolled: true 
    }).then(list => {
      // Versuche vorhandenen Tab zu fokussieren
      for (let client of list) {
        if (client.url.includes(url.split('?')[0]) && 'focus' in client) {
          return client.focus();
        }
      }
      // Andernfalls neuen Tab öffnen
      if (self.clients.openWindow) {
        return self.clients.openWindow(url);
      }
    })
  );
});

self.addEventListener('notificationclose', event => {
  // Optional: Analytics oder Tracking
  console.log('Notification closed:', event.notification.data);
});

// Background Sync für offline Notifications
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

function doBackgroundSync() {
  // Implementierung für Background Sync falls benötigt
  return Promise.resolve();
}
