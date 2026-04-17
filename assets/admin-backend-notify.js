/* Push It — Backend Notification Panel Handlers */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initSubscriptionPanel();
        initQuickNotificationPanel();
    });

    /* ------------------------------------------------------------------ */
    /* Subscription Panel                                                   */
    /* ------------------------------------------------------------------ */
    function initSubscriptionPanel() {
        var subscribeBtn = document.getElementById('pushit-subscribe-backend');
        var statusBtn    = document.getElementById('pushit-status-check');
        var disableBtn   = document.getElementById('pushit-disable');
        var resetBtn     = document.getElementById('pushit-reset');

        if (subscribeBtn) {
            subscribeBtn.addEventListener('click', function () {
                var topics = subscribeBtn.getAttribute('data-topics') || 'editorial';
                PushIt.requestBackend(topics);
            });
        }

        if (statusBtn) {
            statusBtn.addEventListener('click', function () {
                PushIt.getStatus().then(function (s) {
                    alert(s.isSubscribed
                        ? PushIt.i18n.get('status_active')
                        : PushIt.i18n.get('status_inactive')
                    );
                });
            });
        }

        if (disableBtn) {
            disableBtn.addEventListener('click', function () {
                PushIt.disable();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                PushItReset();
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Quick Notification Panel                                             */
    /* ------------------------------------------------------------------ */
    function initQuickNotificationPanel() {
        var panel = document.getElementById('pushit-quick-notifications');
        if (!panel) return;

        var sendUrl   = panel.getAttribute('data-send-url') || '';
        var systemUrl = panel.getAttribute('data-system-url') || '';

        function sendQuickNotification(type, title, body) {
            var confirmMsg = PushIt.i18n.get('quick_notification_confirm_prefix') + ' '
                + type.toUpperCase() + '-'
                + PushIt.i18n.get('quick_notification_confirm_suffix') + '?\n\n'
                + title + '\n' + body;

            if (!window.confirm(confirmMsg)) return;

            var urlParams = new URLSearchParams({
                title:     title,
                body:      body,
                url:       systemUrl,
                user_type: 'backend',
                topics:    'system,admin,' + type,
                send:      '1',
            });

            fetch(sendUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    urlParams.toString(),
            }).then(function (response) {
                if (response.ok) {
                    alert(PushIt.i18n.get('notification_sent_success'));
                } else {
                    alert(PushIt.i18n.get('notification_sent_error'));
                }
            }).catch(function (error) {
                alert(PushIt.i18n.get('network_error') + ': ' + error.message);
            });
        }

        var criticalBtn = document.getElementById('quick-critical');
        var warningBtn  = document.getElementById('quick-warning');
        var infoBtn     = document.getElementById('quick-info');

        if (criticalBtn) {
            criticalBtn.addEventListener('click', function () {
                sendQuickNotification(
                    'critical',
                    PushIt.i18n.get('critical_error_title'),
                    PushIt.i18n.get('critical_error_message')
                );
            });
        }

        if (warningBtn) {
            warningBtn.addEventListener('click', function () {
                sendQuickNotification(
                    'warning',
                    PushIt.i18n.get('system_warning_title'),
                    PushIt.i18n.get('system_warning_message')
                );
            });
        }

        if (infoBtn) {
            infoBtn.addEventListener('click', function () {
                sendQuickNotification(
                    'info',
                    PushIt.i18n.get('system_info_title'),
                    PushIt.i18n.get('system_info_message')
                );
            });
        }
    }
}());
