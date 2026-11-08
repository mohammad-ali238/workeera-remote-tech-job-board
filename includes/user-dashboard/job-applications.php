<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the employer "Job Applications" dashboard section.
 */
class Workeera_Employer_Job_Applications {

    /**
     * Number of applications shown per page.
     */
    private static $per_page = 10;

    /**
     * If the employer has this many jobs or fewer, the job filter Select2
     * preloads them all. Above this threshold, options are fetched via AJAX.
     */
    private static $jobs_preload_threshold = 50;

    /**
     * Statuses an employer (or admin) is allowed to set on an application.
     */
    public static function actionable_statuses() {
        return [ 'pending', 'reviewed', 'shortlisted', 'rejected' ];
    }

    /**
     * Register enqueue and AJAX hooks for the employer applications section.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 6 );

        add_action( 'wp_ajax_workeera_employer_apps_list',        [ $this, 'ajax_apps_list' ] );
        add_action( 'wp_ajax_workeera_employer_app_status',       [ $this, 'ajax_update_status' ] );
        add_action( 'wp_ajax_workeera_employer_app_bulk_status',  [ $this, 'ajax_bulk_update_status' ] );
        add_action( 'wp_ajax_workeera_employer_jobs_search',      [ $this, 'ajax_jobs_search' ] );
    }

    /**
     * Enqueue the job-applications assets when viewing the dashboard section.
     */
    public function enqueue_scripts() {
        global $workeera_plugin_settings;

        $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if ( ! empty( $dashboard_page ) && get_post_type( $dashboard_page ) == 'page' && is_page( $dashboard_page ) && isset( $_GET['section-type'] ) && $_GET['section-type'] == 'applications' ) {
            wp_enqueue_style( 'workeera-job-applications', Workeera_Plugin::root_url() . 'css/job-applications.css', array( 'workeera-styles' ), WORKEERA_VERSION );

            wp_enqueue_script( 'workeera-job-applications', Workeera_Plugin::root_url() . 'js/job-applications.js', array( 'workeera-scripts', 'select2' ), WORKEERA_VERSION, true );
            wp_localize_script( 'workeera-job-applications', 'workeera_empapps_str', self::localize_strings() );
        }
    }

    /**
     * Strings exposed to job-applications.js.
     */
    public static function localize_strings() {
        return [
            'confirm_title'        => esc_html_x( 'Confirm Action', 'employer applications', 'workeera-remote-tech-job-board' ),
            'confirm_btn'          => esc_html_x( 'Yes, Confirm', 'employer applications', 'workeera-remote-tech-job-board' ),
            'cancel_btn'           => esc_html_x( 'Cancel', 'employer applications', 'workeera-remote-tech-job-board' ),
            'generic_err'          => esc_html__( 'Something went wrong. Please try again.', 'workeera-remote-tech-job-board' ),
            'close_btn'            => esc_html_x( 'Close', 'employer applications', 'workeera-remote-tech-job-board' ),
            'cover_letter_title'   => esc_html_x( 'Cover Letter', 'employer applications', 'workeera-remote-tech-job-board' ),
            /* translators: %s: target status label (Reviewed, Shortlisted, Rejected, Pending) */
            'single_confirm_msg'   => esc_html_x( 'Are you sure you want to mark this application as %s?', 'employer applications', 'workeera-remote-tech-job-board' ),
            /* translators: 1: count of applications, 2: target status label */
            'bulk_confirm_msg'     => esc_html_x( 'Are you sure you want to mark %1$s applications as %2$s?', 'employer applications', 'workeera-remote-tech-job-board' ),
            'no_selection_err'     => esc_html_x( 'Please select at least one application.', 'employer applications', 'workeera-remote-tech-job-board' ),
            'no_action_err'        => esc_html_x( 'Please select a bulk action.', 'employer applications', 'workeera-remote-tech-job-board' ),
            /* translators: %s: count of selected items */
            'selection_count_lbl'  => esc_html_x( '%s selected', 'employer applications', 'workeera-remote-tech-job-board' ),
            'select2_searching'    => esc_html_x( 'Searching...', 'Select2 dropdown', 'workeera-remote-tech-job-board' ),
            'select2_loading_more' => esc_html_x( 'Loading more results...', 'Select2 dropdown', 'workeera-remote-tech-job-board' ),
            'select2_no_results'   => esc_html_x( 'No results found', 'Select2 dropdown', 'workeera-remote-tech-job-board' ),
            'all_jobs_lbl'         => esc_html_x( 'All Jobs', 'employer applications', 'workeera-remote-tech-job-board' ),
            'all_employers_lbl'    => esc_html_x( 'All Employers', 'employer applications', 'workeera-remote-tech-job-board' ),
            /* translators: %s: count of applications */
            'bulk_delete_confirm_msg' => esc_html_x( 'Are you sure you want to delete %s applications? This action cannot be undone.', 'employer applications', 'workeera-remote-tech-job-board' ),
            'delete_confirm_btn'      => esc_html_x( 'Yes, Delete', 'employer applications', 'workeera-remote-tech-job-board' ),
            // Status labels for confirm-box messages.
            'status_pending_lbl'     => esc_html_x( 'Pending', 'application status', 'workeera-remote-tech-job-board' ),
            'status_reviewed_lbl'    => esc_html_x( 'Reviewed', 'application status', 'workeera-remote-tech-job-board' ),
            'status_shortlisted_lbl' => esc_html_x( 'Shortlisted', 'application status', 'workeera-remote-tech-job-board' ),
            'status_rejected_lbl'    => esc_html_x( 'Rejected', 'application status', 'workeera-remote-tech-job-board' ),
        ];
    }

