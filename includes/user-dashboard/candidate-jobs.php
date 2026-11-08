<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the candidate "Applied Jobs" dashboard section.
 */
class Workeera_Candidate_Applied_Jobs {

    /**
     * Number of applications shown per page.
     */
    private static $per_page = 10;

    /**
     * Register the enqueue and AJAX hooks for the applied jobs section.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 6 );
        add_action( 'wp_ajax_workeera_applied_jobs_list', [ $this, 'ajax_applied_jobs_list' ] );
        add_action( 'wp_ajax_workeera_withdraw_application', [ $this, 'ajax_withdraw_application' ] );
    }

    /**
     * Enqueue the applied-jobs assets when viewing the dashboard section.
     */
    public function enqueue_scripts() {
        global $workeera_plugin_settings;

        $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if ( ! empty( $dashboard_page ) && get_post_type( $dashboard_page ) == 'page' && is_page( $dashboard_page ) && isset( $_GET['section-type'] ) && $_GET['section-type'] == 'applied-jobs' ) {
            wp_enqueue_style( 'workeera-applied-jobs', Workeera_Plugin::root_url() . 'css/applied-jobs.css', array( 'workeera-styles' ), WORKEERA_VERSION );

            wp_enqueue_script( 'workeera-candidate-jobs', Workeera_Plugin::root_url() . 'js/candidate-jobs.js', array( 'workeera-scripts' ), WORKEERA_VERSION, true );
            wp_localize_script( 'workeera-candidate-jobs', 'workeera_candjobs_str', self::localize_strings() );
        }
    }

