<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Renders the jobs listings shortcode, REST endpoint and filter query hooks.
 */
class Workeera_Listing {

    /**
     * Jobs per page for listings.
     */
    const JOBS_PER_PAGE = 10;

    /**
     * Register shortcode, asset enqueue, body class, REST routes and WP_Query filters.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 5 );
        add_shortcode( 'workeera_jobs', [ $this, 'listings' ] );
        add_filter( 'body_class', [ $this, 'body_class' ], 5 );

        // REST API endpoint for jobs listing.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // WP_Query join/where hooks for custom tables.
        add_filter( 'posts_join',    [ $this, 'listings_query_join' ],    10, 2 );
        add_filter( 'posts_where',   [ $this, 'listings_query_where' ],   10, 2 );
        add_filter( 'posts_orderby', [ $this, 'listings_query_orderby' ], 10, 2 );
        add_filter( 'posts_groupby', [ $this, 'listings_query_groupby' ], 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Body class
    // -------------------------------------------------------------------------

    /**
     * Add a workeera-page body class on the configured jobs page.
     *
     * @param  array $classes Existing body classes.
     * @return array          Filtered body classes.
     */
    public function body_class( $classes ) {
        global $workeera_plugin_settings;

        $jobs_page = isset( $workeera_plugin_settings['pages_settings']['jobs_page'] ) ? $workeera_plugin_settings['pages_settings']['jobs_page'] : '';
        if ( ! empty( $jobs_page ) && get_post_type( $jobs_page ) === 'page' ) {
            $classes[] = 'workeera-page';
        }
        return $classes;
    }

    // -------------------------------------------------------------------------
    // Enqueue scripts
    // -------------------------------------------------------------------------

    /**
     * Enqueue listing styles and scripts on the jobs page and single job views.
     */
    public function enqueue_scripts() {
        global $workeera_plugin_settings;

        $jobs_page = isset( $workeera_plugin_settings['pages_settings']['jobs_page'] ) ? $workeera_plugin_settings['pages_settings']['jobs_page'] : '';
        if ( ! empty( $jobs_page ) && get_post_type( $jobs_page ) === 'page' && is_page( $jobs_page ) ) {

            wp_enqueue_style( 'workeera-jquery-ui', Workeera_Plugin::root_url() . 'css/jquery-ui/jquery-ui.css', [], WORKEERA_VERSION );
            wp_enqueue_style( 'select2', Workeera_Plugin::root_url() . 'css/select2/select2.min.css', [], WORKEERA_VERSION );
            wp_enqueue_style( 'workeera-common-fields', Workeera_Plugin::root_url() . 'css/common-fields.css', [ 'workeera-styles' ], WORKEERA_VERSION );
            wp_enqueue_style( 'workeera-listing-styles', Workeera_Plugin::root_url() . 'css/listing-styles.css', [ 'workeera-styles' ], WORKEERA_VERSION );

            wp_enqueue_script( 'jquery-ui-slider' );
            wp_enqueue_script( 'select2', Workeera_Plugin::root_url() . 'js/select2/select2.full.min.js', [ 'jquery' ], WORKEERA_VERSION, true );
            wp_enqueue_script( 'workeera-listing-scripts', Workeera_Plugin::root_url() . 'js/listing-scripts.js', [ 'jquery', 'workeera-scripts' ], WORKEERA_VERSION, true );

            // Localize listing script vars.
            wp_localize_script( 'workeera-listing-scripts', 'workeera_listing_str', $this->localize_vars() );

        } elseif (is_singular('workeera_job')) {

            wp_enqueue_style( 'workeera-listing-styles', Workeera_Plugin::root_url() . 'css/listing-styles.css', [ 'workeera-styles' ], WORKEERA_VERSION );
            wp_enqueue_style( 'workeera-common-fields', Workeera_Plugin::root_url() . 'css/common-fields.css', [ 'workeera-styles' ], WORKEERA_VERSION );
            wp_enqueue_style( 'workeera-listing-detail', Workeera_Plugin::root_url() . 'css/listing-detail.css', [ 'workeera-styles' ], WORKEERA_VERSION );

            wp_enqueue_script( 'workeera-listing-scripts', Workeera_Plugin::root_url() . 'js/listing-scripts.js', [ 'jquery', 'workeera-scripts' ], WORKEERA_VERSION, true );

            // Localize listing script vars.
            wp_localize_script( 'workeera-listing-scripts', 'workeera_listing_str', $this->localize_vars() );
        }
    }

