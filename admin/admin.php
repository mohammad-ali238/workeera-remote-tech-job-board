<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Top-level admin controller — menu pages, included subsystems and shared chrome.
 */
class Workeera_Admin {

    /**
     * Wire up admin menu, includes and footer/enqueue hooks.
     */
    public function __construct() {

        add_action('admin_menu', [$this, 'admin_menu_pages'], 10);

        $this->includes();

        add_action('admin_footer', [$this, 'popup_modal_init'], 20);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
    }

    /**
     * Include admin-only subsystems (ajax, taxonomies, post meta, settings).
     */
    private function includes() {
        if (is_admin()) {

            include_once WORKEERA_ABSPATH . 'admin/admin-ajax.php';

            // custom taxonomy
            include_once WORKEERA_ABSPATH . 'admin/taxonomies/taxonomy.php';
            include_once WORKEERA_ABSPATH . 'admin/taxonomies/common-taxonomy.php';
            include_once WORKEERA_ABSPATH . 'admin/taxonomies/class-importdata.php';
            
            // post meta
            include_once WORKEERA_ABSPATH . 'admin/post-meta-box.php';
            include_once WORKEERA_ABSPATH . 'admin/post-type-listings.php';

            // settings
            include_once WORKEERA_ABSPATH . 'admin/settings/field-functions.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/general-settings.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/job-listings-settings.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/job-apply-settings.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/job-submission-settings.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/company-settings.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/candidate-settings.php';
            include_once WORKEERA_ABSPATH . 'admin/settings/pages-settings.php';

            // admin job applications page
            include_once WORKEERA_ABSPATH . 'admin/job-applications.php';

            // addons page (distribution server client)
            include_once WORKEERA_ABSPATH . 'admin/addons.php';
        }
    }

    /**
     * Register the top-level "Workeera" admin menu and Settings submenu.
     */
    public function admin_menu_pages() {
        add_menu_page(esc_html_x('Workeera Dashboard', 'Admin page title', 'workeera-remote-tech-job-board'), esc_html_x('Workeera', 'Main dashboard title', 'workeera-remote-tech-job-board'), 'manage_options', 'workeera-dashboard', [$this, 'dashboard'], 'dashicons-businessperson', 50);

        // Free AI Credits — registered before Settings; hidden when the feature
        // is disabled via the General settings master switch.
        if ( class_exists( 'Workeera_Free_AI_Credits' ) ) {
            Workeera_Free_AI_Credits::register_submenu();
        }

        add_submenu_page('workeera-dashboard', esc_html_x('Workeera Options Page', 'Admin page title', 'workeera-remote-tech-job-board'), esc_html_x('Settings', 'Main menu title', 'workeera-remote-tech-job-board'), 'manage_options', 'workeera-options', [$this, 'create_options_page']);
    }

