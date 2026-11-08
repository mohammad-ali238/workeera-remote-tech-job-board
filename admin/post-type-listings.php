<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Customises the admin list tables for workeera_candidate and workeera_job post types.
 */
class Workeera_Post_Type_Listings {

    /**
     * Register column, query, filter and AJAX hooks for the admin list tables.
     */
    public function __construct() {
        // Candidate columns.
        add_filter( 'manage_workeera_candidate_posts_columns',          [ $this, 'candidate_columns' ] );
        add_action( 'manage_workeera_candidate_posts_custom_column',    [ $this, 'render_candidate_column' ], 10, 2 );
        add_filter( 'manage_edit-workeera_candidate_sortable_columns',  [ $this, 'candidate_sortable_columns' ] );

        // Job columns.
        add_filter( 'manage_workeera_job_posts_columns',                [ $this, 'job_columns' ] );
        add_action( 'manage_workeera_job_posts_custom_column',          [ $this, 'render_job_column' ], 10, 2 );
        add_filter( 'manage_edit-workeera_job_sortable_columns',        [ $this, 'job_sortable_columns' ] );

        // Make our custom column the primary column so WP attaches row actions to it.
        add_filter( 'list_table_primary_column',                      [ $this, 'set_primary_column' ], 10, 2 );

        // Custom sort + extended search via SQL filters (admin main query only).
        add_filter( 'posts_join',                                     [ $this, 'admin_posts_join' ], 10, 2 );
        add_filter( 'posts_orderby',                                  [ $this, 'admin_posts_orderby' ], 10, 2 );
        add_filter( 'posts_search',                                   [ $this, 'admin_posts_search' ], 10, 2 );
        add_filter( 'posts_where',                                    [ $this, 'admin_posts_where' ], 10, 2 );

        // Employer filter (workeera_job only).
        add_action( 'restrict_manage_posts',                          [ $this, 'render_job_employer_filter' ], 10, 2 );
        add_action( 'pre_get_posts',                                  [ $this, 'apply_job_employer_filter' ] );

        // AJAX endpoint backing the employer Select2.
        add_action( 'workeera_ajax_get_jobs_employers',                 [ $this, 'ajax_search_employers' ] );
    }

    // -------------------------------------------------------------------------
    // Candidate post type — columns & rendering
    // -------------------------------------------------------------------------

