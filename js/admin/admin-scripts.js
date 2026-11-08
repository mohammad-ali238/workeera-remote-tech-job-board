/**
 * Workeera admin-screen scripts.
 *
 * Wires up admin-only UI behaviour: color and page selectors, AJAX-backed
 * Select2 dropdowns (posts, users, job employer filter, taxonomy terms),
 * media uploader for image fields, term add/edit/delete handlers, the CSV
 * data importer (with optional parent-phase pass for hierarchical
 * taxonomies), and miscellaneous job-status toggles.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // For color selection form field
        if ($('.workeera-color-field').length > 0) {
            $('.workeera-color-field').wpColorPicker();
        }

        // Sortable switch list (reusable settings field — e.g. Active Filters,
        // Resume Sections). Dragging only reorders rows in the DOM; each row
        // carries a hidden order[] input so the new order posts with Save.
        $('.workeera-sortable-switchlist').each(function () {
            var $list = $(this);
            if (typeof $list.sortable === 'function') {
                $list.sortable({
                    axis: 'y',
                    handle: '.workeera-sortsw-handle',
                    placeholder: 'workeera-sortsw-placeholder',
                    forcePlaceholderSize: true,
                    tolerance: 'pointer'
                });
            }
        });

        if ($('.workeera-page-selecter').length > 0) {
            $('.workeera-page-selecter').select2({
                width: '100%',
                allowClear: true,
                ajax: {
                    type: "POST",
                    url: workeera_adminscript_str.admin_url,
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            q: params.term,
                            workeera_nonce: workeera_adminscript_str.nonce,
                            workeera_action: 'workeera_ajax_get_page_results'
                        }
                        return query;
                    },
                    processResults: function(data) {
                        var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                        if (nonce_err == 'failed') {
                            return false;
                        }
                        return {
                            results: data.results
                        };
                    }
                },
                minimumInputLength: 1,
                language: {
                    inputTooShort: function () {
                        return workeera_func_str.type_to_search;
                    }
                }
            });
        }

        if ($('.workeera-postitms-dropdown').length > 0) {
            $('.workeera-postitms-dropdown').select2({
                width: '100%',
                allowClear: true,
                placeholder: workeera_adminscript_str.srch_post_itms,
                ajax: {
                    type: "POST",
                    url: workeera_adminscript_str.admin_url,
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            q: params.term,
                            pstype: $(this).attr('data-ptype'),
                            workeera_nonce: workeera_adminscript_str.nonce,
                            workeera_action: 'workeera_ajax_get_posts_results'
                        }
                        return query;
                    },
                    processResults: function(data) {
                        var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                        if (nonce_err == 'failed') {
                            return false;
                        }
                        return {
                            results: data.results
                        };
                    }
                },
                minimumInputLength: 1,
                language: {
                    inputTooShort: function () {
                        return workeera_func_str.type_to_search;
                    }
                }
            });
        }

        // for users selection
        if ($('.workeera-users-dropdown').length > 0) {
            $('.workeera-users-dropdown').each(function() {
                $.workeera_users_slect_drpdwn($(this));
            });
        }

        // Job employer filter (workeera_job admin list page) — Select2 backed by AJAX.
        if ($('.workeera-job-employer-filter').length > 0) {
            $('.workeera-job-employer-filter').each(function() {
                $.workeera_job_employer_filter_drpdwn($(this));
            });
        }

        if ($('.workeera-pmetasettins-main').length > 0) {
            $('.workeera-pmetasettins-main').each(function() {
                $.workeera_grid_settings_con_adjustr($(this));
            });
        }

        // terms parent select2 dropdown
        if ($('.workeera-parent-term-drpdwn').length > 0) {
            $.workeera_term_parent_dropdown($('.workeera-parent-term-drpdwn'));
        }
    });

    $(window).on("resize", function () {
        if ($('.workeera-pmetasettins-main').length > 0) {
            $('.workeera-pmetasettins-main').each(function() {
                $.workeera_grid_settings_con_adjustr($(this));
            });
        }
    });

    /**
     * Apply a responsive layout class to a post-meta settings container based
     * on its current width.
     *
     * @param {jQuery} target The settings container element.
     */
    $.workeera_grid_settings_con_adjustr = function (target) {
        var cont_width = target.width();
        target.removeClass(function(index, className) {
            return (className.match(/(^|\s)workeera-settinsadjustr-\S+/g) || []).join(' ');
        });
        if (cont_width >= 1000) {
            target.addClass('workeera-settinsadjustr-full');
        } else if (cont_width < 1000 && cont_width >= 800) {
            target.addClass('workeera-settinsadjustr-medium');
        } else if (cont_width < 800 && cont_width >= 500) {
            target.addClass('workeera-settinsadjustr-small');
        } else {
            target.addClass('workeera-settinsadjustr-mobile');
        }
    }

    /**
     * Initialise an AJAX-backed Select2 dropdown for picking users.
     *
     * The `data-user-type` attribute on the target restricts which users are
     * returned by the server-side handler.
     *
     * @param {jQuery} target The <select> element to enhance.
     */
    $.workeera_users_slect_drpdwn = function (target) {
        let user_type = '';
        if ( typeof target.attr('data-user-type') !== 'undefined' ) {
            user_type = target.attr('data-user-type');
        }
        target.select2({
            width: '100%',
            allowClear: true,
            placeholder: workeera_adminscript_str.srch_term_itms,
            ajax: {
                type: "POST",
                url: workeera_adminscript_str.admin_url,
                dataType: 'json',
                data: function (params) {
                    var query = {
                        q: params.term,
                        user_type: user_type,
                        workeera_nonce: workeera_adminscript_str.nonce,
                        workeera_action: 'workeera_ajax_get_users_results'
                    }
                    return query;
                },
                processResults: function(data) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                    if (nonce_err == 'failed') {
                        return false;
                    }
                    return {
                        results: data.results
                    };
                }
            },
            language: {
                inputTooShort: function () {
                    return workeera_func_str.type_to_search;
                }
            }
        });
    }

    /**
     * Select2 init for the "Filter by Employer" dropdown on the workeera_job
     * admin list page. Backed by AJAX action `workeera_ajax_get_jobs_employers`
     * which returns only users who own at least one workeera_job post.
     */
    $.workeera_job_employer_filter_drpdwn = function (target) {
        var placeholder = target.attr('data-placeholder') || '';

        target.select2({
            width: '220px',
            allowClear: true,
            placeholder: placeholder,
            ajax: {
                type: "POST",
                url: workeera_adminscript_str.admin_url,
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1,
                        workeera_nonce: workeera_adminscript_str.nonce,
                        workeera_action: 'workeera_ajax_get_jobs_employers'
                    };
                },
                processResults: function (data, params) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                    if (nonce_err == 'failed') {
                        return { results: [] };
                    }
                    params.page = params.page || 1;
                    return {
                        results: data.results || [],
                        pagination: data.pagination || { more: false }
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
    }

    // For image upload form field
    $('body').on( 'click', '.workeera-form-imgfield .workeera-upload-imgbtn, .workeera-form-imgfield .workeera-field-inputcon input', function() {
        var button = $(this);
        var main_parent = button.parents('.workeera-form-imgfield');
        var custom_uploader = wp.media({
            title: workeera_adminscript_str.insert_img_lbl,
            library : {
                type : 'image'
            },
            button: {
                text: workeera_adminscript_str.use_img_lbl // button label text
            },
            multiple: false
        }).on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            main_parent.find('img').attr('src', attachment.url);
            main_parent.find('.workeera-field-inputcon input[type="text"]').val(attachment.url);
            main_parent.find('.workeera-field-inputcon input[type="hidden"]').val(attachment.id);
            main_parent.find('.workeera-field-imgcon').removeClass('workeera-hide');
            main_parent.find('.workeera-field-btnscon .workeera-remvupload-imgbtn').removeClass('workeera-hide');
        }).open();
    });

    // For removing uploaded image form field
    $('body').on('click', '.workeera-form-imgfield .workeera-remvupload-imgbtn', function() {
        var button = $(this);
        var main_parent = button.parents('.workeera-form-imgfield');
        main_parent.find('.workeera-field-inputcon input').val('');
        main_parent.find('.workeera-field-imgcon').addClass('workeera-hide');
        main_parent.find('.workeera-field-btnscon .workeera-remvupload-imgbtn').addClass('workeera-hide');
    });

    /**
     * Insert a newly-created term row into the admin terms list and briefly
     * highlight it.
     *
     * Invoked as an AJAX-success callback via $.workeera_ajax_success_callback.
     *
     * @param {Object} params            Configuration.
     * @param {jQuery} params.parent_con The form container that was submitted.
     * @param {Object} params.result     AJAX response payload.
     */
    $.workeera_after_term_itemadd_success = function(params) {
        if (params.result.error == '0') {
            if (typeof params.result.adding_itm !== 'undefined') {
                var items_parent_con = $('#the-list');
                items_parent_con.find('tr.no-items').remove();
                // remove title val
                params.parent_con.find('#tag-name').val('');
                params.parent_con.find('#tag-slug').val('');
                //
                if (items_parent_con.find('.workeera-term-listitm').length == 0) {
                    items_parent_con.append(params.result.adding_itm);
                } else if (typeof params.result.parent_id !== 'undefined' && params.result.parent_id > 0 && 
                    $('tr#tag-' + params.result.parent_id).length > 0) {
                    $('tr#tag-' + params.result.parent_id).after(params.result.adding_itm);
                } else {
                    var first_itm = items_parent_con.find('.workeera-term-listitm').first();
                    first_itm.before(params.result.adding_itm);
                }

                // after adding get adding item
                var added_item = $('tr#tag-' + params.result.term_id);
                added_item.css({'background-color': '#ffffc2'});
                setTimeout(function() {
                    added_item.removeAttr('style');
                }, 2000);
            }
        }
    }

    /**
     * Update an existing term row's name/slug after a successful inline edit,
     * or reload the page if the parent term has changed.
     *
     * Invoked as an AJAX-success callback via $.workeera_ajax_success_callback.
     *
     * @param {Object} params        Configuration.
     * @param {Object} params.result AJAX response payload.
     */
    $.workeera_after_term_itemupdate_success = function(params) {
        if (params.result.error == '0') {
            $.workeera_modal_popup_close();
            let term_id = params.result.term_id;
            let term_name = params.result.term_name;
            let term_slug = params.result.term_slug;

            let term_itm_con = $('#tag-' + term_id);

            term_itm_con.find('.column-name strong > a').html(term_name);
            term_itm_con.find('.column-slug').html(term_slug);

            //
            if (params.result.updated_parent == 'yes') {
                window.location.reload();
            }
        }
    }

    /**
     * Initialise the AJAX-backed Select2 dropdown used to pick a parent term
     * when adding or editing a custom taxonomy term.
     *
     * The taxonomy type is read from the target's `data-type` attribute and
     * the editing term id (if any) from the surrounding form.
     *
     * @param {jQuery} target The parent-term <select> element to enhance.
     */
    $.workeera_term_parent_dropdown = function(target) {
        let term_id = target.closest('form').find('input[name="term_id"]').val();
        let term_type = target.attr('data-type');
        if (typeof term_type === 'undefined') {
            return;
        }
        if (typeof term_id === 'undefined') {
            term_id = 0;
        }
        target.select2({
            width: '100%',
            escapeMarkup: function (markup) {
                return markup; // Disable escaping of HTML
            },
            templateResult: function(state) {
                return state.text;
            },
            templateSelection: function(state) {
                return state.text;
            },
            ajax: {
                type: "POST",
                url: workeera_adminscript_str.admin_url,
                dataType: 'json',
                data: function (params) {
                    var query = {
                        q: params.term,
                        term_id: parseInt(term_id),
                        tax_type: term_type,
                        workeera_nonce: workeera_adminscript_str.nonce,
                        workeera_action: 'workeera_ajax_parent_tax_' + term_type + '_drpdwn'
                    }
                    return query;
                },
                processResults: function(data) {
                    var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                    if (nonce_err == 'failed') {
                        return false;
                    }
                    return {
                        results: data.results
                    };
                }
            },
            minimumInputLength: 0,
            language: {
                inputTooShort: function () {
                    return workeera_func_str.type_to_search;
                },
                searching: function () {
                    return workeera_func_str.select2_searching;
                },
                noResults: function () {
                    return workeera_func_str.select2_no_results;
                }
            }
        });
    }

    //
    $('body').on('click', '.workeera-term-listitm .workeera-edit-term-item', function() {
        var $this = $(this);
        var item_parent = $this.closest('.workeera-term-listitm');
        var term_type = item_parent.attr('data-type');
        var term_id = item_parent.attr('id').match(/\d+/g).join();
        var terms_order_by = item_parent.attr('data-orderby');
        let popup_id = 'workeera-admin-popup';
        $.workeera_modal_popup_open({
            target: popup_id,
            class: 'workeera-term-update-modal'
        });
        let content_con = $('#' + popup_id).find('.workeera-modalinit-con');
        content_con.html($.workeera_delay_loader_html());
        $.ajax({
            url: workeera_adminscript_str.admin_url,
            method: "POST",
            data: {
                term_id: term_id,
                order_by: terms_order_by,
                workeera_nonce: workeera_adminscript_str.nonce,
                workeera_action: 'workeera_ajax_update_' + term_type + '_form'
            },
            dataType: "json",
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err == 'failed') {
                    return false;
                }
                $.workeera_remove_delay_loader({
                    target: content_con
                });
                content_con.html(response.update_form);
                var parent_drpdwn_target = content_con.find('.workeera-parent-term-drpdwn');
                if (parent_drpdwn_target.length > 0) {
                    $.workeera_term_parent_dropdown(parent_drpdwn_target);
                }
            },
            error: function() {
                $.workeera_remove_delay_loader({
                    target: content_con
                });
            }
        });
    });
    
    $('body').on('click', '.workeera-term-listitm .delete-tag', function() {
        var $this = $(this);
        var item_parent = $this.closest('.workeera-term-listitm');
        var term_type = item_parent.attr('data-type');
        var term_id = item_parent.attr('id').match(/\d+/g).join();
        
        $.workeera_confirm_box({
            title: workeera_adminscript_str.confirm_comon_title,
            message: workeera_func_str.delet_common_msg,
            buttons: {
                yes: {
                    title: workeera_func_str.delet_btn_lbl,
                    class: 'workeera-delitm-btn',
                    action: function() {
                        var popup_holdr_con = $('#workeera-confirm-popup');
                        var loader_target = popup_holdr_con.find('.workeera-modalinit-con');
                        loader_target.append($.workeera_delay_loader_html());
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: ajaxurl,
                            data: {
                                term_id: term_id,
                                workeera_nonce: workeera_adminscript_str.nonce,
                                action: 'workeera_delete_' + term_type + '_term'
                            },
                            success: function (data) {
                                var nonce_err = $.workeera_ajax_security_err_reload({result: data});
                                if (nonce_err == 'failed') {
                                    return false;
                                }

                                if (data.error == '0') {
                                    // remove item
                                    item_parent.css({'background-color': '#ff8989'});
                                    setTimeout(function() {
                                        item_parent.remove();
                                        window.location.reload();
                                    }, 800);
                                    //
                                    $.workeera_confirm_box.hide();
                                    $.workeera_submit_msg_alert({
                                        msg: data.msg,
                                        class: 'workeera-alert-success'
                                    });
                                } else {
                                    $.workeera_submit_msg_alert({
                                        msg: data.msg,
                                        class: 'workeera-alert-danger'
                                    });
                                }
                                
                                $.workeera_remove_delay_loader({
                                    target: loader_target
                                });
                            },
                            error: function () {
                                $.workeera_remove_delay_loader({
                                    target: loader_target
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

    // for plugin options conditional field
    $('.workeera-conditional-opt-filed').on('change', 'select', function() {

        var this_select = $(this);
        this_select.find('option').each(function() {
            var this_val = $(this).attr('value');
            var related_fields_class = '.workeera-' + this_val + '-related-filed';
            if (this_select.val() == this_val) {
                $(related_fields_class).removeClass('workeera-hide');
            } else {
                if (!$(related_fields_class).hasClass('workeera-hide')) {
                    $(related_fields_class).addClass('workeera-hide');
                }
            }
        });
    });

    /**
     * Entry point: show confirmation popup then kick off the import.
     *
     * For taxonomies with parents (skills, cities) the flow is:
     *   1. Parent phase  — insert all parent rows (sectors / countries) first.
     *   2. Main phase    — insert child rows; parent IDs are now resolvable.
     * For other taxonomies only the main phase runs.
     *
     * @param {jQuery}   $section  The .workeera-importdata-section wrapper element.
     * @param {string}   source    'sample' | 'upload'
     * @param {File|null} file     JS File object when source === 'upload', else null.
     */
    $.workeera_importdata_start = function ( $section, source, file ) {
        var tax_slug = $section.attr('data-tax-slug') || '';
        var tax_name = $section.attr('data-tax-name') || '';
        var has_parent = $section.attr('data-has-parent') === '1';

        if ( ! tax_slug ) {
            return;
        }

        // Substitute the taxonomy name into the translated confirmation message.
        if ( 'upload' === source ) {
            var confirm_msg = workeera_adminscript_str.importdata_confirm_msg.replace( '%s', tax_name );
        } else {
            var confirm_msg = workeera_adminscript_str.importdata_force_confirm_msg.replace( '%s', tax_name );
        }

        $.workeera_confirm_box({
            title   : workeera_adminscript_str.importdata_confirm_title,
            message : confirm_msg,
            buttons : {
                confirm : {
                    title  : workeera_adminscript_str.importdata_confirm_btn,
                    class  : 'workeera-delitm-btn',
                    action : function () {
                        $.workeera_confirm_box.hide();

                        if ( 'upload' === source && file ) {
                            // Phase 0: upload the file first, then start importing.
                            $.workeera_importdata_upload_then_process( $section, file, has_parent );
                        } else {
                            // Start with the parent phase if needed, else go straight to main.
                            if ( has_parent ) {
                                $.workeera_importdata_run_chunks( $section, source, 'parent', 0, true );
                            } else {
                                $.workeera_importdata_run_chunks( $section, source, 'main', 0, true );
                            }
                        }
                    }
                },
                cancel : {
                    title  : workeera_adminscript_str.importdata_cancel_btn,
                    class  : 'workeera-cancel-act',
                    action : function () {
                        $.workeera_confirm_box.hide();
                    }
                }
            }
        });
    };

    /**
     * Upload the user CSV via AJAX, then start chunk processing.
     *
     * @param {jQuery}  $section
     * @param {File}    file
     * @param {boolean} has_parent  Whether the taxonomy has a parent phase.
     */
    $.workeera_importdata_upload_then_process = function ( $section, file, has_parent ) {
        var tax_slug = $section.attr('data-tax-slug') || '';

        $.workeera_importdata_show_progress( $section, 0 );

        var form_data = new FormData();
        form_data.append( 'action',                'workeera_importdata_upload_csv' );
        form_data.append( 'workeera_nonce',          workeera_adminscript_str.nonce );
        form_data.append( 'tax_slug',              tax_slug );
        form_data.append( 'workeera_importdata_csv', file );

        $.ajax({
            url         : workeera_func_str.ajax_url,
            type        : 'POST',
            data        : form_data,
            processData : false,
            contentType : false,
            dataType    : 'json',
            success     : function ( data ) {
                var nonce_err = $.workeera_ajax_security_err_reload({ result: data });
                if ( nonce_err === 'failed' ) { return; }

                if ( data.error !== '0' ) {
                    $.workeera_importdata_hide_progress( $section );
                    $.workeera_submit_msg_alert({ msg: data.msg, class: 'workeera-alert-danger' });
                    return;
                }

                // File is saved on the server — begin chunked import.
                if ( has_parent ) {
                    $.workeera_importdata_run_chunks( $section, 'upload', 'parent', 0, true );
                } else {
                    $.workeera_importdata_run_chunks( $section, 'upload', 'main', 0, true );
                }
            },
            error : function () {
                $.workeera_importdata_hide_progress( $section );
                $.workeera_submit_msg_alert({
                    msg   : workeera_adminscript_str.importdata_upload_error,
                    class : 'workeera-alert-danger'
                });
            }
        });
    };

    /**
     * Send one chunk request and recurse until the phase (and then the whole
     * import) is complete.
     *
     * Progress bar covers the entire import:
     *   - For single-phase imports: 0 → 100 % over the main CSV.
     *   - For two-phase imports (has_parent): the parent phase occupies the
     *     first 40 % of the bar; the main phase occupies the remaining 60 %.
     *     These weights are arbitrary but give useful visual feedback.
     *
     * @param {jQuery}  $section
     * @param {string}  source    'sample' | 'upload'
     * @param {string}  phase     'parent' | 'main'
     * @param {number}  offset    0-based data-row offset in the current phase CSV.
     * @param {boolean} is_first  True only on the very first chunk of the whole import.
     */
    $.workeera_importdata_run_chunks = function ( $section, source, phase, offset, is_first ) {
        var tax_slug   = $section.attr('data-tax-slug')   || '';
        var has_parent = $section.attr('data-has-parent') === '1';
        var chunk_size = parseInt( $section.attr('data-chunk-size'), 10 ) || 50;

        $.ajax({
            url      : workeera_func_str.ajax_url,
            type     : 'POST',
            dataType : 'json',
            data     : {
                action       : 'workeera_importdata_process_chunk',
                workeera_nonce : workeera_adminscript_str.nonce,
                tax_slug     : tax_slug,
                source       : source,
                phase        : phase,
                offset       : offset,
                is_first     : is_first ? '1' : '0'
            },
            success : function ( data ) {
                var nonce_err = $.workeera_ajax_security_err_reload({ result: data });
                if ( nonce_err === 'failed' ) { return; }

                if ( data.error !== '0' ) {
                    $.workeera_importdata_hide_progress( $section );
                    $.workeera_submit_msg_alert({ msg: data.msg, class: 'workeera-alert-danger' });
                    return;
                }

                // ── Calculate overall progress percentage ─────────────────
                var total     = parseInt( data.total, 10 )     || 1;
                var processed = parseInt( data.processed, 10 ) || 0;
                var phase_pct = Math.min( 100, Math.round( ( processed / total ) * 100 ) );
                var overall_pct;

                if ( has_parent ) {
                    // Parent phase → 0-40 %, Main phase → 40-100 %.
                    if ( 'parent' === phase ) {
                        overall_pct = Math.round( phase_pct * 0.4 );
                    } else {
                        overall_pct = 40 + Math.round( phase_pct * 0.6 );
                    }
                } else {
                    overall_pct = phase_pct;
                }

                $.workeera_importdata_show_progress( $section, overall_pct );

                // ── Decide what to do next ────────────────────────────────
                if ( data.done ) {
                    // Entire import complete.
                    $.workeera_importdata_show_progress( $section, 100 );
                    $.workeera_submit_msg_alert({
                        msg   : workeera_adminscript_str.importdata_success,
                        class : 'workeera-alert-success'
                    });
                    setTimeout( function () {
                        window.location.reload();
                    }, 1500 );

                } else if ( data.phase_done && 'parent' === phase ) {
                    // Parent phase finished — start main phase.
                    $.workeera_importdata_run_chunks( $section, source, 'main', 0, false );

                } else {
                    // More chunks remain in the current phase.
                    var next_offset = processed || ( offset + chunk_size );
                    $.workeera_importdata_run_chunks( $section, source, phase, next_offset, false );
                }
            },
            error : function () {
                $.workeera_importdata_hide_progress( $section );
                $.workeera_submit_msg_alert({
                    msg   : workeera_adminscript_str.importdata_upload_error,
                    class : 'workeera-alert-danger'
                });
            }
        });
    };

    /**
     * Show (or update) the progress bar inside the import section.
     *
     * @param {jQuery} $section
     * @param {number} pct  0–100
     */
    $.workeera_importdata_show_progress = function ( $section, pct ) {
        var $wrap = $section.find('.workeera-importdata-progress-wrap');
        var $bar  = $wrap.find('.workeera-importdata-progress-bar');
        var $txt  = $wrap.find('.workeera-importdata-progress-txt');

        $wrap.removeClass('workeera-hide');
        $bar.css( 'width', pct + '%' );
        $txt.text( pct + '%' );
    };

    /**
     * Hide the progress bar.
     *
     * @param {jQuery} $section
     */
    $.workeera_importdata_hide_progress = function ( $section ) {
        $section.find('.workeera-importdata-progress-wrap').addClass('workeera-hide');
    };

    // ── Event: "Import Sample Data" button clicked ────────────────────────────
    $('body').on( 'click', '.workeera-importdata-sample-btn', function () {
        var $section = $(this).closest('.workeera-importdata-section');
        $.workeera_importdata_start( $section, 'sample', null );
    });

    // ── Event: CSV file selected via the upload label / input ─────────────────
    $('body').on( 'change', '.workeera-importdata-file-input', function () {
        var $input   = $(this);
        var $section = $input.closest('.workeera-importdata-section');
        var file     = $input[0].files && $input[0].files[0] ? $input[0].files[0] : null;

        if ( ! file ) {
            return;
        }

        // Client-side extension check.
        if ( file.name.toLowerCase().slice(-4) !== '.csv' ) {
            $.workeera_submit_msg_alert({
                msg   : workeera_adminscript_str.importdata_invalid_type,
                class : 'workeera-alert-danger'
            });
            $input.val('');
            return;
        }

        // Client-side size check (5 MB = 5 242 880 bytes).
        if ( file.size > 5242880 ) {
            $.workeera_submit_msg_alert({
                msg   : workeera_adminscript_str.importdata_file_too_large,
                class : 'workeera-alert-danger'
            });
            $input.val('');
            return;
        }

        $.workeera_importdata_start( $section, 'upload', file );

        // Reset so the same file can be re-selected if needed.
        $input.val('');
    });

    // ── Event: Remove all terms of taxonomy
    $('body').on( 'click', '.workeera-importdata-clear-btn', function () {
        var $section = $(this).closest('.workeera-importdata-section');
        var tax_slug = $section.attr('data-tax-slug') || '';
        var tax_name = $section.attr('data-tax-name') || '';

        if ( ! tax_slug ) {
            return;
        }

        // Substitute the taxonomy name into the translated confirmation message.
        var confirm_msg = workeera_adminscript_str.importdata_remove_confirm_msg.replace( '%s', tax_name );

        $.workeera_confirm_box({
            title   : workeera_adminscript_str.importdata_confirm_title,
            message : confirm_msg,
            buttons : {
                confirm : {
                    title  : workeera_adminscript_str.importdata_confirm_remove_btn,
                    class  : 'workeera-delitm-btn',
                    action : function () {
                        var confirm_box_con = $('#workeera-confirm-popup').find('.workeera-modal-content-area');
                        confirm_box_con.append($.workeera_delay_loader_html());
                        $.ajax({
                            url         : workeera_func_str.ajax_url,
                            type        : 'POST',
                            data        : {
                                action       : 'workeera_importdata_remove_tax_data',
                                workeera_nonce : workeera_adminscript_str.nonce,
                                tax_slug     : tax_slug,
                            },
                            dataType    : 'json',
                            success     : function ( data ) {
                                var nonce_err = $.workeera_ajax_security_err_reload({ result: data });
                                if ( nonce_err === 'failed' ) { return; }

                                if ( data.error == '0' ) {
                                    $.workeera_submit_msg_alert({
                                        msg   : data.msg,
                                        class : 'workeera-alert-success'
                                    });
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 500);
                                    $.workeera_confirm_box.hide();
                                } else {
                                    $.workeera_submit_msg_alert({
                                        msg   : data.msg,
                                        class : 'workeera-alert-warning'
                                    });
                                }
                                $.workeera_remove_delay_loader({
                                    target: confirm_box_con
                                });
                            },
                            error       : function() {
                                $.workeera_remove_delay_loader({
                                    target: confirm_box_con
                                });
                            }
                        });
                    }
                },
                cancel : {
                    title  : workeera_adminscript_str.importdata_cancel_btn,
                    class  : 'workeera-cancel-act',
                    action : function () {
                        $.workeera_confirm_box.hide();
                    }
                }
            }
        });
    });

    /**
     * Job Status side metabox — toggle the date-row visibility (Featured / Urgent)
     * when their switch input changes.
     */
    $(document).on('change', '.workeera-job-status-side .workeera-job-status-switch-input', function () {
        var $row = $(this).closest('.workeera-job-status-row');
        if (!$row.length) {
            return;
        }
        if ($(this).is(':checked')) {
            $row.addClass('workeera-job-status-row--on');
        } else {
            $row.removeClass('workeera-job-status-row--on');
        }
    });

})(jQuery);