/**
 * Workeera common front-end helpers.
 *
 * Provides shared utilities used across the plugin: modal alert / confirm
 * popups, AJAX form handling, Select2 wrappers (locations, taxonomy terms,
 * skills filtering), the custom date-input calendar widget, and other
 * UI helpers exposed on the jQuery namespace as $.workeera_*.
 */
(function ($) {
    'use strict';

    /**
     * Display a one-button alert modal.
     *
     * @param {Object} params         Configuration.
     * @param {string} params.message HTML message to render inside the modal.
     * @return {boolean|void}         False when an alert is already open.
     */
    $.workeera_alert_box = function (params) {

        if ($('#workeera-alert-box').length) {
            return false;
        }

        var markup = [
            '<div id="workeera-alert-popup" class="workeera-modal-popup workeera-popup-visible workeera-forceclse-popup workeera-alertmsg-modal">',
            '<div class="workeera-modal-container">',
            '<div class="workeera-modal-inner-area">',
            '<div class="workeera-modal-content-area">',
            '<div class="workeera-modalinit-con workeera-confirmsg-con">',
            '<div class="workeera-alert-msg-txt">', params.message, '</div>',
            '<div class="workeera-confirm-popup-btns">',
            '<a class="workeera-btn workeera-alert-close">' + workeera_func_str.conf_ok_lbl + '</a>',
            '</div></div></div></div></div></div>'
        ].join('');

        $(markup).appendTo('body');
        $.workeera_modal_on_open_body({
            popup_type: 'alertmsg'
        });

        var alert_close_button = $('#workeera-alert-popup .workeera-alert-close');

        alert_close_button.click(function () {

            $.workeera_alert_box.hide();
            return false;
        });
    }

    /**
     * Close and remove the alert modal opened by $.workeera_alert_box.
     */
    $.workeera_alert_box.hide = function () {
        $.workeera_modal_popup_close();
        $('#workeera-alert-popup').remove();
    }

    /**
     * Display a confirmation modal with a configurable set of buttons.
     *
     * @param {Object} params         Configuration.
     * @param {string} params.message HTML message to render inside the modal.
     * @param {Object} params.buttons Map of button keys → { title, class, action }.
     */
    $.workeera_confirm_box = function (params) {

        if ($('#workeera-confirm-popup').length > 0) {
            $('#workeera-confirm-popup').remove();
        }

        var conf_btns_html = '';
        $.each(params.buttons, function (name, obj) {

            conf_btns_html += '<button class="workeera-confirm-button ' + obj['class'] + '">' + obj['title'] + '</button>';

            if (!obj.action) {
                obj.action = function () { };
            }
        });

        var markup = [
            '<div id="workeera-confirm-popup" class="workeera-modal-popup workeera-popup-visible workeera-forceclse-popup workeera-confirmsg-modal">',
            '<div class="workeera-modal-container">',
            '<div class="workeera-modal-inner-area">',
            '<div class="workeera-modal-content-area">',
            '<div class="workeera-modalinit-con workeera-confirmsg-con">',
            '<span class="workeera-confrm-msg-txt">', params.message, '</span>',
            '<div class="workeera-confirm-popup-btns">',
            conf_btns_html,
            '</div></div></div></div></div></div>'
        ].join('');

        $(markup).appendTo('body');
        $.workeera_modal_on_open_body({
            popup_type: 'confirmsg'
        });

        var buttons = $('#workeera-confirm-popup .workeera-confirm-button'),
            i = 0;

        $.each(params.buttons, function (name, obj) {
            buttons.eq(i++).click(function () {

                obj.action();
                return false;
            });
        });
    }

    /**
     * Close and remove the confirmation modal opened by $.workeera_confirm_box.
     */
    $.workeera_confirm_box.hide = function () {
        $.workeera_modal_popup_close();
        $('#workeera-confirm-popup').remove();
    }

    $(document).on('mousedown', '.workeera-modal-popup', function (e) {
        //
        if (!$(this).hasClass('workeera-forceclse-popup')) {

            var is_close = true;
            var this_dom = e.target;
            var thisdom_obj = $(this_dom);

            if (thisdom_obj.hasClass('workeera-modal-content-area') || thisdom_obj.parents('.workeera-modal-content-area').length > 0) {
                is_close = false;
            }
            if (is_close === true) {
                $.workeera_modal_popup_close();
            }
        }
    });

    $(document).on('click', '.workeera-popup-closebtn', function (ev) {

        ev.preventDefault();
        $.workeera_modal_popup_close();
    });

    $(document).on('keypress keyup blur', '.workeera-only-number', function (event) {
        var _this = $(this);
        $(this).val(_this.val().replace(/[^\d,]+/g, ""));
        if ((event.which < 48 || event.which > 57)) {
            event.preventDefault();
        }
    });

    $(document).on('submit', '.workeera-user-form', function (ev) {

        ev.preventDefault();

        var this_form = $(this);
        var from_element = this_form[0];
        var form_data = new FormData(from_element);

        if (!this_form.hasClass('ajax-processing')) {
            if (this_form.hasClass('workeera-withbtn-inline-lodr') && this_form.find('button[type="submit"]').length) {
                this_form.find('button[type="submit"]').append($.workeera_delay_inlineloader_html());
            } else {
                this_form.append($.workeera_delay_loader_html());
            }

            $.ajax({
                type: "POST",
                dataType: "json",
                url: workeera_func_str.ajax_url,
                processData: false,
                contentType: false,
                data: form_data,
                success: function (data) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                    if (nonce_err == 'failed') {
                        return false;
                    }

                    // after success callback for other actions
                    $.workeera_ajax_success_callback({
                        req_data: form_data,
                        result: data,
                        parent_con: this_form
                    });
                    if (data.error == '0') {
                        $.workeera_submit_msg_alert({
                            msg: data.msg,
                            class: 'workeera-alert-success'
                        });
                    } else if (data.error == '2') {
                        $.workeera_submit_msg_alert({
                            msg: data.msg
                        });
                    } else {
                        $.workeera_submit_msg_alert({
                            msg: data.msg,
                            class: 'workeera-alert-danger'
                        });
                    }
                    if (typeof data.redirect !== 'undefined' && data.redirect !== undefined && data.redirect != '') {
                        if (data.redirect == 'same') {
                            window.location.reload();
                        } else {
                            window.location.replace(data.redirect);
                        }
                        return false;
                    }
                    this_form.removeClass('ajax-processing');
                    $.workeera_remove_delay_loader({
                        target: this_form
                    });
                },
                error: function () {
                    this_form.removeClass('ajax-processing');
                    $.workeera_remove_delay_loader({
                        target: this_form
                    });
                }
            });
        }

        this_form.addClass('ajax-processing');
    });

    // For switch button in form field
    $('body').on('click', '.workeera-form-switch input[type="checkbox"]', function() {
        if ($(this).is(":checked")) {
            $(this).parents('.workeera-form-switch').find('input[type="hidden"]').val('on');
        } else {
            $(this).parents('.workeera-form-switch').find('input[type="hidden"]').val('off');
        }
    });

    // For buttons in form field
    $('body').on('change', '.workeera-form-optbtn input[type="checkbox"], .workeera-form-optbtn input[type="radio"]', function() {
        var this_btn = $(this);
        var btns_con = this_btn.closest('.workeera-form-optbtns');
        btns_con.find('input[type="checkbox"],input[type="radio"]').each(function() {
            if ($(this).is(":checked")) {
                if (!$(this).closest('.workeera-form-optbtn').hasClass('workeera-chked-optbtn')) {
                    $(this).closest('.workeera-form-optbtn').addClass('workeera-chked-optbtn');
                }
            } else {
                $(this).closest('.workeera-form-optbtn').removeClass('workeera-chked-optbtn');
            }
        });
    });

    /**
     * Dispatch a server-supplied callback after an AJAX request succeeds.
     *
     * If the JSON response carries a `callback` property naming a registered
     * $.workeera_* helper, invoke it with the original request data, result,
     * and the containing element.
     *
     * @param {Object}      params            Configuration.
     * @param {*}           params.req_data   Original request payload.
     * @param {Object}      params.result     AJAX response object.
     * @param {jQuery|null} params.parent_con Optional parent container.
     */
    $.workeera_ajax_success_callback = function (params) {
        let req_data = params.req_data;
        let result = params.result;
        let parent_con = params.parent_con || null;

        if (typeof result.callback !== 'undefined') {
            var callback_function = result.callback;
            $[callback_function]({
                req_data: req_data,
                result: result,
                parent_con: parent_con
            });
        }
    }

    /**
     * Append a transient toast-style message to the body.
     *
     * @param {Object} params       Configuration.
     * @param {string} params.msg   HTML message to show.
     * @param {string} params.class Optional alert class (defaults to workeera-alert-info).
     */
    $.workeera_submit_msg_alert = function (params) {
        let msg = params.msg;
        let alert_class = params.class || 'workeera-alert-info';
        var id = Math.floor(Math.random() * 1000000) + 1;
        if (!$('body').find('.workeera-alert-msg-wrap').length) {
            $('body').append('<div class="workeera-alert-msg-wrap"></div>');
        }
        $('body').find('.workeera-alert-msg-wrap').append('<div id="workeera-alert-' + id + '" class="workeera-alert-msg ' + alert_class + '">' + msg + '</div>');
        setTimeout(function () { $('#workeera-alert-' + id).addClass('workeera-alert-hide'); }, 5000);
        setTimeout(function () { $('#workeera-alert-' + id).remove(); }, 6000);
    }

    /**
     * Reload the page when an AJAX response signals a failed nonce check.
     *
     * @param {Object} params        Configuration.
     * @param {Object} params.result AJAX response object.
     * @return {string|void}         'failed' when the nonce check failed.
     */
    $.workeera_ajax_security_err_reload = function (params) {
        let result = params.result;
        if (result.error !== undefined && typeof result.error !== 'undefined' && result.error == 'security_failed') {
            window.location.reload();
            return 'failed';
        }
    }

    /**
     * Return the HTML for the block-level loading spinner overlay.
     *
     * @return {string} Loader markup.
     */
    $.workeera_delay_loader_html = function () {
        let loader_html = '<div class="workeera-loder-con"><div class="workeera-loder-iner"><div class="workeera-loader"></div></div></div>';

        return loader_html;
    }

    /**
     * Return the HTML for the inline (button-sized) loading spinner.
     *
     * @return {string} Loader markup.
     */
    $.workeera_delay_inlineloader_html = function () {
        let loader_html = '<span class="workeera-loder-con inline-loder"><span class="workeera-loder-iner"><span class="workeera-loader"></span></span></span>';

        return loader_html;
    }

    /**
     * Remove any loading spinner inserted by the delay-loader helpers.
     *
     * @param {Object} params        Configuration.
     * @param {jQuery} params.target Container to clean up.
     */
    $.workeera_remove_delay_loader = function (params) {
        let target_con = params.target;
        target_con.find('.workeera-loder-con').remove();
    }

    /**
     * Mark the body as modal-active and compensate for the removed scrollbar.
     *
     * @param {Object} [params]            Configuration.
     * @param {string} [params.popup_type] Reserved popup-type identifier.
     */
    $.workeera_modal_on_open_body = function (params = {}) {
        let popup_type = params.popup_type || 'common';
        
        const $body = $('body');
        const scroll_width = $body.width();

        $body.addClass('workeera-popup-active');

        // Calculate the scrollbar width by comparing new width with the initial width
        const new_scroll_width = $body.width();
        const scrollbar_width = new_scroll_width - scroll_width;

        $body.css('padding-right', `${scrollbar_width}px`);
    }

    /**
     * Show an existing modal popup by element id.
     *
     * @param {Object} params        Configuration.
     * @param {string} params.target Element id of the modal to open.
     * @param {string} [params.class] Additional CSS class to apply (defaults to workeera-popup-maincon).
     */
    $.workeera_modal_popup_open = function (params) {
        let target = params.target;
        let popup_class = params.class || 'workeera-popup-maincon';
        $('#' + target).attr('class', 'workeera-modal-popup workeera-popup-hide ' + popup_class);
        $('#' + target).removeClass('workeera-popup-hide').addClass('workeera-popup-visible');
        $.workeera_modal_on_open_body();
    }

    /**
     * Hide any open modal popup and restore the body scroll state.
     */
    $.workeera_modal_popup_close = function () {
        $('.workeera-modal-popup').attr('class', 'workeera-modal-popup workeera-popup-hide');
        $('body').removeClass('workeera-popup-active');
        $('body').css('padding-right', '');
    }

    /**
     * Initialise a Select2 dropdown with the shared default options.
     *
     * @param {Object}  [params]         Configuration.
     * @param {jQuery}  params.target    The <select> element to enhance.
     * @param {boolean} [params.search]  Enable the in-dropdown search box.
     */
    $.workeera_select2_common = function (params = {}) {
        let target = params.target;
        let enable_search = params.search || false;

        let select_options = {
            width: '100%',
            allowClear: true,
        };
        if (enable_search === false) {
            select_options.minimumResultsForSearch = 'Infinity';
        }
        if (typeof target.attr('data-placeholder') !== 'undefined') {
            select_options.placeholder = target.attr('placeholder');
        }
        target.select2(select_options);
    }

    /**
     * Initialise (or re-initialise) the city select2 field.
     * Reads country_id from params or from the city element's data-country-id attribute.
     * When country_id is 0 the handler returns cities across all countries.
     *
     * @param {jQuery} city_target  The city <select> element (.workeera-location-city-select2).
     * @param {number} country_id   The currently selected country ID (0 = no filter).
     */
    $.workeera_location_city_select2 = function (city_target, country_id) {
        // Destroy any previous select2 instance before re-initialising.
        if (city_target.hasClass('select2-hidden-accessible')) {
            city_target.select2('destroy');
        }

        country_id = parseInt(country_id) || 0;
        var placeholder = city_target.attr('data-placeholder') || '';

        city_target.select2({
            width: '100%',
            allowClear: true,
            placeholder: placeholder,
            ajax: {
                type: 'POST',
                url: workeera_func_str.ajax_url,
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        country_id   : country_id,
                        q            : params.term || '',
                        page         : params.page || 1,
                        workeera_nonce : workeera_func_str.nonce,
                        action: 'workeera_get_cities_by_country'
                    };
                },
                processResults: function (data, params) {
                    var nonce_err = $.workeera_ajax_security_err_reload({ result: data });
                    if (nonce_err === 'failed') {
                        return { results: [] };
                    }
                    params.page = params.page || 1;
                    return {
                        results    : data.results || [],
                        pagination : data.pagination || { more: false }
                    };
                },
                cache: false
            },
            minimumInputLength: 0,
            language: {
                searching: function () {
                    return workeera_func_str.select2_searching;
                },
                loadingMore: function () {
                    return workeera_func_str.select2_loading_more;
                },
                noResults: function () {
                    return workeera_func_str.select2_no_results;
                }
            }
        });
    };

    /**
     * Initialise location fields (country + city) inside a given container.
     * — Inits the city select2 using the saved country_id from data-country-id.
     * — Binds the country change event to re-init city select2 and clear the city value.
     *
     * @param {jQuery|null} container  Optional scoping container (defaults to whole document).
     */
    $.workeera_init_location_fields = function (container) {
        var scope = container && container.length ? container : $(document);

        scope.find('.workeera-location-city-select2').each(function () {
            var city_target  = $(this);
            var saved_country_id = parseInt(city_target.attr('data-country-id')) || 0;

            // Initialise city select2 with the saved country filter.
            $.workeera_location_city_select2(city_target, saved_country_id);
        });

        // Country change — re-init city select2 with the new country and clear selection.
        scope.find('.workeera-location-country-con').each(function () {
            var country_con = $(this);

            country_con.off('change.workeera_location').on('change.workeera_location', 'select', function () {
                var selected_country_id = parseInt($(this).val()) || 0;

                // Find the sibling city field inside the same location wrapper.
                var location_wrapper = country_con.closest('.workeera-custom-location-field');
                var city_target      = location_wrapper.find('.workeera-location-city-select2');

                if (!city_target.length) {
                    return;
                }

                // Clear saved city value then re-init with the new country.
                city_target.val(null).trigger('change');
                $.workeera_location_city_select2(city_target, selected_country_id);
            });
        });
    };

    /**
     * Initialise a taxonomy term select2 field.
     *
     * Reads the following data-attributes from the <select> element:
     *   data-tax-slug     — taxonomy slug passed to the PHP AJAX handler.
     *   data-placeholder  — placeholder text.
     *   data-multi        — "1" if multiselect.
     *   data-creatable    — "1" if new items can be typed and created (skills).
     *
     * @param {jQuery} target  The <select> element.
     */
    $.workeera_tax_term_select2 = function (target) {
        var tax_slug    = target.attr('data-tax-slug') || '';
        var placeholder = target.attr('data-placeholder') || '';
        var is_multi    = target.attr('data-multi') === '1';
        var creatable   = target.attr('data-creatable') === '1';

        var select2_opts = {
            width: '100%',
            allowClear: true,
            placeholder: placeholder,
            ajax: {
                type: 'POST',
                url: workeera_func_str.ajax_url,
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    var data = {
                        tax_slug      : tax_slug,
                        q             : params.term || '',
                        page          : params.page || 1,
                        workeera_nonce  : workeera_func_str.nonce,
                        action : 'workeera_get_taxonomy_terms'
                    };
                    // For skills, forward the paired sector value so the
                    // server can scope results to skills linked to that sector
                    // (plus skills with no sector association).
                    if (tax_slug === 'skills') {
                        var sid = $.workeera_get_paired_sector_id(target);
                        if (sid > 0) {
                            data.sector_id = sid;
                        }
                    }
                    if (target.hasClass('workeera-term-with-slugs')) {
                        data.with_slugs = 'yes';
                    }
                    return data;
                },
                processResults: function (data, params) {
                    var nonce_err = $.workeera_ajax_security_err_reload({ result: data });
                    if (nonce_err === 'failed') {
                        return { results: [] };
                    }
                    params.page = params.page || 1;
                    return {
                        results    : data.results || [],
                        pagination : data.pagination || { more: false }
                    };
                },
                // Cache:false because skills results depend on the live sector
                // value; with cache:true Select2 would reuse the previous list
                // after the sector has changed.
                cache: ( tax_slug !== 'skills' )
            },
            minimumInputLength: 0,
            language: {
                inputTooShort: function () {
                    return workeera_func_str.type_to_search;
                },
                searching: function () {
                    return workeera_func_str.select2_searching;
                },
                loadingMore: function () {
                    return workeera_func_str.select2_loading_more;
                },
                noResults: function () {
                    return workeera_func_str.select2_no_results;
                }
            }
        };

        // For skills: allow creating new items by typing.
        if (creatable) {
            select2_opts.tags = true;
            select2_opts.createTag = function (params) {
                var term = $.trim(params.term);
                if (term === '') {
                    return null;
                }
                return {
                    id   : '__new__' + term,
                    text : term,
                    newTag: true
                };
            };
            select2_opts.insertTag = function (data, tag) {
                // Show the "create" option at the top of the list.
                data.unshift(tag);
            };
        }

        target.select2(select2_opts);

        // When a new tag is selected for creatable fields, persist it via AJAX
        // and replace the temporary ID with the real database ID.
        if (creatable) {
            target.on('select2:select', function (ev) {
                var selected_data = ev.params.data;

                if (!selected_data.newTag) {
                    return;
                }

                var term_name = selected_data.text;
                var temp_id   = selected_data.id;

                $.ajax({
                    type    : 'POST',
                    url     : workeera_func_str.ajax_url,
                    dataType: 'json',
                    data    : {
                        term_name    : term_name,
                        workeera_nonce : workeera_func_str.nonce,
                        action: 'workeera_add_skill_term_inline'
                    },
                    success: function (data) {
                        var nonce_err = $.workeera_ajax_security_err_reload({ result: data });
                        if (nonce_err === 'failed') {
                            return;
                        }

                        if (data.error === '0' && data.result) {
                            // Replace the temporary option with the real one.
                            var new_option = new Option(
                                data.result.text,
                                data.result.id,
                                false,
                                true
                            );

                            // Remove the temp option and append the real one.
                            target.find('option[value="' + temp_id + '"]').remove();
                            target.append(new_option);

                            // Update the select2 selected values, preserving others.
                            var current_vals = target.val() || [];
                            if (!Array.isArray(current_vals)) {
                                current_vals = [current_vals];
                            }
                            // Remove temp id, add real id.
                            current_vals = current_vals.filter(function (v) {
                                return v !== temp_id;
                            });
                            current_vals.push(String(data.result.id));
                            target.val(current_vals).trigger('change');
                        } else if (data.error !== '0') {
                            // Remove the temp option on failure.
                            target.find('option[value="' + temp_id + '"]').remove();
                            var current_vals = target.val() || [];
                            if (Array.isArray(current_vals)) {
                                current_vals = current_vals.filter(function (v) {
                                    return v !== temp_id;
                                });
                                target.val(current_vals).trigger('change');
                            }
                            if (data.msg) {
                                $.workeera_submit_msg_alert({
                                    msg  : data.msg,
                                    class: 'workeera-alert-danger'
                                });
                            }
                        }
                    }
                });
            });
        }
    };

    /**
     * Initialise all taxonomy term select2 fields found in a given container.
     * Pass a jQuery container or leave empty to scan the whole document.
     *
     * @param {jQuery|null} container  Optional scoping container.
     */
    $.workeera_init_tax_term_fields = function (container) {
        var scope = container && container.length ? container : $(document);

        scope.find('.workeera-tax-term-selectr').each(function () {
            // Avoid double-initialisation.
            if ($(this).hasClass('select2-hidden-accessible')) {
                return;
            }
            $.workeera_tax_term_select2($(this));
        });
    };

    /**
     * Find the form-section scope for a sector / skills field. Walks up to
     * the closest meaningful container so we don't accidentally find a
     * Skills field belonging to another section on the same page.
     */
    $.workeera_skills_field_scope = function ($el) {
        if (!$el || !$el.length) return $(document);
        var $scope = $el.closest('form, .workeera-pmetasettins-main, .workeera-filters-sidebar, .workeera-formfields-con, .workeera-usrform-fields-main-wrapper, .workeera-filters-content').first();
        if (!$scope.length) {
            $scope = $el.closest('.workeera-usrform-section-card, .workeera-usrjobs-wrap, .workeera-empapps-wrap').first();
        }
        return $scope.length ? $scope : $(document);
    };

    /**
     * Read the currently-selected value from a sector field, regardless of
     * how it's rendered.
     *
     * Returns a trimmed string ('' when nothing is selected).
     */
    $.workeera_read_sector_value = function ($sector) {
        if (!$sector || !$sector.length) return '';

        if ($sector.is('select, input, textarea')) {
            var v = $sector.val();
            if ($.isArray(v)) v = v.length ? v[0] : '';
            return (v === null || typeof v === 'undefined') ? '' : String(v).trim();
        }

        // Wrapper element: probe for the active control inside.
        var $r = $sector.find('input[type="radio"]:checked').first();
        if ($r.length) return $.trim(String($r.val() || ''));

        var $c = $sector.find('input[type="checkbox"]:checked').first();
        if ($c.length) return $.trim(String($c.val() || ''));

        var $s = $sector.find('select').first();
        if ($s.length) {
            var sv = $s.val();
            if ($.isArray(sv)) sv = sv.length ? sv[0] : '';
            return (sv === null || typeof sv === 'undefined') ? '' : String(sv).trim();
        }

        return '';
    };

    /**
     * Read the currently-selected sector value from the field paired with the
     * given skills element, returning a numeric sector_id (or 0).
     *
     * Sector field on listings stores slugs; everywhere else it stores IDs.
     * The slug→id map is provided via wp_localize_script.
     */
    $.workeera_get_paired_sector_id = function ($skillsEl) {
        var $scope  = $.workeera_skills_field_scope($skillsEl);
        var $sector = $scope.find('[data-tax-slug="sectors"]').first();
        if (!$sector.length) return 0;

        var raw = $.workeera_read_sector_value($sector);
        if (raw === '') return 0;

        if (/^\d+$/.test(raw)) {
            return parseInt(raw, 10) || 0;
        }

        var map = (workeera_func_str && workeera_func_str.skills_sector_data && workeera_func_str.skills_sector_data.sector_slug_to_id) || {};
        return parseInt(map[raw], 10) || 0;
    };

    /**
     * Tell whether a skill (key by id or slug) is allowed under the current
     * sector value. Skills with no sector association are always allowed.
     *
     * @param {string} skillKey       skill_id or skill_slug as the option's value
     * @param {string} sectorRaw      the current sector field value (id or slug; empty = no filter)
     * @param {boolean} keyTypeIsId   true when the skills field uses ids, false for slugs
     */
    $.workeera_skill_is_allowed = function (skillKey, sectorRaw, keyTypeIsId) {
        if (sectorRaw === '' || sectorRaw === null || typeof sectorRaw === 'undefined') {
            return true;
        }
        if (skillKey === '' || skillKey === null || typeof skillKey === 'undefined') {
            return true; // placeholder option
        }
        var data = (workeera_func_str && workeera_func_str.skills_sector_data) || {};
        var map  = keyTypeIsId ? data.map_id : data.map_slug;
        if (!map || typeof map !== 'object') {
            return true;
        }
        // Skills not in the map (or with empty sector list) are universal.
        var entry = map[String(skillKey)];
        if (!entry || !entry.length) {
            return true;
        }
        if (keyTypeIsId) {
            var sid = /^\d+$/.test(String(sectorRaw)) ? parseInt(sectorRaw, 10) : 0;
            if (sid <= 0) {
                // sector value is a slug — convert via map.
                var slugMap = data.sector_slug_to_id || {};
                sid = parseInt(slugMap[sectorRaw], 10) || 0;
            }
            for (var i = 0; i < entry.length; i++) {
                if (parseInt(entry[i], 10) === sid) return true;
            }
            return false;
        }
        // slug-keyed
        for (var j = 0; j < entry.length; j++) {
            if (String(entry[j]) === String(sectorRaw)) return true;
        }
        return false;
    };

    /**
     * Snapshot the original `<option>` list of a Skills <select> the first time
     * we touch it, so subsequent re-filters can rebuild from the full set.
     */
    $.workeera_snapshot_skills_options = function ($sel) {
        if ($sel.data('jwSkillsOrig')) return;
        var orig = [];
        $sel.find('option').each(function () {
            var $o = $(this);
            orig.push({ value: $o.val(), text: $o.text() });
        });
        $sel.data('jwSkillsOrig', orig);
    };

    /**
     * Re-build a local Select2 Skills field with only the options allowed
     * under the current sector.
     *
     * On user-initiated sector changes (`clearSelected = true`) the previous
     * selection is dropped so the field starts fresh; on the initial page-load
     * pass it's preserved so saved skills remain selected when editing an
     * existing job / candidate.
     */
    $.workeera_filter_skills_select = function ($sel, sectorRaw, keyTypeIsId, clearSelected) {
        $.workeera_snapshot_skills_options($sel);
        var orig = $sel.data('jwSkillsOrig') || [];

        // Decide which currently-selected values to keep.
        var keep = [];
        if (!clearSelected) {
            var current = $sel.val();
            if (current !== null && typeof current !== 'undefined') {
                keep = $.isArray(current) ? current.slice() : [ String(current) ];
            }
        }

        var wasSelect2 = $sel.hasClass('select2-hidden-accessible');
        if (wasSelect2) {
            $sel.select2('destroy');
        }

        $sel.empty();
        for (var i = 0; i < orig.length; i++) {
            var opt           = orig[i];
            var v             = opt.value;
            var stillSelected = !clearSelected && (
                keep.indexOf(String(v)) !== -1 || (v === '' && keep.indexOf('') !== -1)
            );
            var allowed = $.workeera_skill_is_allowed(v, sectorRaw, keyTypeIsId);
            if (allowed || stillSelected || v === '') {
                var $o = $('<option/>').val(v).text(opt.text);
                if (stillSelected) {
                    $o.prop('selected', true);
                }
                $sel.append($o);
            }
        }

        if (clearSelected) {
            $sel.val($sel.prop('multiple') ? [] : '');
        }

        if (wasSelect2) {
            if ($sel.hasClass('workeera-select2-wsearch')) {
                $.workeera_select2_common({ target: $sel, search: true });
            } else {
                $.workeera_select2_common({ target: $sel });
            }
        }

        if (clearSelected) {
            $sel.trigger('change');
        }
    };

    /**
     * Toggle visibility of skill checkboxes under the given wrapper based on
     * the current sector value. Disallowed checkboxes are hidden and unchecked.
     */
    $.workeera_filter_skills_checkboxes = function ($wrap, sectorRaw, keyTypeIsId) {
        $wrap.find('input[type="checkbox"], input[type="radio"]').each(function () {
            var $cb     = $(this);
            var v       = $cb.val();
            var allowed = $.workeera_skill_is_allowed(v, sectorRaw, keyTypeIsId);
            // The wrapper element may be a <label>, an option-button div, etc.
            var $label  = $cb.closest('label');
            var $row    = $label.length ? $label : $cb;
            // Prefer hiding the parent option-button if option_buttons() emits one.
            var $optBtn = $cb.closest('.workeera-form-optbtn, .workeera-optbtn-itm, .workeera-grid-optbtn-itm');
            if ($optBtn.length) {
                $row = $optBtn;
            }
            if (allowed) {
                $row.removeClass('workeera-hide').show();
            } else {
                $row.addClass('workeera-hide').hide();
                if ($cb.is(':checked')) {
                    $cb.prop('checked', false).trigger('change');
                }
            }
        });
    };

    /**
     * Apply skills filtering for the section that contains the given sector
     * field. Detects checkbox / local Select2 / AJAX Select2 modes and routes
     * to the right helper.
     *
     * AJAX Select2 fields don't need any DOM filtering — the data callback
     * forwards the current sector_id on every search; we just clear Select2's
     * cached pages so a fresh query fires next time the user opens it.
     */
    $.workeera_apply_skills_filter_for_sector = function ($sector, opts) {
        opts = opts || {};
        // `clear`: drop existing skill selections (true on user-initiated
        // sector change, false on initial page-load pass so saved skills
        // stay selected).
        var clearSelected = !!opts.clear;

        var $scope    = $.workeera_skills_field_scope($sector);
        var sectorRaw = $.workeera_read_sector_value($sector);

        // Skills <select> first.
        var $skillsSel = $scope.find('select[data-tax-slug="skills"]').first();
        if ($skillsSel.length) {
            // If sector value looks numeric → skill keys are ids; otherwise slugs.
            var keyTypeIsId = !/[^0-9]/.test(($skillsSel.find('option').filter(function () {
                return $(this).val() !== '';
            }).first().val() || ''));

            if ($skillsSel.hasClass('workeera-tax-term-selectr')) {
                // AJAX mode.
                if (clearSelected) {
                    // Clear any previously-selected skills (their sector
                    // association may no longer match) and remove the
                    // dynamically-added <option> rows so the next dropdown
                    // open re-issues a fresh AJAX query with the new sector_id.
                    $skillsSel.val($skillsSel.prop('multiple') ? [] : '');
                    $skillsSel.find('option').each(function () {
                        var $o = $(this);
                        if ($o.val() !== '') {
                            $o.remove();
                        }
                    });
                    $skillsSel.trigger('change');
                }
                if ($skillsSel.data('select2')) {
                    try { $skillsSel.select2('close'); } catch (e) {}
                }
                return;
            }

            $.workeera_filter_skills_select($skillsSel, sectorRaw, keyTypeIsId, clearSelected);
            return;
        }

        // Skills checkbox container (no <select> in this section).
        var $skillsCheckboxes = $scope.find('[data-tax-slug="skills"]').filter(function () {
            return !$(this).is('select');
        }).first();
        if ($skillsCheckboxes.length) {
            // Detect key type by the first non-empty checkbox value.
            var firstVal = $skillsCheckboxes.find('input[type="checkbox"], input[type="radio"]').filter(function () {
                return $(this).val() !== '';
            }).first().val() || '';
            var checkKeyTypeIsId = /^\d+$/.test(String(firstVal).trim());
            $.workeera_filter_skills_checkboxes($skillsCheckboxes, sectorRaw, checkKeyTypeIsId);
        }
    };

    // Delegate change so it works with Select2-enhanced sectors and dynamic
    // markup alike. (Select2 fires change on the underlying <select>.)
    // User-initiated changes clear any prior skills selection — saved values
    // are kept on the initial page-load pass below.
    $(document).on('change.workeera_skills_filter', '[data-tax-slug="sectors"]', function () {
        $.workeera_apply_skills_filter_for_sector($(this), { clear: true });
    });

    // -------------------------------------------------------------------------

    /*
    * On document ready
    */
    $(document).ready(function() {
        // select2
        if ($('.workeera-select2-dropdown').length > 0) {
            $('.workeera-select2-dropdown').each(function() {
                // Taxonomy term fields are initialised separately below.
                if ($(this).hasClass('workeera-tax-term-selectr')) {
                    return;
                }
                if ($(this).hasClass('workeera-select2-wsearch')) {
                    $.workeera_select2_common({
                        target: $(this),
                        search: true
                    });
                } else {
                    $.workeera_select2_common({
                        target: $(this)
                    });
                }
            });
        }

        // Location fields (country + city) on metaboxes.
        $.workeera_init_location_fields();

        // Taxonomy term select2 fields (metaboxes).
        $.workeera_init_tax_term_fields();

        // Initial sector → skills filter pass so saved/pre-selected sector
        // values constrain the Skills field on first paint.
        $('[data-tax-slug="sectors"]').each(function () {
            $.workeera_apply_skills_filter_for_sector($(this));
        });

        // daterangepicker
        if ($('.workeera-daterangepicker').length > 0) {
            $('.workeera-daterangepicker').each(function() {
                $.workeera_daterangepicker_call($(this));
            });
        }

        // form actions update with ajax
        if ($('.workeera-form-actions-con').length > 0) {
            $('.workeera-form-actions-con').each(function() {
                $.workeera_form_actions_update_on_load($(this));
            });
        }
        if ($('.workeera-post-token-con').length > 0) {
            setTimeout(function() {
                $('.workeera-post-token-con').each(function() {
                    $.workeera_custpost_token_on_load($(this));
                });
            }, 100);
        }
    });

    /**
     * Initialise the daterangepicker plugin on the given input.
     *
     * @param {jQuery} target The input element to enhance.
     */
    $.workeera_daterangepicker_call = function (target) {
        target.daterangepicker({
            opens: 'left',
            "isInvalidDate": function(date) {
                // Disable weekends (Saturday = 6, Sunday = 0)
                return (date.day() === 0 || date.day() === 6);
            },
            locale: {
                format: 'DD-MM-YYYY'
            }
        });
    }

    /*
    * For pagination
    */
    $('.workeera-items-paginatcon').on('click', '.workeera-carditms-pagelinks a', function() {
        var this_itm = $(this);
        var cards_section = this_itm.closest('.workeera-dynm-cards-section');

        var filtr_term_id = '';
        if (cards_section.find('.workeera-dynm-topterms-filtr').length) {
            filtr_term_id = cards_section.find('.workeera-dynm-topterms-filtr').find('li.active').find('a').attr('data-id');
        }

        if (!cards_section.find('.workeera-loder-con').length) {
            cards_section.append($.workeera_delay_loader_html());
        }

        $.ajax({
            type: "POST",
            dataType: "json",
            url: workeera_func_str.ajax_url,
            data: {
                gid: cards_section.attr('data-id'),
                page_num: this_itm.attr('data-pagenum'),
                term_id: filtr_term_id,
                action: 'workeera_cards_pagination_pagenum_call'
            },
            success: function (data) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                if (nonce_err == 'failed') {
                    return false;
                }

                if (data.cards != '') {
                    cards_section.find('.workeera-dynm-cards-holdrcon').html(data.cards);
                    cards_section.find('.workeera-items-paginatcon').html(data.pagination);
                }
                
                $.workeera_remove_delay_loader({
                    target: cards_section
                });
            },
            error: function () {
                $.workeera_remove_delay_loader({
                    target: cards_section
                });
            }
        });
    });

    /**
     * Check whether an element is at least partially visible in the viewport.
     *
     * @param {jQuery} $el The element to test.
     * @return {boolean}   True when any part of the element is in view.
     */
    $.workeera_is_element_in_viewport = function ($el) {
        var scroll_top = $(window).scrollTop();
        var scroll_bottom = scroll_top + $(window).height();
        
        var el_top = $el.offset().top;
        var el_bottom = el_top + $el.outerHeight();
        
        return (el_bottom >= scroll_top) && (el_top <= scroll_bottom);
    }

    /**
     * Trigger the "load more" link inside the given container once it scrolls
     * into view.
     *
     * @param {jQuery} target The pagination container.
     */
    $.workeera_load_carditms_on_scroll = function (target) {
        if ($.workeera_is_element_in_viewport(target)) {
            target.find('a').trigger('click');
        }
    }

    /**
     * Fetch a fresh nonce field via AJAX and append it to the given container.
     *
     * @param {jQuery} target The form-actions container to receive the nonce field.
     */
    $.workeera_form_actions_update_on_load = function(target) {
        $.ajax({
            url: workeera_func_str.ajax_url,
            method: "POST",
            data: {action: 'workeera_get_site_nonce_token'},
            dataType: "json",
            success: function (response) {
                target.append(response.nonce);
            }
        });
    }

    /**
     * Fetch a fresh nonce field via AJAX and append it to the given container.
     *
     * Skipped for new-post forms (post ID 0): the per-post token cannot be
     * minted before the post exists — the server generates it after insert.
     *
     * @param {jQuery} target The form-actions container to receive the nonce field.
     */
    $.workeera_custpost_token_on_load = function(target) {
        var post_id = parseInt(target.data('post-id'), 10) || 0;
        if (post_id < 1) {
            return;
        }
        $.ajax({
            url: workeera_func_str.ajax_url,
            method: "POST",
            data: {post_id: post_id, action: 'workeera_add_post_nonce_token'},
            dataType: "json",
            success: function (response) {
                target.append(response.nonce);
            }
        });
    }

    /**
     * Smoothly scroll the window to bring the target element into view when
     * it is currently outside the visible portion of the viewport.
     *
     * @param {jQuery} target      Element to scroll to.
     * @param {number} [top_space] Offset (px) applied to the scroll target.
     * @param {string} [space_opr] 'plus' to add `top_space` to the offset, otherwise subtract.
     */
    $.workeera_item_animate_to_visibility = function (target, top_space = 80, space_opr = 'minus') {
        const element = target;
        if (element.length) {
            const element_top = element.offset().top; // Element's top position relative to the document
            const element_bottom = element_top + element.outerHeight(); // Element's bottom position
            const viewport_top = $(window).scrollTop(); // Current top of the viewport
            const viewport_bottom = viewport_top + $(window).height(); // Bottom of the viewport
    
            // Check if the element is outside the visible part of the viewport
            if (element_bottom < viewport_top || element_top > viewport_bottom) {
                // Scroll to the element with animation
                $('html, body').animate(
                    { scrollTop: space_opr == 'plus' ? element_top + top_space : element_top - top_space },
                    500 // Duration in milliseconds
                );
            }
        }
    }

    /*
    * For adding popover
    */
    $('body').on('mouseover', '[data-workeera-popover]', function() {
        var element = $(this);
        element.addClass('workeera-popover-domelem');
    
        // Get the computed style of the element
        var element_style = element.css('position');
    
        if (element_style === 'static') {
            element.css('position', 'relative');
        }
    
        var element_rect = this.getBoundingClientRect();
        var element_space_above = element_rect.top;
    
        // Create popover element
        var popover_con = $('<span>', {
            class: 'workeera-popover-holder',
            html: '<span class="workeera-popover-content">' + element.attr('data-workeera-popover') + '</span><span class="workeera-popover-arrow"></span>'
        });
    
        element.append(popover_con);
        var popover_con_elem = element.find('.workeera-popover-holder');
        var popover_height = popover_con_elem.outerHeight();
    
        if (element_space_above > popover_height) {
            popover_con_elem.css('top', '-' + popover_height + 'px');
        } else {
            popover_con_elem.addClass('workeera-bottom-popover');
            popover_con_elem.css('bottom', '-' + popover_height + 'px');
        }
    
        element.addClass('workeera-popover-hovered');
    });
    
    // Delegate the mouseout event to remove the popover
    $('body').on('mouseout', '[data-workeera-popover]', function() {
        var element = $(this);
        element.removeClass('workeera-popover-hovered');
        element.find('.workeera-popover-holder').remove();
    });

    /*
    * Input Calendar Popup
    */
    $('body').on('focus', '.workeera-date-input', function() {
        var this_input = $(this);
        if (this_input.closest('.workeera-calendarinput-wrap').length == 0) {
            this_input.wrap('<div class="workeera-calendarinput-wrap"></div>');
            this_input.closest('.workeera-calendarinput-wrap').append('<div class="workeera-calendar-popup"></div>');
        }

        $.workeera_hide_open_calendars();

        const calendar_input_wrap = this_input.closest('.workeera-calendarinput-wrap');
        const calendar_holder = calendar_input_wrap.find('.workeera-calendar-popup');

        if (typeof this_input.attr('data-miny') !== 'undefined') {
            calendar_holder.attr('data-miny', this_input.attr('data-miny'));
            this_input.removeAttr('data-miny');
        }
        if (typeof this_input.attr('data-maxy') !== 'undefined') {
            calendar_holder.attr('data-maxy', this_input.attr('data-maxy'));
            this_input.removeAttr('data-maxy');
        }
        if (typeof this_input.attr('data-blocked_weekdays') !== 'undefined') {
            calendar_holder.attr('data-blocked_weekdays', this_input.attr('data-blocked_weekdays'));
            this_input.removeAttr('data-blocked_weekdays');
        }
        if (typeof this_input.attr('data-min-date') !== 'undefined') {
            calendar_holder.attr('data-min-date', this_input.attr('data-min-date'));
            this_input.removeAttr('data-min-date');
        }

        let today = new Date();

        let max_year = today.getFullYear();
        let min_year = 1920;
        if (typeof calendar_holder.attr('data-miny') !== 'undefined') {
            min_year = parseInt(calendar_holder.attr('data-miny'));
        }
        if (typeof calendar_holder.attr('data-maxy') !== 'undefined') {
            max_year = parseInt(calendar_holder.attr('data-maxy'));
        }

        let selected_date = this_input.val();

        // Initialize with today's date
        let selected_day = today.getDate();
        let selected_month = today.getMonth(); // Months are zero-indexed
        let selected_year = today.getFullYear();

        const date_pattern = /^(\d{2})\/(\d{2})\/(\d{4})$/;
        if (selected_date != '' && date_pattern.test(selected_date)) {
            const [, day, month, year] = selected_date.match(date_pattern);

            // Convert the day, month, and year to integers
            selected_day = parseInt(day, 10);
            selected_month = parseInt(month, 10) - 1;
            selected_year = parseInt(year);
        }

        let def_day = selected_day;
        let def_month = selected_month;
        let def_year = selected_year;

        if (typeof calendar_holder.attr('data-day') === 'undefined') {
            calendar_holder.attr('data-day', selected_day);
            calendar_holder.attr('data-month', selected_month);
            calendar_holder.attr('data-year', selected_year);
        }

        $.workeera_generate_calendar({
            calendar: calendar_holder,
            date_input: this_input,
            day: def_day,
            month: def_month,
            year: def_year,
            min_year: min_year,
            max_year: max_year
        });
    });

    /**
     * Hide all open date-input calendar popups on the page.
     */
    $.workeera_hide_open_calendars = function() {
        $('.workeera-date-input').each(function() {
            var this_input = $(this);
            var calendar_input_wrap = this_input.closest('.workeera-calendarinput-wrap');
            var calendar_holder = calendar_input_wrap.find('.workeera-calendar-popup');
            calendar_holder.hide();
        });
    }

    /**
     * Build a <select> element containing year options between two bounds.
     *
     * @param {number} min_year Minimum year (inclusive).
     * @param {number} max_year Maximum year (inclusive).
     * @return {jQuery}         The constructed <select> element.
     */
    $.workeera_generate_year_options = function (min_year, max_year) {
        const $yearSelect = $('<select></select>');
        for (let year = min_year; year <= max_year; year++) {
            const $option = $('<option></option>').val(year).text(year);
            $yearSelect.append($option);
        }
        return $yearSelect;
    }

    /**
     * Re-render the day grid of the calendar popup for the given month/year.
     *
     * @param {jQuery} calendar_holder Calendar wrapper element.
     * @param {jQuery} dateinput_field The bound date input.
     * @param {number} month           Currently-viewed month (0-11) used for comparison.
     * @param {number} year            Currently-viewed year used for comparison.
     * @param {number} selected_day    Day-of-month currently selected.
     * @param {number} selected_month  Month (0-11) of the currently-selected date.
     * @param {number} selected_year   Year of the currently-selected date.
     */
    $.workeera_update_calendar = function (calendar_holder, dateinput_field, month, year, selected_day, selected_month, selected_year) {
        const month_name = new Date(selected_year, selected_month).toLocaleString('default', { month: 'long' });
        calendar_holder.find('.workeera-month-name').text(month_name);

        const $days = calendar_holder.find('.workeera-calendar-days').empty();

        const first_day = new Date(selected_year, selected_month, 1).getDay();
        const last_date = new Date(selected_year, selected_month + 1, 0).getDate();

        for (let i = 0; i < first_day; i++) {
            $days.append($('<div class="workeera-calendar-day workeera-disabled"></div>'));
        }

        let block_weekdays = [];
        if (typeof calendar_holder.attr('data-blocked_weekdays') !== 'undefined') {
            block_weekdays = calendar_holder.attr('data-blocked_weekdays').split(',');
        }

        let min_date_obj = null;
        if (typeof calendar_holder.attr('data-min-date') !== 'undefined') {
            const min_date = calendar_holder.attr('data-min-date');
            const [min_date_day, min_date_month, min_date_year] = min_date.split('-');
            min_date_obj = new Date(min_date_year, min_date_month - 1, min_date_day);
        }
        
        for (let i = 1; i <= last_date; i++) {
            const $day = $('<div class="workeera-calendar-day"></div>').text(i);
            const date_obj = new Date(selected_year, selected_month, i);
            const date_weekday = date_obj.getDay();
            if (block_weekdays.includes(date_weekday.toString())) {
                $day.addClass('workeera-disabled-date');
            }
            if (min_date_obj !== null && min_date_obj > date_obj) {
                $day.addClass('workeera-disabled-date');
            }

            // Check if this is the selected date
            if (i === selected_day && month === selected_month && year === selected_year) {
                $day.addClass('workeera-selected');
            }

            $day.on('click', function() {

                if ($day.hasClass('workeera-disabled-date')) {
                    return;
                }

                calendar_holder.attr('data-day', i);
                calendar_holder.attr('data-month', selected_month);
                calendar_holder.attr('data-year', selected_year);

                // Display the selected date in the input
                const full_date = `${(i < 10 ? '0' : '') + i}/${(selected_month < 9 ? '0' : '') + (selected_month + 1)}/${selected_year}`;
                dateinput_field.val(full_date);
                calendar_holder.hide(); // Hide the calendar after selection
            });

            $days.append($day);
        }
    }

    /**
     * Build the custom calendar popup (header, weekday row, day grid) for a
     * date-input field and wire up its month/year navigation.
     *
     * @param {Object} params             Configuration.
     * @param {jQuery} params.calendar    Calendar popup container.
     * @param {jQuery} params.date_input  The bound date input element.
     * @param {number} [params.day]       Initial day-of-month.
     * @param {number} [params.month]     Initial month (0-11).
     * @param {number} [params.year]      Initial year.
     * @param {number} [params.min_year]  Minimum selectable year.
     * @param {number} [params.max_year]  Maximum selectable year.
     */
    $.workeera_generate_calendar = function (params) {
        let today = new Date();

        let calendar_holder = params.calendar;
        let dateinput_field = params.date_input;
        let day = params.day || today.getDate();
        let month = params.month || today.getMonth();
        let year = params.year || today.getFullYear();
        let min_year = params.min_year || 1920;
        let max_year = params.max_year || today.getFullYear();

        let selected_day = calendar_holder.attr('data-day');
        let selected_month = calendar_holder.attr('data-month');
        let selected_year = calendar_holder.attr('data-year');

        selected_day = parseInt(selected_day, 10);
        selected_month = parseInt(selected_month, 10);
        selected_year = parseInt(selected_year);

        calendar_holder.empty();

        const $header = $('<div class="workeera-calendar-header"></div>');

        const $prevButton = $('<a class="workeera-monthchnger-btn"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg></a>');
        $prevButton.on('click', function() {
            let year_dropdown = calendar_holder.find('.workeera-calendar-header select');
            if (selected_month === 0) {
                selected_month = 11;
                selected_year--;
            } else {
                selected_month--;
            }
            if (selected_year < min_year) {
                selected_year = min_year;
            }
            year_dropdown.val(selected_year);
            $.workeera_update_calendar(calendar_holder, dateinput_field, month, year, selected_day, selected_month, selected_year);
        });

        const $nextButton = $('<a class="workeera-monthchnger-btn"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg></a>');
        $nextButton.on('click', function() {
            let year_dropdown = calendar_holder.find('.workeera-calendar-header select');
            if (selected_month === 11) {
                selected_month = 0;
                selected_year++;
            } else {
                selected_month++;
            }
            if (selected_year > max_year) {
                selected_year = max_year;
            }
            year_dropdown.val(selected_year);
            $.workeera_update_calendar(calendar_holder, dateinput_field, month, year, selected_day, selected_month, selected_year);
        });

        const $monthYear = $('<span class="workeera-month-name"></span>');

        const $yearSelect = $.workeera_generate_year_options(min_year, max_year);
        $yearSelect.val(selected_year);
        $yearSelect.on('change', function() {
            selected_year = parseInt($(this).val());
            $.workeera_update_calendar(calendar_holder, dateinput_field, month, year, selected_day, selected_month, selected_year);
        });

        $header.append($prevButton, $monthYear, $yearSelect, $nextButton);
        calendar_holder.append($header);
        const week_days = [
            '<div class="workeera-calendar-weekdays">',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_sun + '</strong></div>',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_mon + '</strong></div>',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_tue + '</strong></div>',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_wed + '</strong></div>',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_thu + '</strong></div>',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_fri + '</strong></div>',
            '<div class="workeera-calendar-weekday"><strong>' + workeera_func_str.day_sat + '</strong></div>',
            '</div>'
        ].join('');
        calendar_holder.append(week_days);

        const $days = $('<div class="workeera-calendar-days"></div>');
        calendar_holder.append($days);

        $.workeera_update_calendar(calendar_holder, dateinput_field, month, year, selected_day, selected_month, selected_year);

        calendar_holder.removeAttr('style');
    }

    /*
    * For click on dom events
    */
    $(document).on('click', function(event) {
        // For hide date calendars
        if ($('.workeera-date-input').length > 0) {
            $('.workeera-date-input').each(function() {
                var this_input = $(this);
                var calendar_input_wrap = this_input.closest('.workeera-calendarinput-wrap');
                var calendar_holder = calendar_input_wrap.find('.workeera-calendar-popup');
                if (!$(event.target).closest('.workeera-calendarinput-wrap').length) {
                    calendar_holder.hide();
                }
            });
        }
    });

    // -------------------------------------------------------------------------
    // Apply method (Job Submission) — show/hide the apply-URL field based on
    // the dropdown's current value. PHP renders the initial state so this
    // handler only needs to react to user changes.
    // -------------------------------------------------------------------------

    /**
     * Show or hide the external apply-URL field based on the apply-method
     * select's current value.
     *
     * @param {jQuery} $select The apply-method <select> element.
     */
    $.workeera_toggle_apply_method_url = function ($select) {
        if (!$select || !$select.length) {
            return;
        }
        var val = $select.val();
        if ($.isArray(val)) val = val.length ? val[0] : '';
        val = (val === null || typeof val === 'undefined') ? '' : String(val).trim();

        // Scope to the same row first, then fall back to the closest form /
        // metabox container so the helper works on both front-end and admin.
        var $scope = $select.closest('.workeera-apply-method-row');
        if (!$scope.length) {
            $scope = $select.closest('form, .workeera-formfields-con, .workeera-pmetasettins-main').first();
        }
        if (!$scope.length) {
            $scope = $select.parent();
        }

        var $url_field = $scope.find('.workeera-apply-method-url-field').first();
        if (!$url_field.length) {
            return;
        }

        if (val === 'external') {
            $url_field.removeClass('workeera-hide').show();
        } else {
            $url_field.addClass('workeera-hide').hide();
        }
    };

    // Select2-enhanced selects still dispatch change on the underlying <select>.
    $(document).on('change.workeera_apply_method', '.workeera-apply-method-select', function () {
        $.workeera_toggle_apply_method_url($(this));
    });

})(jQuery);