    /**
     * Enqueue job-applications assets on the main Workeera dashboard screen.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_dashboard_assets( $hook ) {
        if ( $hook !== 'toplevel_page_workeera-dashboard' ) {
            return;
        }

        wp_enqueue_style(
            'workeera-job-applications',
            Workeera_Plugin::root_url() . 'css/job-applications.css',
            [ 'workeera-styles' ],
            WORKEERA_VERSION
        );
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

    /**
     * Render the main "Workeera Dashboard" admin page with welcome banner and stats.
     */
    public function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'workeera-remote-tech-job-board' ) );
        }

        $stats        = self::get_admin_dashboard_stats();
        $recent_apps  = self::get_admin_recent_applications( 10 );
        $current_user = wp_get_current_user();
        $display_name = ( $current_user && ! empty( $current_user->display_name ) ) ? $current_user->display_name : '';

        $apps_url = add_query_arg( [ 'page' => 'workeera-applications' ], admin_url( 'admin.php' ) );

        $admin_nonce = wp_create_nonce( 'workeera_admin_nonce' );
        ?>
        <div class="wrap workeera-page workeera-empapps-wrap workeera-admin-empapps-wrap workeera-admin-dashboard-wrap"
             data-context="admin"
             data-nonce="<?php echo esc_attr( $admin_nonce ); ?>"
             data-ajax-status="workeera_admin_apps_status">

            <h1 class="wp-heading-inline"><?php echo esc_html_x( 'Workeera Dashboard', 'admin dashboard', 'workeera-remote-tech-job-board' ); ?></h1>
            <hr class="wp-header-end">

            <!-- Welcome banner -->
            <div class="workeera-admin-dshb-welcome">
                <strong class="workeera-admin-dshb-welcome-title">
                    <?php
                    if ( ! empty( $display_name ) ) {
                        /* translators: %s: admin user display name */
                        echo esc_html( sprintf( esc_html_x( 'Welcome, %s!', 'admin dashboard', 'workeera-remote-tech-job-board' ), $display_name ) );
                    } else {
                        echo esc_html_x( 'Welcome!', 'admin dashboard', 'workeera-remote-tech-job-board' );
                    }
                    ?>
                </strong>
                <span class="workeera-admin-dshb-welcome-sub">
                    <?php echo esc_html_x( 'A quick overview of jobs, candidates and applications across the site.', 'admin dashboard', 'workeera-remote-tech-job-board' ); ?>
                </span>
                <?php
                // Free AI credits balance (only when enabled + verified + unexpired).
                // Self-generated markup with all dynamic values escaped inside the method.
                if ( class_exists( 'Workeera_Free_AI_Credits' ) ) {
                    echo Workeera_Free_AI_Credits::dashboard_badge_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </div>

            <!-- Stats grid -->
            <div class="workeera-admin-dshb-stats-grid">
                <?php
                self::render_admin_stat_card( [
                    'theme' => 'primary',
                    'icon'  => self::admin_stat_icon( 'briefcase' ),
                    'label' => esc_html_x( 'Total Jobs', 'admin dashboard', 'workeera-remote-tech-job-board' ),
                    'value' => $stats['jobs_total'],
                ] );
                self::render_admin_stat_card( [
                    'theme' => 'success',
                    'icon'  => self::admin_stat_icon( 'check-circle' ),
                    'label' => esc_html_x( 'Active Jobs', 'admin dashboard', 'workeera-remote-tech-job-board' ),
                    'value' => $stats['jobs_active'],
                ] );
                self::render_admin_stat_card( [
                    'theme' => 'warning',
                    'icon'  => self::admin_stat_icon( 'clock' ),
                    'label' => esc_html_x( 'Pending Jobs', 'admin dashboard', 'workeera-remote-tech-job-board' ),
                    'value' => $stats['jobs_pending'],
                ] );
                self::render_admin_stat_card( [
                    'theme' => 'info',
                    'icon'  => self::admin_stat_icon( 'users' ),
                    'label' => esc_html_x( 'Total Candidates', 'admin dashboard', 'workeera-remote-tech-job-board' ),
                    'value' => $stats['candidates_total'],
                ] );
                self::render_admin_stat_card( [
                    'theme' => 'primary',
                    'icon'  => self::admin_stat_icon( 'send' ),
                    'label' => esc_html_x( 'Total Applications', 'admin dashboard', 'workeera-remote-tech-job-board' ),
                    'value' => $stats['applications_total'],
                ] );
                self::render_admin_stat_card( [
                    'theme' => 'warning',
                    'icon'  => self::admin_stat_icon( 'hourglass' ),
                    'label' => esc_html_x( 'Pending Applications', 'admin dashboard', 'workeera-remote-tech-job-board' ),
                    'value' => $stats['applications_pending'],
                ] );
                ?>
            </div>

            <!-- Recent applications panel -->
            <div class="workeera-admin-dshb-recent">
                <div class="workeera-admin-dshb-recent-header">
                    <h2 class="workeera-admin-dshb-recent-title"><?php echo esc_html_x( 'Recent Applications', 'admin dashboard', 'workeera-remote-tech-job-board' ); ?></h2>
                    <?php if ( ! empty( $recent_apps ) ) : ?>
                        <a class="button workeera-admin-dshb-recent-viewall" href="<?php echo esc_url( $apps_url ); ?>">
                            <?php echo esc_html_x( 'View all applications', 'admin dashboard', 'workeera-remote-tech-job-board' ); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ( empty( $recent_apps ) ) : ?>
                    <div class="workeera-admin-dshb-recent-empty">
                        <span class="dashicons dashicons-portfolio"></span>
                        <p><?php echo esc_html_x( 'No applications received yet.', 'admin dashboard', 'workeera-remote-tech-job-board' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped workeera-admin-dshb-recent-table">
                        <tbody>
                            <?php foreach ( $recent_apps as $row ) : ?>
                                <?php
                                $application_id = absint( $row['application_id'] );
                                $status         = sanitize_key( $row['status'] );
                                ?>
                                <tr class="workeera-empapps-item"
                                    data-application-id="<?php echo absint( $application_id ); ?>"
                                    data-current-status="<?php echo esc_attr( $status ); ?>">
                                    <td class="workeera-admin-empapps-cardcell">
                                        <?php Workeera_Admin_Apps_List_Table::render_card_content( $row ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Aggregate counts across jobs, candidates and applications.
     *
     * @return array<string, int> Stat keys mapped to counts.
     */
    private static function get_admin_dashboard_stats() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'posts';
        $jf_table    = $wpdb->prefix . 'workeera_job_fields';
        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $now_mysql   = current_time( 'mysql' );

        // Jobs ---------------------------------------------------------------
        $jobs_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table}
             WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft','inherit')",
            'workeera_job'
        ) );

        $jobs_active = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table} AS p
             LEFT JOIN {$jf_table} AS jf ON jf.job_id = p.ID
             WHERE p.post_type = %s AND p.post_status = 'publish'
               AND ( jf.expiry_date IS NULL OR jf.expiry_date >= %s )
               AND ( jf.is_filled IS NULL OR jf.is_filled = 0 )",
            'workeera_job', $now_mysql
        ) );

        $jobs_pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table}
             WHERE post_type = %s AND post_status = 'pending'",
            'workeera_job'
        ) );

        // Candidates ---------------------------------------------------------
        $candidates_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table}
             WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft','inherit')",
            'workeera_candidate'
        ) );

        // Applications -------------------------------------------------------
        $applications_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$apps_table}" );
        $applications_pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$apps_table} WHERE status = %s",
            'pending'
        ) );

        return [
            'jobs_total'           => $jobs_total,
            'jobs_active'          => $jobs_active,
            'jobs_pending'         => $jobs_pending,
            'candidates_total'     => $candidates_total,
            'applications_total'   => $applications_total,
            'applications_pending' => $applications_pending,
        ];
    }

    /**
     * Fetch the most-recent applications across all employers.
     *
     * @param  int $limit Maximum number of rows to return.
     * @return array<int, array<string, mixed>> Application rows ordered DESC by applied_date.
     */
    private static function get_admin_recent_applications( $limit = 10 ) {
        global $wpdb;

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.application_id, a.job_id, a.user_id, a.applicant_name, a.applicant_email,
                    a.cv_data, a.status, a.applied_date,
                    p.post_title, p.post_status, p.post_author
             FROM {$apps_table} AS a
             INNER JOIN {$posts_table} AS p ON p.ID = a.job_id
             WHERE p.post_type = %s
             ORDER BY a.applied_date DESC
             LIMIT %d",
            'workeera_job', absint( $limit )
        ), ARRAY_A );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Render one stat card on the admin dashboard.
     *
     * @param array $args Card config: theme, icon (raw HTML), label and value.
     */
    private static function render_admin_stat_card( $args ) {
        $theme = isset( $args['theme'] ) ? sanitize_html_class( $args['theme'] ) : 'primary';
        $icon  = isset( $args['icon'] )  ? $args['icon']  : '';
        $label = isset( $args['label'] ) ? $args['label'] : '';
        $value = isset( $args['value'] ) ? (int) $args['value'] : 0;
        ?>
        <div class="workeera-admin-dshb-stat-card workeera-admin-dshb-stat-<?php echo esc_attr( $theme ); ?>">
            <div class="workeera-admin-dshb-stat-icon"><?php echo wp_kses( (string) $icon, workeera_svg_allowed_tags() ); ?></div>
            <div class="workeera-admin-dshb-stat-body">
                <span class="workeera-admin-dshb-stat-label"><?php echo esc_html( $label ); ?></span>
                <span class="workeera-admin-dshb-stat-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Return an inline SVG icon for a stat card by key.
     *
     * @param  string $key Icon key (e.g. briefcase, clock, users).
     * @return string      Inline SVG markup, empty string when key is unknown.
     */
    private static function admin_stat_icon( $key ) {
        switch ( $key ) {
            case 'briefcase':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>';
            case 'check-circle':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            case 'clock':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
            case 'users':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
            case 'send':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
            case 'hourglass':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12"/><path d="M6 22h12"/><path d="M6 2v6a6 6 0 0 0 12 0V2"/><path d="M6 22v-6a6 6 0 0 1 12 0v6"/></svg>';
        }
        return '';
    }

    /**
     * Render the Workeera Settings page with tabbed sub-navigation.
     */
    public function create_options_page() {

        $page_url = esc_url(admin_url('/admin.php?page=workeera-options'));

        $options_menu = self::options_menu();

        $menu_tab = workeera_get_query_var('workeera_tab');
        $menu_tab = !empty($menu_tab) ? $menu_tab : 'general';
        $menu_section = workeera_get_query_var('workeera_section');
        ?>
        <div class="workeera-admin-settins-main">
            <form method="post" action="<?php echo esc_url(sanitize_url(admin_url('options.php'))) ?>">
                <div class="workeera-options-tabswrap">
                    <div class="workeera-options-tabs">
                        <?php
                        $active_tab = !empty($menu_tab) && isset($options_menu[$menu_tab]) ? $menu_tab : 'general';
                        foreach ($options_menu as $menu_key => $menu_item) {
                            if (is_array($menu_item)) {
                                $menu_title = isset($menu_item['title']) ? $menu_item['title'] : '';
                                if ($menu_tab == $menu_key && isset($menu_item['sub_menu'])) {
                                    $sub_menu = $menu_item['sub_menu'];
                                }
                            } else {
                                $menu_title = $menu_item;
                            }
                            ?>
                            <a href="<?php echo esc_url(add_query_arg(array('workeera_tab' => $menu_key), $page_url)) ?>" class="workeera-options-tabitem<?php echo esc_attr($menu_key == $active_tab ? ' workeera-active-tab' : '') ?>"><?php echo esc_html($menu_title) ?></a>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                    if (isset($sub_menu) && !empty($sub_menu)) {
                        // Resolve the active section the same way the settings
                        // output below does: explicit query var, else the first.
                        $active_section = array_key_first($sub_menu);
                        if (!empty($menu_section) && isset($sub_menu[$menu_section])) {
                            $active_section = $menu_section;
                        }
                        ?>
                        <div class="workeera-options-subtabs">
                            <?php
                            foreach ($sub_menu as $section_key => $section_item) {
                                $section_title = $section_item;
                                ?>
                                <a href="<?php echo esc_url(add_query_arg(array('workeera_tab' => $menu_tab, 'workeera_section' => $section_key), $page_url)) ?>" class="<?php echo esc_attr($section_key === $active_section ? 'workeera-active-tab' : '') ?>"><?php echo esc_html($section_title) ?></a>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="workeera-admin-options-wrap">
                    <?php
                    if (!empty($menu_tab) && isset($options_menu[$menu_tab]) && is_array($options_menu[$menu_tab]) && isset($options_menu[$menu_tab]['sub_menu'])) {
                        $menu_sections = $options_menu[$menu_tab]['sub_menu'];
                        $section_key = array_key_first($menu_sections);
                        if (!empty($menu_section) && isset($menu_sections[$menu_section])) {
                            $section_key = $menu_section;
                        }
                        settings_fields('workeera_' . $menu_tab . '_' . $section_key . '_options_group');
                        do_settings_sections('workeera_' . $menu_tab . '_' . $section_key . '_options_page');
                    } else if (!empty($menu_tab) && isset($options_menu[$menu_tab]) && !is_array($options_menu[$menu_tab])) {
                        settings_fields('workeera_' . $menu_tab . '_options_group');
                        do_settings_sections('workeera_' . $menu_tab . '_options_page');
                    } else {
                        // Default landing (no tab in the URL) → General tab's
                        // first sub-section ("General settings").
                        settings_fields('workeera_general_general_settings_options_group');
                        do_settings_sections('workeera_general_general_settings_options_page');
                    }
                    ?>
                    <button type="submit" class="button button-primary"><?php echo esc_html_x('Save Settings', 'Admin options', 'workeera-remote-tech-job-board') ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Filterable list of top-level options tabs.
     *
     * @return array<string, string> Tab key to label.
     */
    public static function options_menu() {
        $menu = [
            'general' => [
                'title' => esc_html_x('General', 'Admin options menu', 'workeera-remote-tech-job-board'),
                'sub_menu' => [
                    'general_settings' => esc_html_x('General settings', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                    'salary' => esc_html_x('Salary', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                ],
            ],
            'job_listings' => [
                'title' => esc_html_x('Job Listings', 'Admin options menu', 'workeera-remote-tech-job-board'),
                'sub_menu' => [
                    'jobs' => esc_html_x('Jobs', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                    'applications' => esc_html_x('Job Applications', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                ],
            ],
            'job_submission' => [
                'title' => esc_html_x('Job Submission', 'Admin options menu', 'workeera-remote-tech-job-board'),
                'sub_menu' => [
                    'jobs' => esc_html_x('Jobs', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                ],
            ],
            'company' => [
                'title' => esc_html_x('Companies', 'Admin options menu', 'workeera-remote-tech-job-board'),
                'sub_menu' => [
                    'company_settings' => esc_html_x('Company Settings', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                ],
            ],
            'candidate' => [
                'title' => esc_html_x('Candidates', 'Admin options menu', 'workeera-remote-tech-job-board'),
                'sub_menu' => [
                    'candidates' => esc_html_x('Candidates', 'Admin options sub menu', 'workeera-remote-tech-job-board'),
                ],
            ],
            'pages' => esc_html_x('Pages', 'Admin options menu', 'workeera-remote-tech-job-board'),
        ];

        return apply_filters('workeera_admin_options_navmenu', $menu);
    }

    /**
     * Print the shared admin popup-modal scaffold into the admin footer.
     */
    public function popup_modal_init() {
        ?>
        <div id="workeera-admin-popup" class="workeera-modal-popup workeera-popup-hide">
            <div class="workeera-modal-container">
                <div class="workeera-modal-inner-area">
                    <div class="workeera-modal-content-area">
                        <a href="javascript:;" class="workeera-popup-closebtn"><span class="workeera-remove-icon"></span></a>
                        <div class="workeera-modalinit-con"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Replace the dropdown options list with the currently selected post when one is set.
     *
     * @param  array  $select_options_list Existing post options.
     * @param  int    $post_id             Currently selected post ID.
     * @param  string $post_type           Expected post type.
     * @return array                       Filtered options list.
     */
    public static function selected_post_option_arr($select_options_list, $post_id, $post_type = 'page') {
        if ($post_id > 0 && get_post_type($post_id) == $post_type) {
            /* translators: %d: post ID */
            $select_options_list = [$post_id => get_the_title($post_id) . ' (' . sprintf(esc_html__('id: %d', 'workeera-remote-tech-job-board'), $post_id) . ')'];
        }
        return $select_options_list;
    }
}

new Workeera_Admin;