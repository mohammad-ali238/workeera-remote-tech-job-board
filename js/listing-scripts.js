/**
 * Workeera job listing front-end script.
 *
 * Handles the AJAX-driven listing page: filter collection, REST fetching,
 * URL syncing, pagination, salary slider, mobile filter toggle, and the
 * apply-job popup with CV upload.
 */
(function ($) {
    'use strict';

    /* -------------------------------------------------------------------------
     * State
     * ---------------------------------------------------------------------- */

    var workeeraListing = {

        $wrapper       : null,
        $sidebar       : null,
        $jobsList      : null,
        $paginationHolder : null,
        $resultsCount  : null,
        $loader        : null,
        $noResults     : null,
        $keywordInput  : null,
        $sortSelect    : null,
        $findJobsBtn   : null,

        currentPage    : 1,
        isLoading      : false,
        lastKeyword    : '',
        ignoreFetchOnReset : false,

        /**
         * Bootstrap the listing module.
         */
        init: function () {
            var self = this;

            self.$wrapper          = $( '.workeera-listing-main' );
            if ( ! self.$wrapper.length ) {
                return;
            }

            self.$sidebar           = self.$wrapper.find( '.workeera-filters-sidebar' );
            self.$jobsList          = self.$wrapper.find( '.workeera-jobs-list' );
            self.$paginationHolder  = self.$wrapper.find( '.workeera-jobs-pagination-holder' );
            self.$filterClearHolder = self.$wrapper.find( '.workeera-filters-clear-holder' );
            self.$resultsCount      = self.$wrapper.find( '.workeera-results-count' );
            self.$loader            = self.$wrapper.find( '.workeera-jobs-listin-loader' );
            self.$noResults         = self.$wrapper.find( '.workeera-jobs-no-results' );
            self.$keywordInput      = self.$wrapper.find( '.workeera-keyword-input' );
            self.$sortSelect        = self.$wrapper.find( '.workeera-sort-select' );
            self.$findJobsBtn       = self.$wrapper.find( '.workeera-find-jobs-btn' );

            self.lastKeyword = self.$keywordInput.val();

            self.ignoreFetchOnReset = false;

            // Initial load from URL params.
            self.currentPage = self.getUrlParam( 'page_num' ) ? parseInt( self.getUrlParam( 'page_num' ), 10 ) : 1;
            self.fetchJobs();

            self.bindEvents();
        },

        /* -------------------------------------------------------------------------
         * Event bindings
         * ---------------------------------------------------------------------- */

        /**
         * Wire up filter, sort, keyword, pagination and clear-filter events.
         */
        bindEvents: function () {
            var self = this;

            // Sidebar: "Find Jobs" button triggers filter fetch.
            self.$findJobsBtn.on( 'click', function ( e ) {
                e.preventDefault();
                self.currentPage = 1;
                self.fetchJobs( true );
                // close mobile filters sidebar if open
                self.$sidebar.removeClass( 'workeera-filters-open' );
                $('.workeera-filters-overlay').remove();

                // Scroll to top of listing.
                $( 'html, body' ).animate( { scrollTop: self.$wrapper.offset().top - 60 }, 300 );
            } );

            // Topbar: sort-by change triggers immediately.
            self.$sortSelect.on( 'change', function () {
                if ( self.ignoreFetchOnReset ) {
                    return;
                }
                self.currentPage = 1;
                self.fetchJobs( true );
            } );

            // Topbar: keyword — trigger on Enter or on change when value differs.
            self.$keywordInput.on( 'keydown', function ( e ) {
                if ( e.which === 13 ) { // Enter
                    e.preventDefault();
                    if ( $( this ).val() !== self.lastKeyword ) {
                        self.lastKeyword = $( this ).val();
                        self.currentPage = 1;
                        self.fetchJobs( true );
                    }
                }
            } );

            // Keyword input: detect value change on blur (handles paste / clear).
            self.$keywordInput.on( 'input', function () {
                var val = $( this ).val();
                if ( val === '' && self.lastKeyword !== '' ) {
                    self.lastKeyword = '';
                    self.currentPage = 1;
                    self.fetchJobs( true );
                }
            } );

            // Pagination: delegated click on generated buttons.
            self.$paginationHolder.on( 'click', '.workeera-pagination-nav', function () {
                var page = parseInt( $( this ).data( 'jobs_page' ), 10 );
                if ( page && ! isNaN( page ) ) {
                    self.currentPage = page;
                    self.fetchJobs( false );
                    // Scroll to top of listing.
                    $( 'html, body' ).animate( { scrollTop: self.$wrapper.offset().top - 60 }, 300 );
                }
            } );

            // Remove a single filter
            self.$filterClearHolder.on( 'click', '.workeera-filter-remove-btn', function ( e ) {
                e.preventDefault();
                var keys = $( this ).data( 'keys' );   // array of param names
                self.resetFilterKeys( keys );
                self.currentPage = 1;
                self.fetchJobs( true );
            } );

            // Clear all filters
            self.$filterClearHolder.on( 'click', '.workeera-filter-clear-all-btn', function ( e ) {
                e.preventDefault();
                self.resetAllFilters();
                self.currentPage = 1;
                self.fetchJobs( true );
            } );

            // Clear all filters — triggered from inside the no-results empty state.
            self.$noResults.on( 'click', '.workeera-jobs-no-results-clear', function ( e ) {
                e.preventDefault();
                // Defer to the main Clear All button so a single code path runs.
                self.$filterClearHolder.find( '.workeera-filter-clear-all-btn' ).trigger( 'click' );
            } );
        },

        /* -------------------------------------------------------------------------
         * Filter collection
         * ---------------------------------------------------------------------- */

        /**
         * Collect all active filter values from sidebar + topbar.
         *
         * @return {Object}
         */
        collectFilters: function () {
            var self    = this;
            var filters = {};

            // Keyword.
            var keyword = self.$keywordInput.val();
            if ( keyword ) {
                filters.keyword = keyword;
            }

            // Sort by.
            var sortBy = self.$sortSelect.val();
            if ( sortBy ) {
                filters.sort_by = sortBy;
            }

            // Sidebar inputs (text, checkbox, radio, select, hidden).
            self.$sidebar.find( 'input, select' ).each( function () {
                var $input = $( this );
                var name   = $input.attr( 'name' );

                if ( ! name ) {
                    return;
                }

                // Skip inputs inside a container opted out of the filter query.
                if ( $input.closest( '.workeera-no-filters-con' ).length ) {
                    return;
                }

                name = name.replace(/\[\]/g, '');

                // Skip empty selects and unselected radios/checkboxes.
                var type = $input.attr( 'type' );

                if ( type === 'checkbox' ) {
                    if ( $input.is( ':checked' ) ) {
                        filters[ name ] = $input.val() || '1';
                    }
                    return;
                }

                if ( type === 'radio' ) {
                    if ( $input.is( ':checked' ) ) {
                        filters[ name ] = $input.val();
                    }
                    return;
                }

                var val = $input.val();
                if ( val === '' || val === null ) {
                    return;
                }

                // Multi-select — collect as comma-separated.
                if ( $input.is( 'select[multiple]' ) ) {
                    var selected = [];
                    $input.find( 'option:selected' ).each( function () {
                        var v = $( this ).val();
                        if ( v ) {
                            selected.push( v );
                        }
                    } );
                    if ( selected.length ) {
                        filters[ name ] = selected.join( ',' );
                    }
                    return;
                }

                // Checkbox-style options_group (radio/checkbox inputs sharing a name).
                // These are grouped by name; collect checked values.
                if ( type === 'hidden' && name.indexOf( 'salary' ) === -1 && name.indexOf( 'loc' ) === -1 ) {
                    // Skip hidden inputs that are not salary/location — handled separately.
                    return;
                }

                filters[ name ] = val;
            } );

            // options_group items (radio/checkbox) — re-collect grouped values properly.
            var groupedNames = {};
            self.$sidebar.find( '.workeera-form-opts-group input:checked' ).each( function () {
                var $inp  = $( this );
                var n     = $inp.attr( 'name' );
                if (typeof $inp.attr( 'name' ) !== 'undefined') {
                    n = n.replace(/\[\]/g, '');
                }
                var v     = $inp.val();
                if ( ! n || ! v ) {
                    return;
                }
                if ( ! groupedNames[ n ] ) {
                    groupedNames[ n ] = [];
                }
                groupedNames[ n ].push( v );
            } );
            $.each( groupedNames, function ( name, vals ) {
                filters[ name ] = vals.join( ',' );
            } );

            // Pagination.
            filters.page_num = self.currentPage;

            return filters;
        },

        /* -------------------------------------------------------------------------
         * REST fetch
         * ---------------------------------------------------------------------- */

        /**
         * Fetch jobs via REST API and update the listing.
         *
         * @param {boolean} resetPage - Whether to reset pagination to page 1.
         */
        fetchJobs: function ( resetPage ) {
            var self = this;

            if ( self.isLoading ) {
                return;
            }

            if ( resetPage ) {
                self.currentPage = 1;
            }

            self.isLoading = true;
            self.showLoader();

            var filters = self.collectFilters();
            filters.page_num = self.currentPage;

            // Sync URL.
            self.updateUrl( filters );

            $.ajax( {
                url      : workeera_listing_str.rest_url,
                type     : 'GET',
                data     : filters,
                beforeSend: function ( xhr ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', workeera_listing_str.rest_nonce );
                },
                success  : function ( response ) {
                    self.isLoading = false;
                    self.hideLoader();

                    if ( response && response.success ) {
                        self.renderJobs( response );
                    } else {
                        self.showError();
                    }
                },
                error    : function ( xhr ) {
                    self.isLoading = false;
                    self.hideLoader();

                    // Use common security error handler if nonce related.
                    if ( xhr.status === 403 && typeof $.workeera_ajax_security_err_reload === 'function' ) {
                        $.workeera_ajax_security_err_reload({result: {error: 'security_failed'}});
                        return;
                    }
                    self.showError();
                },
            } );
        },

        /**
         * Reset multiple filter keys at once.
         *
         * @param {Array} keys
         */
        resetFilterKeys: function ( keys ) {
            var self = this;
            self.ignoreFetchOnReset = true;
            $.each( keys, function ( i, key ) {
                self.resetFilterKey( key );
                // When removing country (loc_1), also clear city (loc_2)
                if ( key === 'loc_1' ) {
                    self.resetFilterKey( 'loc_2' );
                }
            } );
            self.ignoreFetchOnReset = false;
        },

        /**
         * Reset a single filter key.
         *
         * @param {string} key
         */
        resetFilterKey: function ( key ) {
            var self = this;

            // ---- Salary range (min/max) ----
            if ( key === 'min_salary' || key === 'max_salary' ) {
                var slider = self.$sidebar.find( '.workeera-job-salary-slider' );
                if ( slider.length ) {
                    var defMin = parseInt( slider.data( 'min' ) ) || 0;
                    var defMax = parseInt( slider.data( 'max' ) ) || 0;
                    slider.slider( 'values', [ defMin, defMax ] );

                    // Update visible labels and hidden inputs
                    var sepratr = ( typeof workeera_func_str !== 'undefined' && workeera_func_str.currency_settings )
                        ? workeera_func_str.currency_settings.thousand_separator
                        : ',';
                    var minFormatted = new Intl.NumberFormat( 'en-US', { minimumFractionDigits: 0 } )
                        .format( defMin ).replace( /,/g, sepratr );
                    var maxFormatted = new Intl.NumberFormat( 'en-US', { minimumFractionDigits: 0 } )
                        .format( defMax ).replace( /,/g, sepratr );

                    self.$sidebar.find( '.workeera-job-min-salary' ).text( minFormatted );
                    self.$sidebar.find( '.workeera-job-max-salary' ).text( maxFormatted );
                    self.$sidebar.find( 'input[name="min_salary"]' ).val( '' );
                    self.$sidebar.find( 'input[name="max_salary"]' ).val( '' );

                    self.$sidebar.find( 'select[name="salary_type"]' ).val( '' ).trigger( 'change' );
                }
                return;
            }

            // ---- Salary type ----
            if ( key === 'salary_type' ) {
                self.$sidebar.find( 'select[name="salary_type"]' ).val( '' ).trigger( 'change' );
                return;
            }

            // ---- Select elements (location, taxonomy, etc.) ----
            var $select = self.$sidebar.find( 'select[name="' + key + '"], select[name="' + key + '[]"]' );
            if ( $select.length ) {
                $select.val( '' ).trigger( 'change' );
                return;
            }

            // ---- Checkboxes (remote_only, urgent) ----
            var $checkbox = self.$sidebar.find( 'input[type="checkbox"][name="' + key + '"]' );
            if ( $checkbox.length ) {
                $checkbox.prop( 'checked', false );
                return;
            }

            // ---- Text inputs ----
            var $text = self.$sidebar.find( 'input[type="text"][name="' + key + '"], input[type="hidden"][name="' + key + '"]' );
            if ( $text.length ) {
                $text.val( '' );
                return;
            }

            // ---- Options group (checkboxes/radio that use name="…[]") ----
            self.$sidebar.find( 'input[name="' + key + '[]"]:checked, input[name="' + key + '"]:checked' ).prop( 'checked', false );

            // ---- Topbar keyword ----
            if ( key === 'keyword' ) {
                self.$keywordInput.val( '' );
            }

            // ---- Topbar sort ----
            if ( key === 'sort_by' ) {
                self.$sortSelect.val( '' ).trigger( 'change' );
            }
        },

        /**
         * Reset every active filter (except page_num).
         */
        resetAllFilters: function () {
            var self = this;
            self.ignoreFetchOnReset = true;

            // Keyword & sort
            self.$keywordInput.val( '' );
            self.$sortSelect.val( '' ).trigger( 'change' );

            // All sidebar inputs (text, select, checkbox, radio)
            self.$sidebar.find( 'input, select' ).not( '[type="hidden"]' ).each( function () {
                var $el = $( this );
                var type = $el.attr( 'type' );
                if ( type === 'checkbox' || type === 'radio' ) {
                    $el.prop( 'checked', false );
                } else if ( $el.is( 'select' ) ) {
                    $el.val( '' ).trigger( 'change' );
                } else {
                    $el.val( '' );
                }
            } );

            // Clear hidden salary inputs
            self.$sidebar.find( 'input[name="min_salary"], input[name="max_salary"]' ).val( '' );

            // Reset salary slider to default values
            var salarySlider = self.$sidebar.find( '.workeera-job-salary-slider' );
            if ( salarySlider.length ) {
                var defMin = parseInt( salarySlider.data( 'min' ) ) || 0;
                var defMax = parseInt( salarySlider.data( 'max' ) ) || 0;
                salarySlider.slider( 'values', [ defMin, defMax ] );

                var sepratr = ( typeof workeera_func_str !== 'undefined' && workeera_func_str.currency_settings )
                    ? workeera_func_str.currency_settings.thousand_separator
                    : ',';
                var minF = new Intl.NumberFormat( 'en-US', { minimumFractionDigits: 0 } )
                    .format( defMin ).replace( /,/g, sepratr );
                var maxF = new Intl.NumberFormat( 'en-US', { minimumFractionDigits: 0 } )
                    .format( defMax ).replace( /,/g, sepratr );

                self.$sidebar.find( '.workeera-job-min-salary' ).text( minF );
                self.$sidebar.find( '.workeera-job-max-salary' ).text( maxF );
                self.$sidebar.find( 'input[name="min_salary"]' ).val( '' );
                self.$sidebar.find( 'input[name="max_salary"]' ).val( '' );
            }

            self.ignoreFetchOnReset = false;
        },

        /* -------------------------------------------------------------------------
         * Render
         * ---------------------------------------------------------------------- */

        /**
         * Inject rendered HTML into the page.
         *
         * @param {Object} response - REST response object.
         */
        renderJobs: function ( response ) {
            var self = this;

            // Results count.
            var total = parseInt( response.total, 10 ) || 0;
            if ( self.$resultsCount.length ) {
                if ( total > 0 ) {
                    self.$resultsCount.html(
                        '<strong>' + total + '</strong> ' +
                        ( total === 1
                            ? workeera_listing_str.results_txt.replace( '%d', '' ).trim()
                            : workeera_listing_str.results_txt.replace( '%d', '' ).trim()
                        )
                    );
                    // Build "Showing X results" with correct pluralisation.
                    self.$resultsCount.html(
                        /* translators: %d: number of jobs */
                        workeera_listing_str.results_txt.replace( '%d', '<strong>' + total + '</strong>' )
                    );
                } else {
                    self.$resultsCount.html( '' );
                }
            }

            // Jobs list.
            if ( response.items_html && response.items_html.trim() !== '' ) {
                self.$jobsList.html( response.items_html );
                self.$noResults.addClass( 'workeera-hide' );
            } else {
                self.$jobsList.html( '' );
                self.$noResults.removeClass( 'workeera-hide' );
            }

            // Pagination.
            if ( response.pagination_html ) {
                self.$paginationHolder.html( response.pagination_html );
            } else {
                self.$paginationHolder.html( '' );
            }

            // Filter buttons
            var filterButtonsHtml = response.filter_buttons_html || '';
            var hasActiveFilters  = ( filterButtonsHtml.trim() !== '' );
            if ( hasActiveFilters ) {
                self.$filterClearHolder.html( filterButtonsHtml ).removeClass( 'workeera-hide' );
            } else {
                self.$filterClearHolder.empty().addClass( 'workeera-hide' );
            }

            // Reveal the "Clear all Filters" button inside the empty state only
            // when there are active filters to clear.
            self.$noResults.find( '.workeera-jobs-no-results-clear' ).toggleClass( 'workeera-hide', ! hasActiveFilters );
        },

        /* -------------------------------------------------------------------------
         * URL management
         * ---------------------------------------------------------------------- */

        /**
         * Push current filters into the browser URL without reloading.
         *
         * @param {Object} filters
         */
        updateUrl: function ( filters ) {
            if ( ! window.history || ! window.history.pushState ) {
                return;
            }

            var params  = new URLSearchParams();
            var exclude = [ 'page_num' ];

            $.each( filters, function ( key, val ) {
                if ( val === '' || val === null || val === undefined ) {
                    return;
                }
                if ( $.inArray( key, exclude ) !== -1 ) {
                    return;
                }
                if ( key === 'page_num' && parseInt( val, 10 ) <= 1 ) {
                    return;
                }
                params.set( key, val );
            } );

            if ( filters.page_num && parseInt( filters.page_num, 10 ) > 1 ) {
                params.set( 'page_num', filters.page_num );
            }

            var newUrl = window.location.pathname;
            var qs     = params.toString();
            if ( qs ) {
                newUrl += '?' + qs;
            }

            window.history.pushState( null, '', newUrl );
        },

        /**
         * Read a query param from the current URL.
         *
         * @param  {string} name
         * @return {string}
         */
        getUrlParam: function ( name ) {
            var params = new URLSearchParams( window.location.search );
            return params.get( name ) || '';
        },

        /* -------------------------------------------------------------------------
         * UI helpers
         * ---------------------------------------------------------------------- */

        /**
         * Show the loader and hide the jobs list.
         */
        showLoader: function () {
            var self = this;
            self.$loader.removeClass( 'workeera-hide' );
            self.$jobsList.addClass( 'workeera-hide' );
        },

        /**
         * Hide the loader and show the jobs list.
         */
        hideLoader: function () {
            var self = this;
            self.$loader.addClass( 'workeera-hide' );
            self.$jobsList.removeClass( 'workeera-hide' );
        },

        /**
         * Render a generic error message in place of the jobs list.
         */
        showError: function () {
            var self = this;
            self.$jobsList.html(
                '<p class="workeera-listing-error">' +
                workeera_listing_str.error_txt +
                '</p>'
            );
            self.$paginationHolder.html( '' );
            self.$noResults.addClass( 'workeera-hide' );
        },

    };

    /* -------------------------------------------------------------------------
     * Popstate — handle browser back/forward navigation
     * ---------------------------------------------------------------------- */

    $( window ).on( 'popstate', function () {
        // Re-read URL and fetch.
        workeeraListing.currentPage = workeeraListing.getUrlParam( 'page_num' )
            ? parseInt( workeeraListing.getUrlParam( 'page_num' ), 10 )
            : 1;
        workeeraListing.fetchJobs( false );
    } );

    /* -------------------------------------------------------------------------
     * Boot on DOM ready
     * ---------------------------------------------------------------------- */

    $( function () {
        workeeraListing.init();
    } );

    /*
    * On document ready
    */
    $(document).ready(function() {

        if ($('.workeera-filters-sidebar .workeera-job-salary-slider').length) {
            $('.workeera-filters-sidebar .workeera-job-salary-slider').each(function() {
                $.workeera_listing_filter_salary_slider($(this));
            });
        }
    });

    /* ============================================================
     Salary Slider Filter
     ============================================================ */

    /**
     * Initialise the salary range jQuery UI slider for a filter target.
     *
     * @param {jQuery} target      Slider element to initialise.
     * @param {string} salary_unit Optional salary unit key (defaults to the configured unit).
     */
    $.workeera_listing_filter_salary_slider = function (target, salary_unit = '') {
        const salary_slider_filter = target.closest('.workeera-job-salary-filter');
        if (salary_unit == '') {
            salary_unit = workeera_func_str.default_salary_unit;
        }
        if (typeof workeera_func_str.salary_ranges[salary_unit] === 'undefined') {
            return;
        }
        if (salary_slider_filter.find('select[name="salary_type"]').length && salary_slider_filter.find('select[name="salary_type"]').val() != '') {
            const slected_salary_type = salary_slider_filter.find('select[name="salary_type"]').val();
            if (typeof workeera_func_str.salary_ranges[slected_salary_type] !== 'undefined') {
                salary_unit = slected_salary_type;
            }
        }
        const sepratr = workeera_func_str.currency_settings.thousand_separator;
        const min_salary_val = parseInt(workeera_func_str.salary_ranges[salary_unit].default.min);
        const max_salary_val = parseInt(workeera_func_str.salary_ranges[salary_unit].default.max);
        const interval_val = parseInt(workeera_func_str.salary_ranges[salary_unit].default.interval);
        let min_value = min_salary_val;
        let max_value = max_salary_val;
        if (salary_slider_filter.find('input[name="min_salary"]').length && salary_slider_filter.find('input[name="min_salary"]').val() > 0) {
            min_value = parseInt(salary_slider_filter.find('input[name="min_salary"]').val());
        }
        if (salary_slider_filter.find('input[name="max_salary"]').length && salary_slider_filter.find('input[name="max_salary"]').val() > 0) {
            max_value = parseInt(salary_slider_filter.find('input[name="max_salary"]').val());
        }
        //
        let min_salary = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 0
        }).format(min_value).replace(/,/g, sepratr);
        let max_salary = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 0
        }).format(max_value).replace(/,/g, sepratr);
        salary_slider_filter.find('.workeera-job-min-salary').text(min_salary);
        salary_slider_filter.find('.workeera-job-max-salary').text(max_salary);
        //
        target.slider({
            range: true,
            min: min_salary_val,
            max: max_salary_val,
            step: interval_val,
            values: [min_value, max_value],
            slide: function(event, ui) {
                min_salary = new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 0
                }).format(ui.values[0]).replace(/,/g, sepratr);
                max_salary = new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 0
                }).format(ui.values[1]).replace(/,/g, sepratr);
                salary_slider_filter.find('.workeera-job-min-salary').text(min_salary);
                salary_slider_filter.find('.workeera-job-max-salary').text(max_salary);
                salary_slider_filter.find('input[name="min_salary"]').val(ui.values[0]);
                salary_slider_filter.find('input[name="max_salary"]').val(ui.values[1]);
            }
        });
    }

    $('.workeera-filters-sidebar .workeera-job-salary-filter .workeera-salary-unit').on('change', 'select', function() {
        const target = $(this);
        const salary_slider_filter = target.closest('.workeera-job-salary-filter');
        const salary_unit = target.val();
        const slider_target = salary_slider_filter.find('.workeera-job-salary-slider');
        salary_slider_filter.find('input[name="min_salary"]').val('');
        salary_slider_filter.find('input[name="max_salary"]').val('');
        $.workeera_listing_filter_salary_slider(slider_target, salary_unit);
    });

    /* ============================================================
     Mobile Filters Toggle
     ============================================================ */
  
    $('.workeera-jobs-topbar').on('click', '.workeera-mobile-filter-btn', function() {
        const closest_jobs_layout = $(this).closest('.workeera-listing-main');
        closest_jobs_layout.find('.workeera-filters-sidebar').addClass('workeera-filters-open');
        if (!$('.workeera-filters-overlay').length) {
            $('body').append('<div class="workeera-filters-overlay workeera-filters-overlay-active"></div>');
        }
    });

    $('.workeera-filters-sidebar').on('click', '.workeera-filters-close', function() {
        const closest_jobs_layout = $(this).closest('.workeera-listing-main');
        closest_jobs_layout.find('.workeera-filters-sidebar').removeClass('workeera-filters-open');
        $('.workeera-filters-overlay').remove();
    });

    // hide options group items
    // Handle radio groups
    const workeera_radio_groups = document.querySelectorAll('.workeera-filter-group .workeera-radio-group');
    if (workeera_radio_groups.length) {
        workeera_radio_groups.forEach(function(opt_itms) {
            const workeera_radios = opt_itms.querySelectorAll('.workeera-radio');
            if (workeera_radios.length) {
                workeera_radios.forEach(function(radio, index_count) {
                    if (index_count > 6) {
                        radio.classList.add('workeera-hide');
                    }
                });
            }
        });
    }

    // Handle checkbox groups
    const workeera_checkbox_groups = document.querySelectorAll('.workeera-filter-group .workeera-checkbox-group');
    if (workeera_checkbox_groups.length) {
        workeera_checkbox_groups.forEach(function(opt_itms) {
            const workeera_checkboxes = opt_itms.querySelectorAll('.workeera-checkbox');
            if (workeera_checkboxes.length) {
                workeera_checkboxes.forEach(function(checkbox, index_count) {
                    if (index_count > 6) {
                        checkbox.classList.add('workeera-hide');
                    }
                });
            }
        });
    }

    $('.workeera-filters-sidebar').on('click', '.workeera-filter-group .workeera-optgroupitms-seemore-btn', function() {
        const this_btn = $(this);
        const main_parent = this_btn.closest('.workeera-filter-group');
        if (main_parent.find('.workeera-checkbox-group').length) {
            var group_itms_con = main_parent.find('.workeera-checkbox-group');
        } else {
            var group_itms_con = main_parent.find('.workeera-radio-group');
        }
        if (group_itms_con.hasClass('workeera-all-items-opened')) {
            group_itms_con.find('>.workeera-visible-itm').each(function() {
                $(this).removeClass('workeera-visible-itm').addClass('workeera-hide');
            });
            group_itms_con.removeClass('workeera-all-items-opened');
            this_btn.find('.workeera-optgroup-shwhide-txt').text(this_btn.data('more_text'));
            this_btn.find('.workeera-optgroup-shwhide-icon').text('+');
        } else {
            group_itms_con.find('>.workeera-hide').each(function() {
                $(this).removeClass('workeera-hide').addClass('workeera-visible-itm');
            });
            group_itms_con.addClass('workeera-all-items-opened');
            this_btn.find('.workeera-optgroup-shwhide-txt').text(this_btn.data('less_text'));
            this_btn.find('.workeera-optgroup-shwhide-icon').text('-');
        }
    });

    // apply job button callback
    $('.workeera-job-apply-btn').on('click', function(ev) {
        ev.preventDefault();
        var this_btn = $(this);
        if (this_btn.hasClass('workeera-user-not-loggedin')) {
            window.location.replace(workeera_func_str.login_url);
            return;
        }
        let popup_id = 'workeera-apply-job-popup';
        $.workeera_modal_popup_open({
            target: popup_id,
            class: 'workeera-applyjob-form-modal'
        });
        let content_con = $('#' + popup_id).find('.workeera-modalinit-con');
        content_con.html($.workeera_delay_loader_html());
        $.ajax({
            url: workeera_func_str.site_url,
            method: "POST",
            data: {
                job_id: this_btn.attr('data-id'),
                workeera_nonce: workeera_func_str.nonce,
                workeera_action: 'workeera_ajax_applyjob_form_call'
            },
            dataType: "json",
            success: function (response) {
                var nonce_err = $.workeera_ajax_security_err_reload({result: response});
                if (nonce_err == 'failed') {
                    return false;
                }
                if (response.error == '0') {
                    content_con.html(response.form_html);
                } else {
                    content_con.html('<div class="workeera-error-msg">' + response.msg + '</div>');
                }
                $.workeera_remove_delay_loader({
                    target: content_con
                });
            },
            error: function() {
                $.workeera_remove_delay_loader({
                    target: content_con
                });
            }
        });
    });

    /**
     * Workeera – Apply Job CV Field
     *
     * Handles:
     *  - Radio-style CV selection (highlighting selected item, syncing hidden input).
     *  - File upload trigger from both the "Upload CV" box and the "Upload New CV" button.
     *  - AJAX upload to workeera_applyjob_cv_upload, injecting the returned radio item.
     *
     * Initialised via delegation on `document` so it works after the popup
     * injects the form HTML dynamically.
     */
 
    /**
     * Return the inline SVG icon markup for a CV file extension.
     *
     * @param  {string} ext File extension (e.g. "pdf", "docx").
     * @return {string}     SVG markup for the matching icon, or a fallback.
     */
    $.workeera_applyjob_cv_icon_svg = function ( ext ) {
        ext = ( ext || '' ).toLowerCase();
        var icons = {
            pdf  : '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#E53E3E"/><path d="M24 0 L36 12 L24 12 Z" fill="#FC8181"/><rect x="24" y="0" width="12" height="12" fill="#C53030"/><text x="18" y="32" font-size="11" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">PDF</text></svg>',
            doc  : '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#2B6CB0"/><path d="M24 0 L36 12 L24 12 Z" fill="#63B3ED"/><rect x="24" y="0" width="12" height="12" fill="#1A4F8A"/><text x="18" y="29" font-size="8" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">WORD</text></svg>',
            docx : '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#2B6CB0"/><path d="M24 0 L36 12 L24 12 Z" fill="#63B3ED"/><rect x="24" y="0" width="12" height="12" fill="#1A4F8A"/><text x="18" y="29" font-size="8" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">WORD</text></svg>',
            txt  : '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#718096"/><path d="M24 0 L36 12 L24 12 Z" fill="#A0AEC0"/><rect x="24" y="0" width="12" height="12" fill="#4A5568"/><rect x="5" y="18" width="22" height="2.5" rx="1" fill="white" opacity="0.9"/><rect x="5" y="24" width="22" height="2.5" rx="1" fill="white" opacity="0.7"/><rect x="5" y="30" width="16" height="2.5" rx="1" fill="white" opacity="0.7"/></svg>',
            jpg  : '<svg width="20" height="20" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="44" height="44" rx="4" fill="#2F855A"/><polygon points="4,44 20,18 36,44" fill="#1C4532"/><circle cx="34" cy="10" r="6" fill="#F6E05E"/></svg>',
            jpeg : '<svg width="20" height="20" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="44" height="44" rx="4" fill="#2F855A"/><polygon points="4,44 20,18 36,44" fill="#1C4532"/><circle cx="34" cy="10" r="6" fill="#F6E05E"/></svg>',
            png  : '<svg width="20" height="20" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="44" height="44" rx="4" fill="#2F855A"/><polygon points="4,44 20,18 36,44" fill="#1C4532"/><circle cx="34" cy="10" r="6" fill="#F6E05E"/></svg>',
        };
        var fallback = '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="34" height="42" rx="4" fill="none" stroke="#A0AEC0" stroke-width="2"/><rect x="6" y="22" width="20" height="2" rx="1" fill="#CBD5E0"/><rect x="6" y="28" width="16" height="2" rx="1" fill="#CBD5E0"/></svg>';
        return icons[ ext ] || fallback;
    }
 
    /**
     * Return the CSS class name for a CV file extension icon.
     *
     * @param  {string} ext File extension (e.g. "pdf", "docx").
     * @return {string}     CSS class name for the matching icon.
     */
    $.workeera_applyjob_cv_icon_class = function ( ext ) {
        var map = {
            pdf  : 'workeera-cvmanager-file-icon-pdf',
            doc  : 'workeera-cvmanager-file-icon-doc',
            docx : 'workeera-cvmanager-file-icon-doc',
            txt  : 'workeera-cvmanager-file-icon-txt',
            jpeg : 'workeera-cvmanager-file-icon-img',
            jpg  : 'workeera-cvmanager-file-icon-img',
            png  : 'workeera-cvmanager-file-icon-img',
        };
        return map[ ( ext || '' ).toLowerCase() ] || 'workeera-cvmanager-file-icon-other';
    }
 
    /**
     * Build the HTML for a CV radio item (mirrors PHP render_cv_radio_item).
     *
     * @param  {number}  fileId     Attachment ID of the uploaded CV.
     * @param  {string}  fileName   Original file name (with extension).
     * @param  {boolean} isSelected Whether the item should render as selected.
     * @return {string}             HTML markup for the radio item.
     */
    $.workeera_applyjob_build_cv_item_html = function ( fileId, fileName, isSelected ) {
        var ext      = ( fileName.split('.').pop() || '' ).toLowerCase();
        var iconCls  = $.workeera_applyjob_cv_icon_class( ext );
        var svg      = $.workeera_applyjob_cv_icon_svg( ext );
        var selCls   = isSelected ? ' workeera-applyjob-cv-item--selected' : '';
        var checked  = isSelected ? 'checked' : '';
 
        return '<label class="workeera-applyjob-cv-item' + selCls + '">' +
            '<input type="radio" name="cv_file_id" value="' + fileId + '" ' + checked + ' class="workeera-applyjob-cv-radio">' +
            '<span class="workeera-applyjob-cv-icon ' + iconCls + '">' + svg + '</span>' +
            '<span class="workeera-applyjob-cv-name" title="' + fileName + '">' + fileName + '</span>' +
            '<span class="workeera-applyjob-cv-check-icon">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
            '</span>' +
        '</label>';
    }

    /**
     * Mark a CV radio item as selected and sync the hidden file-id input.
     *
     * @param {jQuery} $item The clicked CV item element.
     */
    $.workeera_applyjob_select_cv_item = function ( $item ) {
        var $fieldGroup = $item.closest('.workeera-applyjob-cv-field-group');
        $fieldGroup.find('.workeera-applyjob-cv-item').removeClass('workeera-applyjob-cv-item--selected');
        $item.addClass('workeera-applyjob-cv-item--selected');
        var fileId = $item.find('.workeera-applyjob-cv-radio').val();
        $fieldGroup.find('.workeera-applyjob-cv-hidden-id').val( fileId );
    }
 
    /**
     * Upload a chosen CV file via AJAX and inject the resulting radio item.
     *
     * @param {File}   file        File selected from the file input.
     * @param {jQuery} $fieldGroup The CV field group container.
     */
    $.workeera_applyjob_upload_cv = function ( file, $fieldGroup ) {
        var config = workeera_listing_str;
 
        // Client-side validation.
        if ( config.max_size_bytes && file.size > config.max_size_bytes ) {
            $.workeera_submit_msg_alert({ msg: config.error_size || 'File too large.', class: 'workeera-alert-danger' });
            return;
        }
 
        // MIME type check against accept attribute.
        var acceptTypes = ( config.accept || '' ).split(',').map(function(s){ return s.trim(); });
        if ( acceptTypes.length && acceptTypes[0] !== '' && acceptTypes.indexOf( file.type ) === -1 ) {
            $.workeera_submit_msg_alert({ msg: config.error_type || 'File type not allowed.', class: 'workeera-alert-danger' });
            return;
        }
 
        $fieldGroup.append($.workeera_delay_loader_html());
 
        var formData = new FormData();
        formData.append('cv_file',     file);
        formData.append('action',      'workeera_applyjob_cv_upload');
        formData.append('workeera_nonce', config.nonce || workeera_func_str.nonce);
 
        $.ajax({
            url         : config.ajax_url || workeera_func_str.ajax_url,
            type        : 'POST',
            data        : formData,
            processData : false,
            contentType : false,
            dataType    : 'json',
            success     : function ( response ) {
                $.workeera_remove_delay_loader({
                    target: $fieldGroup
                });
 
                var nonceErr = $.workeera_ajax_security_err_reload({ result: response });
                if ( nonceErr === 'failed' ) { return; }
 
                if ( response.error == '0' ) {
                    // Deselect every existing item.
                    $fieldGroup.find('.workeera-applyjob-cv-item').removeClass('workeera-applyjob-cv-item--selected');
 
                    // Determine which list container to use.
                    // If upload-box is present (no existing CVs path), inject into its inner list.
                    var $list = $fieldGroup.find('.workeera-applyjob-cv-list--inline');
                    if ( ! $list.length ) {
                        $list = $fieldGroup.find('.workeera-applyjob-cv-list');
                    }
 
                    // Use server-returned HTML if available, otherwise build locally.
                    var itemHtml = response.item_html
                        ? response.item_html
                        : $.workeera_applyjob_build_cv_item_html( response.file_id, file.name, true );
 
                    $list.append( itemHtml );
 
                    // Sync hidden input.
                    $fieldGroup.find('.workeera-applyjob-cv-hidden-id').val( response.file_id );
 
                    // If the upload box was the entry point, hide it after first successful upload
                    // so only the list of items shows.
                    var $uploadBox = $fieldGroup.find('.workeera-applyjob-cv-upload-box');
                    if ( $uploadBox.length && $list.children().length === 1 ) {
                        // Keep upload box visible but move content focus to the list.
                        // Do NOT hide the box; just let the list grow inside it.
                    }
 
                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-success' });
                } else {
                    $.workeera_submit_msg_alert({ msg: response.msg, class: 'workeera-alert-danger' });
                }
 
                // Reset file input so the same file can be re-selected if needed.
                $fieldGroup.find('.workeera-applyjob-cv-file-input').val('');
            },
            error: function () {
                $.workeera_remove_delay_loader({
                    target: $fieldGroup
                });
                $.workeera_submit_msg_alert({ msg: workeera_listing_str.error_generic, class: 'workeera-alert-danger' });
                $fieldGroup.find('.workeera-applyjob-cv-file-input').val('');
            }
        });
    }
 
    /* ── Delegated event bindings */
    $(document)
 
        // Click on a CV item row → select it.
        .on( 'click', '.workeera-applyjob-cv-item', function () {
            $.workeera_applyjob_select_cv_item( $(this) );
        })
 
        // Upload trigger button (both "Upload CV" and "Upload New CV" button).
        .on( 'click', '.workeera-applyjob-cv-upload-trigger', function () {
            var $fieldGroup = $(this).closest('.workeera-applyjob-cv-field-group');
            $fieldGroup.find('.workeera-applyjob-cv-file-input').trigger('click');
        })
 
        // Hidden file input changes (file chosen via dialog).
        .on( 'change', '.workeera-applyjob-cv-file-input', function () {
            var file = this.files && this.files[0];
            if ( ! file ) { return; }
            var $fieldGroup = $(this).closest('.workeera-applyjob-cv-field-group');
            $.workeera_applyjob_upload_cv( file, $fieldGroup );
        });

    $.workeera_apply_job_callback = function (args_data) {
        var parent_container = args_data.parent_con;
        var response = args_data.result;
        var applyjob_form_iner = parent_container.closest('.workeera-applyjob-form-iner-con');
        applyjob_form_iner.html(response.html);
    }

})(jQuery);
