/**
 * Employer dashboard — manage jobs listing, filters, and row-level actions.
 */
(function ($) {
    'use strict';

    $(document).ready(function() {

        // Initialise manage jobs listing if the wrapper exists on this page.
        if ($('.workeera-usrjobs-list-holder').length) {
            $.workeera_manage_jobs_load({});
        }
    });

    /**
     * Return the shell-injected loader markup for the manage-jobs container.
     *
     * @param  {Object} params        Parameters.
     * @param  {jQuery} params.target Container holding the loader template.
     * @return {string}               Loader HTML markup.
     */
    $.workeera_mngjobs_delay_loader_html = function(params) {
        let target_con = params.target;
        return target_con.find('.workeera-init-loder-holdr').html();
    }

    /**
     * Remove the inline manage-jobs loader from the given target container.
     *
     * @param {Object} params        Parameters.
     * @param {jQuery} params.target Container holding the loader.
     */
    $.workeera_remove_mngjobs_delay_loader = function(params) {
        let target_con = params.target;
        target_con.find('.workeera-usrjobs-loder-con .workeera-mangjobs-loadr').remove();
    }

    /**
     * Internal state object for the manage-jobs section.
     * Tracks current page, search, sort, and status filter values so every
     * helper can read from one place.
     */
    var workeera_manage_jobs_state = {
        page          : 1,
        search        : '',
        sort          : '',
        status_filter : ''
    };

    /**
     * Read current filter/sort values from the DOM and update the state object.
     */
    $.workeera_manage_jobs_read_filters = function () {
        var wrap = $('.workeera-usrjobs-wrap');
        workeera_manage_jobs_state.search        = $.trim(wrap.find('.workeera-usrjobs-search-input').val());
        workeera_manage_jobs_state.sort          = wrap.find('.workeera-usrjobs-sort-select').val();
        workeera_manage_jobs_state.status_filter = wrap.find('.workeera-usrjobs-status-select').val();
    };

    /**
     * Fetch the manage-jobs list via AJAX and inject the resulting HTML.
     *
     * @param {Object} params Optional overrides such as { page: number }.
     */
    $.workeera_manage_jobs_load = function (params) {
        params = params || {};

        var wrap   = $('.workeera-usrjobs-wrap');
        var holder = wrap.find('.workeera-usrjobs-list-holder');

        if (!holder.length) {
            return;
        }

        // Update page if passed.
        if (typeof params.page !== 'undefined') {
            workeera_manage_jobs_state.page = parseInt(params.page) || 1;
        }

        var nonce = wrap.attr('data-nonce') || workeera_func_str.nonce;

        var list_con = holder.find('.workeera-usrjobs-list');
        list_con.addClass('workeera-hide');

        var empty_con = holder.find('.workeera-usrjobs-empty');
        // Hide empty con.
        empty_con.addClass('workeera-hide');

        // Show loader inside the holder.
        if (!holder.find('.workeera-usrjobs-loder-con .workeera-mangjobs-loadr').length) {
            holder.find('.workeera-usrjobs-loder-con').append($.workeera_mngjobs_delay_loader_html({target: holder}));
        }

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action        : 'workeera_manage_jobs_list',
                workeera_nonce  : nonce,
                page_num      : workeera_manage_jobs_state.page,
                search        : workeera_manage_jobs_state.search,
                sort          : workeera_manage_jobs_state.sort,
                status_filter : workeera_manage_jobs_state.status_filter
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err === 'failed') {
                    return;
                }

                $.workeera_remove_mngjobs_delay_loader({target: holder});

                if (response.error !== '0') {
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
                    return;
                }

                var pagination_con = holder.find('.workeera-usrjobs-pagination');

                if (response.is_empty === '1') {
                    // Show empty state, hide list and pagination.
                    list_con.html('');
                    pagination_con.html('');

                    // Update CTA button label.
                    empty_con.find('.workeera-usrjobs-empty-cta-btn').text(response.empty_cta_label);

                    empty_con.removeClass('workeera-hide');
                } else {

                    // Replace list wrapper content.
                    list_con.replaceWith($(response.list_html));
                    list_con.removeClass('workeera-hide');

                    // Re-bind filled checkboxes after fresh HTML injection.
                    $.workeera_manage_jobs_bind_filled();

                    pagination_con.html(response.pagination_html);
                }
            },
            error: function () {
                $.workeera_remove_mngjobs_delay_loader({target: holder});
                list_con.removeClass('workeera-hide');
            }
        });
    };

    /**
     * Bind the "Filled job?" checkbox change event on freshly injected rows.
     * Called after each AJAX list refresh.
     */
    $.workeera_manage_jobs_bind_filled = function () {
        var holder = $('.workeera-usrjobs-list-holder');

        // Remove any previous delegation binding to avoid duplicates.
        holder.off('change.workeera_filled', '.workeera-usrjobs-filled-chk');

        holder.on('change.workeera_filled', '.workeera-usrjobs-filled-chk', function () {
            var chk     = $(this);
            var itm_con = chk.closest('.workeera-usrjobs-item');
            var job_id  = parseInt(chk.attr('data-job-id')) || 0;
            var filled  = chk.is(':checked') ? 1 : 0;
            var wrap    = $('.workeera-usrjobs-wrap');
            var nonce   = wrap.attr('data-nonce') || workeera_func_str.nonce;

            if (job_id < 1) {
                return;
            }

            // Disable checkbox during AJAX to prevent double-toggling.
            chk.prop('disabled', true);
            itm_con.append($.workeera_delay_loader_html());

            $.ajax({
                type    : 'POST',
                url     : workeera_func_str.ajax_url,
                dataType: 'json',
                data    : {
                    action       : 'workeera_toggle_filled_job_status',
                    workeera_nonce : nonce,
                    job_id       : job_id,
                    filled       : filled
                },
                success: function (response) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                    if (nonce_err === 'failed') {
                        return;
                    }

                    if (response.error === '0') {
                        $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-success'});
                    } else {
                        // Revert checkbox state on error.
                        chk.prop('checked', !filled);
                        $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
                    }

                    chk.prop('disabled', false);
                    $.workeera_remove_delay_loader({
                        target: itm_con
                    });
                },
                error: function () {
                    // Revert on network error.
                    chk.prop('checked', !filled).prop('disabled', false);
                    $.workeera_remove_delay_loader({
                        target: itm_con
                    });
                }
            });
        });
    };

    // -------------------------------------------------------------------------
    // Manage jobs filter / sort / search event bindings
    // -------------------------------------------------------------------------

    // Sort dropdown change — reset to page 1.
    $('body').on('change', '.workeera-usrjobs-sort-select', function () {
        $.workeera_manage_jobs_read_filters();
        workeera_manage_jobs_state.page = 1;
        $.workeera_manage_jobs_load({});
    });

    // Status filter dropdown change — reset to page 1.
    $('body').on('change', '.workeera-usrjobs-status-select', function () {
        $.workeera_manage_jobs_read_filters();
        workeera_manage_jobs_state.page = 1;
        $.workeera_manage_jobs_load({});
    });

    // Search input — debounced, reset to page 1.
    var workeera_search_timer = null;
    $('body').on('input', '.workeera-usrjobs-search-input', function () {
        clearTimeout(workeera_search_timer);
        workeera_search_timer = setTimeout(function () {
            $.workeera_manage_jobs_read_filters();
            workeera_manage_jobs_state.page = 1;
            $.workeera_manage_jobs_load({});
        }, 450);
    });

    // Pagination button clicks (delegated — buttons are injected dynamically).
    $('body').on('click', '.workeera-usrjobs-pagination .workeera-usrjobs-page-nav', function () {
        var page = parseInt($(this).attr('data-page')) || 1;
        $.workeera_manage_jobs_read_filters();
        $.workeera_manage_jobs_load({page: page});
    });

    // delete user job
    $('body').on('click', '.workeera-usrjobs-list .workeera-usrjobs-action-delete', function () {
        var this_btn = $(this);
        var job_id = parseInt(this_btn.attr('data-job-id')) || 0;
        var nonce = workeera_func_str.nonce;

        if (job_id < 1) {
            return;
        }

        $.workeera_confirm_box({
            title: workeera_dashboard_str.confirm_comon_title,
            message: workeera_func_str.delet_common_msg,
            buttons: {
                yes: {
                    title: workeera_func_str.delet_btn_lbl,
                    class: 'workeera-delitm-btn',
                    action: function() {
                        var item_parent = this_btn.closest('.workeera-usrjobs-item');
                        var items_parent = item_parent.closest('.workeera-usrjobs-list');
                        var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
                        confirm_box_con.append($.workeera_delay_loader_html());
                        $.ajax({
                            type    : 'POST',
                            url     : workeera_func_str.ajax_url,
                            dataType: 'json',
                            data    : {
                                action       : 'workeera_delete_user_job',
                                workeera_nonce : nonce,
                                job_id       : job_id
                            },
                            success: function (response) {
                                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                                if (nonce_err === 'failed') {
                                    return;
                                }

                                if (response.error === '0') {
                                    $.workeera_confirm_box.hide();
                                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-success'});
                                    item_parent.css({'background-color': '#ffd3d3'});
                                    setTimeout(function() {
                                        item_parent.remove();
                                        if (!items_parent.find('.workeera-usrjobs-item').length) {
                                            window.location.reload();
                                        }
                                    }, 1000);
                                } else {
                                    // Revert checkbox state on error.
                                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
                                    $.workeera_remove_delay_loader({
                                        target: confirm_box_con
                                    });
                                }
                            },
                            error: function () {
                                // Revert on network error.
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
                    action: function() {
                        $.workeera_confirm_box.hide();
                    }
                }
            }
        });
    });

})(jQuery);