    // -------------------------------------------------------------------------
    // Static shell — markup wrapper, filter bar, bulk bar, list holder.
    // The list itself is loaded via AJAX.
    // -------------------------------------------------------------------------

    /**
     * Render the applications section shell with filter and bulk action bars.
     */
    public static function list_applications() {
        global $workeera_plugin_settings;

        $user_id            = get_current_user_id();
        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : '';

        // Pre-fill job filter from URL if one was passed (e.g., from manage-jobs).
        $preselected_job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;

        $total_user_jobs = self::count_user_jobs( $user_id );
        $job_select_mode = ( $total_user_jobs <= self::$jobs_preload_threshold ) ? 'preload' : 'ajax';

        $preloaded_jobs = [];
        if ( 'preload' === $job_select_mode ) {
            $preloaded_jobs = self::get_user_jobs_for_select( $user_id, self::$jobs_preload_threshold );
        }

        // When a preselected job exists in AJAX mode, also fetch its label so the option renders correctly.
        $preselected_job_label = '';
        if ( $preselected_job_id > 0 ) {
            $job_post = get_post( $preselected_job_id );
            if ( $job_post && $job_post->post_type === 'workeera_job' && (int) $job_post->post_author === (int) $user_id ) {
                $preselected_job_label = $job_post->post_title;
            } else {
                $preselected_job_id = 0; // not owned by this user — drop.
            }
        }
        ?>
        <div class="workeera-empapps-wrap workeera-usrdshb-main-wrap"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'workeera_nonce' ) ); ?>"
             data-job-select-mode="<?php echo esc_attr( $job_select_mode ); ?>"
             data-preselected-job="<?php echo absint( $preselected_job_id ); ?>">
            <div class="workeera-usrdshb-container">

                <!-- Header -->
                <div class="workeera-empapps-header">
                    <strong class="workeera-empapps-title"><?php echo esc_html_x( 'Job Applications', 'employer applications', 'workeera-remote-tech-job-board' ); ?></strong>
                </div>

                <!-- Filter Bar -->
                <div class="workeera-empapps-filterbar">
                    <div class="workeera-empapps-filters-wrap">
                        <div class="workeera-empapps-search-wrap">
                            <span class="workeera-empapps-search-icon">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                            </span>
                            <input type="text" class="workeera-empapps-field-input workeera-empapps-search-input" placeholder="<?php echo esc_attr_x( 'Search by name, email or job title...', 'employer applications', 'workeera-remote-tech-job-board' ); ?>">
                        </div>
                        <select class="workeera-empapps-field-select workeera-empapps-sort-select">
                            <option value=""><?php echo esc_html_x( 'Sort by...', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                            <option value="newest"><?php echo esc_html_x( 'Newest First', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                            <option value="oldest"><?php echo esc_html_x( 'Oldest First', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                            <?php
                            /**
                             * Emit extra <option> elements for the employer applications
                             * "Sort by" dropdown. The chosen value round-trips to the
                             * `workeera_employer_apps_list_orderby` filter as the sort key.
                             */
                            do_action( 'workeera_employer_apps_sort_options' );
                            ?>
                        </select>
                        <select class="workeera-empapps-field-select workeera-empapps-status-select">
                            <option value=""><?php echo esc_html_x( 'All Statuses', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                            <option value="pending"><?php echo esc_html_x( 'Pending', 'application status', 'workeera-remote-tech-job-board' ); ?></option>
                            <option value="reviewed"><?php echo esc_html_x( 'Reviewed', 'application status', 'workeera-remote-tech-job-board' ); ?></option>
                            <option value="shortlisted"><?php echo esc_html_x( 'Shortlisted', 'application status', 'workeera-remote-tech-job-board' ); ?></option>
                            <option value="rejected"><?php echo esc_html_x( 'Rejected', 'application status', 'workeera-remote-tech-job-board' ); ?></option>
                        </select>
                    </div>
                    <select class="workeera-empapps-field-select workeera-empapps-job-select" data-placeholder="<?php echo esc_attr_x( 'All Jobs', 'employer applications', 'workeera-remote-tech-job-board' ); ?>">
                        <option value=""><?php echo esc_html_x( 'All Jobs', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                        <?php
                        if ( 'preload' === $job_select_mode ) {
                            foreach ( $preloaded_jobs as $jid => $jlabel ) {
                                $selected_attr = ( $jid === $preselected_job_id ) ? ' selected' : '';
                                echo '<option value="' . absint( $jid ) . '"' . esc_attr( $selected_attr ) . '>' . esc_html( $jlabel ) . '</option>';
                            }
                        } elseif ( $preselected_job_id > 0 && ! empty( $preselected_job_label ) ) {
                            // Inject the preselected option for AJAX mode so it renders before search loads.
                            echo '<option value="' . absint( $preselected_job_id ) . '" selected>' . esc_html( $preselected_job_label ) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="workeera-empapps-bulk-bar workeera-hide">
                    <label class="workeera-empapps-bulk-selectall">
                        <input type="checkbox" class="workeera-empapps-bulk-master-chk" />
                        <span class="workeera-empapps-bulk-selectall-lbl"><?php echo esc_html_x( 'Select all', 'employer applications', 'workeera-remote-tech-job-board' ); ?></span>
                    </label>
                    <?php /* translators: %s: number of selected items */ ?>
                    <span class="workeera-empapps-bulk-count" data-tpl="<?php echo esc_attr_x( '%s selected', 'employer applications', 'workeera-remote-tech-job-board' ); ?>">0</span>
                    <select class="workeera-empapps-field-select workeera-empapps-bulk-action-select">
                        <option value=""><?php echo esc_html_x( 'Bulk Actions', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="reviewed"><?php echo esc_html_x( 'Mark as Reviewed', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="shortlisted"><?php echo esc_html_x( 'Mark as Shortlisted', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                        <option value="rejected"><?php echo esc_html_x( 'Mark as Rejected', 'employer applications', 'workeera-remote-tech-job-board' ); ?></option>
                    </select>
                    <button type="button" class="workeera-empapps-bulk-apply-btn workeera-btn">
                        <?php echo esc_html_x( 'Apply', 'employer applications', 'workeera-remote-tech-job-board' ); ?>
                    </button>
                </div>

                <!-- Applications List (populated via AJAX) -->
                <div class="workeera-empapps-list-holder">
                    <div class="workeera-usrjobs-loder-con"><?php Workeera_Dashboard_Common::loader( 'employer-apps' ); ?></div>
                    <div class="workeera-empapps-list"></div>
                    <div class="workeera-usrjobs-pagination"></div>
                    <div class="workeera-empapps-empty workeera-hide">
                        <div class="workeera-empapps-empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M22 11h-6"/>
                                <path d="M19 8v6"/>
                            </svg>
                        </div>
                        <strong class="workeera-empapps-empty-title"><?php echo esc_html_x( 'No Applications Found', 'employer applications', 'workeera-remote-tech-job-board' ); ?></strong>
                        <div class="workeera-empapps-empty-desc"><?php echo esc_html_x( "You haven't received any applications yet, or none match your filters.", 'employer applications', 'workeera-remote-tech-job-board' ); ?></div>
                    </div>
                    <div class="workeera-init-loder-holdr workeera-hide"><?php Workeera_Dashboard_Common::loader( 'employer-apps' ); ?></div>
                </div>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — applications list
    // -------------------------------------------------------------------------

    /**
     * AJAX handler returning the employer's applications list and pagination.
     */
    public function ajax_apps_list() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id = get_current_user_id();

        // Sanitise inputs.
        $page          = isset( $_POST['page_num'] )      ? max( 1, absint( $_POST['page_num'] ) ) : 1;
        $search        = isset( $_POST['search'] )        ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $sort          = isset( $_POST['sort'] )          ? sanitize_key( $_POST['sort'] ) : '';
        $status_filter = isset( $_POST['status_filter'] ) ? sanitize_key( $_POST['status_filter'] ) : '';
        $job_filter    = isset( $_POST['job_filter'] )    ? absint( $_POST['job_filter'] ) : 0;

        if ( ! in_array( $status_filter, self::actionable_statuses(), true ) ) {
            $status_filter = '';
        }

        $result      = self::get_applications( $user_id, $page, $search, $sort, $status_filter, $job_filter );
        $rows        = $result['rows'];
        $total_found = $result['total'];

        ob_start();
        if ( ! empty( $rows ) ) {
            echo '<div class="workeera-empapps-list">';
            foreach ( $rows as $row ) {
                self::render_app_item( $row );
            }
            echo '</div>';

            $info_label = sprintf(
                /* translators: 1: first item on page, 2: last item on page, 3: total items */
                esc_html_x( 'Showing %1$s–%2$s of %3$s applications', 'employer applications pagination', 'workeera-remote-tech-job-board' ),
                number_format_i18n( ( $page - 1 ) * self::$per_page + 1 ),
                number_format_i18n( min( $page * self::$per_page, $total_found ) ),
                number_format_i18n( $total_found )
            );

            $pagination_html = Workeera_Dashboard_Common::render_pagination( $page, $total_found, self::$per_page, $info_label );
        } else {
            echo '<div class="workeera-empapps-list"></div>';
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
    // AJAX — single status update
    // -------------------------------------------------------------------------

    /**
     * AJAX handler that updates a single application's status.
     */
    public function ajax_update_status() {
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_nonce' ) ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id        = get_current_user_id();
        $application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
        $new_status     = isset( $_POST['new_status'] )     ? sanitize_key( $_POST['new_status'] ) : '';

        if ( $application_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid application.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( ! in_array( $new_status, self::actionable_statuses(), true ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid status.', 'workeera-remote-tech-job-board' ) ] );
        }

        // ownership and capability check happens in update_app_status_for_owner(), which returns the number of rows updated (0 or 1 in this case).
        $updated = self::update_app_status_for_owner( [ $application_id ], $user_id, $new_status );

        if ( $updated < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to update this application.', 'workeera-remote-tech-job-board' ) ] );
        }

        /**
         * Fires after an employer changes an application's status.
         *
         * @param int    $application_id
         * @param int    $employer_user_id
         * @param string $new_status
         */
        do_action( 'workeera_after_application_status_change', $application_id, $user_id, $new_status );

        wp_send_json( [
            'error'        => '0',
            'msg'          => esc_html__( 'Application status updated.', 'workeera-remote-tech-job-board' ),
            'new_status'   => $new_status,
            'badge_class'  => self::status_badge_class( $new_status ),
            'badge_label'  => self::status_badge_label( $new_status ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — bulk status update
    // -------------------------------------------------------------------------

    /**
     * AJAX handler that bulk-updates statuses for multiple applications.
     */
    public function ajax_bulk_update_status() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id     = get_current_user_id();
        $raw_ids     = isset( $_POST['application_ids'] ) ? (array) $_POST['application_ids'] : [];
        $new_status  = isset( $_POST['new_status'] ) ? sanitize_key( $_POST['new_status'] ) : '';

        $app_ids = [];
        foreach ( $raw_ids as $rid ) {
            $id = absint( $rid );
            if ( $id > 0 ) {
                $app_ids[] = $id;
            }
        }
        $app_ids = array_unique( $app_ids );

        if ( empty( $app_ids ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications selected.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Bulk action only allows mutating to reviewed/shortlisted/rejected (not "pending").
        if ( ! in_array( $new_status, [ 'reviewed', 'shortlisted', 'rejected' ], true ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid bulk action.', 'workeera-remote-tech-job-board' ) ] );
        }

        // ownership and capability check happens in update_app_status_for_owner(), which returns the number of rows updated.
        $updated_count = self::update_app_status_for_owner( $app_ids, $user_id, $new_status );

        if ( $updated_count < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications were updated.', 'workeera-remote-tech-job-board' ) ] );
        }

        /**
         * Fires after an employer bulk-updates application statuses.
         *
         * @param int[]  $app_ids
         * @param int    $employer_user_id
         * @param string $new_status
         * @param int    $updated_count
         */
        do_action( 'workeera_after_applications_bulk_status_change', $app_ids, $user_id, $new_status, $updated_count );

        $msg = sprintf(
            /* translators: 1: count, 2: status label */
            esc_html_x( '%1$s applications marked as %2$s.', 'employer applications', 'workeera-remote-tech-job-board' ),
            number_format_i18n( $updated_count ),
            self::status_badge_label( $new_status )
        );

        wp_send_json( [
            'error'         => '0',
            'msg'           => $msg,
            'updated_count' => absint( $updated_count ),
            'new_status'    => $new_status,
            'badge_class'   => self::status_badge_class( $new_status ),
            'badge_label'   => self::status_badge_label( $new_status ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Select2 paginated job search (employer's own jobs).
    // -------------------------------------------------------------------------

    /**
     * AJAX handler powering the Select2 job filter for the employer's own jobs.
     */
    public function ajax_jobs_search() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'results' => [], 'pagination' => [ 'more' => false ] ] );
        }

        $user_id = get_current_user_id();

        $page    = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $search  = isset( $_POST['q'] )    ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        $args = [
            'post_type'      => 'workeera_job',
            'post_status'    => [ 'publish', 'pending', 'draft', 'trash' ],
            'author'         => absint( $user_id ),
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            's'              => $search,
        ];

        $query = new WP_Query( $args );

        $results = [];
        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $job_id ) {
                $results[] = [
                    'id'   => absint( $job_id ),
                    'text' => esc_html( get_the_title( $job_id ) ),
                ];
            }
        }

        $total = absint( $query->found_posts );

        wp_send_json( [
            'results'    => $results,
            'pagination' => [ 'more' => ( $offset + $per_page ) < $total ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Count all jobs authored by a user (across statuses).
     */
    private static function count_user_jobs( $user_id ) {
        $args = [
            'post_type'      => 'workeera_job',
            'post_status'    => [ 'publish', 'pending', 'draft' ],
            'author'         => absint( $user_id ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];
        $q = new WP_Query( $args );
        return absint( $q->found_posts );
    }

    /**
     * Fetch a flat [ job_id => title ] list for the employer's jobs (preload mode).
     */
    private static function get_user_jobs_for_select( $user_id, $limit = 50 ) {
        $args = [
            'post_type'      => 'workeera_job',
            'post_status'    => [ 'publish', 'pending', 'draft' ],
            'author'         => absint( $user_id ),
            'posts_per_page' => absint( $limit ),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ];

        $q   = new WP_Query( $args );
        $out = [];
        if ( ! empty( $q->posts ) ) {
            foreach ( $q->posts as $job_id ) {
                $out[ absint( $job_id ) ] = get_the_title( $job_id );
            }
        }
        return $out;
    }

    /**
     * Fetch paginated, filtered, sorted applications for a given employer.
     *
     * @return array { rows: array<array>, total: int }
     */
    private static function get_applications( $user_id, $page, $search, $sort, $status_filter, $job_filter ) {
        global $wpdb;

        $per_page = self::$per_page;
        $offset   = ( $page - 1 ) * $per_page;

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $where_parts  = [];
        $placeholders = [];

        $where_parts[]  = 'p.post_author = %d';
        $placeholders[] = absint( $user_id );

        $where_parts[]  = 'p.post_type = %s';
        $placeholders[] = 'workeera_job';

        if ( ! empty( $search ) ) {
            $like_val       = '%' . $wpdb->esc_like( $search ) . '%';
            $where_parts[]  = '(a.applicant_name LIKE %s OR a.applicant_email LIKE %s OR p.post_title LIKE %s)';
            $placeholders[] = $like_val;
            $placeholders[] = $like_val;
            $placeholders[] = $like_val;
        }

        if ( ! empty( $status_filter ) ) {
            $where_parts[]  = 'a.status = %s';
            $placeholders[] = $status_filter;
        }

        if ( $job_filter > 0 ) {
            $where_parts[]  = 'a.job_id = %d';
            $placeholders[] = $job_filter;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_parts );
        $order_sql = ( 'oldest' === $sort ) ? 'ORDER BY a.applied_date ASC' : 'ORDER BY a.applied_date DESC';

        /**
         * Addon extension points for the employer applications list query.
         *
         * `workeera_employer_apps_list_join` may return an extra JOIN clause
         * (e.g. a scores table); `workeera_employer_apps_list_orderby` may
         * replace the ORDER BY clause. Both must return developer-built SQL
         * fragments only — never user input.
         *
         * @param string $sql  Current fragment.
         * @param string $sort Requested sort key ('newest' | 'oldest' | '' for the default/no explicit sort).
         */
        $join_sql  = apply_filters( 'workeera_employer_apps_list_join', '', $sort );
        $order_sql = apply_filters( 'workeera_employer_apps_list_orderby', $order_sql, $sort );

        $count_sql = "SELECT COUNT(*) FROM {$apps_table} AS a INNER JOIN {$posts_table} AS p ON p.ID = a.job_id {$join_sql} {$where_sql}";
        $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $placeholders ) );

        if ( $total < 1 ) {
            return [ 'rows' => [], 'total' => 0 ];
        }

        $data_placeholders   = $placeholders;
        $data_placeholders[] = $per_page;
        $data_placeholders[] = $offset;

        $data_sql = "SELECT a.application_id, a.job_id, a.user_id, a.applicant_name, a.applicant_email, a.applicant_phone, a.cv_data, a.cover_letter,
                            a.status, a.is_withdrawn, a.applied_date,
                            p.post_title, p.post_status
                     FROM {$apps_table} AS a
                     INNER JOIN {$posts_table} AS p ON p.ID = a.job_id
                     {$join_sql} {$where_sql} {$order_sql}
                     LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_placeholders ), ARRAY_A );

        return [ 'rows' => is_array( $rows ) ? $rows : [], 'total' => $total ];
    }

    /**
     * Update application status for the rows that belong to jobs owned by the
     * given employer. Returns the number of rows actually updated.
     *
     * @param int[]  $app_ids
     * @param int    $user_id
     * @param string $new_status
     * @return int
     */
    private static function update_app_status_for_owner( $app_ids, $user_id, $new_status ) {
        global $wpdb;

        $app_ids = array_map( 'absint', (array) $app_ids );
        $app_ids = array_filter( $app_ids );
        if ( empty( $app_ids ) ) {
            return 0;
        }

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $placeholders = implode( ',', array_fill( 0, count( $app_ids ), '%d' ) );

        // First, restrict to applications whose job is owned by this user.
        $owned_sql = "SELECT a.application_id
                      FROM {$apps_table} AS a
                      INNER JOIN {$posts_table} AS p ON p.ID = a.job_id
                      WHERE p.post_author = %d AND p.post_type = %s AND a.application_id IN ({$placeholders})";

        $owned_args = array_merge( [ absint( $user_id ), 'workeera_job' ], $app_ids );
        $owned_ids  = $wpdb->get_col( $wpdb->prepare( $owned_sql, $owned_args ) );

        if ( empty( $owned_ids ) ) {
            return 0;
        }

        $owned_ids        = array_map( 'absint', $owned_ids );
        $owned_placeholders = implode( ',', array_fill( 0, count( $owned_ids ), '%d' ) );

        $update_sql  = "UPDATE {$apps_table} SET status = %s WHERE application_id IN ({$owned_placeholders})";
        $update_args = array_merge( [ $new_status ], $owned_ids );

        $result = $wpdb->query( $wpdb->prepare( $update_sql, $update_args ) );

        return ( false === $result ) ? 0 : (int) $result;
    }

    // -------------------------------------------------------------------------
    // Item renderer
    // -------------------------------------------------------------------------

    /**
     * Render a single application row in the employer listing.
     *
     * @param array $row Application row joined with the post and applicant details.
     */
    private static function render_app_item( $row ) {
        global $workeera_plugin_settings;

        $application_id = isset( $row['application_id'] ) ? absint( $row['application_id'] ) : 0;
        $job_id         = isset( $row['job_id'] ) ? absint( $row['job_id'] ) : 0;
        $applicant_uid  = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
        $applicant_name = isset( $row['applicant_name'] ) ? $row['applicant_name'] : '';
        $applicant_email = isset( $row['applicant_email'] ) ? $row['applicant_email'] : '';
        $cv_data        = isset( $row['cv_data'] ) ? maybe_unserialize( $row['cv_data'] ) : null;
        $status         = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'pending';
        $applied_date   = isset( $row['applied_date'] ) ? sanitize_text_field( wp_unslash( $row['applied_date'] ) ) : '';
        $post_title     = isset( $row['post_title'] ) ? $row['post_title'] : '';

        // Candidate profile-driven fields.
        $candidate_id    = $applicant_uid > 0 ? workeera_find_candidate_post_attached( $applicant_uid ) : false;
        $applicant_jtitle = '';
        $age              = 0;
        $experience_label = '';
        if ( $candidate_id ) {
            $applicant_jtitle = workeera_get_object_meta( $candidate_id, 'job_title' );
            $dob              = workeera_get_object_meta( $candidate_id, 'date_of_birth' );
            $age              = workeera_calculate_age_from_dob( $dob );
            $exp_labels       = Workeera_Object_Terms_Relation::get_object_term_labels( $candidate_id, 'experiences' );
            if ( ! empty( $exp_labels ) ) {
                $experience_label = reset( $exp_labels );
            }
        }

        // Job-side info for the bottom bar.
        $job_fields  = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $deadline    = isset( $job_fields['application_deadline'] ) ? sanitize_text_field( wp_unslash( $job_fields['application_deadline'] ) ) : '';
        $expiry      = isset( $job_fields['expiry_date'] ) ? sanitize_text_field( wp_unslash( $job_fields['expiry_date'] ) ) : '';

        $deadline_label = '';
        if ( ! empty( $deadline ) ) {
            $deadline_ts = strtotime( $deadline );
            if ( $deadline_ts ) {
                /* translators: %s: formatted date */
                $deadline_label = sprintf( esc_html_x( 'Deadline: %s', 'employer applications', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), $deadline_ts ) ) );
            }
        }

        $expiry_label = '';
        if ( ! empty( $expiry ) ) {
            $expiry_ts = strtotime( $expiry );
            if ( $expiry_ts ) {
                if ( $expiry_ts < current_time( 'timestamp' ) ) {
                    /* translators: %s: formatted date */
                    $expiry_label = sprintf( esc_html_x( 'Expired: %s', 'employer applications', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), $expiry_ts ) ) );
                } else {
                    /* translators: %s: formatted date */
                    $expiry_label = sprintf( esc_html_x( 'Expires: %s', 'employer applications', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), $expiry_ts ) ) );
                }
            }
        }

        // Edit job URL within the dashboard.
        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : '';
        $edit_job_url       = $dashboard_page_url ? add_query_arg( [ 'section-type' => 'job', 'job_id' => $job_id ], $dashboard_page_url ) : '';

        // CV download — routed through the Workeera_Job_Applications handler so the
        // file is only served to admins, the applicant, or the job's employer.
        $cv_url           = '';
        $cv_name          = '';
        $cv_file_exists   = false;
        if ( is_array( $cv_data ) && ! empty( $cv_data['file'] ) && file_exists( $cv_data['file'] ) ) {
            $cv_url         = Workeera_Job_Applications::get_download_cv_url( $application_id );
            $cv_name        = isset( $cv_data['name'] ) ? $cv_data['name'] : '';
            $cv_file_exists = true;
        }

        $applied_label = '';
        if ( ! empty( $applied_date ) ) {
            $applied_ts = strtotime( $applied_date );
            if ( $applied_ts ) {
                /* translators: %s: formatted date */
                $applied_label = sprintf( esc_html_x( 'Applied: %s', 'employer applications', 'workeera-remote-tech-job-board' ), esc_html( date_i18n( get_option( 'date_format' ), $applied_ts ) ) );
            }
        }

        $badge_cls = self::status_badge_class( $status );
        $badge_lbl = self::status_badge_label( $status );
        ?>
        <div class="workeera-empapps-item" data-application-id="<?php echo absint( $application_id ); ?>" data-current-status="<?php echo esc_attr( $status ); ?>">
            <div class="workeera-empapps-item-main">
                <label class="workeera-empapps-checkbox-wrap" title="<?php echo esc_attr_x( 'Select application', 'employer applications', 'workeera-remote-tech-job-board' ); ?>">
                    <input type="checkbox" class="workeera-empapps-item-chk" data-application-id="<?php echo absint( $application_id ); ?>" />
                </label>

                <div class="workeera-empapps-applicant-pic-wrap">
                    <?php
                    // Profile picture (background-image div, no <img>).
                    workeera_user_profile_pic_html( $applicant_uid, 'workeera-empapps-applicant-pic' );
                    ?>
                </div>

                <div class="workeera-empapps-applicant-info">
                    <strong class="workeera-empapps-applicant-name"><?php echo esc_html( $applicant_name ); ?></strong>
                    <?php
                    /**
                     * Render extra applicant meta right after the applicant name
                     * on the employer applications list (e.g. addon match scores).
                     *
                     * @param array $row Application row (application_id, user_id, …).
                     */
                    do_action( 'workeera_employer_app_item_after_name', $row );
                    ?>
                    <?php if ( ! empty( $applicant_jtitle ) ) : ?>
                        <div class="workeera-empapps-applicant-jtitle"><?php echo esc_html( $applicant_jtitle ); ?></div>
                    <?php endif; ?>
                    <div class="workeera-empapps-applicant-meta">
                        <?php if ( ! empty( $applicant_email ) ) : ?>
                            <span class="workeera-empapps-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <a href="mailto:<?php echo esc_attr( $applicant_email ); ?>"><?php echo esc_html( $applicant_email ); ?></a>
                            </span>
                        <?php endif; ?>
                        <?php
                        // Applicant phone — shown only while the phone field is enabled
                        // in the Job Applications settings.
                        $applicant_phone = isset( $row['applicant_phone'] ) ? $row['applicant_phone'] : '';
                        if ( ! empty( $applicant_phone ) && 'off' !== Workeera_Job_Applications::apply_field_mode( 'phone_number_field' ) ) :
                            ?>
                            <span class="workeera-empapps-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <a href="tel:<?php echo esc_attr( $applicant_phone ); ?>"><?php echo esc_html( $applicant_phone ); ?></a>
                            </span>
                        <?php endif; ?>
                        <?php if ( $age > 0 ) : ?>
                            <span class="workeera-empapps-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php
                                /* translators: %s: age in years */
                                echo esc_html( sprintf( _nx( '%s year', '%s years', $age, 'employer applications', 'workeera-remote-tech-job-board' ), number_format_i18n( $age ) ) );
                                ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( ! empty( $experience_label ) ) : ?>
                            <span class="workeera-empapps-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-3V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v2H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM9 5h6v2H9V5z"/></svg>
                                <?php echo esc_html( $experience_label ); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( ! empty( $applied_label ) ) : ?>
                            <span class="workeera-empapps-meta-item">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo esc_html( $applied_label ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="workeera-empapps-status-action">
                    <?php if ( ! empty( $row['is_withdrawn'] ) ) : // Candidate withdrew this application — tag it before the status dropdown. ?>
                        <span class="workeera-empapps-withdrawn-tag"><?php echo esc_html_x( 'Withdrawn', 'employer applications', 'workeera-remote-tech-job-board' ); ?></span>
                    <?php endif; ?>
                    <button type="button" class="workeera-empapps-status-trigger" data-application-id="<?php echo absint( $application_id ); ?>" data-current-status="<?php echo esc_attr( $status ); ?>">
                        <span class="workeera-empapps-status-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge_lbl ); ?></span>
                        <svg class="workeera-empapps-status-chevron" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>
                    </button>
                    <div class="workeera-empapps-status-menu workeera-hide">
                        <?php foreach ( self::actionable_statuses() as $opt_status ) : ?>
                            <button type="button"
                                    class="workeera-empapps-status-menu-item<?php echo esc_attr( $opt_status === $status ? ' workeera-empapps-status-menu-active' : '' ); ?>"
                                    data-action-status="<?php echo esc_attr( $opt_status ); ?>">
                                <span class="workeera-empapps-status-dot <?php echo esc_attr( self::status_badge_class( $opt_status ) ); ?>"></span>
                                <?php echo esc_html( self::status_badge_label( $opt_status ) ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="workeera-empapps-actions">
                    <?php
                    $has_cover_letter = '' !== trim( (string) ( isset( $row['cover_letter'] ) ? $row['cover_letter'] : '' ) );
                    Workeera_Job_Applications::render_app_actions_dropdown(
                        $application_id,
                        [
                            'cv_url'           => $cv_url,
                            'cv_name'          => $cv_name,
                            'cv_exists'        => $cv_file_exists,
                            'has_cover_letter' => $has_cover_letter,
                            'context'          => 'front',
                            'row'              => $row,
                        ]
                    );
                    ?>
                </div>
            </div>

            <?php if ( ! empty( $post_title ) || $deadline_label || $expiry_label ) : ?>
                <div class="workeera-empapps-jobinfo-bar">
                    <?php if ( ! empty( $post_title ) ) : ?>
                        <?php if ( $edit_job_url ) : ?>
                            <a href="<?php echo esc_url( $edit_job_url ); ?>" class="workeera-empapps-jobinfo-title">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" /></svg>
                                <?php echo esc_html( $post_title ); ?>
                            </a>
                        <?php else : ?>
                            <span class="workeera-empapps-jobinfo-title">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" /></svg>
                                <?php echo esc_html( $post_title ); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( $deadline_label ) : ?>
                        <span class="workeera-empapps-jobinfo-meta">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php echo esc_html( $deadline_label ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $expiry_label ) : ?>
                        <span class="workeera-empapps-jobinfo-meta">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.96"/></svg>
                            <?php echo esc_html( $expiry_label ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
    public static function status_badge_class( $status ) {
        $map = [
            'pending'     => 'workeera-empapps-status-pending',
            'reviewed'    => 'workeera-empapps-status-reviewed',
            'shortlisted' => 'workeera-empapps-status-shortlisted',
            'rejected'    => 'workeera-empapps-status-rejected',
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : 'workeera-empapps-status-pending';
    }

    /**
     * Return the translatable label for the given application status.
     *
     * @param  string $status Application status key.
     * @return string         Human-readable label.
     */
    public static function status_badge_label( $status ) {
        $map = [
            'pending'     => esc_html_x( 'Pending',     'application status', 'workeera-remote-tech-job-board' ),
            'reviewed'    => esc_html_x( 'Reviewed',    'application status', 'workeera-remote-tech-job-board' ),
            'shortlisted' => esc_html_x( 'Shortlisted', 'application status', 'workeera-remote-tech-job-board' ),
            'rejected'    => esc_html_x( 'Rejected',    'application status', 'workeera-remote-tech-job-board' ),
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : esc_html_x( 'Pending', 'application status', 'workeera-remote-tech-job-board' );
    }
}

new Workeera_Employer_Job_Applications;
