window.PushItLang = window.PushItLang || {};
window.PushItLang.en = {
  // Error messages
  'error.browser_not_supported': 'Web Push is not supported by this browser',
  'error.permission_denied': 'Permission for notifications denied. Please try again or check browser settings.',
  'error.vapid_not_configured': 'VAPID Public Key not configured (window.PushItPublicKey)',
  'error.vapid_invalid': 'VAPID Public Key seems to be invalid (too short)',
  'error.server_error': 'Server error: {status}',
  'error.subscription_failed': 'Subscription failed: {error}',
  'error.unsubscribe_failed': 'Unsubscribe failed: {error}',
  'error.unknown_error': 'Unknown error',
  'error.generic': 'Error: {message}',
  
  // Success/Info messages
  'success.push_notifications_activated': 'Push notifications activated:',
  'success.push_notifications_deactivated': 'Push notifications deactivated:',
  'success.frontend_notifications_activated': 'Frontend notifications activated!',
  'success.backend_notifications_activated': 'Backend notifications activated!',
  'success.notifications_disabled': 'Notifications disabled.',
  
  // Backend-specific messages
  'backend.notifications_activated': 'Backend notifications have been activated!',
  'backend.activation_error': 'Error activating notifications: {message}',
  'backend.test_sent': 'Test notification has been sent!',
  'backend.test_error': 'Error sending test notification!',
  'backend.status_reset': 'Backend subscription status has been reset.',
  
  // Browser-specific instructions
  'instructions.notifications_blocked': 'Notifications have been blocked. Please enable them in your browser settings:\n\n',
  'instructions.safari': '🔧 Safari:\n1. Click the lock icon in the address bar\n2. Select "Settings for this website"\n3. Set "Notifications" to "Allow"\n\nAlternatively: Safari → Preferences → Websites → Notifications',
  'instructions.chrome': '🔧 Chrome:\n1. Click the lock icon in the address bar\n2. Enable "Notifications"\n3. Reload the page\n\nAlternatively: Chrome → Settings → Privacy → Site Settings → Notifications',
  'instructions.firefox': '🔧 Firefox:\n1. Click the shield icon in the address bar\n2. Enable "Notifications"\n3. Reload the page\n\nAlternatively: Firefox → Settings → Privacy → Permissions → Notifications',
  'instructions.generic': '🔧 Browser Settings:\n1. Search for "Notifications"\n2. Add this domain to the allowed list\n3. Reload the page',
  
  // Console Log Messages
  'log.subscription_error': 'Subscription error:',
  'log.unsubscribe_error': 'Unsubscribe error:',
  'log.activate_error': 'Error activating:',
  'log.deactivate_error': 'Error deactivating:',
  'log.backend_subscription_error': 'Backend subscription error:',
  'log.test_notification_error': 'Test notification error:',
  
  // Status messages
  'status_active': 'Notifications are active',
  'status_inactive': 'Notifications are not active',
  
  // Quick Notifications
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
  'system_info_message': 'New system information available.'
};