    /**
     * Replace the default candidate columns with Workeera-specific ones.
     *
     * @param  array $columns Existing column map.
     * @return array          Filtered column map.
     */
    public function candidate_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( $key === 'title' ) {
                $new['workeera_candidate'] = esc_html_x( 'Candidate', 'admin column', 'workeera-remote-tech-job-board' );
            } elseif ( $key === 'date' ) {
                $new['workeera_user'] = esc_html_x( 'User', 'admin column', 'workeera-remote-tech-job-board' );
                $new[ $key ]        = $label;
            } else {
                $new[ $key ] = $label;
            }
        }
        return $new;
    }

    /**
     * Declare which candidate columns are sortable.
     *
     * @param  array $columns Existing sortable column map.
     * @return array          Filtered sortable map.
     */
    public function candidate_sortable_columns( $columns ) {
        $columns['workeera_candidate'] = 'title';
        return $columns;
    }

    /**
     * Render content for the custom candidate columns.
     *
     * @param string $column_name Column slug.
     * @param int    $post_id     Candidate post ID.
     */
    public function render_candidate_column( $column_name, $post_id ) {
        if ( $column_name === 'workeera_candidate' ) {
            self::render_candidate_main_cell( $post_id );
        } elseif ( $column_name === 'workeera_user' ) {
            self::render_user_cell( $post_id );
        }
    }

    /**
     * Render the main candidate cell with profile pic, name and job title.
     *
     * @param int $post_id Candidate post ID.
     */
    private static function render_candidate_main_cell( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $title     = self::display_title( $post_id );
        $edit_link = get_edit_post_link( $post_id );
        $job_title = workeera_get_object_meta( $post_id, 'job_title' );
        ?>
        <div class="workeera-admin-pt-listing-cell">
            <?php workeera_user_profile_pic_html( absint( $post->post_author ), 'workeera-admin-pt-pic' ); ?>
            <div class="workeera-admin-pt-listing-info">
                <strong>
                    <?php if ( $edit_link ) : ?>
                        <a class="row-title" href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $title ); ?>
                    <?php endif; ?>
                </strong>
                <?php if ( ! empty( $job_title ) ) : ?>
                    <div class="workeera-admin-pt-listing-jtitle"><?php echo esc_html( $job_title ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the author/user cell with user ID and email linked to the user profile.
     *
     * @param int $post_id Candidate post ID.
     */
    private static function render_user_cell( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            echo '&mdash;';
            return;
        }

        $author_id = absint( $post->post_author );
        if ( $author_id < 1 ) {
            echo '&mdash;';
            return;
        }

        $user = get_userdata( $author_id );
        if ( ! $user ) {
            /* translators: %d: user ID */
            echo '<span class="workeera-admin-pt-user-deleted">' . esc_html( sprintf( esc_html_x( '#%d (deleted)', 'admin user', 'workeera-remote-tech-job-board' ), $author_id ) ) . '</span>';
            return;
        }

        $profile_link = get_edit_user_link( $author_id );
        $email        = ! empty( $user->user_email ) ? $user->user_email : '';

        /* translators: %d: user ID */
        $id_label = sprintf( esc_html_x( 'User #%d', 'admin user', 'workeera-remote-tech-job-board' ), $author_id );
        ?>
        <div class="workeera-admin-pt-user-cell">
            <strong class="workeera-admin-pt-user-id">
                <?php if ( $profile_link ) : ?>
                    <a href="<?php echo esc_url( $profile_link ); ?>"><?php echo esc_html( $id_label ); ?></a>
                <?php else : ?>
                    <?php echo esc_html( $id_label ); ?>
                <?php endif; ?>
            </strong>
            <?php if ( ! empty( $email ) ) : ?>
                <div class="workeera-admin-pt-user-email">
                    <?php if ( $profile_link ) : ?>
                        <a href="<?php echo esc_url( $profile_link ); ?>"><?php echo esc_html( $email ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $email ); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Job post type — columns & rendering
    // -------------------------------------------------------------------------

    /**
     * Replace the default job columns with Workeera-specific ones.
     *
     * @param  array $columns Existing column map.
     * @return array          Filtered column map.
     */
    public function job_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( $key === 'title' ) {
                $new['workeera_title'] = esc_html_x( 'Title', 'admin column', 'workeera-remote-tech-job-board' );
            } elseif ( $key === 'date' ) {
                $new['workeera_apps']     = esc_html_x( 'Applications', 'admin column', 'workeera-remote-tech-job-board' );
                $new['workeera_deadline'] = esc_html_x( 'Deadline',     'admin column', 'workeera-remote-tech-job-board' );
                $new['workeera_expiry']   = esc_html_x( 'Expiry',       'admin column', 'workeera-remote-tech-job-board' );
                $new['workeera_status']   = esc_html_x( 'Status',       'admin column', 'workeera-remote-tech-job-board' );
                $new[ $key ]            = $label;
            } else {
                $new[ $key ] = $label;
            }
        }
        return $new;
    }

    /**
     * Declare which job columns are sortable.
     *
     * @param  array $columns Existing sortable column map.
     * @return array          Filtered sortable map.
     */
    public function job_sortable_columns( $columns ) {
        $columns['workeera_title']    = 'title';
        $columns['workeera_apps']     = 'workeera_apps';
        $columns['workeera_deadline'] = 'workeera_deadline';
        $columns['workeera_expiry']   = 'workeera_expiry';
        return $columns;
    }

    /**
     * Render content for the custom job columns.
     *
     * @param string $column_name Column slug.
     * @param int    $post_id     Job post ID.
     */
    public function render_job_column( $column_name, $post_id ) {
        switch ( $column_name ) {
            case 'workeera_title':
                self::render_job_main_cell( $post_id );
                break;
            case 'workeera_apps':
                self::render_job_apps_cell( $post_id );
                break;
            case 'workeera_deadline':
                self::render_job_deadline_cell( $post_id );
                break;
            case 'workeera_expiry':
                self::render_job_expiry_cell( $post_id );
                break;
            case 'workeera_status':
                self::render_job_status_cell( $post_id );
                break;
        }
    }

    /**
     * Render the main job cell with logo, title and employer info.
     *
     * @param int $post_id Job post ID.
     */
    private static function render_job_main_cell( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $title        = self::display_title( $post_id );
        $edit_link    = get_edit_post_link( $post_id );
        $logo_url     = function_exists( 'workeera_job_logo' ) ? workeera_job_logo( $post_id ) : '';
        $company_info = workeera_job_company_info( $post_id );
        $company_name = is_array( $company_info ) && ! empty( $company_info['name'] )
            ? workeera_strip_slashes( $company_info['name'] )
            : '';

        $author_id    = absint( $post->post_author );
        $user         = $author_id > 0 ? get_userdata( $author_id ) : null;
        $user_email   = $user && ! empty( $user->user_email ) ? $user->user_email : '';
        $profile_link = $author_id > 0 ? get_edit_user_link( $author_id ) : '';

        $employer_parts = [];
        if ( ! empty( $company_name ) ) {
            $employer_parts[] = '<span class="workeera-admin-pt-employer-company">' . esc_html( $company_name ) . '</span>';
        }
        if ( $author_id > 0 ) {
            /* translators: %d: user ID */
            $id_label = sprintf( esc_html_x( 'User #%d', 'admin user', 'workeera-remote-tech-job-board' ), $author_id );
            $id_html  = $profile_link
                ? '<a href="' . esc_url( $profile_link ) . '">' . esc_html( $id_label ) . '</a>'
                : esc_html( $id_label );
            $employer_parts[] = '<span class="workeera-admin-pt-employer-user">' . $id_html . '</span>';
        }
        if ( ! empty( $user_email ) ) {
            $email_html = $profile_link
                ? '<a href="' . esc_url( $profile_link ) . '">' . esc_html( $user_email ) . '</a>'
                : esc_html( $user_email );
            $employer_parts[] = '<span class="workeera-admin-pt-employer-email">' . $email_html . '</span>';
        }
        ?>
        <div class="workeera-admin-pt-listing-cell">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div class="workeera-admin-pt-pic" style="background-image: url('<?php echo esc_url( $logo_url ); ?>');"></div>
            <?php else : ?>
                <div class="workeera-admin-pt-pic workeera-admin-pt-pic-empty"></div>
            <?php endif; ?>
            <div class="workeera-admin-pt-listing-info">
                <strong>
                    <?php if ( $edit_link ) : ?>
                        <a class="row-title" href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $title ); ?>
                    <?php endif; ?>
                </strong>
                <?php if ( ! empty( $employer_parts ) ) : ?>
                    <div class="workeera-admin-pt-employer-info"><?php echo wp_kses( implode( ' &middot; ', $employer_parts ), array(
                        'span' => array( 'class' => true ),
                        'a'    => array( 'href' => true, 'class' => true ),
                    ) ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the applications-count cell linked to the filtered applications page.
     *
     * @param int $post_id Job post ID.
     */
    private static function render_job_apps_cell( $post_id ) {
        global $wpdb;
        $apps_table = $wpdb->prefix . 'workeera_applications';
        $count = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$apps_table} WHERE job_id = %d", $post_id )
        );

        if ( $count > 0 ) {
            $url = add_query_arg(
                [
                    'page'   => 'workeera-applications',
                    'job_id' => absint( $post_id ),
                ],
                admin_url( 'admin.php' )
            );
            echo '<a href="' . esc_url( $url ) . '" class="workeera-admin-pt-apps-link">' . esc_html( number_format_i18n( $count ) ) . '</a>';
        } else {
            echo '<span class="workeera-admin-pt-apps-zero">' . esc_html( number_format_i18n( 0 ) ) . '</span>';
        }
    }

    /**
     * Render the application deadline cell.
     *
     * @param int $post_id Job post ID.
     */
    private static function render_job_deadline_cell( $post_id ) {
        $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post_id ], 'all', 1 );
        $deadline   = isset( $job_fields['application_deadline'] ) ? $job_fields['application_deadline'] : '';

        if ( empty( $deadline ) ) {
            echo '&mdash;';
            return;
        }

        $ts = strtotime( $deadline );
        if ( ! $ts ) {
            echo '&mdash;';
            return;
        }

        echo esc_html( date_i18n( get_option( 'date_format' ), $ts ) );
    }

    /**
     * Render the expiry-date cell with a past/future visual class.
     *
     * @param int $post_id Job post ID.
     */
    private static function render_job_expiry_cell( $post_id ) {
        $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post_id ], 'all', 1 );
        $expiry     = isset( $job_fields['expiry_date'] ) ? $job_fields['expiry_date'] : '';

        if ( empty( $expiry ) ) {
            echo '&mdash;';
            return;
        }

        $ts = strtotime( $expiry );
        if ( ! $ts ) {
            echo '&mdash;';
            return;
        }

        $is_past = ( $ts < current_time( 'timestamp' ) );
        $class   = $is_past ? 'workeera-admin-pt-expiry-past' : 'workeera-admin-pt-expiry-future';
        echo '<span class="' . esc_attr( $class ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), $ts ) ) . '</span>';
    }

    /**
     * Resolve the effective listing status for a job.
     *
     * @param  int    $post_id Job post ID.
     * @return string          One of: active, pending, moderated, expired, inactive.
     */
    public static function resolve_job_listing_status( $post_id ) {
        $post = get_post( absint( $post_id ) );
        if ( ! $post || $post->post_type !== 'workeera_job' ) {
            return 'inactive';
        }

        $job_fields  = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => absint( $post_id ) ], 'all', 1 );
        $stored      = isset( $job_fields['listing_status'] ) ? sanitize_key( $job_fields['listing_status'] ) : '';
        $post_status = sanitize_key( $post->post_status );
        $expiry      = isset( $job_fields['expiry_date'] ) ? $job_fields['expiry_date'] : '';

        // 1. Expiry beats everything else when the date has passed.
        if ( ! empty( $expiry ) ) {
            $expiry_ts = strtotime( $expiry );
            if ( $expiry_ts && $expiry_ts < current_time( 'timestamp' ) ) {
                return 'expired';
            }
        }

        // 2. Explicit moderated flag in workeera_job_fields.
        if ( $stored === 'moderated' ) {
            return 'moderated';
        }

        // 3. WP post_status → mapped status.
        switch ( $post_status ) {
            case 'publish':
                return 'active';
            case 'pending':
                return 'pending';
            case 'draft':
            default:
                return 'inactive';
        }
    }

    /**
     * Translate a status key to a human-readable label.
     *
     * @param  string $status Status key.
     * @return string         Label for the Status column.
     */
    private static function job_status_label( $status ) {
        $map = [
            'active'    => esc_html_x( 'Active',    'admin job status', 'workeera-remote-tech-job-board' ),
            'pending'   => esc_html_x( 'Pending',   'admin job status', 'workeera-remote-tech-job-board' ),
            'moderated' => esc_html_x( 'Moderated', 'admin job status', 'workeera-remote-tech-job-board' ),
            'expired'   => esc_html_x( 'Expired',   'admin job status', 'workeera-remote-tech-job-board' ),
            'inactive'  => esc_html_x( 'Inactive',  'admin job status', 'workeera-remote-tech-job-board' ),
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : $map['inactive'];
    }

    /**
     * Translate a status key to its CSS modifier class.
     *
     * @param  string $status Status key.
     * @return string         CSS class for the status badge.
     */
    private static function job_status_class( $status ) {
        $map = [
            'active'    => 'workeera-admin-jobsts-active',
            'pending'   => 'workeera-admin-jobsts-pending',
            'moderated' => 'workeera-admin-jobsts-moderated',
            'expired'   => 'workeera-admin-jobsts-expired',
            'inactive'  => 'workeera-admin-jobsts-inactive',
        ];
        return isset( $map[ $status ] ) ? $map[ $status ] : $map['inactive'];
    }

    /**
     * Render the resolved status badge cell for a job.
     *
     * @param int $post_id Job post ID.
     */
    private static function render_job_status_cell( $post_id ) {
        $status = self::resolve_job_listing_status( $post_id );
        echo '<span class="workeera-admin-jobsts ' . esc_attr( self::job_status_class( $status ) ) . '">'
            . esc_html( self::job_status_label( $status ) )
            . '</span>';
    }

    // -------------------------------------------------------------------------
    // Primary column override
    // -------------------------------------------------------------------------

    /**
     * Force our custom column to be the primary column on Workeera list screens.
     *
     * @param  string $default   Default primary column.
     * @param  string $screen_id Current screen ID.
     * @return string            Primary column slug.
     */
    public function set_primary_column( $default, $screen_id ) {
        if ( $screen_id === 'edit-workeera_candidate' ) {
            return 'workeera_candidate';
        }
        if ( $screen_id === 'edit-workeera_job' ) {
            return 'workeera_title';
        }
        return $default;
    }

    // -------------------------------------------------------------------------
    // Query modifications (admin main query only): sort + extended search
    // -------------------------------------------------------------------------

    /**
     * Check if the current admin main query targets one of our list pages.
     *
     * @param  WP_Query     $query Current query object.
     * @return string|false        Post type slug when applicable, false otherwise.
     */
    private function applies_to( $query ) {
        global $pagenow;
        if ( ! is_admin() || $pagenow !== 'edit.php' ) {
            return false;
        }
        if ( ! $query->is_main_query() ) {
            return false;
        }
        $pt = $query->get( 'post_type' );
        if ( $pt === 'workeera_candidate' || $pt === 'workeera_job' ) {
            return $pt;
        }
        return false;
    }

    /**
     * Inject JOIN clauses to support custom sort, filter and search modes.
     *
     * @param  string   $join  Existing JOIN SQL.
     * @param  WP_Query $query Current query.
     * @return string          Filtered JOIN SQL.
     */
    public function admin_posts_join( $join, $query ) {
        $pt = $this->applies_to( $query );
        if ( ! $pt ) {
            return $join;
        }

        global $wpdb;

        // Candidate + Job: join users table to enable searching by author email
        // (and additionally display_name on jobs).
        if ( ( $pt === 'workeera_candidate' || $pt === 'workeera_job' ) && $query->is_search() && strpos( $join, 'jw_pt_users' ) === false ) {
            $join .= " LEFT JOIN {$wpdb->users} AS jw_pt_users ON jw_pt_users.ID = {$wpdb->posts}.post_author ";
        }

        // Job: joins required for the custom orderby keys.
        if ( $pt === 'workeera_job' ) {
            $orderby = $query->get( 'orderby' );

            if ( $orderby === 'workeera_apps' && strpos( $join, 'jw_pt_app_counts' ) === false ) {
                $apps_table = $wpdb->prefix . 'workeera_applications';
                $join .= " LEFT JOIN ( SELECT job_id, COUNT(*) AS app_count FROM {$apps_table} GROUP BY job_id ) AS jw_pt_app_counts ON jw_pt_app_counts.job_id = {$wpdb->posts}.ID ";
            }

            if ( ( $orderby === 'workeera_deadline' || $orderby === 'workeera_expiry' ) && strpos( $join, 'jw_pt_jf' ) === false ) {
                $jf_table = $wpdb->prefix . 'workeera_job_fields';
                $join .= " LEFT JOIN {$jf_table} AS jw_pt_jf ON jw_pt_jf.job_id = {$wpdb->posts}.ID ";
            }

            // The Status filter at the top of the list relies on the same JF
            // table for expiry_date / listing_status; join it on demand when a
            // listing_status filter is active.
            if ( ! empty( $_GET['listing_status'] ) && strpos( $join, 'jw_pt_jf' ) === false ) {
                $jf_table = $wpdb->prefix . 'workeera_job_fields';
                $join .= " LEFT JOIN {$jf_table} AS jw_pt_jf ON jw_pt_jf.job_id = {$wpdb->posts}.ID ";
            }
        }

        return $join;
    }

    /**
     * Filter the WHERE clause to narrow the job admin list by listing status.
     *
     * @param  string   $where Existing WHERE SQL.
     * @param  WP_Query $query Current query.
     * @return string          Filtered WHERE SQL.
     */
    public function admin_posts_where( $where, $query ) {
        $pt = $this->applies_to( $query );
        if ( $pt !== 'workeera_job' ) {
            return $where;
        }
        if ( empty( $_GET['listing_status'] ) ) {
            return $where;
        }

        $status = sanitize_key( wp_unslash( $_GET['listing_status'] ) );
        if ( ! in_array( $status, [ 'active', 'pending', 'moderated', 'expired' ], true ) ) {
            return $where;
        }

        global $wpdb;
        $now = current_time( 'mysql' );

        switch ( $status ) {
            case 'active':
                // Published, not yet expired, not explicitly moderated.
                $where .= $wpdb->prepare(
                    " AND {$wpdb->posts}.post_status = %s
                       AND ( jw_pt_jf.expiry_date IS NULL OR jw_pt_jf.expiry_date >= %s )
                       AND ( jw_pt_jf.listing_status IS NULL OR jw_pt_jf.listing_status <> %s ) ",
                    'publish', $now, 'moderated'
                );
                break;

            case 'pending':
                $where .= $wpdb->prepare(
                    " AND {$wpdb->posts}.post_status = %s ",
                    'pending'
                );
                break;

            case 'moderated':
                $where .= $wpdb->prepare(
                    " AND jw_pt_jf.listing_status = %s ",
                    'moderated'
                );
                break;

            case 'expired':
                $where .= $wpdb->prepare(
                    " AND jw_pt_jf.expiry_date IS NOT NULL AND jw_pt_jf.expiry_date < %s ",
                    $now
                );
                break;
        }

        return $where;
    }

    /**
     * Filter the ORDER BY clause for our custom sortable columns.
     *
     * @param  string   $orderby_sql Existing ORDER BY SQL.
     * @param  WP_Query $query       Current query.
     * @return string                Filtered ORDER BY SQL.
     */
    public function admin_posts_orderby( $orderby_sql, $query ) {
        $pt = $this->applies_to( $query );
        if ( $pt !== 'workeera_job' ) {
            return $orderby_sql;
        }

        $orderby = $query->get( 'orderby' );
        $order   = strtoupper( (string) $query->get( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';

        switch ( $orderby ) {
            case 'workeera_apps':
                return "COALESCE(jw_pt_app_counts.app_count, 0) {$order}";
            case 'workeera_deadline':
                return "jw_pt_jf.application_deadline {$order}";
            case 'workeera_expiry':
                return "jw_pt_jf.expiry_date {$order}";
        }

        return $orderby_sql;
    }

    /**
     * Extend the admin search clause to match author email, login and display name.
     *
     * @param  string   $search Existing search SQL.
     * @param  WP_Query $query  Current query.
     * @return string           Filtered search SQL.
     */
    public function admin_posts_search( $search, $query ) {
        $pt = $this->applies_to( $query );
        if ( ! $pt || ! $query->is_search() ) {
            return $search;
        }

        global $wpdb;
        $term = trim( (string) $query->get( 's' ) );
        if ( $term === '' ) {
            return $search;
        }

        $like = '%' . $wpdb->esc_like( $term ) . '%';

        // Candidate: title / excerpt / content / author email.
        if ( $pt === 'workeera_candidate' ) {
            return $wpdb->prepare(
                " AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR jw_pt_users.user_email LIKE %s ) ",
                $like, $like, $like, $like
            );
        }

        // Job: title / excerpt / content / author email / display name / login.
        if ( $pt === 'workeera_job' ) {
            return $wpdb->prepare(
                " AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR jw_pt_users.user_email LIKE %s OR jw_pt_users.display_name LIKE %s OR jw_pt_users.user_login LIKE %s ) ",
                $like, $like, $like, $like, $like, $like
            );
        }

        return $search;
    }

    // -------------------------------------------------------------------------
    // Employer filter (workeera_job only) — Select2 dropdown above the list,
    // backed by an AJAX search of users who own at least one job post.
    // -------------------------------------------------------------------------

    /**
     * Render the employer + listing-status filter dropdowns above the job list.
     *
     * @param string $post_type Current admin list post type.
     * @param string $which     Either "top" or "bottom" tablenav.
     */
    public function render_job_employer_filter( $post_type, $which = '' ) {
        if ( $post_type !== 'workeera_job' ) {
            return;
        }

        $current_employer = isset( $_GET['employer_id'] ) ? absint( $_GET['employer_id'] ) : 0;
        $employer_label   = '';

        if ( $current_employer > 0 ) {
            $user = get_userdata( $current_employer );
            if ( $user ) {
                $name  = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
                $email = ! empty( $user->user_email ) ? $user->user_email : '';
                if ( ! empty( $email ) ) {
                    /* translators: 1: user name, 2: email, 3: user ID */
                    $employer_label = sprintf( esc_html_x( '%1$s (%2$s) (id: %3$d)', 'admin employer dropdown', 'workeera-remote-tech-job-board' ), $name, $email, $user->ID );
                } else {
                    /* translators: 1: user name, 2: user ID */
                    $employer_label = sprintf( esc_html_x( '%1$s (id: %2$d)', 'admin employer dropdown', 'workeera-remote-tech-job-board' ), $name, $user->ID );
                }
            } else {
                $current_employer = 0; // not a valid user — drop.
            }
        }
        ?>
        <select name="employer_id" class="workeera-job-employer-filter" data-placeholder="<?php echo esc_attr_x( 'All Employers', 'admin job filter', 'workeera-remote-tech-job-board' ); ?>">
            <option value=""></option>
            <?php if ( $current_employer > 0 ) : ?>
                <option value="<?php echo absint( $current_employer ); ?>" selected><?php echo esc_html( $employer_label ); ?></option>
            <?php endif; ?>
        </select>

        <?php
        // "Listing status" dropdown — sits alongside the employer filter and
        // narrows the list by computed job state (post_status / expiry / flag).
        $current_status = isset( $_GET['listing_status'] ) ? sanitize_key( wp_unslash( $_GET['listing_status'] ) ) : '';
        $status_options = [
            'active'    => esc_html_x( 'Active',    'admin job status filter', 'workeera-remote-tech-job-board' ),
            'pending'   => esc_html_x( 'Pending',   'admin job status filter', 'workeera-remote-tech-job-board' ),
            'moderated' => esc_html_x( 'Moderated', 'admin job status filter', 'workeera-remote-tech-job-board' ),
            'expired'   => esc_html_x( 'Expired',   'admin job status filter', 'workeera-remote-tech-job-board' ),
        ];
        ?>
        <select name="listing_status" class="workeera-job-status-filter">
            <option value=""><?php echo esc_html_x( 'All Statuses', 'admin job status filter', 'workeera-remote-tech-job-board' ); ?></option>
            <?php foreach ( $status_options as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Apply the employer_id GET filter to the job list query.
     *
     * @param WP_Query $query Current query object.
     */
    public function apply_job_employer_filter( $query ) {
        $pt = $this->applies_to( $query );
        if ( $pt !== 'workeera_job' ) {
            return;
        }
        if ( ! isset( $_GET['employer_id'] ) ) {
            return;
        }

        $employer_id = absint( $_GET['employer_id'] );
        if ( $employer_id > 0 ) {
            $query->set( 'author', $employer_id );
        }
    }

    /**
     * AJAX handler returning Select2 results for users that authored a workeera_job.
     */
    public function ajax_search_employers() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'results' => [], 'pagination' => [ 'more' => false ] ] );
        }

        global $wpdb;

        $search   = isset( $_POST['q'] )    ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $per_page = 30;
        $offset   = ( $page - 1 ) * $per_page;

        $args  = [ 'workeera_job' ];
        if ( ! empty( $search ) ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $count_sql = "SELECT COUNT(DISTINCT u.ID)
                      FROM {$wpdb->users} AS u
                      INNER JOIN {$wpdb->posts} AS p ON p.post_author = u.ID
                      WHERE p.post_type = %s";
        if ( ! empty( $search ) ) {
            $count_sql .= " AND ( u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s )";
        }
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );

        $args[] = $per_page;
        $args[] = $offset;

        $data_sql = "SELECT DISTINCT u.ID, u.display_name, u.user_login, u.user_email
                     FROM {$wpdb->users} AS u
                     INNER JOIN {$wpdb->posts} AS p ON p.post_author = u.ID
                     WHERE p.post_type = %s";
        if ( ! empty( $search ) ) {
            $data_sql .= " AND ( u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s )";
        }
        $data_sql .= " ORDER BY u.display_name ASC
                     LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $args ) );

        $results = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $name  = ! empty( $row->display_name ) ? $row->display_name : $row->user_login;
                $email = ! empty( $row->user_email ) ? $row->user_email : '';
                if ( ! empty( $email ) ) {
                    /* translators: 1: user name, 2: email, 3: user ID */
                    $text = sprintf( esc_html_x( '%1$s (%2$s) (id: %3$d)', 'admin employer dropdown', 'workeera-remote-tech-job-board' ), $name, $email, $row->ID );
                } else {
                    /* translators: 1: user name, 2: user ID */
                    $text = sprintf( esc_html_x( '%1$s (id: %2$d)', 'admin employer dropdown', 'workeera-remote-tech-job-board' ), $name, $row->ID );
                }
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
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the post title, falling back to a placeholder when empty.
     *
     * @param  int    $post_id Post ID.
     * @return string          Display title.
     */
    private static function display_title( $post_id ) {
        $title = get_the_title( $post_id );
        if ( $title === '' ) {
            $title = esc_html_x( '(no title)', 'admin column', 'workeera-remote-tech-job-board' );
        }
        return $title;
    }

    /**
     * Allowed-tag map for wp_kses() when rendering profile-pic markup.
     *
     * @return array Allowed tags and attributes.
     */
    private static function profile_pic_kses() {
        return [
            'div'    => [ 'class' => [], 'style' => [] ],
            'svg'    => [ 'class' => [], 'width' => [], 'height' => [], 'viewbox' => [], 'fill' => [], 'stroke' => [], 'stroke-width' => [], 'stroke-linecap' => [], 'stroke-linejoin' => [] ],
            'circle' => [ 'cx' => [], 'cy' => [], 'r' => [] ],
            'path'   => [ 'd' => [] ],
        ];
    }
}

new Workeera_Post_Type_Listings;
