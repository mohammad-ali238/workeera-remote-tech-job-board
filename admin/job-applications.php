<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * WP-admin Job Applications page — submenu under the main Workeera menu.
 */
class Workeera_Admin_Job_Applications {

    /**
     * Hook suffix for the submenu page (set by add_submenu_page() at runtime).
     */
    private $hook_suffix = '';

    /**
     * Register submenu, asset enqueue and AJAX hooks.
     */
    public function __construct() {
        // Register submenu after the Settings submenu (which is registered at priority 10).
        add_action( 'admin_menu', [ $this, 'register_submenu' ], 11 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // AJAX endpoints — admin-side only.
        add_action( 'wp_ajax_workeera_admin_apps_status',           [ $this, 'ajax_update_status' ] );
        add_action( 'wp_ajax_workeera_admin_apps_bulk_status',      [ $this, 'ajax_bulk_update_status' ] );
        add_action( 'wp_ajax_workeera_admin_apps_bulk_delete',      [ $this, 'ajax_bulk_delete' ] );
        add_action( 'wp_ajax_workeera_admin_apps_jobs_search',      [ $this, 'ajax_jobs_search' ] );
        add_action( 'wp_ajax_workeera_admin_apps_employers_search', [ $this, 'ajax_employers_search' ] );
    }

    /**
     * Register the Job Applications submenu under the Workeera menu.
     */
    public function register_submenu() {
        $this->hook_suffix = add_submenu_page(
            'workeera-dashboard',
            esc_html_x( 'Job Applications', 'admin page title', 'workeera-remote-tech-job-board' ),
            esc_html_x( 'Job Applications', 'admin menu title', 'workeera-remote-tech-job-board' ),
            'manage_options',
            'workeera-applications',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue scripts on the Job Applications admin screen.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( empty( $this->hook_suffix ) || $hook !== $this->hook_suffix ) {
            return;
        }

        // Select2 + admin styles are already enqueued globally for admin via Workeera_Plugin::admin_enqueues().
        wp_enqueue_script(
            'workeera-job-applications',
            Workeera_Plugin::root_url() . 'js/job-applications.js',
            [ 'jquery', 'select2', 'workeera-scripts', 'workeera-admin-scripts' ],
            WORKEERA_VERSION,
            true
        );

        wp_localize_script(
            'workeera-job-applications',
            'workeera_empapps_str',
            Workeera_Employer_Job_Applications::localize_strings()
        );
    }

    // -------------------------------------------------------------------------
    // Page render
    // -------------------------------------------------------------------------

    /**
     * Render the admin Job Applications listing page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'workeera-remote-tech-job-board' ) );
        }

        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $list_table = new Workeera_Admin_Apps_List_Table();
        $list_table->prepare_items();

        $admin_nonce = wp_create_nonce( 'workeera_admin_nonce' );
        ?>
        <div class="wrap workeera-empapps-wrap workeera-admin-empapps-wrap"
             data-context="admin"
             data-nonce="<?php echo esc_attr( $admin_nonce ); ?>"
             data-job-select-mode="ajax"
             data-ajax-status="workeera_admin_apps_status"
             data-ajax-bulk-status="workeera_admin_apps_bulk_status"
             data-ajax-bulk-delete="workeera_admin_apps_bulk_delete"
             data-ajax-jobs-search="workeera_admin_apps_jobs_search"
             data-ajax-employers-search="workeera_admin_apps_employers_search">

            <h1 class="wp-heading-inline"><?php echo esc_html_x( 'Job Applications', 'admin page title', 'workeera-remote-tech-job-board' ); ?></h1>
            <hr class="wp-header-end">

            <form id="workeera-admin-empapps-form" class="workeera-page" method="get">
                <input type="hidden" name="page" value="workeera-applications" />
                <?php
                $list_table->search_box( esc_html_x( 'Search Applications', 'admin', 'workeera-remote-tech-job-board' ), 'workeera-empapps-search' );
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — single status update
    // -------------------------------------------------------------------------

    /**
     * AJAX: update the status of a single application.
     */
    public function ajax_update_status() {
        
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        $application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
        $new_status     = isset( $_POST['new_status'] )     ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';

        if ( $application_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid application.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( ! in_array( $new_status, Workeera_Employer_Job_Applications::actionable_statuses(), true ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid status.', 'workeera-remote-tech-job-board' ) ] );
        }

        $updated = self::update_app_status_admin( [ $application_id ], $new_status );

        if ( $updated < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications were updated.', 'workeera-remote-tech-job-board' ) ] );
        }

        do_action( 'workeera_after_application_status_change', $application_id, get_current_user_id(), $new_status );

        wp_send_json( [
            'error'        => '0',
            'msg'          => esc_html__( 'Application status updated.', 'workeera-remote-tech-job-board' ),
            'new_status'   => $new_status,
            'badge_class'  => Workeera_Employer_Job_Applications::status_badge_class( $new_status ),
            'badge_label'  => Workeera_Employer_Job_Applications::status_badge_label( $new_status ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — bulk status update
    // -------------------------------------------------------------------------

    /**
     * AJAX: bulk-update statuses for multiple applications.
     */
    public function ajax_bulk_update_status() {
        
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        // application_ids contains a list of integer IDs — absint each one below; no need to unslash ints.
        $raw_ids    = isset( $_POST['application_ids'] ) ? (array) wp_unslash( $_POST['application_ids'] ) : [];
        $new_status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';

        $app_ids = array_unique( array_filter( array_map( 'absint', $raw_ids ) ) );

        if ( empty( $app_ids ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications selected.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( ! in_array( $new_status, [ 'reviewed', 'shortlisted', 'rejected' ], true ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid bulk action.', 'workeera-remote-tech-job-board' ) ] );
        }

        $updated_count = self::update_app_status_admin( $app_ids, $new_status );

        if ( $updated_count < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications were updated.', 'workeera-remote-tech-job-board' ) ] );
        }

        do_action( 'workeera_after_applications_bulk_status_change', $app_ids, get_current_user_id(), $new_status, $updated_count );

        $msg = sprintf(
            /* translators: 1: count, 2: status label */
            esc_html_x( '%1$s applications marked as %2$s.', 'employer applications', 'workeera-remote-tech-job-board' ),
            number_format_i18n( $updated_count ),
            Workeera_Employer_Job_Applications::status_badge_label( $new_status )
        );

        wp_send_json( [
            'error'         => '0',
            'msg'           => $msg,
            'updated_count' => absint( $updated_count ),
            'new_status'    => $new_status,
            'badge_class'   => Workeera_Employer_Job_Applications::status_badge_class( $new_status ),
            'badge_label'   => Workeera_Employer_Job_Applications::status_badge_label( $new_status ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — bulk delete (admin only)
    // -------------------------------------------------------------------------

    /**
     * AJAX: bulk-delete the given application IDs.
     */
    public function ajax_bulk_delete() {
        
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $wpdb;

        // application_ids contains a list of integer IDs — absint each one below.
        $raw_ids = isset( $_POST['application_ids'] ) ? (array) wp_unslash( $_POST['application_ids'] ) : [];
        $app_ids = array_unique( array_filter( array_map( 'absint', $raw_ids ) ) );

        if ( empty( $app_ids ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications selected.', 'workeera-remote-tech-job-board' ) ] );
        }

        $apps_table   = $wpdb->prefix . 'workeera_applications';
        $placeholders = implode( ',', array_fill( 0, count( $app_ids ), '%d' ) );

        $deleted = $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$apps_table} WHERE application_id IN ({$placeholders})", $app_ids )
        );

        if ( false === $deleted || $deleted < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No applications were deleted.', 'workeera-remote-tech-job-board' ) ] );
        }

        /**
         * Fires after an admin bulk-deletes applications.
         *
         * @param int[] $app_ids
         * @param int   $deleted_count
         */
        do_action( 'workeera_admin_after_applications_bulk_delete', $app_ids, (int) $deleted );

        $msg = sprintf(
            /* translators: %s: count of deleted applications */
            esc_html_x( '%s applications deleted.', 'admin applications', 'workeera-remote-tech-job-board' ),
            number_format_i18n( (int) $deleted )
        );

        wp_send_json( [
            'error'         => '0',
            'msg'           => $msg,
            'deleted_count' => absint( $deleted ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Select2 paginated job search (across ALL jobs that have at least
    // one application).
    // -------------------------------------------------------------------------

    /**
     * AJAX: paginated Select2 job search across jobs that have applications.
     */
    public function ajax_jobs_search() {
        
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $wpdb;

        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $search   = isset( $_POST['q'] )    ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        $args       = [ 'workeera_job' ];
        if ( ! empty( $search ) ) {
            $args[]  = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Count distinct jobs that have applications.
        $count_sql = "SELECT COUNT(DISTINCT p.ID)
                      FROM {$wpdb->prefix}posts AS p
                      INNER JOIN {$wpdb->prefix}workeera_applications AS a ON a.job_id = p.ID
                      WHERE p.post_type = %s";
        if ( ! empty( $search ) ) {
            $count_sql .= " AND p.post_title LIKE %s";
        }
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );

        $args[] = $per_page;
        $args[] = $offset;

        $data_sql = "SELECT DISTINCT p.ID, p.post_title
                     FROM {$wpdb->prefix}posts AS p
                     INNER JOIN {$wpdb->prefix}workeera_applications AS a ON a.job_id = p.ID
                     WHERE p.post_type = %s";
        if ( ! empty( $search ) ) {
            $data_sql .= " AND p.post_title LIKE %s";
        }
        $data_sql .= " ORDER BY p.post_date DESC
                     LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $args ) );

        $results = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $results[] = [
                    'id'   => absint( $row->ID ),
                    'text' => esc_html( $row->post_title ),
                ];
            }
        }

        wp_send_json( [
            'results'    => $results,
            'pagination' => [ 'more' => ( $offset + $per_page ) < $total ],
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Select2 paginated employer search.
    // Only users who have at least one job that has at least one application.
    // -------------------------------------------------------------------------

    /**
     * AJAX: paginated Select2 employer search restricted to users with applied-to jobs.
     */
    public function ajax_employers_search() {
        
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $wpdb;

        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $search   = isset( $_POST['q'] )    ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        $args  = [ 'workeera_job' ];
        if ( ! empty( $search ) ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $count_sql = "SELECT COUNT(DISTINCT u.ID)
                      FROM {$wpdb->prefix}users AS u
                      INNER JOIN {$wpdb->prefix}posts AS p ON p.post_author = u.ID
                      INNER JOIN {$wpdb->prefix}workeera_applications AS a ON a.job_id = p.ID
                      WHERE p.post_type = %s";
        if ( ! empty( $search ) ) {
            $count_sql .= " AND (u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)";
        }
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );

        $args[] = $per_page;
        $args[] = $offset;

        $data_sql = "SELECT DISTINCT u.ID, u.display_name, u.user_login
                     FROM {$wpdb->prefix}users AS u
                     INNER JOIN {$wpdb->prefix}posts AS p ON p.post_author = u.ID
                     INNER JOIN {$wpdb->prefix}workeera_applications AS a ON a.job_id = p.ID
                     WHERE p.post_type = %s";
        if ( ! empty( $search ) ) {
            $data_sql .= " AND (u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)";
        }
        $data_sql .= " ORDER BY u.display_name ASC
                     LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $args ) );

        $results = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $label = ! empty( $row->display_name ) ? $row->display_name : $row->user_login;
                /* translators: 1: user display name, 2: user ID */
                $text  = sprintf( esc_html_x( '%1$s (id: %2$d)', 'employer dropdown label', 'workeera-remote-tech-job-board' ), $label, absint( $row->ID ) );
                $results[] = [
                    'id'   => absint( $row->ID ),
                    'text' => esc_html( $text ),
                ];
            }
        }

        wp_send_json( [
            'results'    => $results,
            'pagination' => [ 'more' => ( $offset + $per_page ) < $total ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Update helper used by single + bulk status AJAX (admin-scoped).
    // -------------------------------------------------------------------------

    /**
     * Update the status of the given applications (admin-scoped, no ownership check).
     *
     * @param  int[]  $app_ids    Application IDs to update.
     * @param  string $new_status New status value.
     * @return int                Number of rows updated.
     */
    private static function update_app_status_admin( $app_ids, $new_status ) {
        global $wpdb;

        $app_ids = array_filter( array_map( 'absint', (array) $app_ids ) );
        if ( empty( $app_ids ) ) {
            return 0;
        }

        $apps_table   = $wpdb->prefix . 'workeera_applications';
        $placeholders = implode( ',', array_fill( 0, count( $app_ids ), '%d' ) );

        $sql  = "UPDATE {$apps_table} SET status = %s WHERE application_id IN ({$placeholders})";
        $args = array_merge( [ $new_status ], $app_ids );

        $result = $wpdb->query( $wpdb->prepare( $sql, $args ) );
        return ( false === $result ) ? 0 : (int) $result;
    }
}

// -----------------------------------------------------------------------------
// WP_List_Table subclass — chrome (filters, pagination, sorting, bulk dropdown).
// Rich card markup is rendered inside a single column via single_row().
// -----------------------------------------------------------------------------

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table subclass that renders the Job Applications admin list.
 */
class Workeera_Admin_Apps_List_Table extends WP_List_Table {

    private static $per_page = 20;

    /**
     * Configure singular/plural labels and disable native AJAX.
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'workeera-application',
            'plural'   => 'workeera-applications',
            'ajax'     => false,
        ] );
    }

    /**
     * Column definitions for the applications list.
     *
     * @return array<string, string> Column slug to header label.
     */
    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'applicant' => esc_html_x( 'Applicant', 'admin applications column', 'workeera-remote-tech-job-board' ),
            'job'       => esc_html_x( 'Job',       'admin applications column', 'workeera-remote-tech-job-board' ),
            'status'    => esc_html_x( 'Status',    'admin applications column', 'workeera-remote-tech-job-board' ),
            'date'      => esc_html_x( 'Applied',   'admin applications column', 'workeera-remote-tech-job-board' ),
        ];
    }

    /**
     * Sortable column configuration.
     *
     * @return array<string, array{0:string,1:bool}> Column slug to [orderby key, default-desc flag].
     */
    public function get_sortable_columns() {
        return [
            'job'    => [ 'job_title',    false ],
            'status' => [ 'status',       false ],
            'date'   => [ 'applied_date', true  ], // default sort: applied_date DESC
        ];
    }

    /**
     * Bulk-action options for the list table.
     *
     * @return array<string, string> Action key to label.
     */
    public function get_bulk_actions() {
        return [
            'reviewed'    => esc_html_x( 'Mark as Reviewed',    'admin applications bulk', 'workeera-remote-tech-job-board' ),
            'shortlisted' => esc_html_x( 'Mark as Shortlisted', 'admin applications bulk', 'workeera-remote-tech-job-board' ),
            'rejected'    => esc_html_x( 'Mark as Rejected',    'admin applications bulk', 'workeera-remote-tech-job-board' ),
            'delete'      => esc_html_x( 'Delete',              'admin applications bulk', 'workeera-remote-tech-job-board' ),
        ];
    }

    /**
     * Render the bulk-actions select with markup tailored for the shared JS handler.
     *
     * @param string $which Either "top" or "bottom" tablenav.
     */
    public function bulk_actions( $which = '' ) {
        if ( is_null( $this->_actions ) ) {
            $this->_actions = $this->get_bulk_actions();
        }

        if ( empty( $this->_actions ) ) {
            return;
        }

        $two = ( 'top' === $which ) ? '1' : '2';

        echo '<div class="alignleft actions bulkactions">';
        echo '<label for="workeera-empapps-bulk-' . esc_attr( $two ) . '" class="screen-reader-text">' . esc_html_x( 'Select bulk action', 'admin', 'workeera-remote-tech-job-board' ) . '</label>';
        echo '<select name="workeera_admin_bulk_action_' . esc_attr( $two ) . '" id="workeera-empapps-bulk-' . esc_attr( $two ) . '" class="workeera-empapps-bulk-action-select">';
        echo '<option value="">' . esc_html_x( 'Bulk actions', 'admin', 'workeera-remote-tech-job-board' ) . '</option>';
        foreach ( $this->_actions as $name => $title ) {
            $class = 'delete' === $name ? ' class="workeera-empapps-bulk-delete-opt"' : '';
            echo '<option value="' . esc_attr( $name ) . '"' . $class . '>' . esc_html( $title ) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button action workeera-empapps-bulk-apply-btn">' . esc_html_x( 'Apply', 'admin', 'workeera-remote-tech-job-board' ) . '</button>';
        echo '</div>';
    }

    /**
     * Render the status, job and employer filter dropdowns above the table.
     *
     * @param string $which Either "top" or "bottom" tablenav.
     */
    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        $current_status   = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : '';
        $current_job      = isset( $_GET['job_id'] )        ? absint( $_GET['job_id'] )        : 0;
        $current_employer = isset( $_GET['employer_id'] )   ? absint( $_GET['employer_id'] )   : 0;
        $current_app_sort = isset( $_GET['app_sort'] )      ? sanitize_key( wp_unslash( $_GET['app_sort'] ) ) : '';

        // Pre-fetch label for selected job / employer so the AJAX Select2 has
        // a labelled option to render before search loads.
        $job_label = '';
        if ( $current_job > 0 ) {
            $job_post = get_post( $current_job );
            if ( $job_post && $job_post->post_type === 'workeera_job' ) {
                $job_label = $job_post->post_title;
            } else {
                $current_job = 0;
            }
        }

        $employer_label = '';
        if ( $current_employer > 0 ) {
            $emp_user = get_userdata( $current_employer );
            if ( $emp_user ) {
                /* translators: 1: user display name, 2: user ID */
                $employer_label = sprintf( esc_html_x( '%1$s (id: %2$d)', 'employer dropdown label', 'workeera-remote-tech-job-board' ), $emp_user->display_name, $emp_user->ID );
            } else {
                $current_employer = 0;
            }
        }

        $statuses = Workeera_Employer_Job_Applications::actionable_statuses();
        ?>
        <div class="alignleft actions workeera-empapps-admin-filters">
            <label for="workeera-empapps-status-select" class="screen-reader-text"><?php echo esc_html_x( 'Filter by status', 'admin applications', 'workeera-remote-tech-job-board' ); ?></label>
            <select name="status_filter" id="workeera-empapps-status-select" class="workeera-empapps-status-select">
                <option value=""><?php echo esc_html_x( 'All Statuses', 'admin applications', 'workeera-remote-tech-job-board' ); ?></option>
                <?php foreach ( $statuses as $st ) : ?>
                    <option value="<?php echo esc_attr( $st ); ?>" <?php selected( $current_status, $st ); ?>>
                        <?php echo esc_html( Workeera_Employer_Job_Applications::status_badge_label( $st ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="workeera-empapps-job-select" class="screen-reader-text"><?php echo esc_html_x( 'Filter by job', 'admin applications', 'workeera-remote-tech-job-board' ); ?></label>
            <select name="job_id" id="workeera-empapps-job-select" class="workeera-empapps-job-select" data-placeholder="<?php echo esc_attr_x( 'All Jobs', 'admin applications', 'workeera-remote-tech-job-board' ); ?>">
                <option value=""></option>
                <?php if ( $current_job > 0 ) : ?>
                    <option value="<?php echo absint( $current_job ); ?>" selected><?php echo esc_html( $job_label ); ?></option>
                <?php endif; ?>
            </select>

            <label for="workeera-empapps-employer-select" class="screen-reader-text"><?php echo esc_html_x( 'Filter by employer', 'admin applications', 'workeera-remote-tech-job-board' ); ?></label>
            <select name="employer_id" id="workeera-empapps-employer-select" class="workeera-empapps-employer-select" data-placeholder="<?php echo esc_attr_x( 'All Employers', 'admin applications', 'workeera-remote-tech-job-board' ); ?>">
                <option value=""></option>
                <?php if ( $current_employer > 0 ) : ?>
                    <option value="<?php echo absint( $current_employer ); ?>" selected><?php echo esc_html( $employer_label ); ?></option>
                <?php endif; ?>
            </select>

            <label for="workeera-empapps-sort-select" class="screen-reader-text"><?php echo esc_html_x( 'Sort by', 'admin applications', 'workeera-remote-tech-job-board' ); ?></label>
            <select name="app_sort" id="workeera-empapps-sort-select" class="workeera-empapps-sort-select">
                <option value=""><?php echo esc_html_x( 'Sort by...', 'admin applications', 'workeera-remote-tech-job-board' ); ?></option>
                <option value="newest" <?php selected( $current_app_sort, 'newest' ); ?>><?php echo esc_html_x( 'Newest First', 'admin applications', 'workeera-remote-tech-job-board' ); ?></option>
                <option value="oldest" <?php selected( $current_app_sort, 'oldest' ); ?>><?php echo esc_html_x( 'Oldest First', 'admin applications', 'workeera-remote-tech-job-board' ); ?></option>
                <?php
                /**
                 * Emit extra <option> elements for the admin applications "Sort by"
                 * dropdown. The chosen value round-trips to the
                 * `workeera_admin_apps_list_orderby` filter as the sort key.
                 *
                 * @param string $current_app_sort Currently selected sort value.
                 */
                do_action( 'workeera_admin_apps_sort_options', $current_app_sort );
                ?>
            </select>

            <button type="submit" class="button"><?php echo esc_html_x( 'Filter', 'admin applications', 'workeera-remote-tech-job-board' ); ?></button>
        </div>
        <?php
    }

    /**
     * Print the empty-state message when no rows match.
     */
    public function no_items() {
        echo esc_html_x( 'No applications found.', 'admin applications', 'workeera-remote-tech-job-board' );
    }

    /**
     * Render the checkbox column for a row.
     *
     * @param  array  $item Row data.
     * @return string       Checkbox HTML.
     */
    public function column_cb( $item ) {
        return '<input type="checkbox" class="workeera-empapps-item-chk" data-application-id="' . absint( $item['application_id'] ) . '" />';
    }

    /**
     * Default column renderer (rows are emitted via single_row()).
     *
     * @param  array  $item        Row data.
     * @param  string $column_name Column slug.
     * @return string              Always empty string.
     */
    public function column_default( $item, $column_name ) {
        return '';
    }

    /**
     * Render one row as a card-style cell spanning the data columns.
     *
     * @param array $item Row data.
     */
    public function single_row( $item ) {
        $application_id = absint( $item['application_id'] );
        $status         = sanitize_key( $item['status'] );
        $columns_count  = count( $this->get_columns() );

        echo '<tr class="workeera-empapps-item" data-application-id="' . absint( $application_id ) . '" data-current-status="' . esc_attr( $status ) . '">';
        echo '<th class="check-column" scope="row">' . wp_kses(
            $this->column_cb( $item ),
            array( 'input' => array( 'type' => true, 'class' => true, 'data-application-id' => true ) )
        ) . '</th>';
        echo '<td class="workeera-admin-empapps-cardcell" colspan="' . absint( $columns_count - 1 ) . '">';
        self::render_card_content( $item );
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Render the rich card body for one application row.
     *
     * @param array $row Application row data.
     */
    public static function render_card_content( $row ) {
        $application_id  = absint( $row['application_id'] );
        $job_id          = absint( $row['job_id'] );
        $applicant_uid   = absint( $row['user_id'] );
        $applicant_name  = isset( $row['applicant_name'] ) ? $row['applicant_name'] : '';
        $applicant_email = isset( $row['applicant_email'] ) ? $row['applicant_email'] : '';
        $cv_data         = isset( $row['cv_data'] ) ? maybe_unserialize( $row['cv_data'] ) : null;
        $status          = sanitize_key( $row['status'] );
        $applied_date    = isset( $row['applied_date'] ) ? sanitize_text_field( wp_unslash( $row['applied_date'] ) ) : '';
        $post_title      = isset( $row['post_title'] ) ? $row['post_title'] : '';

        // Candidate-driven fields.
        $candidate_id     = $applicant_uid > 0 ? workeera_find_candidate_post_attached( $applicant_uid ) : false;
        $candidate_url    = '';
        $applicant_jtitle = '';
        $age              = 0;
        $experience_label = '';
        if ( $candidate_id ) {
            $candidate_post = get_post( $candidate_id );
            if ( $candidate_post && $candidate_post->post_type === 'workeera_candidate' && $candidate_post->post_status !== 'trash' ) {
                $candidate_url = get_edit_post_link( $candidate_id );
            }
            $applicant_jtitle = workeera_get_object_meta( $candidate_id, 'job_title' );
            $dob              = workeera_get_object_meta( $candidate_id, 'date_of_birth' );
            $age              = workeera_calculate_age_from_dob( $dob );
            $exp_labels       = Workeera_Object_Terms_Relation::get_object_term_labels( $candidate_id, 'experiences' );
            if ( ! empty( $exp_labels ) ) {
                $experience_label = reset( $exp_labels );
            }
        }

        // Job-side info.
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

        // Job-edit URL — admin context uses get_edit_post_link().
        $job_post     = get_post( $job_id );
        $edit_job_url = '';
        if ( $job_post && $job_post->post_type === 'workeera_job' && $job_post->post_status !== 'trash' ) {
            $edit_job_url = get_edit_post_link( $job_id );
        }

        // CV download URL (gated by Workeera_Job_Applications::download_cv_handle).
        $cv_url         = '';
        $cv_name        = '';
        $cv_file_exists = false;
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

        $badge_cls = Workeera_Employer_Job_Applications::status_badge_class( $status );
        $badge_lbl = Workeera_Employer_Job_Applications::status_badge_label( $status );
        ?>
        <div class="workeera-admin-empapps-itembody">
            <div class="workeera-admin-empapps-applicant-pic-wrap">
                <?php
                workeera_user_profile_pic_html( $applicant_uid, 'workeera-empapps-applicant-pic' );
                ?>
            </div>

            <div class="workeera-admin-empapps-applicant-info">
                <strong class="workeera-admin-empapps-applicant-name">
                    <?php if ( $candidate_url ) : ?>
                        <a href="<?php echo esc_url( $candidate_url ); ?>"><?php echo esc_html( $applicant_name ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $applicant_name ); ?>
                    <?php endif; ?>
                </strong>
                <?php
                /**
                 * Render extra applicant meta right after the applicant name
                 * on the admin applications list (e.g. addon match scores).
                 *
                 * @param array $row Application row (application_id, user_id, …).
                 */
                do_action( 'workeera_admin_app_item_after_name', $row );
                ?>
                <?php if ( ! empty( $applicant_jtitle ) ) : ?>
                    <div class="workeera-admin-empapps-applicant-jtitle"><?php echo esc_html( $applicant_jtitle ); ?></div>
                <?php endif; ?>
                <div class="workeera-admin-empapps-applicant-meta">
                    <?php if ( ! empty( $applicant_email ) ) : ?>
                        <span class="workeera-admin-empapps-meta-item">
                            <span class="dashicons dashicons-email"></span>
                            <a href="mailto:<?php echo esc_attr( $applicant_email ); ?>"><?php echo esc_html( $applicant_email ); ?></a>
                        </span>
                    <?php endif; ?>
                    <?php
                    // Applicant phone — shown only while the phone field is enabled
                    // in the Job Applications settings.
                    $applicant_phone = isset( $row['applicant_phone'] ) ? $row['applicant_phone'] : '';
                    if ( ! empty( $applicant_phone ) && 'off' !== Workeera_Job_Applications::apply_field_mode( 'phone_number_field' ) ) :
                        ?>
                        <span class="workeera-admin-empapps-meta-item">
                            <span class="dashicons dashicons-phone"></span>
                            <a href="tel:<?php echo esc_attr( $applicant_phone ); ?>"><?php echo esc_html( $applicant_phone ); ?></a>
                        </span>
                    <?php endif; ?>
                    <?php if ( $age > 0 ) : ?>
                        <span class="workeera-admin-empapps-meta-item">
                            <span class="dashicons dashicons-clock"></span>
                            <?php
                            /* translators: %s: age in years */
                            echo esc_html( sprintf( _nx( '%s year', '%s years', $age, 'employer applications', 'workeera-remote-tech-job-board' ), number_format_i18n( $age ) ) );
                            ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $experience_label ) ) : ?>
                        <span class="workeera-admin-empapps-meta-item">
                            <span class="dashicons dashicons-businessperson"></span>
                            <?php echo esc_html( $experience_label ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $applied_label ) ) : ?>
                        <span class="workeera-admin-empapps-meta-item">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html( $applied_label ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! empty( $row['is_withdrawn'] ) ) : // Candidate withdrew this application — tag it before the status dropdown. ?>
                <span class="workeera-admin-empapps-withdrawn-tag"><?php echo esc_html_x( 'Withdrawn', 'admin applications', 'workeera-remote-tech-job-board' ); ?></span>
            <?php endif; ?>

            <div class="workeera-empapps-status-action">
                <button type="button" class="workeera-empapps-status-trigger" data-application-id="<?php echo absint( $application_id ); ?>" data-current-status="<?php echo esc_attr( $status ); ?>">
                    <span class="workeera-empapps-status-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge_lbl ); ?></span>
                    <span class="dashicons dashicons-arrow-down-alt2 workeera-empapps-status-chevron"></span>
                </button>
                <div class="workeera-empapps-status-menu workeera-hide">
                    <?php foreach ( Workeera_Employer_Job_Applications::actionable_statuses() as $opt_status ) : ?>
                        <button type="button"
                                class="workeera-empapps-status-menu-item<?php echo esc_attr($opt_status === $status ? ' workeera-empapps-status-menu-active' : ''); ?>"
                                data-action-status="<?php echo esc_attr( $opt_status ); ?>">
                            <span class="workeera-empapps-status-dot <?php echo esc_attr( Workeera_Employer_Job_Applications::status_badge_class( $opt_status ) ); ?>"></span>
                            <?php echo esc_html( Workeera_Employer_Job_Applications::status_badge_label( $opt_status ) ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="workeera-admin-empapps-actions">
                <?php
                $has_cover_letter = '' !== trim( (string) ( isset( $row['cover_letter'] ) ? $row['cover_letter'] : '' ) );
                Workeera_Job_Applications::render_app_actions_dropdown(
                    $application_id,
                    [
                        'cv_url'           => $cv_url,
                        'cv_name'          => $cv_name,
                        'cv_exists'        => $cv_file_exists,
                        'has_cover_letter' => $has_cover_letter,
                        'context'          => 'admin',
                        'row'              => $row,
                    ]
                );
                ?>
            </div>
        </div>

        <?php if ( ! empty( $post_title ) || $deadline_label || $expiry_label ) : ?>
            <div class="workeera-admin-empapps-jobinfo-bar">
                <?php if ( ! empty( $post_title ) ) : ?>
                    <?php if ( $edit_job_url ) : ?>
                        <a href="<?php echo esc_url( $edit_job_url ); ?>" class="workeera-admin-empapps-jobinfo-title">
                            <span class="dashicons dashicons-portfolio"></span>
                            <?php echo esc_html( $post_title ); ?>
                        </a>
                    <?php else : ?>
                        <span class="workeera-admin-empapps-jobinfo-title">
                            <span class="dashicons dashicons-portfolio"></span>
                            <?php echo esc_html( $post_title ); ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ( $deadline_label ) : ?>
                    <span class="workeera-admin-empapps-jobinfo-meta">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html( $deadline_label ); ?>
                    </span>
                <?php endif; ?>
                <?php if ( $expiry_label ) : ?>
                    <span class="workeera-admin-empapps-jobinfo-meta">
                        <span class="dashicons dashicons-backup"></span>
                        <?php echo esc_html( $expiry_label ); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Build the SQL query (filters, search, sort) and populate $this->items.
     */
    public function prepare_items() {
        global $wpdb;

        $this->_column_headers = [
            $this->get_columns(),
            [], // hidden
            $this->get_sortable_columns(),
            'cb', // primary column for the cb selector
        ];

        $per_page    = self::$per_page;
        $current_page = $this->get_pagenum();
        $offset      = ( $current_page - 1 ) * $per_page;

        /**
         * Addon extension point — extra JOIN clause (e.g. a scores table) for
         * the admin applications list. Must return a developer-built SQL
         * fragment only — never user input.
         *
         * @param string $join_sql Current JOIN fragment ('').
         */
        $join_sql = apply_filters( 'workeera_admin_apps_list_join', '' );

        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}workeera_applications AS a INNER JOIN {$wpdb->prefix}posts AS p ON p.ID = a.job_id {$join_sql} WHERE p.post_type = %s";
        $data_sql = "SELECT a.application_id, a.job_id, a.user_id, a.applicant_name, a.applicant_email, a.applicant_phone,
                            a.cv_data, a.cover_letter, a.status, a.is_withdrawn, a.applied_date,
                            p.post_title, p.post_status, p.post_author
                     FROM {$wpdb->prefix}workeera_applications AS a
                     INNER JOIN {$wpdb->prefix}posts AS p ON p.ID = a.job_id
                     {$join_sql} WHERE p.post_type = %s";

        $placeholders = [];

        $placeholders[] = 'workeera_job';

        // Search (s URL param via WP_List_Table search box).
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
        if ( ! empty( $search ) ) {
            $like_val       = '%' . $wpdb->esc_like( $search ) . '%';
            $count_sql .= " AND (a.applicant_name 
                     LIKE %s OR a.applicant_email LIKE %s OR p.post_title LIKE %s)";
            $data_sql .= " AND (a.applicant_name 
                     LIKE %s OR a.applicant_email LIKE %s OR p.post_title LIKE %s)";
            $placeholders[] = $like_val;
            $placeholders[] = $like_val;
            $placeholders[] = $like_val;
        }

        // Status filter.
        $status = isset( $_REQUEST['status_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['status_filter'] ) ) : '';
        if ( in_array( $status, Workeera_Employer_Job_Applications::actionable_statuses(), true ) ) {
            $count_sql .= " AND 
                     a.status = %s";
            $data_sql .= " AND 
                     a.status = %s";
            $placeholders[] = $status;
        }

        // Job filter.
        $job_filter = isset( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : 0;
        if ( $job_filter > 0 ) {
            $count_sql .= " AND 
                     a.job_id = %d";
            $data_sql .= " AND 
                     a.job_id = %d";
            $placeholders[] = $job_filter;
        }

        // Employer filter.
        $employer_filter = isset( $_REQUEST['employer_id'] ) ? absint( $_REQUEST['employer_id'] ) : 0;
        if ( $employer_filter > 0 ) {
            $count_sql .= " AND 
                     p.post_author = %d";
            $data_sql .= " AND 
                     p.post_author = %d";
            $placeholders[] = $employer_filter;
        }

        // Sort.
        $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'applied_date';
        $order   = isset( $_REQUEST['order'] ) ? strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) : '';

        // "Sort by..." dropdown value (newest | oldest | addon-provided | '').
        $app_sort = isset( $_REQUEST['app_sort'] ) ? sanitize_key( wp_unslash( $_REQUEST['app_sort'] ) ) : '';

        /**
         * Addon extension point — replace the default ordering of the admin
         * applications list. Return a full "ORDER BY …" clause (developer-built
         * SQL only) to take over; return '' to keep the default behaviour.
         *
         * @param string $custom_order Custom ORDER BY clause ('').
         * @param string $orderby      Requested orderby column key.
         * @param string $order        Requested direction ('asc'|'desc'|'').
         * @param string $app_sort     "Sort by..." dropdown value ('newest'|'oldest'|''|addon key).
         */
        $custom_order = apply_filters( 'workeera_admin_apps_list_orderby', '', $orderby, $order, $app_sort );

        if ( ! empty( $custom_order ) ) {
            $data_sql .= ' ' . $custom_order;
        } elseif ( 'newest' === $app_sort ) {
            $data_sql .= ' ORDER BY a.applied_date DESC';
        } elseif ( 'oldest' === $app_sort ) {
            $data_sql .= ' ORDER BY a.applied_date ASC';
        } else {
        switch ( $orderby ) {
            case 'job_title':
                if ( $order === 'asc' ) {
                    $data_sql .= " ORDER BY 
                             p.post_title ASC";
                } else {
                    $data_sql .= " ORDER BY 
                             p.post_title DESC";
                }
                break;
            case 'status':
                if ( $order === 'asc' ) {
                    $data_sql .= " ORDER BY 
                             a.status ASC, a.applied_date DESC";
                } else {
                    $data_sql .= " ORDER BY 
                             a.status DESC, a.applied_date DESC";
                }
                break;
            case 'applied_date':
            default:
                if ( $order === 'asc' ) {
                    $data_sql .= " ORDER BY
                             a.applied_date ASC";
                } else {
                    $data_sql .= " ORDER BY
                            a.applied_date DESC";
                }
                break;
        }
        }

        // Total count.
        $total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $placeholders ) );

        // Data query.
        $data_placeholders   = $placeholders;
        $data_placeholders[] = $per_page;
        $data_placeholders[] = $offset;

        $data_sql .= " LIMIT 
                 %d OFFSET %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_placeholders ), ARRAY_A );

        $this->items = is_array( $rows ) ? $rows : [];

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total_items / $per_page ),
        ] );
    }
}

new Workeera_Admin_Job_Applications;
