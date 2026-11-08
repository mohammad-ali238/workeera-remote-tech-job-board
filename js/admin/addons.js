/**
 * Workeera Addons admin page behavior.
 *
 * Drives the install / activate / update buttons and the "Check for updates"
 * refresh action over admin-ajax. All strings and the nonce come from the
 * localized `workeera_addons_str` object — no inline JS on the page.
 */
(function ($) {
    'use strict';

    if (typeof workeera_addons_str === 'undefined') {
        return;
    }

    var $wrap = $('.workeera-addons-wrap');
    if (!$wrap.length) {
        return;
    }

    var $notice = $wrap.find('.workeera-addons-notice');
    var noticeTimer = null;

    /**
     * Show an admin notice above the grid (auto-hides after a few seconds).
     *
     * @param {string}  message Text to show.
     * @param {boolean} isError Whether to style as an error.
     */
    function showNotice(message, isError) {
        window.clearTimeout(noticeTimer);
        $notice
            .removeClass('notice-success notice-error')
            .addClass(isError ? 'notice-error' : 'notice-success')
            .find('p').text(message).end()
            .prop('hidden', false);

        noticeTimer = window.setTimeout(function () {
            $notice.prop('hidden', true);
        }, 5000);
    }

    /**
     * Re-render the action button (and stale installed-version note) of every
     * card from the `addons` state map returned by the server.
     *
     * @param {Object} addons Map of slug => addon state.
     */
    function updateCards(addons) {
        $.each(addons || {}, function (slug, addon) {
            var $card = $wrap.find('.workeera-addon-card[data-slug="' + slug + '"]');
            if (!$card.length) {
                return;
            }
            if (addon.button_html) {
                $card.find('.workeera-addon-action-area').html(addon.button_html);
            }
            if (addon.status !== 'update') {
                $card.find('.workeera-addon-installed-ver').remove();
            }
            $card.removeClass('workeera-addon-busy');
        });
    }

    /**
     * POST an addons AJAX action.
     *
     * @param {string}   action  Short action name (install|activate|refresh).
     * @param {Object}   data    Extra request data (e.g. slug).
     * @param {Function} always  Called after every response (success or error).
     */
    function request(action, data, always) {
        $.post(
            workeera_addons_str.ajax_url,
            $.extend({
                action: 'workeera_addons_' + action,
                nonce: workeera_addons_str.nonce
            }, data || {})
        ).done(function (response) {
            if (!response || response.error === '1') {
                showNotice((response && response.msg) ? response.msg : workeera_addons_str.error, true);
            } else {
                if (response.msg) {
                    showNotice(response.msg, false);
                }
                if (action === 'refresh') {
                    window.location.reload();
                    return;
                }
                updateCards(response.addons);
            }
            if (typeof always === 'function') {
                always();
            }
        }).fail(function () {
            showNotice(workeera_addons_str.error, true);
            if (typeof always === 'function') {
                always();
            }
        });
    }

    // Install / Update / Activate buttons (delegated — buttons get re-rendered).
    $wrap.on('click', '.workeera-addon-action', function () {
        var $btn = $(this);
        var $card = $btn.closest('.workeera-addon-card');
        var action = $btn.data('action');
        var slug = $btn.data('slug');

        if (!action || !slug || $card.hasClass('workeera-addon-busy')) {
            return;
        }

        var busyLabel = workeera_addons_str.installing;
        if (action === 'activate') {
            busyLabel = workeera_addons_str.activating;
        } else if ($btn.hasClass('workeera-addon-update-btn')) {
            busyLabel = workeera_addons_str.updating;
        }

        $btn.prop('disabled', true).text(busyLabel);
        $card.addClass('workeera-addon-busy');

        request(action, { slug: slug }, function () {
            $card.removeClass('workeera-addon-busy');
        });
    });

    // "Check for updates" — clears the 2-hour cache and re-fetches the catalog.
    $wrap.on('click', '.workeera-addons-refresh', function () {
        var $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        $btn.prop('disabled', true).text(workeera_addons_str.refreshing);

        request('refresh', {}, function () {
            $btn.prop('disabled', false).text(workeera_addons_str.refresh);
        });
    });
})(jQuery);
