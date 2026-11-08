/**
 * Dashboard scripts — collapsible form sections, photo upload/remove,
 * job description character counter, and dashboard top menu interactions.
 */
(function ($) {
    'use strict';

    $('.workeera-usrform-section-openclose').on('click', '.workeera-usrform-section-header', function(e) {
        e.preventDefault();
        $(this).parent('.workeera-usrform-section-openclose').toggleClass('workeera-usrform-is-open');
    });

    $(document).ready(function() {
        $.workeera_job_desc_charcount_init();
    });

    $('.workeera-usrform-wrapper').on('click', '.workeera-usrform-photo-upload .workeera-photo-svgicnlbl-con', function () {
        // The "My Companies" logo box has its own handlers (uploads on save).
        if ($(this).closest('.workeera-usrcmp-logo-box').length) {
            return;
        }
        $(this).closest('.workeera-usrform-photo-upload').find('input[type="file"]').trigger('click');
    });

    // Logo upload call
    $('.workeera-usrform-wrapper').on('change', '.workeera-usrform-photo-upload input[type="file"]', function () {
        if ($(this).closest('.workeera-usrcmp-logo-box').length) {
            return;
        }
        var this_btn = $(this);
        var file = this.files;
        $.workeera_picture_upload(file[0], this_btn);
    });

    /**
     * Validate and upload a profile/logo/cover picture via AJAX.
     *
     * @param {File}   file     Selected image file.
     * @param {jQuery} this_btn Trigger element that initiated the upload.
     */
    $.workeera_picture_upload = function(file, this_btn) {
        if (this_btn.hasClass('ajax-processing')) {
            return;
        }
        var main_pic_holder = this_btn.closest('.workeera-usrform-photo-upload');
        var icn_placeholder = main_pic_holder.find('.workeera-photo-svgicnlbl-con');
        var img_holder = main_pic_holder.find('.workeera-user-photo-imgholdr');
        var img_act_for = main_pic_holder.attr('data-img-for');

        const file_size = file.size;
        if (file_size > 0) {
            const allow_file_size = parseInt(workeera_dashboard_str.allow_pic_size) * 1024 * 1024;
            if (file_size > allow_file_size) {
                $.workeera_submit_msg_alert({
                    msg: workeera_dashboard_str.pic_size_err,
                    class: 'workeera-alert-danger'
                });
                return;
            }
            const allow_file_types = JSON.parse(workeera_dashboard_str.allow_pic_mimes);
            if (Object.values(allow_file_types).includes(file.type) === false) {
                $.workeera_submit_msg_alert({
                    msg: workeera_dashboard_str.pic_mimetype_err,
                    class: 'workeera-alert-danger'
                });
                return;
            }

            //
            if (!img_holder.find('.workeera-user-photo-imgbox').length) {
                return;
            }

            var form_data = new FormData();
            form_data.append('pic_file', file);
            if (typeof main_pic_holder.attr('data-job_id') !== 'undefined') {
                form_data.append('job_id', main_pic_holder.attr('data-job_id'));
            }
            form_data.append('workeera_nonce', workeera_func_str.nonce);

            var action_call = 'workeera_upload_' + img_act_for;
            form_data.append('action', action_call);

            if (main_pic_holder.find('.workeera-loder-con').length == 0) {
                main_pic_holder.append($.workeera_delay_loader_html());
            }

            this_btn.addClass('ajax-processing');
            $.ajax({
                url: workeera_func_str.ajax_url,
                method: "POST",
                data: form_data,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function (response) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                    if (nonce_err == 'failed') {
                        return false;
                    }

                    // after success callback for other actions
                    $.workeera_ajax_success_callback({
                        req_data: form_data,
                        result: response,
                        parent_con: this_btn
                    });
                    if (response.error == '0') {
                        main_pic_holder.addClass('workeera-with-filled-photo');
                        icn_placeholder.addClass('workeera-hide');
                        img_holder.removeClass('workeera-hide');
                        img_holder.find('.workeera-user-photo-imgbox').removeAttr('style');
                        img_holder.find('.workeera-user-photo-imgbox').css({'background-image':'url(\'' + response.img_url + '\')'});
                    } else {
                        $.workeera_submit_msg_alert({
                            msg: response.msg,
                            class: 'workeera-alert-danger'
                        });
                    }
                    $.workeera_remove_delay_loader({
                        target: main_pic_holder
                    });
                    this_btn.removeClass('ajax-processing');
                },
                error: function() {
                    $.workeera_remove_delay_loader({
                        target: main_pic_holder
                    });
                    this_btn.removeClass('ajax-processing');
                }
            });
        }
    }

    // Remove uploaded photo
    $('.workeera-usrform-wrapper').on('click', '.workeera-usrform-photo-upload .workeera-user-photo-remve', function () {
        var this_btn = $(this);
        // The "My Companies" logo box has its own remove handler (clears on save).
        if (this_btn.closest('.workeera-usrcmp-logo-box').length) {
            return;
        }
        if (this_btn.hasClass('ajax-processing')) {
            return;
        }
        var main_pic_holder = this_btn.closest('.workeera-usrform-photo-upload');
        var icn_placeholder = main_pic_holder.find('.workeera-photo-svgicnlbl-con');
        var img_holder = main_pic_holder.find('.workeera-user-photo-imgholdr');
        var img_act_for = main_pic_holder.attr('data-img-for');
        var req_data = {};
        if (typeof main_pic_holder.attr('data-job_id') !== 'undefined') {
            req_data.job_id = main_pic_holder.attr('data-job_id');
        }
        req_data.workeera_nonce = workeera_func_str.nonce;

        var action_call = 'workeera_remve_uploaded_' + img_act_for;
        req_data.action = action_call;

        if (main_pic_holder.find('.workeera-loder-con').length == 0) {
            main_pic_holder.append($.workeera_delay_loader_html());
        }

        this_btn.addClass('ajax-processing');
        $.ajax({
            url: workeera_func_str.ajax_url,
            method: "POST",
            data: req_data,
            dataType: "json",
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err == 'failed') {
                    return false;
                }

                if (response.error == '0') {
                    main_pic_holder.removeClass('workeera-with-filled-photo');
                    icn_placeholder.removeClass('workeera-hide');
                    img_holder.addClass('workeera-hide');
                    img_holder.find('.workeera-user-photo-imgbox').css({'background-image':''});
                } else {
                    $.workeera_submit_msg_alert({
                        msg: response.msg,
                        class: 'workeera-alert-danger'
                    });
                }
                $.workeera_remove_delay_loader({
                    target: main_pic_holder
                });
                this_btn.removeClass('ajax-processing');
            },
            error: function() {
                $.workeera_remove_delay_loader({
                    target: main_pic_holder
                });
                this_btn.removeClass('ajax-processing');
            }
        });
    });

    /**
     * Extract plain-text content from the TinyMCE editor body or fall back to
     * the raw textarea value, stripping HTML tags.
     *
     * @param  {string} editor_id  The wp_editor() ID.
     * @return {string}            Plain text.
     */
    $.workeera_job_desc_plain_text = function (editor_id) {
        if (typeof tinymce !== 'undefined' && tinymce.get(editor_id)) {
            return tinymce.get(editor_id).getContent({ format: 'text' });
        }
        // Fallback: strip HTML from textarea value.
        var raw = $('textarea#' + editor_id).val() || '';
        return $('<div>').html(raw).text();
    };

    /**
     * Update the character-count display for a given editor.
     *
     * @param  {string} editor_id    The wp_editor() ID.
     * @param  {jQuery} count_span   The <span> element showing the count.
     */
    $.workeera_job_desc_update_count = function (editor_id, count_span) {
        var plain = $.workeera_job_desc_plain_text(editor_id);
        var length = plain.length;

        count_span.text(length);

        // Toggle over-limit style (should not normally fire, but kept as safety).
        if (length >= parseInt(workeera_dashboard_str.description_max_len)) {
            count_span.addClass('workeera-charcount-limit');
        } else {
            count_span.removeClass('workeera-charcount-limit');
        }
    };

    /**
     * Initialise the character counter for all job description editors on the page.
     * Called on document ready and after TinyMCE initialises (via a small poll).
     */
    $.workeera_job_desc_charcount_init = function () {
        $('.workeera-usrdshb-job-form').each(function () {
            var form        = $(this);
            var count_wrap  = form.find('.workeera-desc-charcount-wrap');
            if (!count_wrap.length) {
                return; // no description field in this form
            }

            var count_span  = count_wrap.find('.workeera-desc-charcount');
            // Derive the editor ID from the textarea inside the wp-editor area.
            var textarea    = form.find('textarea[name="job_description"]');
            if (!textarea.length) {
                return;
            }
            var editor_id = textarea.attr('id');

            // Bind to the plain textarea (visible when TinyMCE is not active).
            textarea.on('input keydown paste', function () {
                // Use setTimeout so paste content is available.
                setTimeout(function () {
                    $.workeera_job_desc_update_count(editor_id, count_span);
                }, 0);
            });

            // Bind to TinyMCE once it initialises.
            // TinyMCE may not be ready immediately; poll until the editor exists.
            var tmce_bind_attempts = 0;
            var tmce_bind_interval = setInterval(function () {
                tmce_bind_attempts++;
                if (typeof tinymce !== 'undefined' && tinymce.get(editor_id)) {
                    var tmce_editor = tinymce.get(editor_id);

                    tmce_editor.on('keydown keyup paste input NodeChange', function () {
                        setTimeout(function () {
                            $.workeera_job_desc_update_count(editor_id, count_span);
                        }, 0);
                    });

                    // Update count immediately with current content.
                    $.workeera_job_desc_update_count(editor_id, count_span);

                    clearInterval(tmce_bind_interval);
                }
                // Stop polling after ~10 seconds to avoid runaway intervals.
                if (tmce_bind_attempts > 100) {
                    clearInterval(tmce_bind_interval);
                    // Still update from the textarea for non-TinyMCE setups.
                    $.workeera_job_desc_update_count(editor_id, count_span);
                }
            }, 100);
        });
    };

    /**
     * Post-job-submission hook — swap the dashboard main wrap with the view
     * returned by the server, if present.
     *
     * @param {Object} params            Hook parameters.
     * @param {Object} params.result     AJAX response object.
     * @param {jQuery} params.parent_con Element nested inside the dashboard wrap.
     */
    $.workeera_after_job_submission_hook = function(params = {}) {
        if (typeof params.result.view !== 'undefined') {
            params.parent_con.closest('.workeera-usrdshb-main-wrap').html(params.result.view);
        }
    }

    // -------------------------------------------------------------------------
    // Dashboard topbar — open/close the dashboard menu dropdown.
    // -------------------------------------------------------------------------

    /**
     * Close all open dashboard menu panels and reset their trigger state.
     */
    $.workeera_dshb_menu_close_all = function () {
        $('.workeera-dshb-menu-panel').addClass('workeera-hide');
        $('.workeera-dshb-menu-trigger').attr('aria-expanded', 'false');
    };

    // Toggle on trigger click.
    $(document).on('click.workeera_dshb_menu', '.workeera-dshb-menu-trigger', function (e) {
        e.stopPropagation();
        var $trigger = $(this);
        var $menu    = $trigger.closest('[data-workeera-dshb-menu]');
        var $panel   = $menu.find('.workeera-dshb-menu-panel');
        var was_open = !$panel.hasClass('workeera-hide');

        // Close any other open menus first.
        $.workeera_dshb_menu_close_all();

        if (!was_open) {
            $panel.removeClass('workeera-hide');
            $trigger.attr('aria-expanded', 'true');
        }
    });

    // Click anywhere outside the open menu — close.
    $(document).on('click.workeera_dshb_menu_outside', function (e) {
        if (!$(e.target).closest('[data-workeera-dshb-menu]').length) {
            $.workeera_dshb_menu_close_all();
        }
    });

    // Escape key — close.
    $(document).on('keydown.workeera_dshb_menu', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            $.workeera_dshb_menu_close_all();
        }
    });

    /**
     * Enable/disable the required field(s) inside a collapsible company-fields
     * block, so a hidden required input never blocks the job form submission.
     *
     * @param {jQuery}  $fields  The .workeera-company-fields-collapsible element.
     * @param {boolean} enabled  Whether the fields are active (add-new mode).
     */
    $.workeera_company_toggle_required = function ($fields, enabled) {
        $fields.find('[required]').each(function () {
            this.disabled = !enabled;
        });
    };

    // Job-posting multi-company picker: each picker starts in "existing" mode
    // with the fields collapsed + the required field disabled.
    $(document).ready(function () {
        $('.workeera-usrform-company-picker').each(function () {
            var $panel  = $(this).closest('.workeera-usrform-form-panel');
            var $fields = $panel.find('.workeera-company-fields-collapsible');
            $fields.addClass('workeera-hide');
            $.workeera_company_toggle_required($fields, false);
        });
    });

    // Collapse the add-new fields and return to "pick existing" mode.
    $.workeera_company_close_addnew = function ($panel) {
        var $fields = $panel.find('.workeera-company-fields-collapsible');
        $panel.find('.workeera-company-mode').val('existing');
        $panel.find('.workeera-company-selected-id').val($panel.find('.workeera-company-select').val() || '');
        $.workeera_company_toggle_required($fields, false);
        $panel.find('.workeera-usrform-company-cancel-btn').addClass('workeera-hide');
        $fields.stop(true, true).slideUp(180, function () {
            $fields.addClass('workeera-hide');
        });
    };

    // Pick an existing company from the dropdown.
    $('body').on('change', '.workeera-company-select', function () {
        var $panel = $(this).closest('.workeera-usrform-form-panel');
        $panel.find('.workeera-company-mode').val('existing');
        $panel.find('.workeera-company-selected-id').val($(this).val() || '');
        $.workeera_company_close_addnew($panel);
    });

    // Cancel adding a new company — close the empty fields.
    $('body').on('click', '.workeera-usrform-company-cancel-btn', function () {
        $.workeera_company_close_addnew($(this).closest('.workeera-usrform-form-panel'));
    });

    // Reveal the empty fields to add a brand-new company.
    $('body').on('click', '.workeera-usrform-company-addnew-btn', function () {
        var $panel  = $(this).closest('.workeera-usrform-form-panel');
        var $fields = $panel.find('.workeera-company-fields-collapsible');

        $panel.find('.workeera-company-mode').val('new');
        $panel.find('.workeera-company-selected-id').val('');

        // Fresh, empty fields.
        $fields.find('input[type="text"], input[type="url"], input[type="email"], textarea').val('');

        // Reset the logo preview to its empty state.
        var $upload = $fields.find('.workeera-usrform-photo-upload');
        $upload.removeClass('workeera-with-filled-photo');
        $upload.find('.workeera-user-photo-imgholdr').addClass('workeera-hide');
        $upload.find('.workeera-user-photo-imgbox').css('background-image', '');
        $upload.find('.workeera-photo-svgicnlbl-con').removeClass('workeera-hide');

        $.workeera_company_toggle_required($fields, true);
        $panel.find('.workeera-usrform-company-cancel-btn').removeClass('workeera-hide');
        $fields.removeClass('workeera-hide').hide().stop(true, true).slideDown(180);
        $fields.find('input[name="workeera_meta[company_info][name]"]').trigger('focus');
    });

})(jQuery);
