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
  
  // Browser-spezifische Anleitungen
  'instructions.notifications_blocked': 'Benachrichtigungen wurden blockiert. Bitte aktivieren Sie diese in den Browser-Einstellungen:\n\n',
  'instructions.safari': '🔧 Safari:\n1. Klicken Sie auf das Schloss-Symbol in der Adressleiste\n2. Wählen Sie "Einstellungen für diese Website"\n3. Setzen Sie "Benachrichtigungen" auf "Erlauben"\n\nAlternativ: Safari → Einstellungen → Websites → Benachrichtigungen',
  'instructions.chrome': '🔧 Chrome:\n1. Klicken Sie auf das Schloss-Symbol in der Adressleiste\n2. Aktivieren Sie "Benachrichtigungen"\n3. Laden Sie die Seite neu\n\nAlternativ: Chrome → Einstellungen → Datenschutz → Website-Einstellungen → Benachrichtigungen',
  'instructions.firefox': '🔧 Firefox:\n1. Klicken Sie auf das Schild-Symbol in der Adressleiste\n2. Aktivieren Sie "Benachrichtigungen"\n3. Laden Sie die Seite neu\n\nAlternativ: Firefox → Einstellungen → Datenschutz → Berechtigungen → Benachrichtigungen',
  'instructions.generic': '🔧 Browser-Einstellungen:\n1. Suchen Sie nach "Benachrichtigungen" oder "Notifications"\n2. Fügen Sie diese Domain zur Erlaubt-Liste hinzu\n3. Laden Sie die Seite neu',
  
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
