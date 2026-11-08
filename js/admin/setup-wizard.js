/**
 * Workeera setup wizard — front-end controller.
 *
 * Reads localised vars from `workeera_setup_wizard_data` and wires up:
 *   - Step navigation (next / back / skip).
 *   - "Run Setup" AJAX submission with logging into the done step.
 *   - "Skip" AJAX submission with redirect to the WP dashboard.
 */
(function ($) {
    'use strict';

    if (typeof window.workeera_setup_wizard_data === 'undefined') {
        return;
    }

    var data = window.workeera_setup_wizard_data;
    var strings = data.strings || {};

    /**
     * Move the visible step to the given index (1, 2 or 3).
     *
     * @param {number} stepIndex
     */
    function goToStep(stepIndex) {
        var $root = $('.workeera-sw');
        $root.find('.workeera-sw-step').removeClass('workeera-sw-step--active');
        $root.find('.workeera-sw-step[data-step="' + stepIndex + '"]').addClass('workeera-sw-step--active');

        // Update stepper indicators.
        $root.find('.workeera-sw-stepper-item').each(function () {
            var $item = $(this);
            var marker = parseInt($item.attr('data-step-marker'), 10);
            $item.removeClass('workeera-sw-stepper-item--active workeera-sw-stepper-item--done');
            if (marker < stepIndex) {
                $item.addClass('workeera-sw-stepper-item--done');
            } else if (marker === stepIndex) {
                $item.addClass('workeera-sw-stepper-item--active');
            }
        });

        // Scroll the card back to the top on step changes.
        var $page = $('.workeera-sw-page');
        if ($page.length) {
            $page.scrollTop(0);
        }
    }

    /**
     * Render the AJAX `log` array into the .workeera-sw-log container.
     *
     * @param {Array<string>} logLines
     */
    function renderLog(logLines) {
        var $log = $('.workeera-sw-log');
        $log.empty();
        if (!logLines || !logLines.length) {
            $log.append($('<span class="workeera-sw-log-line"></span>').text(strings.log_empty || ''));
            return;
        }
        for (var i = 0; i < logLines.length; i++) {
            $log.append($('<span class="workeera-sw-log-line"></span>').text(logLines[i]));
        }
    }

    /**
     * Display an inline error message inside the card.
     *
     * @param {string} message
     */
    function showError(message) {
        var $err = $('.workeera-sw-error');
        $err.text(message || strings.generic_error || '').show();
    }

    /**
     * Hide the inline error banner.
     */
    function clearError() {
        $('.workeera-sw-error').hide().text('');
    }

    /**
     * Collect the form values into a payload object for ajax_apply.
     *
     * @return {Object}
     */
    function collectPayload() {
        var $form = $('.workeera-sw-form');
        var payload = {
            action: 'workeera_setup_wizard_apply',
            workeera_nonce: data.nonce,
            settings_defaults: $form.find('input[name="settings_defaults"]').is(':checked') ? '1' : '',
            page_user_dashboard: $form.find('input[name="page_user_dashboard"]').is(':checked') ? '1' : '',
            page_jobs: $form.find('input[name="page_jobs"]').is(':checked') ? '1' : '',
            page_terms: $form.find('input[name="page_terms"]').is(':checked') ? '1' : '',
            'sample_data[]': []
        };

        $form.find('input[name="sample_data[]"]:checked').each(function () {
            payload['sample_data[]'].push($(this).val());
        });

        return payload;
    }

    /**
     * Submit the "Run Setup" AJAX call and advance to step 3.
     */
    function runSetup() {
        clearError();

        var $btn = $('.workeera-sw-run');
        var originalLabel = $btn.text();
        $btn.prop('disabled', true).addClass('is-loading').text(strings.running || originalLabel);

        $.ajax({
            url: data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: collectPayload()
        })
            .done(function (response) {
                if (!response || response.error === '1') {
                    showError((response && response.msg) ? response.msg : (strings.generic_error || ''));
                    return;
                }
                renderLog(response.log || []);
                goToStep(3);
            })
            .fail(function () {
                showError(strings.network_error || '');
            })
            .always(function () {
                $btn.prop('disabled', false).removeClass('is-loading').text(originalLabel);
            });
    }

    /**
     * Submit the skip AJAX call, then redirect to the wp-admin dashboard.
     */
    function skipWizard() {
        clearError();

        $.ajax({
            url: data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'workeera_setup_wizard_skip',
                workeera_nonce: data.nonce
            }
        })
            .always(function () {
                window.location.href = data.dashboard_url;
            });
    }

    // ───────────────────────────────────────────────────────────────────────
    // Event wiring
    // ───────────────────────────────────────────────────────────────────────
    $(document).on('click', '.workeera-sw-next', function (e) {
        e.preventDefault();
        var target = parseInt($(this).attr('data-target-step'), 10) || 2;
        clearError();
        goToStep(target);
    });

    $(document).on('click', '.workeera-sw-back', function (e) {
        e.preventDefault();
        var target = parseInt($(this).attr('data-target-step'), 10) || 1;
        clearError();
        goToStep(target);
    });

    $(document).on('click', '.workeera-sw-skip', function (e) {
        e.preventDefault();
        skipWizard();
    });

    $(document).on('click', '.workeera-sw-run', function (e) {
        e.preventDefault();
        runSetup();
    });

    // Expose minimal hook for debugging / external triggers.
    window.WorkeeraSetupWizard = {
        goToStep: goToStep
    };

    document.addEventListener( 'click', function ( e ) {
        var t = e.target;
        if ( ! t || ! t.classList || ! t.classList.contains( 'workeera-setup-wizard-notice-dismiss' ) ) {
            return;
        }
        e.preventDefault();
        var notice = t.closest( '.workeera-setup-wizard-notice' );
        var form = new FormData();
        form.append( 'action', 'workeera_setup_wizard_dismiss_notice' );
        form.append( 'workeera_nonce', workeera_setup_wizard_data.nonce );
        fetch( workeera_setup_wizard_data.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        } ).then( function () {
            if ( notice && notice.parentNode ) {
                notice.parentNode.removeChild( notice );
            }
        } ).catch( function () {
            if ( notice && notice.parentNode ) {
                notice.parentNode.removeChild( notice );
            }
        } );
    } );

}(jQuery));