    /**
     * Strings shared by the candidate-jobs.js front-end.
     */
    public static function localize_strings() {
        return [
            'withdraw_confirm_title' => esc_html_x( 'Withdraw Application', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'withdraw_confirm_msg'   => esc_html_x( 'Are you sure you want to withdraw this application? This action cannot be undone.', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'withdraw_confirm_btn'   => esc_html_x( 'Yes, Withdraw', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'withdrawn_lbl'          => esc_html_x( 'Withdrawn', 'applied jobs status', 'workeera-remote-tech-job-board' ),
            'cancel_btn'             => esc_html_x( 'Cancel', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'generic_err'            => esc_html__( 'Something went wrong. Please try again.', 'workeera-remote-tech-job-board' ),
        ];
    }

    // -------------------------------------------------------------------------
    // Static shell — markup wrapper with filter bar; list is loaded via AJAX.
    // -------------------------------------------------------------------------

    /**
     * Render the applied jobs section shell with filter bar.
     */
    public static function jobs() {
        global $workeera_plugin_settings;

        $jobs_page     = isset( $workeera_plugin_settings['pages_settings']['jobs_page'] ) ? $workeera_plugin_settings['pages_settings']['jobs_page'] : '';
        $browse_url    = ! empty( $jobs_page ) ? get_permalink( $jobs_page ) : home_url( '/' );
        ?>
        <div class="workeera-appliedjobs-wrap workeera-usrdshb-main-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'workeera_nonce' ) ); ?>">
            <div class="workeera-usrdshb-container">

                <!-- Header -->
                <div class="workeera-applied-header">
                    <strong class="workeera-applied-title"><?php echo esc_html_x( 'Applied Jobs', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></strong>
                </div>

                <!-- Filter Bar -->
                <div class="workeera-applied-filterbar">
                    <div class="workeera-applied-search-wrap">
                        <span class="workeera-applied-search-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </span>
                        <input type="text" class="workeera-applied-field-input workeera-applied-search-input" placeholder="<?php echo esc_attr_x( 'Search by job title...', 'applied jobs', 'workeera-remote-tech-job-board' ); ?>">
                    </div>
                    <select class="workeera-applied-field-select workeera-applied-sort-select">
                        <option value="newest"><?php echo esc_html_x( 'Newest First', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="oldest"><?php echo esc_html_x( 'Oldest First', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                    </select>
                    <select class="workeera-applied-field-select workeera-applied-status-select">
                        <option value=""><?php echo esc_html_x( 'All Statuses', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="pending"><?php echo esc_html_x( 'Pending', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="reviewed"><?php echo esc_html_x( 'Reviewed', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="shortlisted"><?php echo esc_html_x( 'Shortlisted', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="rejected"><?php echo esc_html_x( 'Rejected', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></option>
                    </select>
                </div>

                <!-- Applied Jobs List (populated via AJAX) -->
                <div class="workeera-applied-list-holder">
                    <div class="workeera-usrjobs-loder-con"><?php Workeera_Dashboard_Common::loader( 'applied' ); ?></div>
                    <div class="workeera-applied-list"></div>
                    <div class="workeera-usrjobs-pagination"></div>
                    <div class="workeera-applied-empty workeera-hide">
                        <div class="workeera-applied-empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                                <line x1="12" y1="12" x2="12" y2="16" />
                                <line x1="10" y1="14" x2="14" y2="14" />
                            </svg>
                        </div>
                        <strong class="workeera-applied-empty-title"><?php echo esc_html_x( 'No Applications Found', 'applied jobs', 'workeera-remote-tech-job-board' ); ?></strong>
                        <div class="workeera-applied-empty-desc"><?php echo esc_html_x( "You haven't applied to any jobs yet, or no applications match your filters.", 'applied jobs', 'workeera-remote-tech-job-board' ); ?></div>
                        <a href="<?php echo esc_url( $browse_url ); ?>" class="workeera-applied-empty-cta workeera-btn">
                            <?php echo esc_html_x( 'Browse Jobs', 'applied jobs', 'workeera-remote-tech-job-board' ); ?>
                        </a>
                    </div>
                    <div class="workeera-init-loder-holdr workeera-hide"><?php Workeera_Dashboard_Common::loader( 'applied' ); ?></div>
                </div>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — return list HTML + pagination HTML
    // -------------------------------------------------------------------------

    /**
     * AJAX handler returning the current user's applied jobs list and pagination.
     */
    public function ajax_applied_jobs_list() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id = get_current_user_id();

        if ( ! workeera_is_user_candidate( $user_id ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Sanitize inputs.
        $page   = isset( $_POST['page_num'] )      ? max( 1, absint( $_POST['page_num'] ) ) : 1;
        $search = isset( $_POST['search'] )        ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $sort   = isset( $_POST['sort'] )          ? sanitize_key( $_POST['sort'] ) : 'newest';
        $status = isset( $_POST['status_filter'] ) ? sanitize_key( $_POST['status_filter'] ) : '';

        $allowed_sorts    = [ 'newest', 'oldest' ];
        $allowed_statuses = [ 'pending', 'reviewed', 'shortlisted', 'rejected' ];

        if ( ! in_array( $sort, $allowed_sorts, true ) ) {
            $sort = 'newest';
        }
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }

        $result      = self::get_applied_applications( $user_id, $page, $search, $sort, $status );
        $rows        = $result['rows'];
        $total_found = $result['total'];

        ob_start();
        if ( ! empty( $rows ) ) {
            echo '<div class="workeera-applied-list">';
            foreach ( $rows as $row ) {
                self::render_applied_item( $row );
            }
            echo '</div>';

            $info_label = sprintf(
                /* translators: 1: first item on page, 2: last item on page, 3: total items */
                esc_html_x( 'Showing %1$s–%2$s of %3$s job applications', 'applied jobs pagination', 'workeera-remote-tech-job-board' ),
                number_format_i18n( ( $page - 1 ) * self::$per_page + 1 ),
                number_format_i18n( min( $page * self::$per_page, $total_found ) ),
                number_format_i18n( $total_found )
            );

            $pagination_html = Workeera_Dashboard_Common::render_pagination( $page, $total_found, self::$per_page, $info_label );
        } else {
            echo '<div class="workeera-applied-list"></div>';
            $pagination_html = '';
        }
        $list_html = ob_get_clean();

        wp_send_json( [
            'error'           => '0',
            'list_html'       => $list_html,
            'pagination_html' => $pagination_html,
            'total'           => absint( $total_found ),
            'is_empty'        => empty( $rows ) ? '1' : '0',
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — withdraw application
    // -------------------------------------------------------------------------

    /**
     * AJAX handler that withdraws the current user's application.
     *
     * The application row is NOT deleted — it is flagged via the
     * `is_withdrawn` column so employers/admins keep the record (shown with
     * a "Withdrawn" tag). Withdrawing is permanent: there is no undo.
     */
    public function ajax_withdraw_application() {
        global $wpdb;

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id        = get_current_user_id();
        $application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;

        if ( $application_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid application.', 'workeera-remote-tech-job-board' ) ] );
        }

        $apps_table = $wpdb->prefix . 'workeera_applications';

        // Verify ownership of the application before flagging it.
        $application = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, is_withdrawn FROM {$apps_table} WHERE application_id = %d LIMIT 1",
                $application_id
            ),
            ARRAY_A
        );

        if ( ! $application || (int) $application['user_id'] !== (int) $user_id ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to withdraw this application.', 'workeera-remote-tech-job-board' ) ] );
        }

        if ( ! empty( $application['is_withdrawn'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'This application has already been withdrawn.', 'workeera-remote-tech-job-board' ) ] );
        }

        $updated = $wpdb->update(
            $apps_table,
            [ 'is_withdrawn' => 1 ],
            [ 'application_id' => $application_id, 'user_id' => $user_id ],
            [ '%d' ],
            [ '%d', '%d' ]
        );

        if ( false === $updated || 0 === $updated ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Failed to withdraw application.', 'workeera-remote-tech-job-board' ) ] );
        }

        /**
         * Fires after a candidate withdraws their application.
         *
         * @param int $application_id
         * @param int $user_id
         */
        do_action( 'workeera_after_application_withdrawn', $application_id, $user_id );

        wp_send_json( [
            'error' => '0',
            'msg'   => esc_html__( 'Application withdrawn successfully.', 'workeera-remote-tech-job-board' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Query helper
    // -------------------------------------------------------------------------

    /**
     * Fetch paginated, filtered, sorted applications for the given user.
     *
     * @return array { rows: array<array>, total: int }
     */
    private static function get_applied_applications( $user_id, $page = 1, $search = '', $sort = 'newest', $status = '' ) {
        global $wpdb;

        $per_page = self::$per_page;
        $offset   = ( $page - 1 ) * $per_page;

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $where_parts  = [];
        $placeholders = [];

        $where_parts[]  = 'a.user_id = %d';
        $placeholders[] = absint( $user_id );

        $join_posts = "LEFT JOIN {$posts_table} AS p ON p.ID = a.job_id";

        if ( ! empty( $search ) ) {
            $where_parts[]  = 'p.post_title LIKE %s';
            $placeholders[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        if ( ! empty( $status ) ) {
            $where_parts[]  = 'a.status = %s';
            $placeholders[] = $status;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_parts );

        $order_sql = ( 'oldest' === $sort ) ? 'ORDER BY a.applied_date ASC' : 'ORDER BY a.applied_date DESC';

        // Count.
        $count_sql = "SELECT COUNT(*) FROM {$apps_table} AS a {$join_posts} {$where_sql}";
        $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $placeholders ) );

        if ( $total < 1 ) {
            return [ 'rows' => [], 'total' => 0 ];
        }

        $data_placeholders   = $placeholders;
        $data_placeholders[] = $per_page;
        $data_placeholders[] = $offset;

        $data_sql = "SELECT a.application_id, a.job_id, a.status, a.is_withdrawn, a.applied_date, p.post_title, p.post_status
                     FROM {$apps_table} AS a {$join_posts} {$where_sql} {$order_sql}
                     LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_placeholders ), ARRAY_A );

        return [ 'rows' => is_array( $rows ) ? $rows : [], 'total' => $total ];
    }

    // -------------------------------------------------------------------------
    // Row renderer
    // -------------------------------------------------------------------------

    /**
     * Render a single applied-jobs row.
     *
     * @param array $row Application row keyed by application_id, job_id, status, applied_date, post_title, post_status.
     */
    private static function render_applied_item( $row ) {
        $application_id = isset( $row['application_id'] ) ? absint( $row['application_id'] ) : 0;
        $job_id         = isset( $row['job_id'] ) ? absint( $row['job_id'] ) : 0;
        $status         = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'pending';
        $is_withdrawn   = ! empty( $row['is_withdrawn'] );
        $applied_date   = isset( $row['applied_date'] ) ? sanitize_text_field( wp_unslash( $row['applied_date'] ) ) : '';
        $post_title     = isset( $row['post_title'] ) ? $row['post_title'] : '';
        $post_status    = isset( $row['post_status'] ) ? sanitize_key( $row['post_status'] ) : '';

        $job_title    = ! empty( $post_title ) ? $post_title : esc_html_x( '(Job no longer available)', 'applied jobs', 'workeera-remote-tech-job-board' );
        $company_info = $job_id > 0 ? workeera_job_company_info( $job_id ) : [];
        $company_name = is_array( $company_info ) && ! empty( $company_info['name'] ) ? workeera_strip_slashes( $company_info['name'] ) : '';

        $applied_label = '';
        if ( ! empty( $applied_date ) ) {
            $applied_ts = strtotime( $applied_date );
            if ( $applied_ts ) {
                /* translators: %s: formatted date */
                $applied_label = sprintf( esc_html_x( 'Applied: %s', 'applied jobs', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), $applied_ts ) ) );
            }
        }

        $view_url   = ( $job_id > 0 && 'publish' === $post_status ) ? get_permalink( $job_id ) : '';
        $badge_cls  = self::status_badge_class( $status );
        $badge_lbl  = self::status_badge_label( $status );
        ?>
        <div class="workeera-applied-item<?php echo esc_attr( $is_withdrawn ? ' workeera-applied-item-withdrawn' : '' ); ?>" data-application-id="<?php echo absint( $application_id ); ?>">
            <div class="workeera-applied-item-main">
                <div class="workeera-applied-info">
                    <strong class="workeera-applied-jobtitle"><?php echo esc_html( $job_title ); ?></strong>
                    <?php if ( $company_name ) : ?>
                        <div class="workeera-applied-company"><?php echo esc_html( $company_name ); ?></div>
                    <?php endif; ?>
                    <?php if ( $applied_label ) : ?>
                        <div class="workeera-applied-meta">
                            <span class="workeera-applied-meta-date">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                <?php echo esc_html( $applied_label ); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="workeera-applied-status">
                    <?php if ( $is_withdrawn ) : ?>
                        <span class="workeera-applied-status-badge workeera-applied-status-withdrawn"><?php echo esc_html_x( 'Withdrawn', 'applied jobs status', 'workeera-remote-tech-job-board' ); ?></span>
                    <?php else : ?>
                        <span class="workeera-applied-status-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge_lbl ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="workeera-applied-actions">
                    <?php if ( $view_url ) : ?>
                        <a href="<?php echo esc_url( $view_url ); ?>" class="workeera-applied-action-btn" title="<?php echo esc_attr_x( 'View Job', 'applied jobs', 'workeera-remote-tech-job-board' ); ?>" target="_blank" rel="noopener noreferrer">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </a>
                    <?php else : ?>
                        <span class="workeera-applied-action-btn" title="<?php echo esc_attr_x( 'View Job', 'applied jobs', 'workeera-remote-tech-job-board' ); ?>" style="opacity:0.35;cursor:default;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </span>
                    <?php endif; ?>
                    <?php if ( ! $is_withdrawn ) : // Withdrawing is permanent — no button (and no undo) once flagged. ?>
                        <button class="workeera-applied-action-btn workeera-applied-withdraw-btn" data-application-id="<?php echo absint( $application_id ); ?>" title="<?php echo esc_attr_x( 'Withdraw', 'applied jobs', 'workeera-remote-tech-job-board' ); ?>">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Status badge helpers
    // -------------------------------------------------------------------------

    /**
     * Return the CSS modifier class for the given application status.
     *
     * @param  string $status Application status key.
     * @return string         CSS class name.
     */
    private static function status_badge_class( $status ) {
        $map = [
            'pending'     => 'workeera-applied-status-pending',
            'reviewed'    => 'workeera-applied-status-reviewed',
            'shortlisted' => 'workeera-applied-status-shortlisted',
            'rejected'    => 'workeera-applied-status-rejected',
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : 'workeera-applied-status-pending';
    }

    /**
     * Return the translatable label for the given application status.
     *
     * @param  string $status Application status key.
     * @return string         Human-readable label.
     */
    private static function status_badge_label( $status ) {
        $map = [
            'pending'     => esc_html_x( 'Pending',     'applied jobs status', 'workeera-remote-tech-job-board' ),
            'reviewed'    => esc_html_x( 'Reviewed',    'applied jobs status', 'workeera-remote-tech-job-board' ),
            'shortlisted' => esc_html_x( 'Shortlisted', 'applied jobs status', 'workeera-remote-tech-job-board' ),
            'rejected'    => esc_html_x( 'Rejected',    'applied jobs status', 'workeera-remote-tech-job-board' ),
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : esc_html_x( 'Pending', 'applied jobs status', 'workeera-remote-tech-job-board' );
    }
}

/**
 * Handles the candidate "Recommended Jobs" dashboard section.
 */
class Workeera_Candidate_Recommended_Jobs {

    /**
     * Number of recommendations shown per page.
     */
    private static $per_page = 10;

    /**
     * Hard cap on the total number of recommendations a candidate ever sees.
     */
    private static $max_total = 50;

    /**
     * Register the enqueue and AJAX hooks for the recommended jobs section.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 6 );
        add_action( 'wp_ajax_workeera_recommended_jobs_list', [ $this, 'ajax_recommended_jobs_list' ] );
    }

    /**
     * Enqueue the recommended-jobs assets when viewing the dashboard section.
     */
    public function enqueue_scripts() {
        global $workeera_plugin_settings;

        $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if ( ! empty( $dashboard_page ) && get_post_type( $dashboard_page ) == 'page' && is_page( $dashboard_page ) && isset( $_GET['section-type'] ) && $_GET['section-type'] == 'recommended-jobs' ) {
            wp_enqueue_style( 'workeera-recommended-jobs', Workeera_Plugin::root_url() . 'css/recommended-jobs.css', array( 'workeera-styles' ), WORKEERA_VERSION );

            wp_enqueue_script( 'workeera-candidate-jobs', Workeera_Plugin::root_url() . 'js/candidate-jobs.js', array( 'workeera-scripts' ), WORKEERA_VERSION, true );
            wp_localize_script( 'workeera-candidate-jobs', 'workeera_candjobs_str', self::localize_strings() );
        }
    }

    /**
     * Strings shared by the candidate-jobs.js front-end.
     */
    public static function localize_strings() {
        return [
            'withdraw_confirm_title' => esc_html_x( 'Withdraw Application', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'withdraw_confirm_msg'   => esc_html_x( 'Are you sure you want to withdraw this application? This action cannot be undone.', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'withdraw_confirm_btn'   => esc_html_x( 'Yes, Withdraw', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'withdrawn_lbl'          => esc_html_x( 'Withdrawn', 'applied jobs status', 'workeera-remote-tech-job-board' ),
            'cancel_btn'             => esc_html_x( 'Cancel', 'applied jobs', 'workeera-remote-tech-job-board' ),
            'generic_err'            => esc_html__( 'Something went wrong. Please try again.', 'workeera-remote-tech-job-board' ),
        ];
    }

    // -------------------------------------------------------------------------
    // Static shell — markup wrapper with filter bar; list is loaded via AJAX.
    // -------------------------------------------------------------------------

    /**
     * Render the recommended jobs section shell with filter bar.
     */
    public static function jobs() {
        global $workeera_plugin_settings;

        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : '';
        $resume_url         = $dashboard_page_url; // resume detail is the default candidate section

        $jobtype_options = self::get_jobtype_options();
        ?>
        <div class="workeera-recommended-jobs-wrap workeera-usrdshb-main-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'workeera_nonce' ) ); ?>">
            <div class="workeera-usrdshb-container">

                <!-- Header -->
                <div class="workeera-recommended-header">
                    <strong class="workeera-recommended-title"><?php echo esc_html_x( 'Recommended for You', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></strong>
                    <div class="workeera-recommended-sub"><?php echo esc_html_x( 'Jobs based on your profile and activity', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></div>
                </div>

                <!-- Filter Bar -->
                <div class="workeera-recommended-filterbar">
                    <div class="workeera-recommended-search-wrap">
                        <span class="workeera-recommended-search-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </span>
                        <input type="text" class="workeera-recommended-field-input workeera-recommended-search-input" placeholder="<?php echo esc_attr_x( 'Search jobs...', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?>">
                    </div>
                    <select class="workeera-recommended-field-select workeera-recommended-sort-select">
                        <option value="match"><?php echo esc_html_x( 'Best Match', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="newest"><?php echo esc_html_x( 'Newest First', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></option>
                    </select>
                    <select class="workeera-recommended-field-select workeera-recommended-jobtype-select">
                        <option value=""><?php echo esc_html_x( 'Job Type', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <?php foreach ( $jobtype_options as $jt_id => $jt_label ) : ?>
                            <option value="<?php echo absint( $jt_id ); ?>"><?php echo esc_html( $jt_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Recommended Jobs List (populated via AJAX) -->
                <div class="workeera-recommended-list-holder">
                    <div class="workeera-usrjobs-loder-con"><?php Workeera_Dashboard_Common::loader( 'recommended' ); ?></div>
                    <div class="workeera-recommended-list"></div>
                    <div class="workeera-usrjobs-pagination"></div>
                    <div class="workeera-recommended-empty workeera-hide">
                        <div class="workeera-recommended-empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                                <line x1="12" y1="12" x2="12" y2="16" />
                                <line x1="10" y1="14" x2="14" y2="14" />
                            </svg>
                        </div>
                        <strong class="workeera-recommended-empty-title"><?php echo esc_html_x( 'No Recommendations Found', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></strong>
                        <div class="workeera-recommended-empty-desc"><?php echo esc_html_x( 'Try adjusting your filters or complete your profile to get better matches.', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></div>
                        <a href="<?php echo esc_url( $resume_url ); ?>" class="workeera-recommended-empty-cta workeera-btn"><?php echo esc_html_x( 'Update Profile', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?></a>
                    </div>
                    <div class="workeera-init-loder-holdr workeera-hide"><?php Workeera_Dashboard_Common::loader( 'recommended' ); ?></div>
                </div>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — return list HTML + pagination HTML
    // -------------------------------------------------------------------------

    /**
     * AJAX handler returning the candidate's recommended jobs list and pagination.
     */
    public function ajax_recommended_jobs_list() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id      = get_current_user_id();
        $candidate_id = workeera_is_user_candidate( $user_id );

        if ( ! $candidate_id ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Sanitize inputs.
        $page     = isset( $_POST['page_num'] )      ? max( 1, absint( $_POST['page_num'] ) ) : 1;
        $search   = isset( $_POST['search'] )        ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $sort     = isset( $_POST['sort'] )          ? sanitize_key( $_POST['sort'] ) : 'match';
        $jobtype  = isset( $_POST['status_filter'] ) ? absint( $_POST['status_filter'] ) : 0; // jobtype_id

        $allowed_sorts = [ 'match', 'newest' ];
        if ( ! in_array( $sort, $allowed_sorts, true ) ) {
            $sort = 'match';
        }

        $candidate_sector_ids = Workeera_Object_Terms_Relation::get_object_term_ids( $candidate_id, 'sectors' );
        $candidate_skill_ids  = Workeera_Object_Terms_Relation::get_object_term_ids( $candidate_id, 'skills' );

        // No matching basis at all — render empty state.
        if ( empty( $candidate_sector_ids ) && empty( $candidate_skill_ids ) ) {
            wp_send_json( [
                'error'           => '0',
                'list_html'       => '<div class="workeera-recommended-list"></div>',
                'pagination_html' => '',
                'total'           => 0,
                'is_empty'        => '1',
            ] );
        }

        $result      = self::get_recommended_jobs( $candidate_id, $candidate_sector_ids, $candidate_skill_ids, $page, $search, $sort, $jobtype );
        $rows        = $result['rows'];
        $total_found = $result['total'];

        ob_start();
        if ( ! empty( $rows ) ) {
            echo '<div class="workeera-recommended-list">';
            foreach ( $rows as $row ) {
                self::render_recommended_card( absint( $row['job_id'] ) );
            }
            echo '</div>';

            $info_label = sprintf(
                /* translators: 1: first item on page, 2: last item on page, 3: total items */
                esc_html_x( 'Showing %1$s–%2$s of %3$s jobs', 'recommended jobs pagination', 'workeera-remote-tech-job-board' ),
                number_format_i18n( ( $page - 1 ) * self::$per_page + 1 ),
                number_format_i18n( min( $page * self::$per_page, $total_found ) ),
                number_format_i18n( $total_found )
            );

            $pagination_html = Workeera_Dashboard_Common::render_pagination( $page, $total_found, self::$per_page, $info_label );
        } else {
            echo '<div class="workeera-recommended-list"></div>';
            $pagination_html = '';
        }
        $list_html = ob_get_clean();

        wp_send_json( [
            'error'           => '0',
            'list_html'       => $list_html,
            'pagination_html' => $pagination_html,
            'total'           => absint( $total_found ),
            'is_empty'        => empty( $rows ) ? '1' : '0',
        ] );
    }

    // -------------------------------------------------------------------------
    // Query helper
    // -------------------------------------------------------------------------

    /**
     * Fetch recommended jobs for a candidate based on sector + skill overlap.
     *
     * @return array { rows: array, total: int }
     */
    private static function get_recommended_jobs( $candidate_id, $sector_ids, $skill_ids, $page = 1, $search = '', $sort = 'match', $jobtype_id = 0 ) {
        global $wpdb;

        $per_page  = self::$per_page;
        $max_total = self::$max_total;
        $offset    = ( $page - 1 ) * $per_page;

        $posts_table   = $wpdb->prefix . 'posts';
        $sectors_rel   = $wpdb->prefix . 'workeera_sectors_relation';
        $skills_rel    = $wpdb->prefix . 'workeera_skills_relation';
        $jobtypes_rel  = $wpdb->prefix . 'workeera_jobtypes_relation';

        // Sanitise term ID arrays.
        $sector_ids = array_filter( array_map( 'absint', (array) $sector_ids ) );
        $skill_ids  = array_filter( array_map( 'absint', (array) $skill_ids ) );

        // Build the inner UNION ALL of matching (job_id, score) rows.
        // Each matching sector / skill counts as one point toward the match score.
        $union_parts = [];
        $union_args  = [];

        if ( ! empty( $sector_ids ) ) {
            $sector_placeholders = implode( ',', array_fill( 0, count( $sector_ids ), '%d' ) );
            $union_parts[]       = "SELECT object_id AS job_id, 1 AS score FROM {$sectors_rel} WHERE object_type = 'post' AND sector_id IN ({$sector_placeholders})";
            $union_args          = array_merge( $union_args, $sector_ids );
        }

        if ( ! empty( $skill_ids ) ) {
            $skill_placeholders = implode( ',', array_fill( 0, count( $skill_ids ), '%d' ) );
            $union_parts[]      = "SELECT object_id AS job_id, 1 AS score FROM {$skills_rel} WHERE object_type = 'post' AND skill_id IN ({$skill_placeholders})";
            $union_args         = array_merge( $union_args, $skill_ids );
        }

        if ( empty( $union_parts ) ) {
            return [ 'rows' => [], 'total' => 0 ];
        }

        $union_sql = '(' . implode( ' UNION ALL ', $union_parts ) . ')';

        // Outer WHERE clauses on posts.
        $where_parts  = [ "p.post_type = 'workeera_job'", "p.post_status = 'publish'" ];
        $where_args   = [];

        if ( ! empty( $search ) ) {
            $where_parts[] = 'p.post_title LIKE %s';
            $where_args[]  = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Optional jobtype filter (jobtype_id from dropdown).
        $jobtype_join = '';
        if ( $jobtype_id > 0 ) {
            $jobtype_join  = "INNER JOIN {$jobtypes_rel} AS jt ON ( jt.object_id = p.ID AND jt.object_type = 'post' AND jt.jobtype_id = %d )";
            $where_args    = array_merge( [ $jobtype_id ], $where_args );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_parts );

        switch ( $sort ) {
            case 'newest':
                $order_sql = 'ORDER BY p.post_date DESC';
                break;
            case 'match':
            default:
                $order_sql = 'ORDER BY match_score DESC, p.post_date DESC';
                break;
        }

        // Count first — capped at $max_total. Use a subquery so LIMIT is respected.
        $count_inner_sql = "SELECT p.ID
                            FROM {$posts_table} AS p
                            INNER JOIN {$union_sql} AS matches ON matches.job_id = p.ID
                            {$jobtype_join}
                            {$where_sql}
                            GROUP BY p.ID
                            LIMIT %d";

        $count_args = array_merge( $union_args, $where_args, [ $max_total ] );
        $count_sql  = "SELECT COUNT(*) FROM ( {$count_inner_sql} ) AS counted";

        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_args ) );

        if ( $total < 1 ) {
            return [ 'rows' => [], 'total' => 0 ];
        }

        // Page offset cannot exceed the cap.
        if ( $offset >= $max_total ) {
            return [ 'rows' => [], 'total' => $total ];
        }

        // Trim the page window so we never return rows past the cap.
        $page_limit = min( $per_page, max( 0, $max_total - $offset ) );
        if ( $page_limit < 1 ) {
            return [ 'rows' => [], 'total' => $total ];
        }

        $data_sql = "SELECT p.ID AS job_id, SUM(matches.score) AS match_score
                     FROM {$posts_table} AS p
                     INNER JOIN {$union_sql} AS matches ON matches.job_id = p.ID
                     {$jobtype_join}
                     {$where_sql}
                     GROUP BY p.ID
                     {$order_sql}
                     LIMIT %d OFFSET %d";

        $data_args = array_merge( $union_args, $where_args, [ $page_limit, $offset ] );
        $rows      = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_args ), ARRAY_A );

        return [ 'rows' => is_array( $rows ) ? $rows : [], 'total' => $total ];
    }

    // -------------------------------------------------------------------------
    // Card renderer
    // -------------------------------------------------------------------------

    /**
     * Render a single recommended job card.
     *
     * @param int $job_id Job post ID.
     */
    private static function render_recommended_card( $job_id ) {
        $job_id = absint( $job_id );
        if ( $job_id < 1 ) {
            return;
        }

        $post = get_post( $job_id );
        if ( ! $post || $post->post_type !== 'workeera_job' || $post->post_status !== 'publish' ) {
            return;
        }

        $job_url = get_permalink( $job_id );

        $job_fields  = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $is_urgent   = isset( $job_fields['is_urgent'] ) ? (int) $job_fields['is_urgent'] : 0;
        $min_salary  = isset( $job_fields['min_salary'] ) ? absint( $job_fields['min_salary'] ) : 0;
        $max_salary  = isset( $job_fields['max_salary'] ) ? absint( $job_fields['max_salary'] ) : 0;
        $salary_type = isset( $job_fields['salary_type'] ) ? sanitize_key( $job_fields['salary_type'] ) : '';
        $deadline    = isset( $job_fields['application_deadline'] ) ? sanitize_text_field( wp_unslash( $job_fields['application_deadline'] ) ) : '';

        $logo_url     = workeera_job_logo( $job_id );
        $company_info = workeera_job_company_info( $job_id );
        $company_name = is_array( $company_info ) && ! empty( $company_info['name'] ) ? workeera_strip_slashes( $company_info['name'] ) : '';

        $jobtype_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $job_id, 'jobtypes' );

        $salary_units      = workeera_salary_units();
        $salary_unit_label = isset( $salary_units[ $salary_type ] ) ? $salary_units[ $salary_type ] : '';

        $posted_ago = human_time_diff( get_post_time( 'U', false, $post ), current_time( 'timestamp' ) );
        /* translators: %s: human-readable time difference */
        $posted_label = sprintf( esc_html_x( '%s ago', 'recommended jobs time ago', 'workeera-remote-tech-job-board' ), esc_html( $posted_ago ) );

        $deadline_label = '';
        if ( ! empty( $deadline ) ) {
            $deadline_ts = strtotime( $deadline );
            if ( $deadline_ts ) {
                /* translators: %s: formatted date */
                $deadline_label = sprintf( esc_html_x( 'Deadline: %s', 'recommended jobs', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), $deadline_ts ) ) );
            }
        }
        ?>
        <div class="workeera-recommended-card" data-job-id="<?php echo absint( $job_id ); ?>">
            <?php if ( $is_urgent ) : ?>
                <div class="workeera-recommended-card-badges">
                    <span class="workeera-recommended-badge workeera-recommended-badge-urgent">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        <?php echo esc_html_x( 'Urgent', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="workeera-recommended-card-body">
                <div class="workeera-recommended-logo">
                    <a href="<?php echo esc_url( $job_url ); ?>">
                        <div class="workeera-recommended-logobox"<?php echo ( $logo_url ? ' style="background-image: url(\'' . esc_url( $logo_url ) . '\');"' : '' ); ?>></div>
                    </a>
                </div>
                <div class="workeera-recommended-info">
                    <strong class="workeera-recommended-jobtitle">
                        <a href="<?php echo esc_url( $job_url ); ?>"><?php echo esc_html( get_the_title( $job_id ) ); ?></a>
                    </strong>
                    <?php if ( $company_name ) : ?>
                        <div class="workeera-recommended-company"><?php echo esc_html( $company_name ); ?></div>
                    <?php endif; ?>
                    <div class="workeera-recommended-meta">
                        <span class="workeera-recommended-meta-item">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?php echo esc_html( $posted_label ); ?>
                        </span>
                        <?php if ( $deadline_label ) : ?>
                            <span class="workeera-recommended-meta-item">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo esc_html( $deadline_label ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( ! empty( $jobtype_labels ) ) : ?>
                    <div class="workeera-recommended-tags">
                        <?php foreach ( $jobtype_labels as $jt_label ) : ?>
                            <span class="workeera-recommended-tag"><?php echo esc_html( $jt_label ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ( $min_salary || $max_salary ) : ?>
                    <div class="workeera-recommended-salary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                            <circle cx="12" cy="12" r="2"></circle>
                            <path d="M6 12h.01M18 12h.01"></path>
                        </svg>
                        <?php
                        if ( $min_salary && $max_salary && $min_salary !== $max_salary ) {
                            echo wp_kses_post( workeera_formatted_salary( $min_salary ) . ' - ' . workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                        } elseif ( $max_salary ) {
                            echo wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                        } else {
                            echo wp_kses_post( workeera_formatted_salary( $min_salary, '', $salary_unit_label ) );
                        }
                        ?>
                    </div>
                <?php endif; ?>
                <div class="workeera-recommended-actions">
                    <a href="<?php echo esc_url( $job_url ); ?>" class="workeera-btn workeera-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        <?php echo esc_html_x( 'Apply Now', 'recommended jobs', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch all available job-type terms keyed by ID for the filter dropdown.
     */
    private static function get_jobtype_options() {
        global $wpdb;
        $jt_table = $wpdb->prefix . 'workeera_jobtypes';
        $rows     = $wpdb->get_results( "SELECT jobtype_id, label FROM {$jt_table} ORDER BY label ASC", ARRAY_A );
        $options  = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $options[ absint( $row['jobtype_id'] ) ] = $row['label'];
            }
        }
        return $options;
    }
}

new Workeera_Candidate_Applied_Jobs;

new Workeera_Candidate_Recommended_Jobs;
