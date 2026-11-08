/**
 * Workeera — Free AI Credits page.
 *
 * Handles claiming free credits: send a verification email, then poll Hiremium
 * (every 10s, invisibly) until the address is verified and the credits are
 * saved locally — at which point the page reloads to show the active state.
 * A manual "check now" button does the same on demand. All strings/config come
 * from the localized workeera_fac_str object.
 */
(function ($) {
	'use strict';

	var cfg = window.workeera_fac_str || {};
	var i18n = cfg.i18n || {};
	var pollTimer = null;
	var uuid = cfg.uuid || '';

	function notice($card, message, type) {
		var $n = $card.find('.workeera-fac-notice');
		$n.removeClass('is-error is-info is-success')
			.addClass(type === 'error' ? 'is-error' : (type === 'success' ? 'is-success' : 'is-info'))
			.text(message)
			.show();
	}

	function startPolling($card) {
		if (pollTimer || !uuid) {
			return;
		}
		pollTimer = window.setInterval(function () {
			checkStatus($card, false);
		}, cfg.poll_interval || 10000);
	}

	function stopPolling() {
		if (pollTimer) {
			window.clearInterval(pollTimer);
			pollTimer = null;
		}
	}

	function checkStatus($card, manual) {
		if (!uuid) {
			return;
		}
		var $btn = $card.find('.workeera-fac-confirm-btn');
		if (manual) {
			$btn.prop('disabled', true).text(i18n.checking || 'Checking…');
		}

		$.post(cfg.ajax_url, {
			action: 'workeera_fac_sync',
			nonce: cfg.nonce,
			uuid: uuid
		}).done(function (res) {
			if (res && res.verified) {
				stopPolling();
				window.location.reload();
				return;
			}
			if (manual) {
				notice($card, i18n.not_yet || 'Not verified yet.', 'info');
			}
		}).fail(function () {
			if (manual) {
				notice($card, i18n.generic || 'Something went wrong.', 'error');
			}
		}).always(function () {
			if (manual) {
				$btn.prop('disabled', false).text($btn.data('label') || 'Check now');
			}
		});
	}

	$(function () {
		var $card = $('.workeera-fac-claim');
		if (!$card.length) {
			return;
		}

		$card.find('.workeera-fac-confirm-btn').each(function () {
			$(this).data('label', $(this).text());
		});

		// Send verification email.
		$card.on('click', '.workeera-fac-send-btn', function () {
			var $btn = $(this);
			var email = $.trim($card.find('.workeera-fac-email').val());
			if (!email) {
				notice($card, i18n.generic || 'Please enter an email.', 'error');
				return;
			}
			$btn.prop('disabled', true).text(i18n.sending || 'Sending…');

			$.post(cfg.ajax_url, {
				action: 'workeera_fac_send',
				nonce: cfg.nonce,
				email: email
			}).done(function (res) {
				if (!res || res.error === 'security_failed' || res.error === 'forbidden') {
					notice($card, i18n.generic || 'Something went wrong.', 'error');
					return;
				}
				if (res.error && res.error !== '0') {
					notice($card, res.msg || i18n.generic, 'error');
					$btn.prop('disabled', false).text($btn.data('label') || 'Send verification link');
					return;
				}

				if (res.status === 'exists' || res.status === 'limit_reached') {
					notice($card, res.msg || 'This email has already claimed a free trial.', 'info');
					$btn.prop('disabled', false).text($btn.data('label') || 'Send verification link');
					return;
				}

				// Sent / pending — switch to the waiting state and poll.
				uuid = res.uuid || uuid;
				$card.find('.workeera-fac-form').hide();
				$card.find('.workeera-fac-pending').show();
				if (res.msg) {
					notice($card, res.msg, 'success');
				}
				startPolling($card);
			}).fail(function () {
				notice($card, i18n.generic || 'Something went wrong.', 'error');
				$btn.prop('disabled', false).text($btn.data('label') || 'Send verification link');
			});
		});

		// Store the original label for the send button too.
		$card.find('.workeera-fac-send-btn').data('label', $card.find('.workeera-fac-send-btn').text());

		// Manual re-check.
		$card.on('click', '.workeera-fac-confirm-btn', function () {
			checkStatus($card, true);
		});

		// If a claim is already in flight, resume polling automatically.
		if ($card.data('pending') === 1 || $card.data('pending') === '1') {
			startPolling($card);
		}
	});

})(jQuery);