    /**
     * Build the localized JS vars array.
     *
     * @return array
     */
    private function localize_vars() {
        global $workeera_plugin_settings;

        $salary_ranges       = workeera_salary_ranges();
        $default_salary_unit = isset( $workeera_plugin_settings['general_settings']['salary_unit'] ) ? $workeera_plugin_settings['general_settings']['salary_unit'] : 'hourly';
        $salary_unit_switch  = isset( $workeera_plugin_settings['general_settings']['salary_unit_field'] ) ? $workeera_plugin_settings['general_settings']['salary_unit_field'] : '';

        $candidate_settings = isset( $workeera_plugin_settings['candidate_settings'] ) ? $workeera_plugin_settings['candidate_settings'] : [];
        $file_size_bytes    = isset( $candidate_settings['max_cv_file_size'] ) ? intval( $candidate_settings['max_cv_file_size'] ) : 5242880;
        $cv_file_types      = isset( $candidate_settings['cv_file_types'] )   ? (array) $candidate_settings['cv_file_types']   : [ 'pdf', 'docx' ];

        $mime_map      = Workeera_Job_Applications::get_cv_mime_map();
        $accept_parts  = [];
        foreach ( $cv_file_types as $type ) {
            if ( isset( $mime_map[ $type ] ) ) {
                $accept_parts[]         = $mime_map[ $type ];
            }
        }
        $accept_attr  = implode( ',', $accept_parts );

        return [
            'rest_url'              => esc_url_raw( rest_url( 'workeera/v1/jobs' ) ),
            'rest_nonce'            => wp_create_nonce( 'wp_rest' ),
            'salary_ranges'         => $salary_ranges,
            'default_salary_unit'   => $default_salary_unit,
            'salary_unit_switch'    => $salary_unit_switch,
            'loading_txt'           => esc_html__( 'Loading…', 'workeera-remote-tech-job-board' ),
            'no_jobs_txt'           => esc_html__( 'No jobs found matching your criteria.', 'workeera-remote-tech-job-board' ),
            'error_txt'             => esc_html__( 'Something went wrong. Please try again.', 'workeera-remote-tech-job-board' ),
            /* translators: %d: number of results */
            'results_txt'           => esc_html__( 'Showing %d results', 'workeera-remote-tech-job-board' ),
            'apply_filters_txt'     => esc_html__( 'Find Jobs', 'workeera-remote-tech-job-board' ),
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'workeera_nonce' ),
            'max_size_bytes'        => $file_size_bytes,
            'accept'                => $accept_attr,
            'error_size'            => esc_html_x( 'File size exceeds the allowed limit.', 'Apply job CV', 'workeera-remote-tech-job-board' ),
            'error_type'            => esc_html_x( 'File type not allowed.', 'Apply job CV', 'workeera-remote-tech-job-board' ),
            'error_generic'         => esc_html_x( 'An error occurred while uploading.', 'Apply job CV', 'workeera-remote-tech-job-board' ),
        ];
    }

    /**
     * Permission callback verifying the X-WP-Nonce header for REST requests.
     *
     * @param  WP_REST_Request $request Incoming REST request.
     * @return bool|WP_Error            True when verified, WP_Error otherwise.
     */
    public function verify_rest_nonce( WP_REST_Request $request ) {
        // Get nonce from headers
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (!$nonce) {
            return new WP_Error(
                'rest_nonce_missing',
                esc_html__( 'Nonce is missing', 'workeera-remote-tech-job-board' ),
                ['status' => 403]
            );
        }
        
        // Verify the nonce
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_nonce_invalid',
                esc_html__( 'Nonce is invalid or expired', 'workeera-remote-tech-job-board' ),
                ['status' => 403]
            );
        }
        
        return true; // Nonce verified
    }

    // -------------------------------------------------------------------------
    // REST API route
    // -------------------------------------------------------------------------

    /**
     * Register the /workeera/v1/jobs REST route used by the listings UI.
     */
    public function register_rest_routes() {
        register_rest_route(
            'workeera/v1',
            '/jobs',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_get_jobs' ],
                'permission_callback' => '__return_true',
                'args'                => $this->rest_args(),
            ]
        );
    }

    /**
     * Define and sanitize REST args.
     *
     * @return array
     */
    private function rest_args() {
        return [
            'keyword'     => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Keyword', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'sort_by'     => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
                'filter_title'      => esc_html_x('Order by', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'jobs_in'     => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Jobs in', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'jobtypes'    => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Job Types', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'timezones'   => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Timezone', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'skills'      => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Skills', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'loc'         => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Location', 'Locations Field', 'workeera-remote-tech-job-board')
            ],
            'loc_1'       => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('Country', 'Locations Field', 'workeera-remote-tech-job-board')
            ],
            'loc_2'       => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'filter_title'      => esc_html_x('City', 'Locations Field', 'workeera-remote-tech-job-board')
            ],
            'min_salary'  => [
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'filter_title'      => esc_html_x('Salary', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'max_salary'  => [
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'filter_title'      => esc_html_x('Salary', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'salary_type' => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
                'filter_title'      => esc_html_x('Salary unit', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'remote_only' => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
                'filter_title'      => esc_html_x('Remote only', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'urgent'      => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
                'filter_title'      => esc_html_x('Urgent Hiring', 'Listing filters', 'workeera-remote-tech-job-board')
            ],
            'page_num'  => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // REST callback — main query + render
    // -------------------------------------------------------------------------

    /**
     * REST callback: query jobs and return rendered HTML + pagination.
     *
     * @param  WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_get_jobs( WP_REST_Request $request ) {
        global $workeera_plugin_settings;

        $filters = $this->extract_filters( $request );

        $per_page = isset( $workeera_plugin_settings['job_listings_settings']['items_per_page'] )
            ? absint( $workeera_plugin_settings['job_listings_settings']['items_per_page'] )
            : self::JOBS_PER_PAGE;
        if ( $per_page < 1 ) {
            $per_page = self::JOBS_PER_PAGE;
        }

        $paged = max( 1, absint( $request->get_param( 'page_num' ) ) );

        // Store filters in a transient property so join/where hooks can read them.
        $this->current_query_filters = $filters;
        $this->is_listing_query      = true;

        $query_args = [
            'post_type'      => 'workeera_job',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'no_found_rows'  => false,
            'workeera_listing' => true, // flag for our custom hooks
        ];

        // Keyword search.
        if ( ! empty( $filters['keyword'] ) ) {
            $query_args['s'] = $filters['keyword'];
        }

        // Orderby.
        switch ( $filters['sort_by'] ) {
            case 'oldest':
                $query_args['orderby'] = 'date';
                $query_args['order']   = 'ASC';
                break;
            case 'salary_high':
                $query_args['orderby'] = 'workeera_salary_high';
                $query_args['order']   = 'DESC';
                break;
            case 'salary_low':
                $query_args['orderby'] = 'workeera_salary_low';
                $query_args['order']   = 'ASC';
                break;
            default: // newest
                $query_args['orderby'] = 'date';
                $query_args['order']   = 'DESC';
                break;
        }

        $query = new WP_Query( $query_args );

        $this->is_listing_query = false;

        $total_jobs  = absint( $query->found_posts );
        $total_pages = absint( $query->max_num_pages );

        $items_html = '';
        if ( $query->have_posts() ) {
            ob_start();
            while ( $query->have_posts() ) {
                $query->the_post();
                $this->render_job_card( get_post() );
            }
            wp_reset_postdata();
            $items_html = ob_get_clean();
        }

        $filters = $this->extract_filters( $request );

        $pagination_html = $this->render_pagination( $paged, $total_pages );

        return new WP_REST_Response(
            [
                'success'             => true,
                'total'               => $total_jobs,
                'total_pages'         => $total_pages,
                'current_page'        => $paged,
                'items_html'          => $items_html,
                'pagination_html'     => $pagination_html,
                'filter_buttons_html' => $this->render_filter_buttons( $filters ),
            ],
            200
        );
    }

    /**
     * Extract and sanitize filters from the REST request.
     *
     * @param  WP_REST_Request $request
     * @return array
     */
    private function extract_filters( WP_REST_Request $request ) {
        $salary_units = workeera_salary_units();

        $salary_type = $request->get_param( 'salary_type' );
        if ( ! isset( $salary_units[ $salary_type ] ) ) {
            $salary_type = '';
        }

        $sort_by_allowed = [ 'newest', 'oldest', 'salary_high', 'salary_low' ];
        $sort_by         = $request->get_param( 'sort_by' );
        if ( ! in_array( $sort_by, $sort_by_allowed, true ) ) {
            $sort_by = 'newest';
        }

        // Comma-separated taxonomy slugs.
        $parse_slugs = function( $val ) {
            if ( empty( $val ) ) {
                return [];
            }
            return array_filter( array_map( 'sanitize_title', explode( ',', $val ) ) );
        };

        return [
            'keyword'     => sanitize_text_field( $request->get_param( 'keyword' ) ),
            'sort_by'     => $sort_by,
            'jobs_in'     => $parse_slugs( $request->get_param( 'jobs_in' ) ),   // sectors
            'jobtypes'    => $parse_slugs( $request->get_param( 'jobtypes' ) ),
            'timezones'   => $parse_slugs( $request->get_param( 'timezones' ) ),
            'skills'      => $parse_slugs( $request->get_param( 'skills' ) ),
            'loc'         => absint( $request->get_param( 'loc' ) ),
            'loc_1'       => absint( $request->get_param( 'loc_1' ) ),  // country (countries_cities mode)
            'loc_2'       => absint( $request->get_param( 'loc_2' ) ),  // city    (countries_cities mode)
            'min_salary'  => absint( $request->get_param( 'min_salary' ) ),
            'max_salary'  => absint( $request->get_param( 'max_salary' ) ),
            'salary_type' => $salary_type,
            'remote_only' => sanitize_key( $request->get_param( 'remote_only' ) ),
            'urgent'      => sanitize_key( $request->get_param( 'urgent' ) ),
        ];
    }

    // -------------------------------------------------------------------------
    // WP_Query custom join / where / orderby / groupby hooks
    // -------------------------------------------------------------------------

    /**
     * Flag so hooks only apply to our listing query.
     * @var bool
     */
    private $is_listing_query = false;

    /**
     * Current filter set passed from rest_get_jobs().
     * @var array
     */
    private $current_query_filters = [];

    /**
     * Join workeera_job_fields and relation tables.
     */
    public function listings_query_join( $join, WP_Query $query ) {
        global $wpdb;

        if ( empty( $query->query_vars['workeera_listing'] ) ) {
            return $join;
        }

        $filters = $this->current_query_filters;

        // Always join job_fields for salary / location_type / is_urgent / is_filled / is_featured.
        $jf_table = $wpdb->prefix . 'workeera_job_fields';
        $join     .= " LEFT JOIN {$jf_table} AS workeera_jf ON ( workeera_jf.job_id = {$wpdb->posts}.ID )";

        // Join locations table when a location filter is active.
        $needs_location = ( ! empty( $filters['loc'] ) || ! empty( $filters['loc_1'] ) || ! empty( $filters['loc_2'] ) );
        if ( $needs_location ) {
            $loc_table = $wpdb->prefix . 'workeera_locations';
            $join     .= " LEFT JOIN {$loc_table} AS workeera_loc ON ( workeera_loc.object_id = {$wpdb->posts}.ID )";
        }

        // Join sector relation when jobs_in filter is active.
        if ( ! empty( $filters['jobs_in'] ) ) {
            $sectors_table    = $wpdb->prefix . 'workeera_sectors';
            $sectors_rel_table = $wpdb->prefix . 'workeera_sectors_relation';
            $join .= " LEFT JOIN {$sectors_rel_table} AS workeera_sectors_rel ON ( workeera_sectors_rel.object_id = {$wpdb->posts}.ID AND workeera_sectors_rel.object_type = 'post' )";
            $join .= " LEFT JOIN {$sectors_table} AS workeera_sectors_t ON ( workeera_sectors_t.sector_id = workeera_sectors_rel.sector_id )";
        }

        // Join jobtypes relation.
        if ( ! empty( $filters['jobtypes'] ) ) {
            $jt_table     = $wpdb->prefix . 'workeera_jobtypes';
            $jt_rel_table = $wpdb->prefix . 'workeera_jobtypes_relation';
            $join .= " LEFT JOIN {$jt_rel_table} AS workeera_jt_rel ON ( workeera_jt_rel.object_id = {$wpdb->posts}.ID AND workeera_jt_rel.object_type = 'post' )";
            $join .= " LEFT JOIN {$jt_table} AS workeera_jt ON ( workeera_jt.jobtype_id = workeera_jt_rel.jobtype_id )";
        }

        // Join timezones relation.
        if ( ! empty( $filters['timezones'] ) ) {
            $tz_table     = $wpdb->prefix . 'workeera_timezones';
            $tz_rel_table = $wpdb->prefix . 'workeera_timezones_relation';
            $join .= " LEFT JOIN {$tz_rel_table} AS workeera_tz_rel ON ( workeera_tz_rel.object_id = {$wpdb->posts}.ID AND workeera_tz_rel.object_type = 'post' )";
            $join .= " LEFT JOIN {$tz_table} AS workeera_tz ON ( workeera_tz.timezone_id = workeera_tz_rel.timezone_id )";
        }

        // Join skills relation.
        if ( ! empty( $filters['skills'] ) ) {
            $sk_table     = $wpdb->prefix . 'workeera_skills';
            $sk_rel_table = $wpdb->prefix . 'workeera_skills_relation';
            $join .= " LEFT JOIN {$sk_rel_table} AS workeera_sk_rel ON ( workeera_sk_rel.object_id = {$wpdb->posts}.ID AND workeera_sk_rel.object_type = 'post' )";
            $join .= " LEFT JOIN {$sk_table} AS workeera_sk ON ( workeera_sk.skill_id = workeera_sk_rel.skill_id )";
        }

        return $join;
    }

    /**
     * Apply WHERE conditions from active filters.
     */
    public function listings_query_where( $where, WP_Query $query ) {
        global $wpdb;

        if ( empty( $query->query_vars['workeera_listing'] ) ) {
            return $where;
        }

        $filters = $this->current_query_filters;

        // Location filter — loc is used for single-mode (countries_only or cities_only).
        if ( ! empty( $filters['loc'] ) ) {
            $loc_val = absint( $filters['loc'] );
            // Determine if it's a country or city based on context — we check both columns.
            $where .= $wpdb->prepare(
                " AND ( workeera_loc.country_id = %d OR workeera_loc.city_id = %d )",
                $loc_val,
                $loc_val
            );
        }

        // loc_1 = country, loc_2 = city (countries_cities mode).
        if ( ! empty( $filters['loc_1'] ) ) {
            $where .= $wpdb->prepare( " AND workeera_loc.country_id = %d", absint( $filters['loc_1'] ) );
        }
        if ( ! empty( $filters['loc_2'] ) ) {
            $where .= $wpdb->prepare( " AND workeera_loc.city_id = %d", absint( $filters['loc_2'] ) );
        }

        // Salary filter.
        if ( ! empty( $filters['min_salary'] ) && ! empty( $filters['max_salary'] ) ) {
            $user_min = absint( $filters['min_salary'] );
            $user_max = absint( $filters['max_salary'] );
            $where .= $wpdb->prepare(
                " AND (
                    -- Case 1: Both min and max are set - check range overlap
                    (workeera_jf.min_salary > 0 AND workeera_jf.max_salary > 0 
                    AND workeera_jf.min_salary >= %d AND workeera_jf.max_salary <= %d)
                    OR
                    -- Case 2: Only min_salary is set - treat it as 'at least this much'
                    (workeera_jf.min_salary > 0 AND (workeera_jf.max_salary IS NULL OR workeera_jf.max_salary = 0) 
                    AND workeera_jf.min_salary <= %d AND workeera_jf.min_salary >= %d)
                    OR
                    -- Case 3: Only max_salary is set - treat it as 'up to this much'
                    ((workeera_jf.min_salary IS NULL OR workeera_jf.min_salary = 0) AND workeera_jf.max_salary > 0 
                    AND workeera_jf.max_salary <= %d AND workeera_jf.max_salary >= %d)
                )",
                $user_min, $user_max,  // For Case 1
                $user_max, $user_min,  // For Case 2
                $user_max, $user_min   // For Case 3
            );
        }

        // Salary type filter.
        if ( ! empty( $filters['salary_type'] ) ) {
            $where .= $wpdb->prepare( " AND workeera_jf.salary_type = %s", $filters['salary_type'] );
        }

        // Remote only.
        if ( 'on' === $filters['remote_only'] || '1' === $filters['remote_only'] ) {
            $where .= $wpdb->prepare( " AND workeera_jf.location_type = %s", 'remote' );
        }

        // Urgent only.
        if ( 'on' === $filters['urgent'] || '1' === $filters['urgent'] ) {
            $where .= " AND workeera_jf.is_urgent = 1";
        }

        // Sectors (jobs_in) — match by slug.
        if ( ! empty( $filters['jobs_in'] ) ) {
            $slugs        = array_map( 'esc_sql', $filters['jobs_in'] );
            $placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
            $where       .= $wpdb->prepare( " AND workeera_sectors_t.slug IN ( {$placeholders} )", $slugs );
        }

        // Job types — match by slug.
        if ( ! empty( $filters['jobtypes'] ) ) {
            $slugs        = array_map( 'esc_sql', $filters['jobtypes'] );
            $placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
            $where       .= $wpdb->prepare( " AND workeera_jt.slug IN ( {$placeholders} )", $slugs );
        }

        // Timezones — match by slug.
        if ( ! empty( $filters['timezones'] ) ) {
            $slugs        = array_map( 'esc_sql', $filters['timezones'] );
            $placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
            $where       .= $wpdb->prepare( " AND workeera_tz.slug IN ( {$placeholders} )", $slugs );
        }

        // Skills — match by slug.
        if ( ! empty( $filters['skills'] ) ) {
            $slugs        = array_map( 'esc_sql', $filters['skills'] );
            $placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
            $where       .= $wpdb->prepare( " AND workeera_sk.slug IN ( {$placeholders} )", $slugs );
        }

        return $where;
    }

    /**
     * Custom ORDER BY for salary sorts.
     */
    public function listings_query_orderby( $orderby, WP_Query $query ) {
        global $wpdb;

        if ( empty( $query->query_vars['workeera_listing'] ) ) {
            return $orderby;
        }

        $featured_order = "workeera_jf.is_featured DESC, ";

        $sort_by = isset( $this->current_query_filters['sort_by'] ) ? $this->current_query_filters['sort_by'] : 'newest';

        if ( 'salary_high' === $sort_by ) {
            return "workeera_jf.max_salary DESC, {$wpdb->posts}.post_date DESC";
        }

        if ( 'salary_low' === $sort_by ) {
            return "workeera_jf.min_salary ASC, {$wpdb->posts}.post_date DESC";
        }

        return $featured_order . $orderby;
    }

    /**
     * GROUP BY to avoid duplicate posts when multiple relation rows match.
     */
    public function listings_query_groupby( $groupby, WP_Query $query ) {
        global $wpdb;

        if ( empty( $query->query_vars['workeera_listing'] ) ) {
            return $groupby;
        }

        return "{$wpdb->posts}.ID";
    }

    // -------------------------------------------------------------------------
    // Render helpers
    // -------------------------------------------------------------------------

    /**
     * Render a single job card.
     *
     * @param WP_Post $post
     */
    private function render_job_card( WP_Post $post ) {
        global $workeera_plugin_settings;

        $job_id  = absint( $post->ID );
        $job_url = get_permalink( $job_id );

        $job_fields  = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $is_featured = isset( $job_fields['is_featured'] ) ? (int) $job_fields['is_featured'] : 0;
        $is_urgent   = isset( $job_fields['is_urgent'] ) ? (int) $job_fields['is_urgent'] : 0;
        $is_filled   = isset( $job_fields['is_filled'] ) ? (int) $job_fields['is_filled'] : 0;
        $min_salary  = isset( $job_fields['min_salary'] ) ? absint( $job_fields['min_salary'] ) : 0;
        $max_salary  = isset( $job_fields['max_salary'] ) ? absint( $job_fields['max_salary'] ) : 0;
        $salary_type = isset( $job_fields['salary_type'] ) ? $job_fields['salary_type'] : '';
        $loc_type    = isset( $job_fields['location_type'] ) ? $job_fields['location_type'] : '';

        // Logo.
        $logo_url = workeera_job_logo( $job_id );

        // Company info.
        $company_info = workeera_job_company_info( $job_id );
        $company_name = isset( $company_info['name'] ) ? workeera_strip_slashes( $company_info['name'] ) : '';

        // Taxonomy terms.
        $jobtype_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $job_id, 'jobtypes' );

        // Location.
        $location_row = Workeera_Object_Terms_Relation::get_post_location( $job_id );
        $location_str = $this->get_location_string( $location_row );

        // Location type label & icon.
        $allow_location_type = isset( $workeera_plugin_settings['job_submission_settings']['allow_location_type'] ) ? $workeera_plugin_settings['job_submission_settings']['allow_location_type'] : 'on';
        $loc_type_data = $this->get_location_type_display( $loc_type );
        if ($allow_location_type != 'on') {
            $loc_type_data = null;
        }

        // Salary units label.
        $salary_units = workeera_salary_units();
        $salary_unit_label = isset( $salary_units[ $salary_type ] ) ? $salary_units[ $salary_type ] : '';

        // Posted time.
        $posted_ago = human_time_diff( get_post_time( 'U', false, $post ), current_time( 'timestamp' ) );
        /* translators: %s: human-readable time difference */
        $posted_label = sprintf( esc_html__( 'Posted %s ago', 'workeera-remote-tech-job-board' ), esc_html( $posted_ago ) );
        ?>
        <div class="workeera-job-card<?php echo esc_attr( $is_featured ? ' workeera-job-featured' : '' ); ?>">
            <?php if ( $is_featured || $is_urgent ) : ?>
                <div class="workeera-job-badges">
                    <?php if ( $is_featured ) : ?>
                        <span class="workeera-badge workeera-badge-featured">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php echo esc_html__( 'Featured', 'workeera-remote-tech-job-board' ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $is_urgent ) : ?>
                        <span class="workeera-badge workeera-badge-urgent">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <?php echo esc_html__( 'Urgent', 'workeera-remote-tech-job-board' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="workeera-job-body">
                <div class="workeera-job-left">
                    <div class="workeera-job-logo">
                        <a href="<?php echo esc_url( $job_url ); ?>">
                            <div class="workeera-job-logobox"<?php echo ($logo_url ? ' style="background-image: url(\'' . esc_url( $logo_url ) . '\');"' : ''); ?>></div>
                        </a>
                    </div>
                    <div class="workeera-job-info">
                        <div class="workeera-job-title">
                            <a href="<?php echo esc_url( $job_url ); ?>"><?php echo esc_html( get_the_title( $job_id ) ); ?></a>
                        </div>
                        <?php if ( $company_name ) : ?>
                            <p class="workeera-job-company"><?php echo esc_html( $company_name ); ?></p>
                        <?php endif; ?>
                        <div class="workeera-job-meta">
                            <?php if ( $location_str ) : ?>
                                <span class="workeera-job-meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <?php echo esc_html( $location_str ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $loc_type_data ) : ?>
                                <span class="workeera-job-meta-item">
                                    <?php echo wp_kses( (string) $loc_type_data['icon'], workeera_svg_allowed_tags() ); ?>
                                    <?php echo esc_html( $loc_type_data['label'] ); ?>
                                </span>
                            <?php endif; ?>
                            <span class="workeera-job-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo esc_html( $posted_label ); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if ( ! empty( $jobtype_labels ) ) : ?>
                    <div class="workeera-job-middle">
                        <div class="workeera-job-tags">
                            <?php foreach ( $jobtype_labels as $jt_label ) : ?>
                                <span class="workeera-badge workeera-badge-info"><?php echo esc_html( $jt_label ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="workeera-job-right">
                    <?php if ( $min_salary || $max_salary ) : ?>
                        <span class="workeera-job-salary">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="6" width="20" height="12" rx="2"/>
                                <circle cx="12" cy="12" r="2"/>
                                <path d="M6 12h.01M18 12h.01"/>
                            </svg>
                            <?php
                            if ( $min_salary && $max_salary && $min_salary !== $max_salary ) {
                                echo wp_kses_post( workeera_formatted_salary( $min_salary ) ) . ' - ' . wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                            } elseif ( $max_salary ) {
                                echo wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                            } else {
                                echo wp_kses_post( workeera_formatted_salary( $min_salary, '', $salary_unit_label ) );
                            }
                            ?>
                        </span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $job_url ); ?>" class="workeera-btn workeera-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        <?php echo esc_html__( 'Apply Now', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Build a human-readable location string from a location row.
     *
     * @param  array $location_row
     * @return string
     */
    private function get_location_string( $location_row ) {
        global $wpdb;

        if ( empty( $location_row ) ) {
            return '';
        }

        $parts = [];

        $city_id    = ! empty( $location_row['city_id'] ) ? absint( $location_row['city_id'] ) : 0;
        $country_id = ! empty( $location_row['country_id'] ) ? absint( $location_row['country_id'] ) : 0;

        if ( $city_id > 0 ) {
            $cities_table = $wpdb->prefix . 'workeera_cities';
            $city_label   = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$cities_table} WHERE city_id = %d LIMIT 1", $city_id ) );
            if ( $city_label ) {
                $parts[] = $city_label;
            }
        }

        if ( $country_id > 0 ) {
            $countries_table = $wpdb->prefix . 'workeera_countries';
            $country_label   = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$countries_table} WHERE country_id = %d LIMIT 1", $country_id ) );
            if ( $country_label ) {
                $parts[] = $country_label;
            }
        }

        return implode( ', ', $parts );
    }

    /**
     * Return icon SVG + label for a location type.
     *
     * @param  string $loc_type
     * @return array|null
     */
    private function get_location_type_display( $loc_type ) {
        $types = [
            'remote' => [
                'label' => esc_html__( 'Remote', 'workeera-remote-tech-job-board' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
            ],
            'hybrid' => [
                'label' => esc_html__( 'Hybrid', 'workeera-remote-tech-job-board' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            ],
            'onsite' => [
                'label' => esc_html__( 'On-Site', 'workeera-remote-tech-job-board' ),
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
            ],
        ];

        return isset( $types[ $loc_type ] ) ? $types[ $loc_type ] : null;
    }

    /**
     * Render pagination HTML.
     *
     * @param  int $current_page
     * @param  int $total_pages
     * @return string
     */
    private function render_pagination( $current_page, $total_pages ) {
        if ( $total_pages <= 1 ) {
            return '';
        }

        $prev_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
        $next_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';

        $html  = '<div class="workeera-pagination">';

        // Previous.
        if ( $current_page <= 1 ) {
            $html .= '<button class="workeera-pagination-btn workeera-pagination-disabled" disabled>' . $prev_svg . '</button>';
        } else {
            $html .= '<button class="workeera-pagination-btn workeera-pagination-nav" data-jobs_page="' . ( $current_page - 1 ) . '">' . $prev_svg . '</button>';
        }

        // Page numbers.
        $page_links = $this->build_page_links( $current_page, $total_pages );
        foreach ( $page_links as $link ) {
            if ( 'ellipsis' === $link ) {
                $html .= '<span class="workeera-pagination-dots">&hellip;</span>';
            } elseif ( (int) $link === $current_page ) {
                $html .= '<button class="workeera-pagination-btn workeera-pagination-active" data-jobs_page="' . absint( $link ) . '">' . absint( $link ) . '</button>';
            } else {
                $html .= '<button class="workeera-pagination-btn workeera-pagination-nav" data-jobs_page="' . absint( $link ) . '">' . absint( $link ) . '</button>';
            }
        }

        // Next.
        if ( $current_page >= $total_pages ) {
            $html .= '<button class="workeera-pagination-btn workeera-pagination-disabled" disabled>' . $next_svg . '</button>';
        } else {
            $html .= '<button class="workeera-pagination-btn workeera-pagination-nav" data-jobs_page="' . ( $current_page + 1 ) . '">' . $next_svg . '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build compact page number array with ellipsis markers.
     *
     * @param  int $current
     * @param  int $total
     * @return array
     */
    private function build_page_links( $current, $total ) {
        if ( $total <= 7 ) {
            return range( 1, $total );
        }

        $links = [ 1 ];

        if ( $current > 3 ) {
            $links[] = 'ellipsis';
        }

        $start = max( 2, $current - 1 );
        $end   = min( $total - 1, $current + 1 );

        for ( $i = $start; $i <= $end; $i++ ) {
            $links[] = $i;
        }

        if ( $current < $total - 2 ) {
            $links[] = 'ellipsis';
        }

        $links[] = $total;

        return $links;
    }

    /**
     * Render filter-remove buttons (individual + clear all).
     *
     * @param  array $filters Active filters from the request.
     * @return string HTML
     */
    private function render_filter_buttons( $filters ) {
        $args_def = $this->rest_args();
        $buttons  = [];

        // Keyword
        if ( ! empty( $filters['keyword'] ) ) {
            $buttons[] = [
                'label' => $args_def['keyword']['filter_title'],
                'keys'  => [ 'keyword' ],
            ];
        }

        // Sort by
        if ( ! empty( $filters['sort_by'] ) && $filters['sort_by'] != 'newest' ) {
            $buttons[] = [
                'label' => $args_def['sort_by']['filter_title'],
                'keys'  => [ 'sort_by' ],
            ];
        }

        // Jobs in (sectors)
        if ( ! empty( $filters['jobs_in'] ) ) {
            $buttons[] = [
                'label' => $args_def['jobs_in']['filter_title'],
                'keys'  => [ 'jobs_in' ],
            ];
        }

        // Job types
        if ( ! empty( $filters['jobtypes'] ) ) {
            $buttons[] = [
                'label' => $args_def['jobtypes']['filter_title'],
                'keys'  => [ 'jobtypes' ],
            ];
        }

        // Timezones
        if ( ! empty( $filters['timezones'] ) ) {
            $buttons[] = [
                'label' => $args_def['timezones']['filter_title'],
                'keys'  => [ 'timezones' ],
            ];
        }

        // Skills
        if ( ! empty( $filters['skills'] ) ) {
            $buttons[] = [
                'label' => $args_def['skills']['filter_title'],
                'keys'  => [ 'skills' ],
            ];
        }

        // Location – single mode (loc)
        if ( ! empty( $filters['loc'] ) ) {
            $buttons[] = [
                'label' => $args_def['loc']['filter_title'],
                'keys'  => [ 'loc' ],
            ];
        }

        // Country (countries_cities mode)
        if ( ! empty( $filters['loc_1'] ) ) {
            $buttons[] = [
                'label' => $args_def['loc_1']['filter_title'],
                'keys'  => [ 'loc_1' ],
            ];
        }

        // City (countries_cities mode)
        if ( ! empty( $filters['loc_2'] ) ) {
            $buttons[] = [
                'label' => $args_def['loc_2']['filter_title'],
                'keys'  => [ 'loc_2' ],
            ];
        }

        // Salary range (min + max)
        if ( ! empty( $filters['min_salary'] ) || ! empty( $filters['max_salary'] ) ) {
            $buttons[] = [
                'label' => $args_def['min_salary']['filter_title'],
                'keys'  => [ 'min_salary', 'max_salary' ],
            ];
        }

        // Salary type
        if ( ! empty( $filters['salary_type'] ) ) {
            $buttons[] = [
                'label' => $args_def['salary_type']['filter_title'],
                'keys'  => [ 'salary_type' ],
            ];
        }

        // Remote only
        if ( 'on' === $filters['remote_only'] || '1' === $filters['remote_only'] ) {
            $buttons[] = [
                'label' => $args_def['remote_only']['filter_title'],
                'keys'  => [ 'remote_only' ],
            ];
        }

        // Urgent
        if ( 'on' === $filters['urgent'] || '1' === $filters['urgent'] ) {
            $buttons[] = [
                'label' => $args_def['urgent']['filter_title'],
                'keys'  => [ 'urgent' ],
            ];
        }

        if ( empty( $buttons ) ) {
            return '';
        }

        $html = '<div class="workeera-filter-remove-buttons">';
        $html .= '<div class="workeera-filter-removebtn-itms">';
        $html .= '<div class="workeera-clear-filters-label"><strong>' . esc_html__( 'Clear Filters:', 'workeera-remote-tech-job-board' ) . '</strong></div>';
        foreach ( $buttons as $btn ) {
            $html .= sprintf(
                '<button class="workeera-filter-remove-btn workeera-btn" data-keys="%s">%s <span>&times;</span></button> ',
                esc_attr( wp_json_encode( $btn['keys'] ) ),
                esc_html( $btn['label'] )
            );
        }
        $html .= '</div>';
        $html .= sprintf(
            '<div class="workeera-clearall-btn-holder"><button class="workeera-filter-clear-all-btn workeera-btn"><span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                </svg></span>%s</button></div>',
            esc_html__( 'Clear All', 'workeera-remote-tech-job-board' )
        );
        $html .= '</div>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // Shortcode — static shell (AJAX fills the list)
    // -------------------------------------------------------------------------

    /**
     * Shortcode callback that renders the listings page shell.
     *
     * @param  array $args Shortcode attributes.
     * @return string      Rendered listings HTML.
     */
    public function listings( $args ) {
        global $wpdb, $workeera_plugin_settings;

        $random_num = wp_rand( 10000000, 99999999 );

        $salary_ranges       = workeera_salary_ranges();
        $default_salary_unit = isset( $workeera_plugin_settings['general_settings']['salary_unit'] ) ? $workeera_plugin_settings['general_settings']['salary_unit'] : 'hourly';
        $salary_unit_switch  = isset( $workeera_plugin_settings['general_settings']['salary_unit_field'] ) ? $workeera_plugin_settings['general_settings']['salary_unit_field'] : '';

        $countries      = Workeera_Object_Terms::countries_list();
        $countries_list = [ '' => esc_html__( 'Select Country', 'workeera-remote-tech-job-board' ) ];
        foreach ( $countries as $country ) {
            $countries_list[ $country['country_id'] ] = $country['label'];
        }

        // Active Filters is a sortable-switch setting → { order, items }. Derive
        // the ENABLED filter slugs in the admin-chosen order. Older installs may
        // still hold a legacy flat array, and a fresh install has nothing saved.
        $raw_active_filters = isset( $workeera_plugin_settings['job_listings_settings']['listing_active_filters'] )
            ? $workeera_plugin_settings['job_listings_settings']['listing_active_filters']
            : null;
        $known_listing_filters = [ 'location', 'salary', 'sectors', 'jobtypes', 'timezones', 'skills' ];

        if ( is_array( $raw_active_filters ) && isset( $raw_active_filters['items'] ) ) {
            $listing_active_filters = Workeera_Form_Fields::sortable_switch_enabled_in_order( $raw_active_filters, $known_listing_filters );
        } elseif ( is_array( $raw_active_filters ) && ! empty( $raw_active_filters ) ) {
            $listing_active_filters = array_values( $raw_active_filters );
        } else {
            $listing_active_filters = [ 'location', 'sectors', 'jobtypes', 'timezones', 'skills' ];
        }

        $allow_listing_filters = isset( $workeera_plugin_settings['job_listings_settings']['allow_listing_filters'] )
            ? $workeera_plugin_settings['job_listings_settings']['allow_listing_filters']
            : 'on';

        if ( empty( $listing_active_filters ) || ! is_array( $listing_active_filters ) ) {
            $allow_listing_filters = '';
        }

        // Read current URL params to pre-populate filters.
        $keyword_val    = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : '';
        if ( mb_strlen( $keyword_val ) > 150 ) {
            $keyword_val = mb_substr( $keyword_val, 0, 150 );
        }
        $sort_by_val    = isset( $_GET['sort_by'] ) ? sanitize_key( $_GET['sort_by'] ) : '';
        $remote_only    = isset( $_GET['remote_only'] ) ? '1' : '';
        $urgent_val     = isset( $_GET['urgent'] ) ? '1' : '';

        ob_start();
        ?>
        <div class="workeera-common-wrapper workeera-listing-main">
            <div class="workeera-container">
                <div class="workeera-jobs-layout<?php echo esc_attr( $allow_listing_filters !== 'on' ? ' workeera-fulljobs-layout' : '' ); ?>">

                    <?php if ( $allow_listing_filters === 'on' ) : ?>
                    <div class="workeera-filters-sidebar">
                        <div class="workeera-filters-header">
                            <strong class="workeera-filters-heading"><?php echo esc_html__( 'Filters', 'workeera-remote-tech-job-board' ); ?></strong>
                            <button class="workeera-filters-close">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>

                        <div class="workeera-filters-content">
                            <?php
                            /**
                             * Fires at the top of the jobs-listing filters sidebar,
                             * just inside the filters container.
                             */
                            do_action( 'workeera_listing_filters_top' );

                            // Buffer each filter block so they can be emitted in
                            // the admin-configured (drag-sortable) order below.
                            $wk_filter_html = [];

                            // ----- Location filter -----
                            if ( ! empty( $listing_active_filters ) && in_array( 'location', $listing_active_filters, true ) ) {
                                ob_start();
                                $location_filter_type = isset( $workeera_plugin_settings['general_settings']['location_filter_type'] )
                                    ? $workeera_plugin_settings['general_settings']['location_filter_type']
                                    : 'countries_cities';
                                ?>
                                <div class="workeera-custom-location-field">
                                    <?php
                                    if ( in_array( $location_filter_type, [ 'countries_cities', 'countries' ], true ) ) {
                                        $field_label      = ( $location_filter_type === 'countries_cities' )
                                            ? esc_html_x( 'Country', 'Locations Field', 'workeera-remote-tech-job-board' )
                                            : esc_html_x( 'Location', 'Locations Field', 'workeera-remote-tech-job-board' );
                                        $country_field_nm = ( $location_filter_type === 'countries_cities' ) ? 'loc_1' : 'loc';
                                        $saved_country_id = isset( $_GET[ $country_field_nm ] ) ? absint( $_GET[ $country_field_nm ] ) : '';
                                        ?>
                                        <div class="workeera-filter-group">
                                            <label for="location-country-<?php echo absint( $random_num ); ?>" class="workeera-label"><?php echo esc_html( $field_label ); ?></label>
                                            <div class="workeera-location-country-con">
                                                <?php
                                                Workeera_Form_Fields::select( [
                                                    'id'               => 'location-country-' . $random_num,
                                                    'name'             => $country_field_nm,
                                                    'options'          => $countries_list,
                                                    'classes'          => [ 'workeera-select', 'workeera-select2-dropdown', 'workeera-select2-wsearch' ],
                                                    'value'            => $saved_country_id,
                                                    'extra_attributes' => [
                                                        'data-placeholder' => esc_attr_x( 'Country', 'Locations Field', 'workeera-remote-tech-job-board' ),
                                                    ],
                                                    'default'          => '',
                                                ] );
                                                ?>
                                            </div>
                                        </div>
                                        <?php
                                    }

                                    if ( in_array( $location_filter_type, [ 'countries_cities', 'cities' ], true ) ) {
                                        $field_label    = ( $location_filter_type === 'countries_cities' )
                                            ? esc_html_x( 'City', 'Locations Field', 'workeera-remote-tech-job-board' )
                                            : esc_html_x( 'Location', 'Locations Field', 'workeera-remote-tech-job-board' );
                                        $city_field_nm  = ( $location_filter_type === 'countries_cities' ) ? 'loc_2' : 'loc';
                                        $saved_city_id  = isset( $_GET[ $city_field_nm ] ) ? absint( $_GET[ $city_field_nm ] ) : '';
                                        $city_options   = [];

                                        if ( $location_filter_type === 'cities' ) {
                                            $saved_country_id = isset( $workeera_plugin_settings['general_settings']['cities_only_country'] )
                                                ? absint( $workeera_plugin_settings['general_settings']['cities_only_country'] )
                                                : 0;
                                        }

                                        if ( $saved_city_id > 0 ) {
                                            $cities_table     = $wpdb->prefix . 'workeera_cities';
                                            $saved_city_label = (string) $wpdb->get_var(
                                                $wpdb->prepare( "SELECT label FROM {$cities_table} WHERE city_id = %d LIMIT 1", $saved_city_id )
                                            );
                                            if ( $saved_city_label ) {
                                                $city_options[ $saved_city_id ] = $saved_city_label;
                                            }
                                        }
                                        ?>
                                        <div class="workeera-filter-group">
                                            <label for="location-city-<?php echo absint( $random_num ); ?>" class="workeera-label"><?php echo esc_html( $field_label ); ?></label>
                                            <div class="workeera-location-city-con">
                                                <?php
                                                Workeera_Form_Fields::select( [
                                                    'id'               => 'location-city-' . $random_num,
                                                    'name'             => $city_field_nm,
                                                    'options'          => $city_options,
                                                    'classes'          => [ 'workeera-select', 'workeera-location-city-select2' ],
                                                    'value'            => $saved_city_id,
                                                    'extra_attributes' => [
                                                        'data-placeholder' => esc_attr_x( 'City', 'Locations Field', 'workeera-remote-tech-job-board' ),
                                                        'data-country-id'  => isset( $saved_country_id ) ? $saved_country_id : '',
                                                    ],
                                                    'default'          => '',
                                                ] );
                                                ?>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <?php
                                $wk_filter_html['location'] = ob_get_clean();
                            }

                            // ----- Salary filter -----
                            if ( ! empty( $listing_active_filters ) && in_array( 'salary', $listing_active_filters, true ) ) {
                                ob_start();
                                $min_salary      = 10;
                                $max_salary      = 150;
                                $salary_interval = 5;
                                $salary_filter_title = esc_html__( 'Salary', 'workeera-remote-tech-job-board' );

                                if ( isset( $salary_ranges[ $default_salary_unit ]['default'] ) ) {
                                    $min_salary      = $salary_ranges[ $default_salary_unit ]['default']['min'];
                                    $max_salary      = $salary_ranges[ $default_salary_unit ]['default']['max'];
                                    $salary_interval = $salary_ranges[ $default_salary_unit ]['default']['interval'];
                                }
                                if ( $salary_unit_switch !== 'on' && isset( $salary_ranges[ $default_salary_unit ]['title'] ) ) {
                                    /* translators: %s: salary period e.g. Hourly */
                                    $salary_filter_title = sprintf( esc_html__( '%s Salary', 'workeera-remote-tech-job-board' ), esc_html( $salary_ranges[ $default_salary_unit ]['title'] ) );
                                }

                                if ( $salary_unit_switch === 'on' ) {
                                    $salary_unit_value = isset( $_GET['salary_type'] ) ? sanitize_key( $_GET['salary_type'] ) : '';
                                    if ( isset( $salary_ranges[ $salary_unit_value ]['default']['min'] ) ) {
                                        $min_salary      = $salary_ranges[ $salary_unit_value ]['default']['min'];
                                        $max_salary      = $salary_ranges[ $salary_unit_value ]['default']['max'];
                                        $salary_interval = $salary_ranges[ $salary_unit_value ]['default']['interval'];
                                    }
                                }

                                $min_salary_value = isset( $_GET['min_salary'] ) ? absint( $_GET['min_salary'] ) : '';
                                $max_salary_value = isset( $_GET['max_salary'] ) ? absint( $_GET['max_salary'] ) : '';
                                if ( empty( $min_salary_value ) || empty( $max_salary_value ) ) {
                                    $min_salary_value = '';
                                    $max_salary_value = '';
                                }
                                ?>
                                <div class="workeera-filter-group workeera-job-salary-filter">
                                    <label class="workeera-label">
                                        <?php echo esc_html( $salary_filter_title ); ?>: <?php echo wp_kses_post( workeera_formatted_salary( $min_salary, 'workeera-job-min-salary' ) ); ?> - <?php echo wp_kses_post( workeera_formatted_salary( $max_salary, 'workeera-job-max-salary' ) ); ?>
                                    </label>
                                    <div class="workeera-job-salary-slider"
                                         data-min="<?php echo absint( $min_salary ); ?>"
                                         data-max="<?php echo absint( $max_salary ); ?>"
                                         data-step="<?php echo absint( $salary_interval ); ?>"
                                         data-value-min="<?php echo absint( $min_salary_value ); ?>"
                                         data-value-max="<?php echo absint( $max_salary_value ); ?>">
                                    </div>
                                    <input type="hidden" name="min_salary" value="<?php echo esc_attr( workeera_absint( $min_salary_value ) ); ?>">
                                    <input type="hidden" name="max_salary" value="<?php echo esc_attr( workeera_absint( $max_salary_value ) ); ?>">
                                    <?php if ( $salary_unit_switch === 'on' ) : ?>
                                        <div class="workeera-salary-unit">
                                            <?php
                                            $salary_units = [ '' => esc_html__( 'Select salary unit', 'workeera-remote-tech-job-board' ) ];
                                            $salary_unit_args = [
                                                'id'               => 'salary-unit-' . $random_num,
                                                'name'             => 'salary_type',
                                                'classes'          => [ 'workeera-select', 'workeera-select2-dropdown' ],
                                                'extra_attributes' => [ 'data-placeholder' => esc_html__( 'Salary unit', 'workeera-remote-tech-job-board' ) ],
                                                'options'          => array_merge( $salary_units, workeera_salary_units() ),
                                                'default'          => '',
                                            ];
                                            if ( isset( $salary_unit_value ) && ! empty( $salary_unit_value ) && isset( $salary_ranges[ $salary_unit_value ] ) ) {
                                                $salary_unit_args['value'] = $salary_unit_value;
                                            }
                                            Workeera_Form_Fields::select( $salary_unit_args );
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $wk_filter_html['salary'] = ob_get_clean();
                            }

                            // ----- Taxonomy filters -----
                            $taxonomy_fields = [
                                'sectors'  => [
                                    'label'       => esc_html_x( 'Jobs in', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'placeholder' => esc_html_x( 'Select', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'field_name'  => 'jobs_in',
                                    'multi'       => false,
                                    'checkboxes'  => true,
                                ],
                                'jobtypes' => [
                                    'label'       => esc_html_x( 'Job Types', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'placeholder' => esc_html_x( 'Select job types', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'multi'       => true,
                                    'checkboxes'  => true,
                                ],
                                'timezones' => [
                                    'label'       => esc_html_x( 'Timezone', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'placeholder' => esc_html_x( 'Select timezone', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'multi'       => false,
                                    'checkboxes'  => true,
                                ],
                                'skills' => [
                                    'label'       => esc_html_x( 'Skills', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'placeholder' => esc_html_x( 'Select or add skills', 'Listing filters', 'workeera-remote-tech-job-board' ),
                                    'multi'       => true,
                                    'checkboxes'  => true,
                                ],
                            ];
                            foreach ( $taxonomy_fields as $wk_tax_slug => $wk_tax_cfg ) {
                                ob_start();
                                self::render_taxonomy_fields( [ $wk_tax_slug => $wk_tax_cfg ], $random_num, $listing_active_filters );
                                $wk_filter_html[ $wk_tax_slug ] = ob_get_clean();
                            }

                            // Emit location / salary / taxonomy filters in the
                            // admin-configured (drag-sortable) order.
                            foreach ( $listing_active_filters as $wk_slug ) {
                                if ( ! empty( $wk_filter_html[ $wk_slug ] ) ) {
                                    echo $wk_filter_html[ $wk_slug ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered, already-escaped filter markup.
                                }
                            }

                            // ----- Toggle filters -----
                            $remote_jobs_filter = isset( $workeera_plugin_settings['job_listings_settings']['remote_jobs_filter'] )
                                ? $workeera_plugin_settings['job_listings_settings']['remote_jobs_filter']
                                : 'on';
                            $urgent_jobs_filter = isset( $workeera_plugin_settings['job_listings_settings']['urgent_jobs_filter'] )
                                ? $workeera_plugin_settings['job_listings_settings']['urgent_jobs_filter']
                                : 'on';

                            if ( $remote_jobs_filter === 'on' ) :
                                ?>
                                <div class="workeera-filter-group">
                                    <label class="workeera-checkbox workeera-toggle-switch">
                                        <input type="checkbox" name="remote_only" value="1"<?php checked( $remote_only, '1' ); ?>>
                                        <span class="workeera-toggle-slider"></span>
                                        <span><?php echo esc_html__( 'Remote Jobs Only', 'workeera-remote-tech-job-board' ); ?></span>
                                    </label>
                                </div>
                                <?php
                            endif;

                            if ( $urgent_jobs_filter === 'on' ) :
                                ?>
                                <div class="workeera-filter-group">
                                    <label class="workeera-checkbox workeera-toggle-switch">
                                        <input type="checkbox" name="urgent" value="1"<?php checked( $urgent_val, '1' ); ?>>
                                        <span class="workeera-toggle-slider"></span>
                                        <span><?php echo esc_html__( 'Urgent Hiring', 'workeera-remote-tech-job-board' ); ?></span>
                                    </label>
                                </div>
                                <?php
                            endif;
                            ?>
                            <button class="workeera-btn workeera-btn-primary workeera-btn-full workeera-find-jobs-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <?php echo esc_html__( 'Find Jobs', 'workeera-remote-tech-job-board' ); ?>
                            </button>
                            <?php
                            /**
                             * Fires at the bottom of the jobs-listing filters sidebar,
                             * after the Find Jobs button.
                             */
                            do_action( 'workeera_listing_filters_bottom' );
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="workeera-jobs-main">
                        <?php
                        /**
                         * Fires at the top of the jobs-listing results column.
                         */
                        do_action( 'workeera_listing_content_top' );
                        ?>

                        <div class="workeera-jobs-topbar">
                            <div class="workeera-topbar-left">
                                <div class="workeera-topbar-search">
                                    <input type="text" name="keyword" class="workeera-input workeera-keyword-input" value="<?php echo esc_attr( $keyword_val ); ?>" placeholder="<?php echo esc_attr__( 'Search keywords…', 'workeera-remote-tech-job-board' ); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                </div>
                            </div>

                            <div class="workeera-topbar-right">
                                <p class="workeera-results-count"></p>

                                <div class="workeera-topbar-inner-right">
                                    <div class="workeera-topbar-sort">
                                        <select name="sort_by" class="workeera-select workeera-select2-dropdown workeera-sort-select" data-placeholder="<?php echo esc_attr__( 'Order by', 'workeera-remote-tech-job-board' ); ?>">
                                            <option value=""><?php echo esc_html__( 'Select', 'workeera-remote-tech-job-board' ); ?></option>
                                            <option value="newest"<?php selected( $sort_by_val, 'newest' ); ?>><?php echo esc_html__( 'Newest First', 'workeera-remote-tech-job-board' ); ?></option>
                                            <option value="oldest"<?php selected( $sort_by_val, 'oldest' ); ?>><?php echo esc_html__( 'Oldest First', 'workeera-remote-tech-job-board' ); ?></option>
                                            <option value="salary_high"<?php selected( $sort_by_val, 'salary_high' ); ?>><?php echo esc_html__( 'Salary: High to Low', 'workeera-remote-tech-job-board' ); ?></option>
                                            <option value="salary_low"<?php selected( $sort_by_val, 'salary_low' ); ?>><?php echo esc_html__( 'Salary: Low to High', 'workeera-remote-tech-job-board' ); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <?php if ( $allow_listing_filters === 'on' ) : ?>
                                    <button class="workeera-mobile-filter-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/></svg>
                                        <?php echo esc_html__( 'Filters', 'workeera-remote-tech-job-board' ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="workeera-filters-clear-holder workeera-hide"></div>

                        <div class="workeera-jobs-list-holder">
                            <div class="workeera-listin-loadr workeera-jobs-listin-loader">
                                <?php
                                for ($loder = 1; $loder < 9; $loder++) {
                                    ?>
                                    <div class="workeera-joblist-loderitm">
                                        <div class="workeera-itmloder-filled-row">
                                            <span class="item-filled-state"></span>
                                            <span class="item-filled-state"></span>
                                        </div>
                                        <div class="itmloder-info-con">
                                            <div class="itmloder-logodet-con">
                                                <div class="itm-loader-logo">
                                                    <span class="itm-loader-logobox"></span>
                                                </div>
                                                <div class="itm-info-det">
                                                    <span class="item-name-line"></span>
                                                    <span class="item-price-line"></span>
                                                    <span class="item-sts-line"></span>
                                                </div>
                                            </div>
                                            <div class="itm-info-acts">
                                                <span class="item-act-priceinfo"></span>
                                                <span class="item-act-btn"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                            <div class="workeera-jobs-list"></div>
                            <div class="workeera-jobs-no-results workeera-hide">
                                <div class="workeera-jobs-no-results-box">
                                    <span class="workeera-jobs-no-results-icon" aria-hidden="true">
                                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="7"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                            <line x1="8" y1="11" x2="14" y2="11"></line>
                                        </svg>
                                    </span>
                                    <strong class="workeera-jobs-no-results-title"><?php echo esc_html__( 'No jobs found', 'workeera-remote-tech-job-board' ); ?></strong>
                                    <p class="workeera-jobs-no-results-msg"><?php echo esc_html__( 'No jobs found matching your criteria.', 'workeera-remote-tech-job-board' ); ?></p>
                                    <button type="button" class="workeera-jobs-no-results-clear workeera-btn workeera-hide">
                                        <span><?php echo esc_html__( 'Clear all Filters', 'workeera-remote-tech-job-board' ); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="workeera-jobs-pagination-holder"></div>
                        <?php
                        /**
                         * Fires at the bottom of the jobs-listing results column,
                         * after the pagination holder.
                         */
                        do_action( 'workeera_listing_content_bottom' );
                        ?>

                    </div>

                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render select2-powered taxonomy select fields for a post.
     *
     * @param array $taxonomy_fields Config array keyed by tax_slug.
     * @param int   $random_num         random id.
     * @param array $active_filters
     */
    public static function render_taxonomy_fields( $taxonomy_fields, $random_num, $active_filters = [] ) {
        foreach ( $taxonomy_fields as $tax_slug => $field_cfg ) {
            $label       = isset( $field_cfg['label'] ) ? $field_cfg['label'] : '';
            $placeholder = isset( $field_cfg['placeholder'] ) ? $field_cfg['placeholder'] : '';
            $is_multi    = ! empty( $field_cfg['multi'] );
            $checkboxes  = isset($field_cfg['checkboxes']) && ! empty( $field_cfg['checkboxes'] );

            if (empty($active_filters) || !is_array($active_filters)) {
                return;
            }

            // Total terms in this taxonomy
            $total_terms = Workeera_Object_Terms::total_found( $tax_slug );

            if ( $total_terms < 1 || !in_array($tax_slug, $active_filters) ) {
                continue;
            }

            $terms_count_lite = 15;

            $field_name = (isset($field_cfg['field_name']) ? $field_cfg['field_name'] : $tax_slug);

            $get_filter_val = isset($_GET[$field_name]) ? sanitize_text_field( wp_unslash( $_GET[$field_name] ) ) : '';
            if ( $is_multi ) {
                $get_filter_val = !empty($get_filter_val) ? explode(',', $get_filter_val) : [];
            } else {
                $get_filter_val = !empty($get_filter_val) ? [$get_filter_val] : [];
            }

            // Load currently saved terms as pre-selected options.
            $saved_labels = Workeera_Object_Terms_Relation::get_term_labels_by_slugs( $get_filter_val, $tax_slug );

            $options = [ '' => $placeholder ];
            if ( WORKEERA_TERMS_COUNT_LITE > $total_terms ) {

                if ( $checkboxes ) {
                    $options = [];
                }

                foreach ( $saved_labels as $term_slug => $term_label ) {
                    $options[ $term_slug ] = $term_label;
                }
                $terms_options = Workeera_Object_Terms::terms_options( $tax_slug, true );
                if ( !empty($terms_options) ) {
                    foreach ( $terms_options as $term_slug => $term_label ) {
                        if ( ! isset($saved_labels[$term_slug]) ) {
                            $options[ $term_slug ] = $term_label;
                        }
                    }
                }
            } else {
                if ( $is_multi ) {
                    $options = [];
                }
                foreach ( $saved_labels as $term_slug => $term_label ) {
                    $options[ $term_slug ] = $term_label;
                }
            }

            $saved_value = array_keys( $saved_labels );
            if ( ! $is_multi ) {
                $saved_value = ! empty( $saved_value ) ? $saved_value[0] : '';
            }

            // Build classes.
            if ( WORKEERA_TERMS_COUNT_LITE > $total_terms ) {
                $classes = [ 'workeera-select', 'workeera-select2-dropdown', 'workeera-select2-wsearch' ];
            } else {
                $classes = [ 'workeera-select', 'workeera-tax-term-selectr', 'workeera-term-with-slugs' ];
            }

            $extra_attrs = [
                'data-placeholder' => $placeholder,
                'data-tax-slug'    => $tax_slug,
            ];
            if ( $is_multi ) {
                $extra_attrs['data-multi'] = '1';
            }
            ?>
            <div class="workeera-filter-group" data-tax-slug="<?php echo esc_attr( $tax_slug ); ?>">
                <label for="<?php echo esc_attr($tax_slug) ?>-field-<?php echo absint($random_num) ?>" class="workeera-label"><?php echo esc_html( $label ) ?></label>
                <?php
                if ( $terms_count_lite > $total_terms && $checkboxes ) {
                    Workeera_Form_Fields::options_group( [
                        'name'          => $field_name . ( $is_multi ? '[]' : '' ),
                        'value'         => $saved_value,
                        'multiple'      => $is_multi,
                        'options'       => $options,
                        'default'       => '',
                    ] );
                    if ( $total_terms > 7 ) {
                        ?>
                        <a href="javascript:;" class="workeera-optgroupitms-seemore-btn" data-less_text="<?php echo esc_html_x( 'See less', 'Listing filters', 'workeera-remote-tech-job-board' ) ?>" data-more_text="<?php echo esc_html_x( 'See more', 'Listing filters', 'workeera-remote-tech-job-board' ) ?>"><span class="workeera-optgroup-shwhide-txt"><?php echo esc_html_x( 'See more', 'Listing filters', 'workeera-remote-tech-job-board' ) ?></span><span class="workeera-optgroup-shwhide-icon">&plus;</span></a>
                        <?php
                    }
                } else {
                    Workeera_Form_Fields::select( [
                        'id'               => $tax_slug . '-field-' . $random_num,
                        'name'             => $field_name . ( $is_multi ? '[]' : '' ),
                        'options'          => $options,
                        'classes'          => $classes,
                        'value'            => $saved_value,
                        'multiple'         => $is_multi,
                        'extra_attributes' => $extra_attrs,
                        'default'          => '',
                    ] );
                }
                ?>
            </div>
            <?php
        }
    }
}

new Workeera_Listing;
