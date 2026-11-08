/**
 * Workeera employer-applications script.
 *
 * Powers the employer-dashboard (front-end) and wp-admin applications list:
 * AJAX listing with filters, search and pagination; per-row status changes;
 * bulk status changes and (admin) bulk delete; Select2-backed job and
 * employer filters.
 */
(function ($) {
    'use strict';

    /**
     * Section selectors.
     */
    var workeera_empapps_cfg = {
        wrap_sel        : '.workeera-empapps-wrap',
        holder_sel      : '.workeera-empapps-list-holder',
        list_sel        : '.workeera-empapps-list',
        empty_sel       : '.workeera-empapps-empty',
        search_sel      : '.workeera-empapps-search-input',
        sort_sel        : '.workeera-empapps-sort-select',
        status_sel      : '.workeera-empapps-status-select',
        job_sel         : '.workeera-empapps-job-select',
        employer_sel    : '.workeera-empapps-employer-select',
        bulk_bar_sel    : '.workeera-empapps-bulk-bar',
        bulk_master_sel : '.workeera-empapps-bulk-master-chk',
        bulk_action_sel : '.workeera-empapps-bulk-action-select',
        bulk_apply_sel  : '.workeera-empapps-bulk-apply-btn',
        bulk_count_sel  : '.workeera-empapps-bulk-count',
        item_chk_sel    : '.workeera-empapps-item-chk',
        item_sel        : '.workeera-empapps-item'
    };

    /**
     * Per-load runtime state (front-end AJAX list only).
     */
    var workeera_empapps_state = {
        page          : 1,
        search        : '',
        sort          : '',
        status_filter : '',
        job_filter    : 0
    };

    /**
     * Read the wrap's data-attributes into a per-call config so the same
     * helpers work for both front-end (employer dashboard) and back-end
     * (wp-admin) pages without duplicating code.
     *
     * Defaults are the front-end employer-dashboard action names so the
     * front-end markup does not need to declare data-attributes.
     *
     * @return {Object|null} Config object, or null if the wrap is not in the DOM.
     */
    function workeera_empapps_read_wrap_config() {
        var wrap = $(workeera_empapps_cfg.wrap_sel);
        if (!wrap.length) {
            return null;
        }

        return {
            wrap         : wrap,
            context      : wrap.attr('data-context')                  || 'frontend',
            nonce        : wrap.attr('data-nonce')                    || (typeof workeera_func_str !== 'undefined' ? workeera_func_str.nonce : ''),
            ajax_list    : wrap.attr('data-ajax-list')                || 'workeera_employer_apps_list',
            ajax_status  : wrap.attr('data-ajax-status')              || 'workeera_employer_app_status',
            ajax_bulk_st : wrap.attr('data-ajax-bulk-status')         || 'workeera_employer_app_bulk_status',
            ajax_bulk_del: wrap.attr('data-ajax-bulk-delete')         || '',
            ajax_jobs    : wrap.attr('data-ajax-jobs-search')         || 'workeera_employer_jobs_search',
            ajax_emps    : wrap.attr('data-ajax-employers-search')    || ''
        };
    }

    /* ------------------------------------------------------------------ */
    /* Loader helpers (front-end AJAX list only)                           */
    /* ------------------------------------------------------------------ */

    /**
     * Return the inline loader HTML stored in the target's init-loader holder.
     *
     * @param  {Object} params        Parameters bag.
     * @param  {jQuery} params.target Target element containing the loader template.
     * @return {string}               Loader HTML string.
     */
    $.workeera_empapps_delay_loader_html = function (params) {
        return params.target.find('.workeera-init-loder-holdr').html();
    };

    /**
     * Remove the loader element from a target container.
     *
     * @param {Object} params        Parameters bag.
     * @param {jQuery} params.target Target element holding the loader.
     */
    $.workeera_empapps_remove_loader = function (params) {
        params.target.find('.workeera-usrjobs-loder-con .workeera-listin-loadr').remove();
    };

    /* ------------------------------------------------------------------ */
    /* Filter read (front-end only)                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Read current filter values from the wrap into the runtime state object.
     */
    $.workeera_empapps_read_filters = function () {
        var wrap = $(workeera_empapps_cfg.wrap_sel);
        if (!wrap.length) {
            return;
        }
        workeera_empapps_state.search        = $.trim(wrap.find(workeera_empapps_cfg.search_sel).val() || '');
        workeera_empapps_state.sort          = wrap.find(workeera_empapps_cfg.sort_sel).val() || '';
        workeera_empapps_state.status_filter = wrap.find(workeera_empapps_cfg.status_sel).val() || '';
        workeera_empapps_state.job_filter    = parseInt(wrap.find(workeera_empapps_cfg.job_sel).val(), 10) || 0;
    };

    /* ------------------------------------------------------------------ */
    /* Front-end AJAX list loader                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Load the applications list via AJAX and render the result.
     *
     * @param {Object} [params]      Optional parameters.
     * @param {number} [params.page] Page number to load (defaults to current state).
     */
    $.workeera_empapps_load = function (params) {
        params = params || {};

        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg) {
            return;
        }

        var holder = cfg.wrap.find(workeera_empapps_cfg.holder_sel);
        if (!holder.length) {
            return;
        }

        if (typeof params.page !== 'undefined') {
            workeera_empapps_state.page = parseInt(params.page, 10) || 1;
        }

        var list_con       = holder.find(workeera_empapps_cfg.list_sel);
        var empty_con      = holder.find(workeera_empapps_cfg.empty_sel);
        var pagination_con = holder.find('.workeera-usrjobs-pagination');

        list_con.addClass('workeera-hide');
        empty_con.addClass('workeera-hide');

        // Reset bulk state on every reload.
        $.workeera_empapps_reset_bulk_state();

        if (!holder.find('.workeera-usrjobs-loder-con .workeera-listin-loadr').length) {
            holder.find('.workeera-usrjobs-loder-con').append($.workeera_empapps_delay_loader_html({target: holder}));
        }

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action        : cfg.ajax_list,
                workeera_nonce  : cfg.nonce,
                page_num      : workeera_empapps_state.page,
                search        : workeera_empapps_state.search,
                sort          : workeera_empapps_state.sort,
                status_filter : workeera_empapps_state.status_filter,
                job_filter    : workeera_empapps_state.job_filter
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err === 'failed') {
                    return;
                }

                $.workeera_empapps_remove_loader({target: holder});

                if (response.error !== '0') {
                    $.workeera_submit_msg_alert({msg: response.msg || workeera_empapps_str.generic_err, class: 'workeera-alert-danger'});
                    return;
                }

                if (response.is_empty === '1') {
                    list_con.html('');
                    pagination_con.html('');
                    empty_con.removeClass('workeera-hide');
                } else {
                    list_con.replaceWith($(response.list_html));
                    pagination_con.html(response.pagination_html);
                }
            },
            error: function () {
                $.workeera_empapps_remove_loader({target: holder});
                list_con.removeClass('workeera-hide');
            }
        });
    };

    /* ------------------------------------------------------------------ */
    /* Bulk selection helpers                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Collect the application IDs of every checked item in the list.
     *
     * @return {Array} Numeric application IDs.
     */
    $.workeera_empapps_collect_selected = function () {
        var ids = [];
        $(workeera_empapps_cfg.wrap_sel).find(workeera_empapps_cfg.item_chk_sel + ':checked').each(function () {
            var id = parseInt($(this).attr('data-application-id'), 10) || 0;
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    };

    /**
     * Refresh the bulk-actions bar visibility, count, and master checkbox state.
     */
    $.workeera_empapps_update_bulk_bar = function () {
        var wrap     = $(workeera_empapps_cfg.wrap_sel);
        var bulk_bar = wrap.find(workeera_empapps_cfg.bulk_bar_sel);
        if (!bulk_bar.length) {
            return;
        }

        var ids = $.workeera_empapps_collect_selected();
        var total_visible = wrap.find(workeera_empapps_cfg.item_chk_sel).length;
        var count_con = bulk_bar.find(workeera_empapps_cfg.bulk_count_sel);
        var tpl = count_con.attr('data-tpl') || '%s';

        if (ids.length > 0) {
            bulk_bar.removeClass('workeera-hide');
            count_con.text(tpl.replace('%s', ids.length));
        } else {
            bulk_bar.addClass('workeera-hide');
        }

        var master = bulk_bar.find(workeera_empapps_cfg.bulk_master_sel);
        if (master.length) {
            if (ids.length === 0) {
                master.prop('checked', false).prop('indeterminate', false);
            } else if (ids.length === total_visible && total_visible > 0) {
                master.prop('checked', true).prop('indeterminate', false);
            } else {
                master.prop('checked', false).prop('indeterminate', true);
            }
        }
    };

    /**
     * Clear the bulk-actions bar and reset its inputs to defaults.
     */
    $.workeera_empapps_reset_bulk_state = function () {
        var wrap     = $(workeera_empapps_cfg.wrap_sel);
        var bulk_bar = wrap.find(workeera_empapps_cfg.bulk_bar_sel);
        if (bulk_bar.length) {
            bulk_bar.addClass('workeera-hide');
            bulk_bar.find(workeera_empapps_cfg.bulk_action_sel).val('');
            bulk_bar.find(workeera_empapps_cfg.bulk_master_sel).prop('checked', false).prop('indeterminate', false);
        }
    };

    /* ------------------------------------------------------------------ */
    /* Status helpers                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Return the localised label for an application status key.
     *
     * @param  {string} status_key Status key (e.g. "pending", "reviewed").
     * @return {string}            Localised label, or the original key if unmapped.
     */
    $.workeera_empapps_status_label = function (status_key) {
        var map = {
            pending     : workeera_empapps_str.status_pending_lbl,
            reviewed    : workeera_empapps_str.status_reviewed_lbl,
            shortlisted : workeera_empapps_str.status_shortlisted_lbl,
            rejected    : workeera_empapps_str.status_rejected_lbl
        };
        return map[status_key] || status_key;
    };

    /* ------------------------------------------------------------------ */
    /* Single status update                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Update the status of a single application via AJAX.
     *
     * @param {number} application_id Application ID to update.
     * @param {string} new_status     New status key.
     * @param {jQuery} item_el        Row element representing the application.
     */
    $.workeera_empapps_update_single_status = function (application_id, new_status, item_el) {
        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg) return;

        var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
        confirm_box_con.append($.workeera_delay_loader_html());

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action         : cfg.ajax_status,
                workeera_nonce   : cfg.nonce,
                application_id : application_id,
                new_status     : new_status
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err === 'failed') {
                    return;
                }

                if (response.error === '0') {
                    $.workeera_confirm_box.hide();
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-success'});

                    if (item_el && item_el.length) {
                        $.workeera_empapps_apply_status_to_item(item_el, response);
                    }
                } else {
                    $.workeera_submit_msg_alert({msg: response.msg || workeera_empapps_str.generic_err, class: 'workeera-alert-danger'});
                    $.workeera_remove_delay_loader({target: confirm_box_con});
                }
            },
            error: function () {
                $.workeera_remove_delay_loader({target: confirm_box_con});
            }
        });
    };

    /**
     * Apply a status update response to one item element in the DOM.
     *
     * Shared between single and bulk updates.
     *
     * @param {jQuery} item_el  Row element representing the application.
     * @param {Object} response AJAX response containing new_status, badge_class, badge_label.
     */
    $.workeera_empapps_apply_status_to_item = function (item_el, response) {
        item_el.attr('data-current-status', response.new_status);
        var trigger = item_el.find('.workeera-empapps-status-trigger');
        trigger.attr('data-current-status', response.new_status);
        var badge = trigger.find('.workeera-empapps-status-badge');
        badge.attr('class', 'workeera-empapps-status-badge ' + response.badge_class).text(response.badge_label);

        item_el.find('.workeera-empapps-status-menu-item').removeClass('workeera-empapps-status-menu-active');
        item_el.find('.workeera-empapps-status-menu-item[data-action-status="' + response.new_status + '"]').addClass('workeera-empapps-status-menu-active');
    };

    /* ------------------------------------------------------------------ */
    /* Bulk status update                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Update the status of multiple applications via AJAX.
     *
     * @param {Array}  ids        Application IDs to update.
     * @param {string} new_status New status key.
     */
    $.workeera_empapps_update_bulk_status = function (ids, new_status) {
        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg) return;

        var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
        confirm_box_con.append($.workeera_delay_loader_html());

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action          : cfg.ajax_bulk_st,
                workeera_nonce    : cfg.nonce,
                application_ids : ids,
                new_status      : new_status
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err === 'failed') {
                    return;
                }

                if (response.error === '0') {
                    $.workeera_confirm_box.hide();
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-success'});

                    ids.forEach(function (app_id) {
                        var item_el = cfg.wrap.find(workeera_empapps_cfg.item_sel + '[data-application-id="' + app_id + '"]');
                        if (!item_el.length) return;
                        $.workeera_empapps_apply_status_to_item(item_el, response);
                        item_el.find(workeera_empapps_cfg.item_chk_sel).prop('checked', false);
                    });

                    // If the active status filter no longer matches the changed rows,
                    // refresh the list. Front-end uses AJAX list refresh; admin reloads
                    // the page (no AJAX list endpoint).
                    if (workeera_empapps_state.status_filter && workeera_empapps_state.status_filter !== response.new_status) {
                        if (cfg.context === 'admin') {
                            window.location.reload();
                        } else {
                            $.workeera_empapps_load({});
                        }
                    } else {
                        $.workeera_empapps_reset_bulk_state();
                        $.workeera_empapps_update_bulk_bar();
                    }
                } else {
                    $.workeera_submit_msg_alert({msg: response.msg || workeera_empapps_str.generic_err, class: 'workeera-alert-danger'});
                    $.workeera_remove_delay_loader({target: confirm_box_con});
                }
            },
            error: function () {
                $.workeera_remove_delay_loader({target: confirm_box_con});
            }
        });
    };

    /* ------------------------------------------------------------------ */
    /* Bulk delete (admin only)                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Delete multiple applications via AJAX (admin context only).
     *
     * @param {Array} ids Application IDs to delete.
     */
    $.workeera_empapps_bulk_delete = function (ids) {
        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg || !cfg.ajax_bulk_del) return;

        var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
        confirm_box_con.append($.workeera_delay_loader_html());

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action          : cfg.ajax_bulk_del,
                workeera_nonce    : cfg.nonce,
                application_ids : ids
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err === 'failed') {
                    return;
                }

                if (response.error === '0') {
                    $.workeera_confirm_box.hide();
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-success'});

                    ids.forEach(function (app_id) {
                        cfg.wrap.find(workeera_empapps_cfg.item_sel + '[data-application-id="' + app_id + '"]').remove();
                    });

                    $.workeera_empapps_reset_bulk_state();
                    $.workeera_empapps_update_bulk_bar();

                    // If the page is now empty, reload to drop pagination/showing-empty state.
                    if (!cfg.wrap.find(workeera_empapps_cfg.item_sel).length) {
                        window.location.reload();
                    }
                } else {
                    $.workeera_submit_msg_alert({msg: response.msg || workeera_empapps_str.generic_err, class: 'workeera-alert-danger'});
                    $.workeera_remove_delay_loader({target: confirm_box_con});
                }
            },
            error: function () {
                $.workeera_remove_delay_loader({target: confirm_box_con});
            }
        });
    };

    /* ------------------------------------------------------------------ */
    /* Generic Select2 AJAX init for jobs / employers filter dropdowns    */
    /* ------------------------------------------------------------------ */

    /**
     * Initialise a Select2 instance backed by a Workeera AJAX search action.
     *
     * @param {jQuery} target      Select element to enhance.
     * @param {string} ajax_action WP AJAX action name to query.
     * @param {string} placeholder Placeholder text for the empty state.
     */
    $.workeera_empapps_init_ajax_select2 = function (target, ajax_action, placeholder) {
        if (!target.length || target.hasClass('select2-hidden-accessible') || !ajax_action) {
            return;
        }

        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg) return;

        target.select2({
            width      : '100%',
            allowClear : true,
            placeholder: placeholder,
            ajax: {
                type    : 'POST',
                url     : workeera_func_str.ajax_url,
                dataType: 'json',
                delay   : 300,
                data: function (params) {
                    return {
                        action       : ajax_action,
                        workeera_nonce : cfg.nonce,
                        q            : params.term || '',
                        page         : params.page || 1
                    };
                },
                processResults: function (data, params) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                    if (nonce_err === 'failed') {
                        return {results: []};
                    }
                    params.page = params.page || 1;
                    return {
                        results    : data.results || [],
                        pagination : data.pagination || {more: false}
                    };
                },
                cache: false
            },
            minimumInputLength: 0,
            language: {
                searching   : function () { return workeera_empapps_str.select2_searching; },
                loadingMore : function () { return workeera_empapps_str.select2_loading_more; },
                noResults   : function () { return workeera_empapps_str.select2_no_results; }
            }
        });
    };

    /**
     * Initialise the job-filter Select2. Front-end uses preload-or-AJAX based on
     * data-job-select-mode. Admin always uses AJAX search.
     */
    $.workeera_empapps_init_job_select2 = function () {
        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg) return;

        var job_select = cfg.wrap.find(workeera_empapps_cfg.job_sel);
        if (!job_select.length || job_select.hasClass('select2-hidden-accessible')) {
            return;
        }

        var mode        = cfg.wrap.attr('data-job-select-mode') || 'preload';
        var placeholder = job_select.attr('data-placeholder') || workeera_empapps_str.all_jobs_lbl;

        if (mode === 'ajax') {
            $.workeera_empapps_init_ajax_select2(job_select, cfg.ajax_jobs, placeholder);
        } else {
            // Preload mode — local search-enabled Select2.
            job_select.select2({
                width      : '100%',
                allowClear : true,
                placeholder: placeholder
            });
        }
    };

    /**
     * Initialise the employer-filter Select2 (admin only).
     */
    $.workeera_empapps_init_employer_select2 = function () {
        var cfg = workeera_empapps_read_wrap_config();
        if (!cfg || !cfg.ajax_emps) return;

        var emp_select = cfg.wrap.find(workeera_empapps_cfg.employer_sel);
        if (!emp_select.length) return;

        var placeholder = emp_select.attr('data-placeholder') || workeera_empapps_str.all_employers_lbl;
        $.workeera_empapps_init_ajax_select2(emp_select, cfg.ajax_emps, placeholder);
    };

    /* ------------------------------------------------------------------ */
    /* Confirm-box helpers                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Open the confirm box and run the supplied callback when the user clicks "yes".
     *
     * @param {object} opts { title, message, yes_class, on_confirm }
     */
    $.workeera_empapps_open_confirm = function (opts) {
        $.workeera_confirm_box({
            title  : opts.title   || workeera_empapps_str.confirm_title,
            message: opts.message || '',
            buttons: {
                yes: {
                    title : opts.yes_label || workeera_empapps_str.confirm_btn,
                    class : opts.yes_class || 'workeera-confirm-btn',
                    action: opts.on_confirm || function () { $.workeera_confirm_box.hide(); }
                },
                no: {
                    title : workeera_empapps_str.cancel_btn,
                    class : 'workeera-cancel-act',
                    action: function () { $.workeera_confirm_box.hide(); }
                }
            }
        });
    };

    /* ------------------------------------------------------------------ */
    /* Event bindings                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Bind all delegated event handlers for the applications list and bulk bar.
     */
    $.workeera_empapps_bind_events = function () {
        var event_ns = '.workeera_empapps';
        var has_ajax_list = $(workeera_empapps_cfg.wrap_sel).find(workeera_empapps_cfg.holder_sel).length > 0;

        // Filter / sort / status changes — AJAX list refresh only when the wrap
        // has the AJAX list holder (front-end). Admin uses native form submission.
        if (has_ajax_list) {
            $('body').off('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.sort_sel)
                     .on('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.sort_sel, function () {
                $.workeera_empapps_read_filters();
                workeera_empapps_state.page = 1;
                $.workeera_empapps_load({});
            });
            $('body').off('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.status_sel)
                     .on('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.status_sel, function () {
                $.workeera_empapps_read_filters();
                workeera_empapps_state.page = 1;
                $.workeera_empapps_load({});
            });
            $('body').off('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.job_sel)
                     .on('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.job_sel, function () {
                $.workeera_empapps_read_filters();
                workeera_empapps_state.page = 1;
                $.workeera_empapps_load({});
            });

            // Debounced search.
            var search_timer = null;
            $('body').off('input' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.search_sel)
                     .on('input' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.search_sel, function () {
                clearTimeout(search_timer);
                search_timer = setTimeout(function () {
                    $.workeera_empapps_read_filters();
                    workeera_empapps_state.page = 1;
                    $.workeera_empapps_load({});
                }, 450);
            });

            // Pagination clicks (front-end custom pagination).
            $('body').off('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-usrjobs-pagination .workeera-usrjobs-page-nav')
                     .on('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-usrjobs-pagination .workeera-usrjobs-page-nav', function () {
                var page = parseInt($(this).attr('data-page'), 10) || 1;
                $.workeera_empapps_read_filters();
                $.workeera_empapps_load({page: page});
            });
        }

        // Item checkboxes — update bulk bar.
        $('body').off('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.item_chk_sel)
                 .on('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.item_chk_sel, function () {
            $.workeera_empapps_update_bulk_bar();
        });

        // Master checkbox — toggle all visible items.
        $('body').off('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.bulk_master_sel)
                 .on('change' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.bulk_master_sel, function () {
            var checked = $(this).is(':checked');
            $(workeera_empapps_cfg.wrap_sel).find(workeera_empapps_cfg.item_chk_sel).prop('checked', checked);
            $.workeera_empapps_update_bulk_bar();
        });

        // Per-item status dropdown trigger — toggle menu.
        $('body').off('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-empapps-status-trigger')
                 .on('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-empapps-status-trigger', function (e) {
            e.stopPropagation();
            var menu = $(this).siblings('.workeera-empapps-status-menu');
            $(workeera_empapps_cfg.wrap_sel).find('.workeera-empapps-status-menu').not(menu).addClass('workeera-hide');
            menu.toggleClass('workeera-hide');
        });

        // Per-item actions dropdown trigger (Download CV / View Cover Letter /
        // addon links) — toggle menu.
        $('body').off('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-appactions-trigger')
                 .on('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-appactions-trigger', function (e) {
            e.stopPropagation();
            var menu = $(this).siblings('.workeera-appactions-menu');
            $(workeera_empapps_cfg.wrap_sel).find('.workeera-appactions-menu').not(menu).addClass('workeera-hide');
            menu.toggleClass('workeera-hide');
        });

        // View Cover Letter — open the shared confirm/loader popup and fetch the
        // cover letter text on demand (same pattern as the addon reason popup).
        $('body').off('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-coverletter-btn')
                 .on('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-coverletter-btn', function () {
            var $btn = $(this);
            var application_id = parseInt($btn.attr('data-application-id'), 10) || 0;

            $btn.closest('.workeera-appactions-menu').addClass('workeera-hide');

            if (application_id < 1 || typeof $.workeera_confirm_box !== 'function') {
                return;
            }

            // Nonce from the surrounding list wrap (front dashboard prints the
            // front nonce, wp-admin the admin nonce); front localized nonce is
            // the fallback.
            var nonce = $btn.closest('[data-nonce]').attr('data-nonce') || '';
            if (nonce === '' && typeof workeera_func_str !== 'undefined') {
                nonce = workeera_func_str.nonce;
            }

            $.workeera_confirm_box({
                message: '<div class="workeera-coverletter-popup">' + $.workeera_delay_loader_html() + '</div>',
                buttons: {
                    close: {
                        title: workeera_empapps_str.close_btn,
                        class: 'workeera-coverletter-close-btn',
                        action: function () {
                            $.workeera_confirm_box.hide();
                        }
                    }
                }
            });

            var $popup = $('#workeera-confirm-popup').find('.workeera-coverletter-popup');

            // Custom AJAX router — admin root in wp-admin, site root on the front.
            var router_url = (typeof workeera_adminscript_str !== 'undefined' && workeera_adminscript_str.admin_url)
                ? workeera_adminscript_str.admin_url
                : (typeof workeera_func_str !== 'undefined' ? workeera_func_str.site_url : '');

            $.ajax({
                type: 'POST',
                url: router_url,
                dataType: 'json',
                data: {
                    workeera_action: 'workeera_ajax_get_cover_letter',
                    workeera_nonce: nonce,
                    application_id: application_id
                },
                success: function (response) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                    if (nonce_err === 'failed') {
                        return;
                    }

                    $.workeera_remove_delay_loader({target: $popup});

                    if (response.error !== '0') {
                        $popup.append($('<p class="workeera-coverletter-error"></p>').text(response.msg || workeera_empapps_str.generic_err));
                        return;
                    }

                    // Plain-text nodes only — applicant name and cover letter are
                    // never inserted as HTML.
                    var $head = $('<div class="workeera-coverletter-head"></div>')
                        .append($('<strong class="workeera-coverletter-title"></strong>').text(workeera_empapps_str.cover_letter_title));
                    if (response.applicant) {
                        $head.append($('<span class="workeera-coverletter-applicant"></span>').text(String(response.applicant)));
                    }

                    $popup.append($head)
                          .append($('<div class="workeera-coverletter-body"></div>').text(String(response.cover_letter || '')));
                },
                error: function () {
                    $.workeera_remove_delay_loader({target: $popup});
                    $popup.append($('<p class="workeera-coverletter-error"></p>').text(workeera_empapps_str.generic_err));
                }
            });
        });

        // Per-item status menu item — confirm + AJAX.
        $('body').off('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-empapps-status-menu-item')
                 .on('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' .workeera-empapps-status-menu-item', function () {
            var btn        = $(this);
            var item_el    = btn.closest(workeera_empapps_cfg.item_sel);
            var app_id     = parseInt(item_el.attr('data-application-id'), 10) || 0;
            var new_status = btn.attr('data-action-status') || '';
            var current    = item_el.attr('data-current-status') || '';

            btn.closest('.workeera-empapps-status-menu').addClass('workeera-hide');

            if (app_id < 1 || !new_status || new_status === current) {
                return;
            }

            var status_lbl = $.workeera_empapps_status_label(new_status);
            var msg = workeera_empapps_str.single_confirm_msg.replace('%s', status_lbl);

            $.workeera_empapps_open_confirm({
                title      : workeera_empapps_str.confirm_title,
                message    : msg,
                on_confirm : function () {
                    $.workeera_empapps_update_single_status(app_id, new_status, item_el);
                }
            });
        });

        // Close any open status / actions menu on outside click / Escape.
        $('body').off('click' + event_ns + '_doc')
                 .on('click' + event_ns + '_doc', function () {
            $(workeera_empapps_cfg.wrap_sel).find('.workeera-empapps-status-menu, .workeera-appactions-menu').addClass('workeera-hide');
        });
        $(document).off('keydown' + event_ns + '_esc')
                   .on('keydown' + event_ns + '_esc', function (e) {
            if (e.key === 'Escape') {
                $(workeera_empapps_cfg.wrap_sel).find('.workeera-empapps-status-menu, .workeera-appactions-menu').addClass('workeera-hide');
            }
        });

        // Bulk Apply button — handles both status changes and (admin-only) delete.
        $('body').off('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.bulk_apply_sel)
                 .on('click' + event_ns, workeera_empapps_cfg.wrap_sel + ' ' + workeera_empapps_cfg.bulk_apply_sel, function (e) {
            e.preventDefault();

            var wrap   = $(workeera_empapps_cfg.wrap_sel);
            var action = wrap.find(workeera_empapps_cfg.bulk_action_sel).first().val() || '';
            var ids    = $.workeera_empapps_collect_selected();

            if (ids.length === 0) {
                $.workeera_submit_msg_alert({msg: workeera_empapps_str.no_selection_err, class: 'workeera-alert-danger'});
                return;
            }
            if (!action) {
                $.workeera_submit_msg_alert({msg: workeera_empapps_str.no_action_err, class: 'workeera-alert-danger'});
                return;
            }

            if (action === 'delete') {
                var del_msg = workeera_empapps_str.bulk_delete_confirm_msg.replace('%s', ids.length);
                $.workeera_empapps_open_confirm({
                    title     : workeera_empapps_str.confirm_title,
                    message   : del_msg,
                    yes_label : workeera_empapps_str.delete_confirm_btn,
                    yes_class : 'workeera-delitm-btn',
                    on_confirm: function () {
                        $.workeera_empapps_bulk_delete(ids);
                    }
                });
                return;
            }

            // Status-change branch (reviewed/shortlisted/rejected).
            var status_lbl = $.workeera_empapps_status_label(action);
            var msg = workeera_empapps_str.bulk_confirm_msg.replace('%1$s', ids.length).replace('%2$s', status_lbl);

            $.workeera_empapps_open_confirm({
                title      : workeera_empapps_str.confirm_title,
                message    : msg,
                on_confirm : function () {
                    $.workeera_empapps_update_bulk_status(ids, action);
                }
            });
        });
    };

    /* ------------------------------------------------------------------ */
    /* On document ready                                                  */
    /* ------------------------------------------------------------------ */

    $(document).ready(function () {

        var wrap = $(workeera_empapps_cfg.wrap_sel);
        if (!wrap.length) {
            return;
        }

        var has_ajax_list = wrap.find(workeera_empapps_cfg.holder_sel).length > 0;

        // Front-end-only: pre-fill state from preselected job (e.g., from manage-jobs link).
        if (has_ajax_list) {
            var preselected_job = parseInt(wrap.attr('data-preselected-job'), 10) || 0;
            if (preselected_job > 0) {
                workeera_empapps_state.job_filter = preselected_job;
            }
        }

        // Admin context: WP_List_Table renders master checkboxes with WP-default
        // IDs. Tag them with our class so the existing handlers pick them up.
        wrap.find('#cb-select-all-1, #cb-select-all-2').addClass(workeera_empapps_cfg.bulk_master_sel.replace(/^\./, ''));

        $.workeera_empapps_init_job_select2();
        $.workeera_empapps_init_employer_select2();
        $.workeera_empapps_bind_events();

        if (has_ajax_list) {
            $.workeera_empapps_read_filters();
            $.workeera_empapps_load({});
        }

    });

})(jQuery);
