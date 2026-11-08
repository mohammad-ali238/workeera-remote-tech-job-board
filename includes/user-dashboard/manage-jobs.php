<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the "Manage Jobs" section of the user dashboard.
 */
class Workeera_User_Manage_Jobs {

    /**
     * Number of jobs to show per page.
     */
    private static $per_page = 10;

    /**
     * Register AJAX action hooks for the manage jobs section.
     */
    public function __construct() {
        add_action( 'wp_ajax_workeera_manage_jobs_list',        [ $this, 'ajax_jobs_list' ] );
        add_action( 'wp_ajax_workeera_toggle_filled_job_status', [ $this, 'ajax_toggle_filled_status' ] );
        add_action( 'wp_ajax_workeera_delete_user_job', [ $this, 'ajax_delete_user_job' ] );
    }

    // -------------------------------------------------------------------------
    // Main listings shell — static HTML wrapper + filter bar only.
    // The job list and pagination are injected via AJAX on page load.
    // -------------------------------------------------------------------------

    /**
     * Render the manage jobs listings shell with filter bar.
     */
    public static function listings() {
        global $workeera_plugin_settings;

        wp_enqueue_script('workeera-managejobs-scripts');

        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = get_permalink( $dashboard_page );
        $job_submission_url = add_query_arg( [ 'section-type' => 'job' ], $dashboard_page_url );
        ?>
        <div class="workeera-usrjobs-wrap workeera-usrdshb-main-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'workeera_nonce' ) ); ?>">
            <div class="workeera-usrdshb-container">

                <!-- Section Header -->
                <div class="workeera-usrjobs-header">
                    <strong class="workeera-usrjobs-title"><?php echo esc_html_x( 'Manage Jobs', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></strong>
                    <a href="<?php echo esc_url( $job_submission_url ); ?>" class="workeera-usrjobs-postnew-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <?php echo esc_html_x( 'Post New Job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                </div>

                <!-- Filter Bar -->
                <div class="workeera-usrjobs-filterbar">
                    <div class="workeera-usrjobs-search-wrap">
                        <span class="workeera-usrjobs-search-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </span>
                        <input type="text" class="workeera-usrjobs-field-input workeera-usrjobs-search-input" placeholder="<?php echo esc_attr_x( 'Search jobs…', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>" />
                    </div>
                    <select class="workeera-usrjobs-field-select workeera-usrjobs-sort-select">
                        <option value=""><?php echo esc_html_x( 'Sort By', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="newest"><?php echo esc_html_x( 'Newest First', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="oldest"><?php echo esc_html_x( 'Oldest First', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="deadline"><?php echo esc_html_x( 'Deadline', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                    </select>
                    <select class="workeera-usrjobs-field-select workeera-usrjobs-status-select">
                        <option value=""><?php echo esc_html_x( 'All Statuses', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="active"><?php echo esc_html_x( 'Active', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="inactive"><?php echo esc_html_x( 'Inactive', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="expired"><?php echo esc_html_x( 'Expired', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></option>
                    </select>
                </div>

                <!-- Jobs Listing (populated via AJAX) -->
                <div class="workeera-usrjobs-list-holder" data-submission-url="<?php echo esc_url( $job_submission_url ); ?>">
                    <div class="workeera-usrjobs-loder-con"><?php self::loader() ?></div>
                    <div class="workeera-usrjobs-list"></div>
                    <div class="workeera-usrjobs-pagination"></div>
                    <div class="workeera-usrjobs-empty workeera-hide">
                        <div class="workeera-usrjobs-empty-icon">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                                <line x1="12" y1="12" x2="12" y2="16" />
                                <line x1="10" y1="14" x2="14" y2="14" />
                            </svg>
                        </div>
                        <p class="workeera-usrjobs-empty-title"><?php echo esc_html_x( 'No Jobs Found', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></p>
                        <p class="workeera-usrjobs-empty-desc"><?php echo esc_html_x( 'You haven\'t posted any jobs yet, or no jobs match your current filters. Start by posting your first job listing.', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></p>
                        <a href="<?php echo esc_url( $job_submission_url ); ?>" class="workeera-usrjobs-empty-cta workeera-usrjobs-empty-cta-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <?php echo esc_html_x( 'Post Your First Job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>
                        </a>
                    </div>
                    <div class="workeera-init-loder-holdr workeera-hide"><?php self::loader() ?></div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Render the placeholder skeleton loader for the listings.
     */
    public static function loader() {
        ?>
        <div class="workeera-listin-loadr workeera-mangjobs-loadr">
            <?php
            for ($loder = 1; $loder < 6; $loder++) {
                ?>
                <div class="workeera-mangjobs-loderitm">
                    <div class="itmloder-info-con">
                        <div class="itm-info-det">
                            <span class="item-name-line"></span>
                            <span class="item-price-line"></span>
                            <span class="item-sts-line"></span>
                        </div>
                        <div class="itm-info-acts">
                            <span class="item-act-btn"></span>
                        </div>
                    </div>
                    <div class="workeera-itmloder-filled-row">
                        <span class="item-filled-state"></span>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — return jobs list HTML + pagination HTML
    // -------------------------------------------------------------------------

    /**
     * AJAX handler returning the user's filtered jobs list and pagination HTML.
     */
    public function ajax_jobs_list() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id = get_current_user_id();

        // Sanitize inputs.
        $page        = isset( $_POST['page_num'] ) ? max( 1, absint( $_POST['page_num'] ) ) : 1;
        $search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $sort        = isset( $_POST['sort'] ) ? sanitize_key( $_POST['sort'] ) : '';
        $status      = isset( $_POST['status_filter'] ) ? sanitize_key( $_POST['status_filter'] ) : '';

        $allowed_sorts   = [ 'newest', 'oldest', 'deadline' ];
        $allowed_statuses = [ 'active', 'inactive', 'moderated', 'expired' ];

        if ( ! in_array( $sort, $allowed_sorts, true ) ) {
            $sort = 'newest';
        }
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }

        // Total count for user (all posts, no filters) — used for "Post Your First Job" vs "Post new job".
        $total_user_jobs = self::workeera_count_user_jobs( $user_id );

        // Filtered + paginated query.
        $result      = self::workeera_get_user_jobs( $user_id, $page, $search, $sort, $status );
        $jobs        = $result['jobs'];
        $total_found = $result['total'];

        global $workeera_plugin_settings;
        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = get_permalink( $dashboard_page );
        $job_submission_url = add_query_arg( [ 'section-type' => 'job' ], $dashboard_page_url );

        ob_start();

        if ( ! empty( $jobs ) ) {
            echo '<div class="workeera-usrjobs-list">';
            foreach ( $jobs as $job ) {
                self::workeera_render_job_item( $job, $job_submission_url );
            }
            echo '</div>';

            $info_label = sprintf(
                /* translators: 1: range start, 2: range end, 3: total count */
                esc_html_x( 'Showing %1$s–%2$s of %3$s jobs', 'manage jobs pagination', 'workeera-remote-tech-job-board' ),
                number_format_i18n( ( $page - 1 ) * self::$per_page + 1 ),
                number_format_i18n( min( $page * self::$per_page, $total_found ) ),
                number_format_i18n( $total_found )
            );

            // Pagination HTML.
            $pagination_html = Workeera_Dashboard_Common::render_pagination( $page, $total_found, self::$per_page, $info_label );
        } else {
            echo '<div class="workeera-usrjobs-list"></div>';
            $pagination_html = '';
        }

        $list_html = ob_get_clean();

        // Empty state CTA label depends on whether user has ANY jobs at all.
        $empty_cta_label = $total_user_jobs > 0
            ? esc_html_x( 'Post New Job', 'manage jobs', 'workeera-remote-tech-job-board' )
            : esc_html_x( 'Post Your First Job', 'manage jobs', 'workeera-remote-tech-job-board' );

        wp_send_json( [
            'error'           => '0',
            'list_html'       => $list_html,
            'pagination_html' => $pagination_html,
            'total'           => absint( $total_found ),
            'is_empty'        => empty( $jobs ) ? '1' : '0',
            'empty_cta_label' => $empty_cta_label,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — toggle filled status
    // -------------------------------------------------------------------------

    /**
     * AJAX handler that toggles a job's "filled" flag for the current user.
     */
    public function ajax_toggle_filled_status() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
    if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
        wp_send_json(array('error' => 'security_failed'));
    }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $job_id  = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
        $filled  = isset( $_POST['filled'] ) ? (int) $_POST['filled'] : 0;

        if ( $job_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid job.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Verify ownership or edit capability.
        if ( ! workeera_is_user_can_job_post( $job_id ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to edit this job.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $wpdb;

        $job_fields_table = $wpdb->prefix . 'workeera_job_fields';

        // Get existing job_fields row.
        $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $job_field_id = isset( $job_fields['job_field_id'] ) ? absint( $job_fields['job_field_id'] ) : 0;

        if ( $job_field_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Job fields record not found.', 'workeera-remote-tech-job-board' ) ] );
        }

        $updated = $wpdb->update(
            $job_fields_table,
            [ 'is_filled' => $filled ? 1 : 0 ],
            [ 'job_field_id'   => $job_field_id ],
            [ '%d' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Failed to update job status.', 'workeera-remote-tech-job-board' ) ] );
        }

        wp_send_json( [
            'error'      => '0',
            'msg'        => $filled
                ? esc_html__( 'Job marked as filled.', 'workeera-remote-tech-job-board' )
                : esc_html__( 'Job unfilled.', 'workeera-remote-tech-job-board' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Count ALL jobs for a user across all post statuses (no filters).
     */
    private static function workeera_count_user_jobs( $user_id ) {
        $args = [
            'post_type'      => 'workeera_job',
            'post_status'    => [ 'publish', 'pending', 'draft', 'trash' ],
            'author'         => absint( $user_id ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];
        $query = new WP_Query( $args );
        return absint( $query->found_posts );
    }

    /**
     * Fetch paginated, filtered, sorted jobs for a user.
     *
     * @param int    $user_id
     * @param int    $page
     * @param string $search   Title search string.
     * @param string $sort     newest|oldest|deadline
     * @param string $status   active|inactive|expired|''
     * @return array { jobs: WP_Post[], total: int }
     */
    private static function workeera_get_user_jobs( $user_id, $page = 1, $search = '', $sort = 'newest', $status = '' ) {
        global $wpdb;

        $per_page   = self::$per_page;
        $offset     = ( $page - 1 ) * $per_page;

        $jf_table   = $wpdb->prefix . 'workeera_job_fields';
        $posts_table = $wpdb->prefix . 'posts';

        // Build WHERE clauses.
        $where_parts  = [];
        $placeholders = [];

        $where_parts[]  = 'p.post_type = %s';
        $placeholders[] = 'workeera_job';

        $where_parts[]  = 'p.post_author = %d';
        $placeholders[] = absint( $user_id );

        $where_parts[] = "p.post_status IN ('publish','pending','draft','trash')";

        if ( ! empty( $search ) ) {
            $where_parts[]  = 'p.post_title LIKE %s';
            $placeholders[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Status filter — joins workeera_job_fields.
        $join_jf = "LEFT JOIN $jf_table AS jf ON jf.job_id = p.ID";

        if ( 'active' === $status ) {
            $where_parts[] = "jf.listing_status = 'active'";
        } elseif ( 'inactive' === $status ) {
            $where_parts[] = "jf.listing_status = 'inactive'";
        } elseif ( 'expired' === $status ) {
            $where_parts[] = "jf.listing_status = 'expired'";
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_parts );

        // ORDER BY.
        switch ( $sort ) {
            case 'oldest':
                $order_sql = 'ORDER BY p.post_date ASC';
                break;
            case 'deadline':
                $order_sql = 'ORDER BY jf.application_deadline ASC';
                break;
            default: // newest
                $order_sql = 'ORDER BY p.post_date DESC';
                break;
        }

        // Count query.
        $count_sql = "SELECT COUNT(*) FROM $posts_table AS p $join_jf $where_sql";
        $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $placeholders ) );

        if ( $total < 1 ) {
            return [ 'jobs' => [], 'total' => 0 ];
        }

        // Data query — fetch post IDs then use get_post() to stay WP-native.
        $placeholders_data   = $placeholders;
        $placeholders_data[] = $per_page;
        $placeholders_data[] = $offset;

        $ids_sql = "SELECT p.ID FROM $posts_table AS p $join_jf $where_sql $order_sql LIMIT %d OFFSET %d";
        $ids     = $wpdb->get_col( $wpdb->prepare( $ids_sql, $placeholders_data ) );

        $jobs = [];
        if ( ! empty( $ids ) ) {
            foreach ( $ids as $id ) {
                $post = get_post( absint( $id ) );
                if ( $post ) {
                    $jobs[] = $post;
                }
            }
        }

        return [ 'jobs' => $jobs, 'total' => $total ];
    }

    /**
     * Output the HTML for one job row.
     *
     * @param WP_Post $job
     * @param string  $job_submission_url
     */
    private static function workeera_render_job_item( $job, $job_submission_url ) {
        global $workeera_plugin_settings;

        $job_id = absint( $job->ID );

        $allow_published_edits = isset($workeera_plugin_settings['job_submission_settings']['allow_published_edits']) ? $workeera_plugin_settings['job_submission_settings']['allow_published_edits'] : '';

        // Job fields row from custom table.
        $job_fields      = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $listing_status  = isset( $job_fields['listing_status'] ) ? sanitize_key( $job_fields['listing_status'] ) : 'active';
        $is_featured     = isset( $job_fields['is_featured'] ) ? (int) $job_fields['is_featured'] : 0;
        $deadline        = isset( $job_fields['application_deadline'] ) ? sanitize_text_field( wp_unslash( $job_fields['application_deadline'] ) ) : '';
        $expiry          = isset( $job_fields['expiry_date'] ) ? sanitize_text_field( wp_unslash( $job_fields['expiry_date'] ) ) : '';
        $is_filled       = isset( $job_fields['is_filled'] ) ? (int) $job_fields['is_filled'] : 0;

        $job_post_status = isset( $job->post_status ) ? sanitize_key( $job->post_status ) : '';
        if ($job_post_status == 'publish') {
            $listing_status = 'active';
        }

        // Sector label (single).
        $sector_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $job_id, 'sectors' );
        $sector_label  = ! empty( $sector_labels ) ? reset( $sector_labels ) : '';

        // Dates.
        $posted_ago   = human_time_diff( get_post_time( 'U', false, $job ), current_time( 'timestamp' ) );
        /* translators: %s: human-readable time difference */
        $posted_label = sprintf( esc_html_x( '%s ago', 'manage jobs time ago', 'workeera-remote-tech-job-board' ), esc_html( $posted_ago ) );

        $deadline_label = '';
        if ( ! empty( $deadline ) ) {
            /* translators: %s: formatted date */
            $deadline_label = sprintf( esc_html_x( 'Deadline: %s', 'manage jobs', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $deadline ) ) ) );
        }

        // Label for expiry column — "Expires:" for future, "Expired:" for past.
        $expiry_label = '';
        if ( ! empty( $expiry ) ) {
            $expiry_ts = strtotime( $expiry );
            if ( $expiry_ts ) {
                $expiry_formatted = date_i18n( get_option( 'date_format' ), $expiry_ts );
                if ( $expiry_ts < current_time( 'timestamp' ) ) {
                    /* translators: %s: formatted date */
                    $expiry_label = sprintf( esc_html_x( 'Expired: %s', 'manage jobs', 'workeera-remote-tech-job-board' ), esc_html( $expiry_formatted ) );
                    $listing_status = 'expired';
                } else {
                    /* translators: %s: formatted date */
                    $expiry_label = sprintf( esc_html_x( 'Expires: %s', 'manage jobs', 'workeera-remote-tech-job-board' ), esc_html( $expiry_formatted ) );
                }
            }
        }

        if ( 'pending' == $job_post_status && $listing_status == 'active' ) {
            $listing_status = 'pending';
        } elseif ( 'publish' != $job_post_status && $listing_status == 'active' ) {
            $listing_status = 'inactive';
        }

        // Status badge CSS class + label.
        $status_badge_class = self::workeera_status_badge_class( $listing_status );
        $status_badge_label = self::workeera_status_badge_label( $listing_status );

        // View URL — only published posts have a public permalink.
        $view_url = ( 'publish' === $job_post_status ) ? get_permalink( $job_id ) : '';
        $edit_url = add_query_arg( [ 'section-type' => 'job', 'job_id' => $job_id ], $job_submission_url );

        // Applications URL — links to the employer applications section pre-filtered by this job.
        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : '';
        $applications_url   = $dashboard_page_url ? add_query_arg( [ 'section-type' => 'applications', 'job_id' => $job_id ], $dashboard_page_url ) : '';

        // Live count of applications for this job.
        global $wpdb;
        $apps_table = $wpdb->prefix . 'workeera_applications';
        $app_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$apps_table} WHERE job_id = %d", $job_id ) );

        // Filled checkbox state.
        $filled_id = 'filled-job-' . $job_id;
        ?>
        <div class="workeera-usrjobs-item" data-job-id="<?php echo absint( $job_id ); ?>">
            <div class="workeera-usrjobs-item-row">
                <div class="workeera-usrjobs-col-info">
                    <span class="workeera-usrjobs-jobtitle"><?php echo esc_html( $job->post_title ); ?></span>
                    <?php if ( $sector_label ) : ?>
                        <span class="workeera-usrjobs-sector"><?php echo esc_html( $sector_label ); ?></span>
                    <?php endif; ?>
                    <div class="workeera-usrjobs-meta">
                        <span class="workeera-usrjobs-meta-item">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            <?php echo esc_html( $posted_label ); ?>
                        </span>
                        <?php if ( $deadline_label ) : ?>
                            <span class="workeera-usrjobs-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                <?php echo esc_html( $deadline_label ); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( $expiry_label ) : ?>
                            <span class="workeera-usrjobs-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10" />
                                    <path d="M3.51 15a9 9 0 1 0 .49-3.96" />
                                </svg>
                                <?php echo esc_html( $expiry_label ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="workeera-usrjobs-col-apps">
                    <span class="workeera-usrjobs-col-label"><?php echo esc_html_x( 'Applications', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></span>
                    <?php if ( $applications_url ) : ?>
                        <a href="<?php echo esc_url( $applications_url ); ?>" class="workeera-usrjobs-applink" title="<?php echo esc_attr_x( 'View applications for this job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                            <?php
                            /* translators: %d: number of applications */
                            echo esc_html( sprintf( _nx( '%d Application', '%d Applications', $app_count, 'manage jobs', 'workeera-remote-tech-job-board' ), $app_count ) );
                            ?>
                        </a>
                    <?php else : ?>
                        <span class="workeera-usrjobs-applink">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                            <?php
                            /* translators: %d: number of applications */
                            echo esc_html( sprintf( _nx( '%d Application', '%d Applications', $app_count, 'manage jobs', 'workeera-remote-tech-job-board' ), $app_count ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="workeera-usrjobs-col-featured">
                    <span class="workeera-usrjobs-col-label"><?php echo esc_html_x( 'Featured', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></span>
                    <?php if ( $is_featured ) : ?>
                        <svg class="workeera-usrjobs-star-yes" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg>
                        <span class="workeera-usrjobs-featured-label workeera-usrjobs-featured-yes"><?php echo esc_html_x( 'Yes', 'manage jobs featured', 'workeera-remote-tech-job-board' ); ?></span>
                    <?php else : ?>
                        <svg class="workeera-usrjobs-star-no" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg>
                        <span class="workeera-usrjobs-featured-label workeera-usrjobs-featured-no"><?php echo esc_html_x( 'No', 'manage jobs featured', 'workeera-remote-tech-job-board' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="workeera-usrjobs-col-status">
                    <span class="workeera-usrjobs-col-label"><?php echo esc_html_x( 'Status', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></span>
                    <span class="workeera-usrjobs-status-badge <?php echo esc_attr( $status_badge_class ); ?>"><?php echo esc_html( $status_badge_label ); ?></span>
                </div>
                <div class="workeera-usrjobs-col-actions">
                    <?php if ( $view_url ) : ?>
                        <a href="<?php echo esc_url( $view_url ); ?>" class="workeera-usrjobs-action-btn" title="<?php echo esc_attr_x( 'View Job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>" target="_blank" rel="noopener noreferrer">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </a>
                    <?php else : ?>
                        <span class="workeera-usrjobs-action-btn" title="<?php echo esc_attr_x( 'View Job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>" style="opacity:0.35;cursor:default;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </span>
                    <?php
                    endif;
                    if ( $allow_published_edits != 'no' ) : ?>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="workeera-usrjobs-action-btn workeera-usrjobs-action-edit" title="<?php echo esc_attr_x( 'Edit Job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </a>
                    <?php endif; ?>
                    <button class="workeera-usrjobs-action-btn workeera-usrjobs-action-delete" data-job-id="<?php echo absint( $job_id ); ?>" title="<?php echo esc_attr_x( 'Delete Job', 'manage jobs', 'workeera-remote-tech-job-board' ); ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="workeera-usrjobs-filled-row">
                <input type="checkbox" id="<?php echo esc_attr( $filled_id ); ?>" class="workeera-usrjobs-filled-chk" data-job-id="<?php echo absint( $job_id ); ?>" <?php checked( $is_filled ); ?> />
                <label class="workeera-usrjobs-filled-label" for="<?php echo esc_attr( $filled_id ); ?>"><?php echo esc_html_x( 'Filled job? Mark if this position has been filled.', 'manage jobs', 'workeera-remote-tech-job-board' ); ?></label>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Badge helpers
    // -------------------------------------------------------------------------

    /**
     * Return the CSS modifier class for a given listing_status.
     *
     * @param  string $status
     * @return string
     */
    private static function workeera_status_badge_class( $status ) {
        $map = [
            'active'   => 'workeera-usrjobs-status-active',
            'inactive' => 'workeera-usrjobs-status-inactive',
            'pending' => 'workeera-usrjobs-status-pending',
            'moderated'   => 'workeera-usrjobs-status-inactive',
            'expired'  => 'workeera-usrjobs-status-expired',
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : 'workeera-usrjobs-status-inactive';
    }

    /**
     * Return the translatable label for a given listing_status.
     *
     * @param  string $status
     * @return string
     */
    private static function workeera_status_badge_label( $status ) {
        $map = [
            'active'   => esc_html_x( 'Active',   'job listing status', 'workeera-remote-tech-job-board' ),
            'inactive' => esc_html_x( 'Inactive', 'job listing status', 'workeera-remote-tech-job-board' ),
            'pending' => esc_html_x( 'Pending Review', 'job listing status', 'workeera-remote-tech-job-board' ),
            'moderated'   => esc_html_x( 'Moderated',   'job listing status', 'workeera-remote-tech-job-board' ),
            'expired'  => esc_html_x( 'Expired',  'job listing status', 'workeera-remote-tech-job-board' ),
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : esc_html_x( 'Inactive', 'job listing status', 'workeera-remote-tech-job-board' );
    }

    /**
     * AJAX handler that deletes a user-owned job listing.
     */
    public function ajax_delete_user_job() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $job_id  = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

        if ( $job_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid job.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Verify ownership or edit capability.
        if ( ! workeera_is_user_can_job_post( $job_id ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to delete this job.', 'workeera-remote-tech-job-board' ) ] );
        }

        wp_delete_post($job_id, true);

        wp_send_json( [
            'error'      => '0',
            'msg'        => esc_html__( 'Job deleted successfully.', 'workeera-remote-tech-job-board' ),
        ] );
    }
}

new Workeera_User_Manage_Jobs;
