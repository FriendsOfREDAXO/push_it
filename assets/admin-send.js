(() => {
	'use strict';

	let initialized = false;

	function updateBodyCounter() {
		const body = document.getElementById('body');
		const counter = document.getElementById('pushit-body-counter');
		if (!body || !counter) {
			return;
		}

		counter.textContent = String(body.value.length);
	}

	function bindTemplateButtons() {
		const buttons = document.querySelectorAll('.pushit-template-btn');
		if (buttons.length === 0) {
			return;
		}

		const title = document.getElementById('title');
		const body = document.getElementById('body');
		const topics = document.getElementById('topics');

		buttons.forEach((button) => {
			button.addEventListener('click', () => {
				if (title) {
					title.value = button.getAttribute('data-title') || '';
				}
				if (body) {
					body.value = button.getAttribute('data-body') || '';
					updateBodyCounter();
				}
				if (topics) {
					topics.value = button.getAttribute('data-topics') || '';
				}
			});
		});
	}

	function bindSendConfirmation() {
		const form = document.getElementById('pushit-send-form');
		if (!form) {
			return;
		}

		form.addEventListener('submit', (event) => {
			const userTypeField = document.getElementById('user_type');
			const testModeField = form.querySelector('input[name="test_mode"]');
			const titleField = document.getElementById('title');

			if (!titleField) {
				return;
			}

			const target = userTypeField ? userTypeField.value : 'frontend';
			const isTestMode = Boolean(testModeField && testModeField.checked);
			const mode = isTestMode ? 'Testversand (nur an mich)' : 'Echtversand';

			const message =
				'Nachricht jetzt senden?\n\n' +
				'Titel: ' + titleField.value + '\n' +
				'Empfängergruppe: ' + target + '\n' +
				'Modus: ' + mode;

			if (!window.confirm(message)) {
				event.preventDefault();
			}
		});
	}

	function initSendUx() {
		if (initialized) {
			return;
		}

		if (!document.getElementById('pushit-send-form')) {
			return;
		}

		initialized = true;
		bindTemplateButtons();
		bindSendConfirmation();

		const body = document.getElementById('body');
		if (body) {
			body.addEventListener('input', updateBodyCounter);
			updateBodyCounter();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSendUx);
	} else {
		initSendUx();
	}

	document.addEventListener('rex:ready', initSendUx);
	if (typeof window.jQuery !== 'undefined') {
		window.jQuery(document).on('rex:ready', initSendUx);
	}
})();
