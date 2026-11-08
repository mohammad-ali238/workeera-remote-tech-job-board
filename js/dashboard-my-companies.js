/**
 * Employer dashboard — "My Companies" listing, inline edit/save, add-new modal,
 * accordion, logo preview, AJAX search + pagination, and delete.
 *
 * Mirrors the Manage Jobs UX (js/dashboard-manage-jobs.js) and reuses the shared
 * helpers exposed on the jQuery namespace by js/common-functions.js
 * ($.workeera_modal_popup_open / $.workeera_modal_popup_close /
 * $.workeera_confirm_box / $.workeera_select2_common /
 * $.workeera_ajax_security_err_reload).
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        // Initialise the companies listing if the wrapper exists on this page.
        if ($('.workeera-usrcmp-list-holder').length) {
            $.workeera_mycompanies_load({});
        }
    });

    /**
     * Internal state object for the My Companies section.
     * Tracks current page and search value so every helper reads from one place.
     */
    var workeera_mycompanies_state = {
        page   : 1,
        search : ''
    };

    /**
     * Read the section nonce (wrapper data-nonce with a global fallback).
     *
     * @return {string} Nonce value.
     */
    $.workeera_mycompanies_nonce = function () {
        return $('.workeera-usrcmp-wrap').attr('data-nonce') || workeera_func_str.nonce;
    };

    /**
     * Return the shell-injected loader markup for the companies container.
     *
     * @param  {Object} params        Parameters.
     * @param  {jQuery} params.target Container holding the loader template.
     * @return {string}               Loader HTML markup.
     */
    $.workeera_mycompanies_delay_loader_html = function (params) {
        var target_con = params.target;
        return target_con.find('.workeera-init-loder-holdr').html();
    };

    /**
     * Remove the inline companies loader from the given target container.
     *
     * @param {Object} params        Parameters.
     * @param {jQuery} params.target Container holding the loader.
     */
    $.workeera_remove_mycompanies_delay_loader = function (params) {
        var target_con = params.target;
        target_con.find('.workeera-usrcmp-loder-con .workeera-usrcmp-loadr').remove();
    };

    /**
     * Initialise (or re-initialise) select2 on the given employee-count <select>.
     *
     * @param {jQuery} target The .workeera-cmp-employee select element.
     */
    $.workeera_mycompanies_init_employee_select2 = function (target) {
        if (!target || !target.length) {
            return;
        }
        if (target.hasClass('select2-hidden-accessible')) {
            target.select2('destroy');
        }
        $.workeera_select2_common({ target: target });
    };

    /**
     * Fetch the companies list via AJAX and inject the resulting HTML.
     *
     * @param {Object} params Optional overrides such as { page: number }.
     */
    $.workeera_mycompanies_load = function (params) {
        params = params || {};

        var wrap   = $('.workeera-usrcmp-wrap');
        var holder = wrap.find('.workeera-usrcmp-list-holder');

        if (!holder.length) {
            return;
        }

        // Update page if passed.
        if (typeof params.page !== 'undefined') {
            workeera_mycompanies_state.page = parseInt(params.page) || 1;
        }

        var nonce = $.workeera_mycompanies_nonce();

        var list_con = holder.find('.workeera-usrcmp-list');
        list_con.addClass('workeera-hide');

        var empty_con = holder.find('.workeera-usrcmp-empty');
        // Hide empty state.
        empty_con.addClass('workeera-hide');

        // Show loader inside the holder (cloned from the hidden template).
        if (!holder.find('.workeera-usrcmp-loder-con .workeera-usrcmp-loadr').length) {
            holder.find('.workeera-usrcmp-loder-con').append($.workeera_mycompanies_delay_loader_html({ target: holder }));
        }

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action       : 'workeera_my_companies_list',
                workeera_nonce : nonce,
                page_num     : workeera_mycompanies_state.page,
                search       : workeera_mycompanies_state.search
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({ result: response });
                if (nonce_err === 'failed') {
                    return;
                }

                $.workeera_remove_mycompanies_delay_loader({ target: holder });

                if (response.error !== '0') {
                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-danger' });
                    return;
                }

                var pagination_con = holder.find('.workeera-usrcmp-pagination');

                if (response.is_empty === '1') {
                    // Show empty state, hide list and pagination.
                    list_con.html('');
                    pagination_con.html('');

                    // Update CTA button label (Add a company / Clear search).
                    empty_con.find('.workeera-usrcmp-empty-cta').text(response.empty_cta_label);

                    empty_con.removeClass('workeera-hide');
                } else {

                    // Replace list wrapper content.
                    list_con.replaceWith($(response.list_html));

                    var new_list = holder.find('.workeera-usrcmp-list');
                    new_list.removeClass('workeera-hide');

                    pagination_con.html(response.pagination_html);

                    // Initialise select2 on freshly injected employee selects.
                    new_list.find('select.workeera-cmp-employee').each(function () {
                        $.workeera_mycompanies_init_employee_select2($(this));
                    });
                }
            },
            error: function () {
                $.workeera_remove_mycompanies_delay_loader({ target: holder });
                list_con.removeClass('workeera-hide');
            }
        });
    };

    /**
     * Build a FormData payload for a create/update request from a form scope.
     *
     * @param  {jQuery}   scope      Container holding the .workeera-cmp-* fields.
     * @param  {string}   company_id Company id ('' for a new company).
     * @return {FormData}            The populated FormData object.
     */
    $.workeera_mycompanies_build_formdata = function (scope, company_id) {
        var form_data = new FormData();
        form_data.append('action', 'workeera_save_company');
        form_data.append('workeera_nonce', $.workeera_mycompanies_nonce());
        form_data.append('company_id', company_id);
        form_data.append('name', $.trim(scope.find('.workeera-cmp-name').val() || ''));
        form_data.append('website', $.trim(scope.find('.workeera-cmp-website').val() || ''));
        form_data.append('tagline', $.trim(scope.find('.workeera-cmp-tagline').val() || ''));
        form_data.append('employee_count', scope.find('.workeera-cmp-employee').val() || '');
        form_data.append('linkedin', $.trim(scope.find('.workeera-cmp-linkedin').val() || ''));

        var logo_input = scope.find('.workeera-cmp-logo')[0];
        if (logo_input && logo_input.files && logo_input.files.length) {
            form_data.append('company_logo', logo_input.files[0]);
        }
        form_data.append('remove_logo', scope.find('.workeera-cmp-remove-logo').val() || '0');

        return form_data;
    };

    /**
     * Show a message inside a company form notice element.
     *
     * @param {jQuery} notice The .workeera-usrcmp-form-notice element.
     * @param {string} msg    Message text.
     * @param {string} type   'success' | 'error'.
     */
    $.workeera_mycompanies_notice = function (notice, msg, type) {
        if (!notice || !notice.length) {
            return;
        }
        notice.removeClass('is-success is-error workeera-hide');
        notice.addClass(type === 'success' ? 'is-success' : 'is-error');
        notice.text(msg);
    };

    /**
     * Save a company (create or update) via a multipart AJAX request.
     *
     * @param {Object}   params            Configuration.
     * @param {jQuery}   params.scope      Container holding the .workeera-cmp-* fields.
     * @param {string}   params.company_id Company id ('' for create).
     * @param {jQuery}   params.btn        The clicked save/create button.
     * @param {jQuery}   params.notice     The notice element for this form.
     * @param {Function} params.onSuccess  Callback fired after a successful save.
     */
    $.workeera_mycompanies_save = function (params) {
        var scope      = params.scope;
        var company_id = params.company_id;
        var btn        = params.btn;
        var notice     = params.notice;
        var on_success = params.onSuccess || function () {};

        var form_data = $.workeera_mycompanies_build_formdata(scope, company_id);

        var btn_label = btn.text();
        btn.prop('disabled', true).addClass('workeera-btn-loading').text(workeera_func_str.processing_lbl || 'Saving…');

        $.ajax({
            type       : 'POST',
            url        : workeera_func_str.ajax_url,
            dataType   : 'json',
            processData: false,
            contentType: false,
            data       : form_data,
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({ result: response });
                if (nonce_err === 'failed') {
                    return;
                }

                btn.prop('disabled', false).removeClass('workeera-btn-loading').text(btn_label);

                if (response.error === '0') {
                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-success' });
                    on_success(response);
                } else {
                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-danger' });
                }
            },
            error: function () {
                btn.prop('disabled', false).removeClass('workeera-btn-loading').text(btn_label);
            }
        });
    };

    /**
     * Reset the add-new modal form to an empty state (fields + logo preview).
     */
    $.workeera_mycompanies_reset_modal = function () {
        var form = $('#workeera-company-modal .workeera-usrcmp-modal-form');

        form.find('.workeera-cmp-name, .workeera-cmp-website, .workeera-cmp-tagline, .workeera-cmp-linkedin').val('');

        var logo_input = form.find('.workeera-cmp-logo');
        if (logo_input.length) {
            logo_input.val('');
        }
        form.find('.workeera-cmp-remove-logo').val('0');

        // Reset the logo preview box.
        var upload_box = form.find('.workeera-usrform-photo-upload');
        upload_box.removeClass('workeera-with-filled-photo');
        upload_box.find('.workeera-cmp-logo-preview').css('background-image', '');
        upload_box.find('.workeera-user-photo-imgholdr').addClass('workeera-hide');
        upload_box.find('.workeera-photo-svgicnlbl-con').removeClass('workeera-hide');

        // Reset the notice.
        form.find('.workeera-usrcmp-form-notice').removeClass('is-success is-error').addClass('workeera-hide').text('');

        // Reset + (re)initialise the employee select2.
        var emp = form.find('select.workeera-cmp-employee');
        if (emp.hasClass('select2-hidden-accessible')) {
            emp.select2('destroy');
        }
        emp.val('');
        $.workeera_select2_common({ target: emp });
    };

    // -------------------------------------------------------------------------
    // Search / pagination event bindings
    // -------------------------------------------------------------------------

    // Search input — debounced, reset to page 1.
    var workeera_mycompanies_search_timer = null;
    $('body').on('input', '.workeera-usrcmp-search-input', function () {
        var val = $.trim($(this).val());
        clearTimeout(workeera_mycompanies_search_timer);
        workeera_mycompanies_search_timer = setTimeout(function () {
            workeera_mycompanies_state.search = val;
            workeera_mycompanies_state.page   = 1;
            $.workeera_mycompanies_load({});
        }, 450);
    });

    // Pagination button clicks (delegated — buttons are injected dynamically).
    $('body').on('click', '.workeera-usrcmp-pagination .workeera-usrjobs-page-nav', function () {
        var page = parseInt($(this).attr('data-page')) || 1;
        $.workeera_mycompanies_load({ page: page });
    });

    // -------------------------------------------------------------------------
    // Accordion
    // -------------------------------------------------------------------------

    // Toggle a company body open/closed. Ignore clicks that originated on a link
    // inside the header (e.g. the website link).
    $('body').on('click', '.workeera-usrcmp-head', function (e) {
        if ($(e.target).closest('a').length) {
            return;
        }

        var head = $(this);
        var item = head.closest('.workeera-usrcmp-item');
        var body = head.siblings('.workeera-usrcmp-body');

        item.toggleClass('workeera-usrcmp-open');
        body.stop(true, true).slideToggle(180);
    });

    // -------------------------------------------------------------------------
    // Logo box (file picker + preview)
    // -------------------------------------------------------------------------

    // Clicking the logo box opens the hidden file input (but not when the click
    // was on the remove button).
    $('body').on('click', '.workeera-usrform-photo-upload.workeera-usrcmp-logo-box', function (e) {
        if ($(e.target).is('.workeera-cmp-logo') || $(e.target).closest('.workeera-user-photo-remve').length) {
            return;
        }
        $(this).find('.workeera-cmp-logo').trigger('click');
    });

    // Preview the chosen logo file (and clear any pending "remove" flag).
    $('body').on('change', '.workeera-cmp-logo', function () {
        var input = this;
        if (!input.files || !input.files.length) {
            return;
        }

        var box     = $(this).closest('.workeera-usrform-photo-upload');
        var preview = box.find('.workeera-cmp-logo-preview');
        var url     = window.URL.createObjectURL(input.files[0]);

        preview.css('background-image', "url('" + url + "')");
        box.find('.workeera-cmp-remove-logo').val('0');
        box.find('.workeera-user-photo-imgholdr').removeClass('workeera-hide');
        box.find('.workeera-photo-svgicnlbl-con').addClass('workeera-hide');
        box.addClass('workeera-with-filled-photo');
    });

    // Remove the chosen/existing logo (marks it for removal on save).
    $('body').on('click', '.workeera-usrcmp-logo-box .workeera-user-photo-remve', function (e) {
        e.stopPropagation();
        var box = $(this).closest('.workeera-usrform-photo-upload');
        box.find('.workeera-cmp-logo').val('');
        box.find('.workeera-cmp-remove-logo').val('1');
        box.find('.workeera-cmp-logo-preview').css('background-image', '');
        box.find('.workeera-user-photo-imgholdr').addClass('workeera-hide');
        box.find('.workeera-photo-svgicnlbl-con').removeClass('workeera-hide');
        box.removeClass('workeera-with-filled-photo');
    });

    // -------------------------------------------------------------------------
    // Inline save (accordion item)
    // -------------------------------------------------------------------------

    $('body').on('click', '.workeera-usrcmp-save-btn', function () {
        var btn        = $(this);
        var item       = btn.closest('.workeera-usrcmp-item');
        var company_id = item.attr('data-company-id') || '';
        var notice     = item.find('.workeera-usrcmp-form-notice');

        $.workeera_mycompanies_save({
            scope     : item,
            company_id: company_id,
            btn       : btn,
            notice    : notice,
            onSuccess : function () {
                // Refresh the list to reflect the saved changes.
                setTimeout(function () {
                    $.workeera_mycompanies_load({});
                }, 700);
            }
        });
    });

    // -------------------------------------------------------------------------
    // Add-new (modal) — open + create
    // -------------------------------------------------------------------------

    // "Add New" buttons (header + empty-state). When the empty-state button is
    // showing the "Clear search" label (a search is active), clear the search
    // instead of opening the modal.
    $('body').on('click', '.workeera-usrcmp-addnew-btn', function () {
        if ($(this).hasClass('workeera-usrcmp-empty-cta') && workeera_mycompanies_state.search !== '') {
            $('.workeera-usrcmp-search-input').val('');
            workeera_mycompanies_state.search = '';
            workeera_mycompanies_state.page   = 1;
            $.workeera_mycompanies_load({});
            return;
        }

        $.workeera_modal_popup_open({ target: 'workeera-company-modal', class: 'workeera-usrcmp-modal-con' });
        $.workeera_mycompanies_reset_modal();
    });

    // Create company (modal).
    $('body').on('click', '.workeera-usrcmp-create-btn', function () {
        var btn    = $(this);
        var form   = $('#workeera-company-modal .workeera-usrcmp-modal-form');
        var notice = form.find('.workeera-usrcmp-form-notice');

        $.workeera_mycompanies_save({
            scope     : form,
            company_id: '',
            btn       : btn,
            notice    : notice,
            onSuccess : function () {
                setTimeout(function () {
                    window.location.reload();
                }, 1000);
            }
        });
    });

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    $('body').on('click', '.workeera-usrcmp-delete-btn', function () {
        var this_btn    = $(this);
        var company_id  = this_btn.attr('data-company-id') || '';
        var nonce       = $.workeera_mycompanies_nonce();

        if (company_id === '') {
            return;
        }

        $.workeera_confirm_box({
            title: workeera_dashboard_str.confirm_comon_title,
            message: workeera_func_str.delet_common_msg,
            buttons: {
                yes: {
                    title: workeera_func_str.delet_btn_lbl,
                    class: 'workeera-delitm-btn',
                    action: function () {
                        var item_parent     = this_btn.closest('.workeera-usrcmp-item');
                        var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
                        confirm_box_con.append($.workeera_delay_loader_html());
                        $.ajax({
                            type    : 'POST',
                            url     : workeera_func_str.ajax_url,
                            dataType: 'json',
                            data    : {
                                action       : 'workeera_delete_company',
                                workeera_nonce : nonce,
                                company_id   : company_id
                            },
                            success: function (response) {
                                var nonce_err = $.workeera_ajax_security_err_reload({ result: response });
                                if (nonce_err === 'failed') {
                                    return;
                                }

                                if (response.error === '0') {
                                    $.workeera_confirm_box.hide();
                                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-success' });
                                    item_parent.css({ 'background-color': '#ffd3d3' });
                                    setTimeout(function () {
                                        $.workeera_mycompanies_load({});
                                    }, 800);
                                } else {
                                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-danger' });
                                    $.workeera_remove_delay_loader({
                                        target: confirm_box_con
                                    });
                                }
                            },
                            error: function () {
                                $.workeera_remove_delay_loader({
                                    target: confirm_box_con
                                });
                            }
                        });
                    }
                },
                no: {
                    title: workeera_func_str.cancel_btn_lbl,
                    class: 'workeera-cancel-act',
                    action: function () {
                        $.workeera_confirm_box.hide();
                    }
                }
            }
        });
    });

})(jQuery);
