window.PushItLang = window.PushItLang || {};
window.PushItLang.de = {
  // Fehlermeldungen
  'error.browser_not_supported': 'Web Push wird von diesem Browser nicht unterstützt',
  'error.permission_denied': 'Berechtigung für Benachrichtigungen verweigert. Bitte versuchen Sie es erneut oder prüfen Sie die Browser-Einstellungen.',
  'error.vapid_not_configured': 'VAPID Public Key nicht konfiguriert (window.PushItPublicKey)',
  'error.vapid_invalid': 'VAPID Public Key scheint ungültig zu sein (zu kurz)',
  'error.server_error': 'Server-Fehler: {status}',
  'error.subscription_failed': 'Subscription fehlgeschlagen: {error}',
  'error.unsubscribe_failed': 'Unsubscribe fehlgeschlagen: {error}',
  'error.unknown_error': 'Unbekannter Fehler',
  'error.generic': 'Fehler: {message}',
  
  // SSL/Service Worker spezifische Fehler
  'error.serviceworker_ssl': 'Service Worker kann nicht geladen werden. Dies liegt wahrscheinlich an einem selbst-signierten SSL-Zertifikat.',
  'error.serviceworker_fetch': 'Service Worker Script kann nicht geladen werden (SSL-Zertifikat Problem)',
  'error.serviceworker_register': 'Service Worker Registrierung fehlgeschlagen',
  
  // Erfolgs-/Info-Meldungen
  'success.push_notifications_activated': 'Push-Benachrichtigungen aktiviert:',
  'success.push_notifications_deactivated': 'Push-Benachrichtigungen deaktiviert:',
  'success.frontend_notifications_activated': 'Frontend-Benachrichtigungen aktiviert!',
  'success.backend_notifications_activated': 'Backend-Benachrichtigungen aktiviert!',
  'success.notifications_disabled': 'Benachrichtigungen deaktiviert.',
  
  // Backend-spezifische Nachrichten
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
  'backend.settings_button': 'Einstellungen',
  
  // Browser-spezifische Anleitungen
  'instructions.notifications_blocked': 'Benachrichtigungen wurden blockiert. Bitte aktivieren Sie diese in den Browser-Einstellungen:\n\n',
  'instructions.safari': '🔧 Safari:\n1. Klicken Sie auf das Schloss-Symbol in der Adressleiste\n2. Wählen Sie "Einstellungen für diese Website"\n3. Setzen Sie "Benachrichtigungen" auf "Erlauben"\n\nAlternativ: Safari → Einstellungen → Websites → Benachrichtigungen',
  'instructions.chrome': '🔧 Chrome:\n1. Klicken Sie auf das Schloss-Symbol in der Adressleiste\n2. Aktivieren Sie "Benachrichtigungen"\n3. Laden Sie die Seite neu\n\nAlternativ: Chrome → Einstellungen → Datenschutz → Website-Einstellungen → Benachrichtigungen',
  'instructions.firefox': '🔧 Firefox:\n1. Klicken Sie auf das Schild-Symbol in der Adressleiste\n2. Aktivieren Sie "Benachrichtigungen"\n3. Laden Sie die Seite neu\n\nAlternativ: Firefox → Einstellungen → Datenschutz → Berechtigungen → Benachrichtigungen',
  'instructions.generic': '🔧 Browser-Einstellungen:\n1. Suchen Sie nach "Benachrichtigungen" oder "Notifications"\n2. Fügen Sie diese Domain zur Erlaubt-Liste hinzu\n3. Laden Sie die Seite neu',
  
  // SSL/Zertifikat-spezifische Anleitungen
  'instructions.ssl_certificate': '🔐 SSL-Zertifikat Problem:\n\nDas SSL-Zertifikat dieser Website wird nicht vertraut. Für Push-Benachrichtigungen müssen Sie:',
  'instructions.ssl_chrome': '🔧 Chrome (Selbst-signiertes Zertifikat):\n1. Klicken Sie auf "Erweitert" in der Sicherheitswarnung\n2. Klicken Sie auf "Weiter zu [Domain] (unsicher)"\n3. ODER: Installieren Sie das Zertifikat in den vertrauenswürdigen Stammzertifikaten\n4. Laden Sie die Seite neu und aktivieren Sie Benachrichtigungen',
  'instructions.ssl_firefox': '🔧 Firefox (Selbst-signiertes Zertifikat):\n1. Klicken Sie auf "Erweitert" in der Sicherheitswarnung\n2. Klicken Sie auf "Ausnahme hinzufügen..."\n3. Bestätigen Sie die Sicherheitsausnahme\n4. Laden Sie die Seite neu und aktivieren Sie Benachrichtigungen',
  'instructions.ssl_safari': '🔧 Safari (Selbst-signiertes Zertifikat):\n1. Gehen Sie zu Safari → Einstellungen → Erweitert\n2. Aktivieren Sie "Entwicklermenü in der Menüleiste anzeigen"\n3. Entwickeln → Zertifikatsfehler für diese Website ignorieren\n4. ODER: Installieren Sie das Zertifikat im Schlüsselbund\n5. Laden Sie die Seite neu',
  'instructions.ssl_general': '💡 Allgemeine Lösung:\n• Verwenden Sie ein gültiges SSL-Zertifikat (Let\'s Encrypt, etc.)\n• Oder testen Sie mit HTTP (nicht empfohlen für Produktion)\n• Service Worker funktionieren nur mit HTTPS oder localhost',
  
  // Console Log Messages
  'log.subscription_error': 'Subscription error:',
  'log.unsubscribe_error': 'Unsubscribe error:',
  'log.activate_error': 'Fehler beim Aktivieren:',
  'log.deactivate_error': 'Fehler beim Deaktivieren:',
  'log.backend_subscription_error': 'Backend subscription error:',
  'log.test_notification_error': 'Test notification error:',
  
  // Status-Meldungen
  'status_active': 'Benachrichtigungen sind aktiv',
  'status_inactive': 'Benachrichtigungen sind nicht aktiv',
  
  // Quick Notifications
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
  'system_info_message': 'Neue System-Information verfügbar.'
};
