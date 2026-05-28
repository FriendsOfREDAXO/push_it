/* Push It — Backend Notification Panel Handlers */
(function () {
    'use strict';

    function init() {
        initSubscriptionPanel();
        initQuickNotificationPanel();
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('rex:ready', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    /* ------------------------------------------------------------------ */
    /* Subscription Panel                                                   */
    /* ------------------------------------------------------------------ */
    function initSubscriptionPanel() {
        var subscribeBtn = document.getElementById('pushit-subscribe-backend');
        var statusBtn    = document.getElementById('pushit-status-check');
        var disableBtn   = document.getElementById('pushit-disable');
        var resetBtn     = document.getElementById('pushit-reset');

        function showPanelMessage(type, text) {
            var panel = (statusBtn && statusBtn.closest('.well')) || document.querySelector('.well');
            if (!panel) {
                alert(text);
                return;
            }

            var host = panel.querySelector('.pushit-status-feedback');
            if (!host) {
                host = document.createElement('div');
                host.className = 'pushit-status-feedback';
                host.style.marginTop = '12px';
                panel.appendChild(host);
            }

            var cssClass = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
            host.innerHTML = '<div class="alert ' + cssClass + '" style="margin-bottom:0;">' + text + '</div>';
        }

        if (subscribeBtn) {
            if (subscribeBtn.dataset.pushitBound !== '1') {
                subscribeBtn.dataset.pushitBound = '1';
                subscribeBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    var topics = subscribeBtn.getAttribute('data-topics') || 'editorial';
                    PushIt.requestBackend(topics);
                });
            }
        }

        if (statusBtn) {
            if (statusBtn.dataset.pushitBound !== '1') {
                statusBtn.dataset.pushitBound = '1';
                statusBtn.addEventListener('click', function (event) {
                    event.preventDefault();

                    if (typeof PushIt === 'undefined' || typeof PushIt.getStatus !== 'function') {
                        showPanelMessage('error', 'PushIt ist nicht initialisiert. Bitte Seite neu laden.');
                        return;
                    }

                    PushIt.getStatus().then(function (s) {
                        var i18n = (PushIt.i18n && typeof PushIt.i18n.get === 'function')
                            ? PushIt.i18n
                            : { get: function (key) { return key; } };

                        var message = s.isSubscribed ? i18n.get('status_active') : i18n.get('status_inactive');
                        showPanelMessage(s.isSubscribed ? 'success' : 'warning', message);
                    }).catch(function (error) {
                        showPanelMessage('error', 'Status konnte nicht geprüft werden: ' + (error && error.message ? error.message : 'unbekannter Fehler'));
                    });
                });
            }
        }

        if (disableBtn) {
            if (disableBtn.dataset.pushitBound !== '1') {
                disableBtn.dataset.pushitBound = '1';
                disableBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    PushIt.disable();
                });
            }
        }

        if (resetBtn) {
            if (resetBtn.dataset.pushitBound !== '1') {
                resetBtn.dataset.pushitBound = '1';
                resetBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    PushItReset();
                });
            }
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
            if (criticalBtn.dataset.pushitBound !== '1') {
                criticalBtn.dataset.pushitBound = '1';
                criticalBtn.addEventListener('click', function () {
                    sendQuickNotification(
                        'critical',
                        PushIt.i18n.get('critical_error_title'),
                        PushIt.i18n.get('critical_error_message')
                    );
                });
            }
        }

        if (warningBtn) {
            if (warningBtn.dataset.pushitBound !== '1') {
                warningBtn.dataset.pushitBound = '1';
                warningBtn.addEventListener('click', function () {
                    sendQuickNotification(
                        'warning',
                        PushIt.i18n.get('system_warning_title'),
                        PushIt.i18n.get('system_warning_message')
                    );
                });
            }
        }

        if (infoBtn) {
            if (infoBtn.dataset.pushitBound !== '1') {
                infoBtn.dataset.pushitBound = '1';
                infoBtn.addEventListener('click', function () {
                    sendQuickNotification(
                        'info',
                        PushIt.i18n.get('system_info_title'),
                        PushIt.i18n.get('system_info_message')
                    );
                });
            }
        }
    }
}());
