(function ($) {
    'use strict';

    /**
     * Configurations for each candidate-side dashboard list section.
     * Both applied-jobs and recommended-jobs share the same load/pagination
     * pipeline; only selectors and the AJAX action differ.
     */
    var workeera_candjobs_configs = {
        applied: {
            wrap_sel        : '.workeera-appliedjobs-wrap',
            holder_sel      : '.workeera-applied-list-holder',
            list_sel        : '.workeera-applied-list',
            empty_sel       : '.workeera-applied-empty',
            search_sel      : '.workeera-applied-search-input',
            sort_sel        : '.workeera-applied-sort-select',
            filter_sel      : '.workeera-applied-status-select',
            ajax_action     : 'workeera_applied_jobs_list'
        },
        recommended: {
            wrap_sel        : '.workeera-recommended-jobs-wrap',
            holder_sel      : '.workeera-recommended-list-holder',
            list_sel        : '.workeera-recommended-list',
            empty_sel       : '.workeera-recommended-empty',
            search_sel      : '.workeera-recommended-search-input',
            sort_sel        : '.workeera-recommended-sort-select',
            filter_sel      : '.workeera-recommended-jobtype-select',
            ajax_action     : 'workeera_recommended_jobs_list'
        }
    };

    /**
     * Per-section runtime state. Tracks the current page + filter values
     * so the load helper can build a single AJAX request.
     */
    var workeera_candjobs_state = {
        applied: {
            page          : 1,
            search        : '',
            sort          : '',
            status_filter : ''
        },
        recommended: {
            page          : 1,
            search        : '',
            sort          : '',
            status_filter : ''
        }
    };

    /**
     * Return the shell-injected loader markup for the given target container.
     *
     * @param  {Object} params        Parameters.
     * @param  {jQuery} params.target Container holding the loader template.
     * @return {string}               Loader HTML markup.
     */
    $.workeera_candjobs_delay_loader_html = function (params) {
        var target = params.target;
        return target.find('.workeera-init-loder-holdr').html();
    };

    /**
     * Remove the inline list loader from the given target container.
     *
     * @param {Object} params        Parameters.
     * @param {jQuery} params.target Container holding the loader.
     */
    $.workeera_candjobs_remove_loader = function (params) {
        var target = params.target;
        target.find('.workeera-usrjobs-loder-con .workeera-listin-loadr').remove();
    };

    /**
     * Read current filter values from the DOM into state for the given section.
     *
     * @param {string} section Section key ('applied' or 'recommended').
     */
    $.workeera_candjobs_read_filters = function (section) {
        var cfg = workeera_candjobs_configs[section];
        if (!cfg) {
            return;
        }
        var wrap = $(cfg.wrap_sel);
        if (!wrap.length) {
            return;
        }
        workeera_candjobs_state[section].search        = $.trim(wrap.find(cfg.search_sel).val() || '');
        workeera_candjobs_state[section].sort          = wrap.find(cfg.sort_sel).val() || '';
        workeera_candjobs_state[section].status_filter = wrap.find(cfg.filter_sel).val() || '';
    };

    /**
     * Fetch a candidate jobs list section via AJAX and inject the resulting HTML.
     *
     * @param {string} section Section key ('applied' or 'recommended').
     * @param {Object} params  Optional overrides such as { page: number }.
     */
    $.workeera_candjobs_load = function (section, params) {
        params = params || {};

        var cfg = workeera_candjobs_configs[section];
        if (!cfg) {
            return;
        }

        var wrap = $(cfg.wrap_sel);
        if (!wrap.length) {
            return;
        }

        var holder = wrap.find(cfg.holder_sel);
        if (!holder.length) {
            return;
        }

        if (typeof params.page !== 'undefined') {
            workeera_candjobs_state[section].page = parseInt(params.page, 10) || 1;
        }

        var nonce = wrap.attr('data-nonce') || workeera_func_str.nonce;

        var list_con  = holder.find(cfg.list_sel);
        var empty_con = holder.find(cfg.empty_sel);
        var pagination_con = holder.find('.workeera-usrjobs-pagination');

        list_con.addClass('workeera-hide');
        empty_con.addClass('workeera-hide');

        if (!holder.find('.workeera-usrjobs-loder-con .workeera-listin-loadr').length) {
            holder.find('.workeera-usrjobs-loder-con').append($.workeera_candjobs_delay_loader_html({target: holder}));
        }

        $.ajax({
            type    : 'POST',
            url     : workeera_func_str.ajax_url,
            dataType: 'json',
            data    : {
                action        : cfg.ajax_action,
                workeera_nonce  : nonce,
                page_num      : workeera_candjobs_state[section].page,
                search        : workeera_candjobs_state[section].search,
                sort          : workeera_candjobs_state[section].sort,
                status_filter : workeera_candjobs_state[section].status_filter
            },
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err === 'failed') {
                    return;
                }

                $.workeera_candjobs_remove_loader({target: holder});

                if (response.error !== '0') {
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
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
                $.workeera_candjobs_remove_loader({target: holder});
                list_con.removeClass('workeera-hide');
            }
        });
    };

    /**
     * Bind change/input/click handlers for a section's filter bar and pagination.
     *
     * Uses delegated bindings so dynamically-injected pagination buttons work.
     *
     * @param {string} section Section key ('applied' or 'recommended').
     */
    $.workeera_candjobs_bind_events = function (section) {
        var cfg = workeera_candjobs_configs[section];
        if (!cfg) {
            return;
        }

        var search_timer = null;
        var event_ns = '.workeera_candjobs_' + section;

        // Sort dropdown — reset to page 1.
        $('body').off('change' + event_ns, cfg.wrap_sel + ' ' + cfg.sort_sel)
                 .on('change' + event_ns, cfg.wrap_sel + ' ' + cfg.sort_sel, function () {
            $.workeera_candjobs_read_filters(section);
            workeera_candjobs_state[section].page = 1;
            $.workeera_candjobs_load(section, {});
        });

        // Filter dropdown — reset to page 1.
        $('body').off('change' + event_ns, cfg.wrap_sel + ' ' + cfg.filter_sel)
                 .on('change' + event_ns, cfg.wrap_sel + ' ' + cfg.filter_sel, function () {
            $.workeera_candjobs_read_filters(section);
            workeera_candjobs_state[section].page = 1;
            $.workeera_candjobs_load(section, {});
        });

        // Debounced search.
        $('body').off('input' + event_ns, cfg.wrap_sel + ' ' + cfg.search_sel)
                 .on('input' + event_ns, cfg.wrap_sel + ' ' + cfg.search_sel, function () {
            clearTimeout(search_timer);
            search_timer = setTimeout(function () {
                $.workeera_candjobs_read_filters(section);
                workeera_candjobs_state[section].page = 1;
                $.workeera_candjobs_load(section, {});
            }, 450);
        });

        // Pagination — buttons are injected dynamically so use delegation.
        $('body').off('click' + event_ns, cfg.wrap_sel + ' .workeera-usrjobs-pagination .workeera-usrjobs-page-nav')
                 .on('click' + event_ns, cfg.wrap_sel + ' .workeera-usrjobs-pagination .workeera-usrjobs-page-nav', function () {
            var page = parseInt($(this).attr('data-page'), 10) || 1;
            $.workeera_candjobs_read_filters(section);
            $.workeera_candjobs_load(section, {page: page});
        });
    };

    /**
     * Bind the withdraw-application click handler on the applied-jobs list.
     */
    $.workeera_candjobs_bind_withdraw = function () {
        $('body').off('click.workeera_candjobs_withdraw', '.workeera-applied-list .workeera-applied-withdraw-btn')
                 .on('click.workeera_candjobs_withdraw', '.workeera-applied-list .workeera-applied-withdraw-btn', function () {
            var this_btn       = $(this);
            var application_id = parseInt(this_btn.attr('data-application-id'), 10) || 0;
            var nonce          = $('.workeera-appliedjobs-wrap').attr('data-nonce') || workeera_func_str.nonce;

            if (application_id < 1) {
                return;
            }

            $.workeera_confirm_box({
                title  : workeera_candjobs_str.withdraw_confirm_title,
                message: workeera_candjobs_str.withdraw_confirm_msg,
                buttons: {
                    yes: {
                        title : workeera_candjobs_str.withdraw_confirm_btn,
                        class : 'workeera-delitm-btn',
                        action: function () {
                            var item_parent     = this_btn.closest('.workeera-applied-item');
                            var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
                            confirm_box_con.append($.workeera_delay_loader_html());

                            $.ajax({
                                type    : 'POST',
                                url     : workeera_func_str.ajax_url,
                                dataType: 'json',
                                data    : {
                                    action         : 'workeera_withdraw_application',
                                    workeera_nonce   : nonce,
                                    application_id : application_id
                                },
                                success: function (response) {
                                    var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                                    if (nonce_err === 'failed') {
                                        return;
                                    }

                                    if (response.error === '0') {
                                        $.workeera_confirm_box.hide();
                                        $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-success'});

                                        // The application stays in the list — mark it withdrawn
                                        // in place: fade the row, swap the status badge and
                                        // drop the withdraw button (withdrawing has no undo).
                                        item_parent.addClass('workeera-applied-item-withdrawn');
                                        item_parent.find('.workeera-applied-status-badge')
                                                   .attr('class', 'workeera-applied-status-badge workeera-applied-status-withdrawn')
                                                   .text(workeera_candjobs_str.withdrawn_lbl);
                                        item_parent.find('.workeera-applied-withdraw-btn').remove();
                                    } else {
                                        $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
                                        $.workeera_remove_delay_loader({target: confirm_box_con});
                                    }
                                },
                                error: function () {
                                    $.workeera_remove_delay_loader({target: confirm_box_con});
                                }
                            });
                        }
                    },
                    no: {
                        title : workeera_candjobs_str.cancel_btn,
                        class : 'workeera-cancel-act',
                        action: function () {
                            $.workeera_confirm_box.hide();
                        }
                    }
                }
            });
        });
    };

    /*
    * On document ready — initialise whichever candidate section is on the page.
    */
    $(document).ready(function () {

        if ($('.workeera-applied-list-holder').length) {
            $.workeera_candjobs_bind_events('applied');
            $.workeera_candjobs_bind_withdraw();
            $.workeera_candjobs_load('applied', {});
        }

        if ($('.workeera-recommended-list-holder').length) {
            $.workeera_candjobs_bind_events('recommended');
            $.workeera_candjobs_load('recommended', {});
        }

    });

})(jQuery);
