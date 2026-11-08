<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Main plugin bootstrap class.
 *
 * Defines plugin constants, loads includes, registers init hooks, sets up
 * the plugin locale, and enqueues front-end / admin assets.
 */
class Workeera_Plugin {

    public $version = '1.0.7';

    /**
     * Boot the plugin: set locale, define constants, include files, and
     * register hooks for menu ordering and asset enqueues.
     */
    public function __construct() {
        $this->define_constants();
        add_action('init', array($this, 'set_plugin_locale'));
        $this->includes();
        $this->init_hooks();
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', array($this, 'menu_order'), 9);
    }

    /**
     * Define a constant if it hasn't already been defined.
     *
     * @param  string $name  Constant name.
     * @param  mixed  $value Constant value.
     */
    private static function define_const($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Define plugin-wide constants (paths, URL, version, term-count cap).
     */
    private function define_constants() {
        self::define_const('WORKEERA_ABSPATH', dirname(WORKEERA_PLUGIN_FILE) . '/');
        self::define_const('WORKEERA_URL', self::root_url());
        self::define_const('WORKEERA_VERSION', $this->version);
        self::define_const('WORKEERA_TERMS_COUNT_LITE', 40);
    }

    /**
     * Load the plugin's translated strings for the current locale.
     */
    public function set_plugin_locale() {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        } else {
            // @todo Remove when start supporting WP 5.0 or later.
            $locale = is_admin() ? get_user_locale() : get_locale();
        }
        $locale = apply_filters('plugin_locale', $locale, 'workeera-remote-tech-job-board');
        unload_textdomain('workeera-remote-tech-job-board');
        load_textdomain('workeera-remote-tech-job-board', WP_LANG_DIR . '/plugins/workeera-remote-tech-job-board-' . $locale . '.mo');
        load_plugin_textdomain('workeera-remote-tech-job-board', false, WORKEERA_ABSPATH . 'languages');
    }

    /**
     * Reorder the WP admin menu so Workeera items appear together in a fixed order.
     *
     * @param  string[] $menu_order WordPress menu order array.
     * @return string[]             Reordered menu items.
     */
    public function menu_order($menu_order) {

        $workeera_posts_arr = array(
            'workeera-dashboard',
            'edit.php?post_type=workeera_job',
            'edit.php?post_type=workeera_candidate',
        );

        $workeera_posts_arr = apply_filters('workeera_main_wp_menu_list', $workeera_posts_arr);

        $workeera_menu_order = array();

        $cus_menu_order = array();

        foreach ($menu_order as $index => $item) {

            if ('workeera-dashboard' === $item) {
                $cus_menu_order[] = $item;
                $cus_menu_order[] = 'edit.php?post_type=workeera_job';
                $cus_menu_order[] = 'edit.php?post_type=workeera_candidate';
                //
                $cus_menu_order = apply_filters('workeera_main_wp_menu_order', $cus_menu_order);
                $workeera_menu_order = array_merge($workeera_menu_order, $cus_menu_order);
                //
            } elseif (!in_array($item, $workeera_posts_arr, true)) {
                $workeera_menu_order[] = $item;
            }
        }

        return $workeera_menu_order;
    }

    /**
     * Require the plugin's class and function files.
     */
    private function includes() {
        include_once WORKEERA_ABSPATH . 'includes/currencies.php';
        include_once WORKEERA_ABSPATH . 'includes/class-form-fields.php';
        include_once WORKEERA_ABSPATH . 'includes/common-functions.php';
        include_once WORKEERA_ABSPATH . 'includes/company-functions.php';
        include_once WORKEERA_ABSPATH . 'includes/job-functions.php';
        include_once WORKEERA_ABSPATH . 'includes/candidate-functions.php';

        include_once WORKEERA_ABSPATH . 'includes/global-options.php';

        // post types
        include_once WORKEERA_ABSPATH . 'includes/post-types.php';

        // custom query handler
        include_once WORKEERA_ABSPATH . 'includes/class-custom-query-handler.php';

        // cron tasks
        include_once WORKEERA_ABSPATH . 'includes/scheduled-tasks.php';

        // terms relations
        include_once WORKEERA_ABSPATH . 'includes/class-object-terms-relation.php';

        include_once WORKEERA_ABSPATH . 'includes/class-object-terms.php';

        // transients
        include_once WORKEERA_ABSPATH . 'includes/class-transients.php';

        // db schema updates when the plugin version changes
        include_once WORKEERA_ABSPATH . 'includes/class-db-updater.php';

        // Free AI credits (Hiremium trial) — loaded on both admin and front end
        // so the AI add-ons can read the trial key during front-end AI calls
        // (e.g. candidate resume parsing). Its admin-only hooks are no-ops on the
        // front end.
        include_once WORKEERA_ABSPATH . 'admin/free-ai-credits.php';

        include_once WORKEERA_ABSPATH . 'admin/admin.php';

        // Setup wizard (loaded admin-side only by the class itself).
        include_once WORKEERA_ABSPATH . 'admin/setup-wizard/class-setup-wizard.php';

        // user dashboard
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/class-user-dashboard.php';

        // listings
        include_once WORKEERA_ABSPATH . 'includes/class-listings.php';

        // listing detail
        include_once WORKEERA_ABSPATH . 'includes/class-post-detail.php';

        // job applications
        include_once WORKEERA_ABSPATH . 'includes/class-job-applications.php';

        // email notifications
        include_once WORKEERA_ABSPATH . 'includes/class-email-notifications.php';
    }
    
    /**
     * Register the front-end and admin asset enqueue hooks.
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'front_enqueues'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueues'));
        add_action('init', array($this, 'ensure_private_uploads_protected'), 20);
    }

    /**
     * Make sure existing CV / application upload folders have a hardening
     * `.htaccess` + `index.html` so direct downloads are blocked even on
     * installs that uploaded files before this protection was introduced.
     *
     * Throttled via a daily transient so we don't walk the directory tree on
     * every request.
     */
    public function ensure_private_uploads_protected() {
        if ( ! function_exists( 'workeera_protect_private_upload_dir' ) ) {
            return;
        }
        if ( get_transient( 'workeera_private_uploads_protected' ) ) {
            return;
        }

        $upload_dir   = wp_get_upload_dir();
        $base         = isset( $upload_dir['basedir'] ) ? trailingslashit( $upload_dir['basedir'] ) . 'wp-workeera' : '';
        $private_dirs = $base !== '' ? array(
            $base . '/candidate-cvs',
            $base . '/application-files',
        ) : array();

        foreach ( $private_dirs as $root ) {
            if ( ! is_dir( $root ) ) {
                continue;
            }
            workeera_protect_private_upload_dir( $root );

            // Walk one year/month deep — that's where wp_handle_upload writes.
            $year_dirs = @glob( trailingslashit( $root ) . '*', GLOB_ONLYDIR );
            if ( is_array( $year_dirs ) ) {
                foreach ( $year_dirs as $year_dir ) {
                    workeera_protect_private_upload_dir( $year_dir );
                    $month_dirs = @glob( trailingslashit( $year_dir ) . '*', GLOB_ONLYDIR );
                    if ( is_array( $month_dirs ) ) {
                        foreach ( $month_dirs as $month_dir ) {
                            workeera_protect_private_upload_dir( $month_dir );
                        }
                    }
                }
            }
        }

        set_transient( 'workeera_private_uploads_protected', 1, DAY_IN_SECONDS );
    }

    /**
     * Build the JS-localized variables shared by front-end and admin scripts.
     *
     * @return array Associative array of strings, URLs, and settings consumed by JS.
     */
    public static function common_localize_vars() {
        global $workeera_plugin_settings;

        $default_salary_unit = isset($workeera_plugin_settings['general_settings']['salary_unit']) ? $workeera_plugin_settings['general_settings']['salary_unit'] : 'hourly';

        $salary_ranges = workeera_salary_ranges();
        $salary_range = isset($salary_ranges[$default_salary_unit]) ? $salary_ranges[$default_salary_unit] : [];
        $currency_settings = workeera_currency_settings();

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = !empty($dashboard_page) && get_post_type($dashboard_page) == 'page' ? get_permalink($dashboard_page) : '';
        $vars = [
            'ajax_url' => esc_url(admin_url('admin-ajax.php')),
            'site_url' => esc_url(home_url('/')),
            'admin_url' => esc_url(admin_url('/')),
            'plugin_url' => self::root_url(),
            'nonce' => esc_attr(wp_create_nonce('workeera_nonce')),
            'confirm_comon_title' => esc_html_x('Confirmation', 'Confirm box title', 'workeera-remote-tech-job-board'),
            'delet_common_msg' => esc_html__('Do you really want to delete it?', 'workeera-remote-tech-job-board'),
            'delet_btn_lbl' => esc_html_x('Delete', 'Confirm Popup', 'workeera-remote-tech-job-board'),
            'cancel_btn_lbl' => esc_html_x('Cancel', 'Confirm Popup', 'workeera-remote-tech-job-board'),
            'conf_ok_lbl' => esc_html_x('OK', 'Confirm Popup', 'workeera-remote-tech-job-board'),
            'type_to_search' => esc_html_x('Please enter 1 or more characters', 'Select2 dropdown', 'workeera-remote-tech-job-board'),
            'select2_searching' => esc_html_x('Searching...', 'Select2 dropdown', 'workeera-remote-tech-job-board'),
            'select2_loading_more' => esc_html_x('Loading more results...', 'Select2 dropdown', 'workeera-remote-tech-job-board'),
            'select2_no_results' => esc_html_x('No results found', 'Select2 dropdown', 'workeera-remote-tech-job-board'),
            'select2_only_select' => esc_html_x('You can only select', 'Select2 dropdown', 'workeera-remote-tech-job-board'),
            'select2_sectors' => esc_html_x('sectors', 'Select2 dropdown', 'workeera-remote-tech-job-board'),
            'day_sun' => esc_html_x('Sun', 'Week days short', 'workeera-remote-tech-job-board'),
            'day_mon' => esc_html_x('Mon', 'Week days short', 'workeera-remote-tech-job-board'),
            'day_tue' => esc_html_x('Tue', 'Week days short', 'workeera-remote-tech-job-board'),
            'day_wed' => esc_html_x('Wed', 'Week days short', 'workeera-remote-tech-job-board'),
            'day_thu' => esc_html_x('Thu', 'Week days short', 'workeera-remote-tech-job-board'),
            'day_fri' => esc_html_x('Fri', 'Week days short', 'workeera-remote-tech-job-board'),
            'day_sat' => esc_html_x('Sat', 'Week days short', 'workeera-remote-tech-job-board'),
            'currency_settings' => $currency_settings,
            'default_salary_unit' => $default_salary_unit,
            'salary_ranges' => $salary_ranges,
            'salary_range' => $salary_range,
            'login_url' => wp_login_url($dashboard_page_url),
            // Skill ↔ sector mapping for the paired Sector → Skills filter.
            // Used by common-functions.js to filter the Skills field whenever
            // a Sector field on the same form changes. Statically cached so
            // it only queries once per request.
            'skills_sector_data' => class_exists('Workeera_Object_Terms') ? Workeera_Object_Terms::skills_sector_map_for_localize() : [
                'map_id'            => (object) [],
                'map_slug'          => (object) [],
                'sector_slug_to_id' => (object) [],
            ],
        ];

        return $vars;
    }

    /**
     * Enqueue plugin styles and scripts on front-end pages.
     */
    public function front_enqueues() {

        wp_enqueue_style('workeera-styles', self::root_url() . 'css/common-styles.css', array(), WORKEERA_VERSION);

        wp_register_script('workeera-scripts', self::root_url() . 'js/common-functions.js', array('jquery'), WORKEERA_VERSION, true);
        wp_localize_script('workeera-scripts', 'workeera_func_str', self::common_localize_vars());
    }

    /**
     * Enqueue plugin styles and scripts on wp-admin pages.
     */
    public function admin_enqueues() {
        wp_enqueue_style('select2', self::root_url() . 'css/select2/select2.min.css', array(), WORKEERA_VERSION);

        wp_enqueue_style('workeera-styles', self::root_url() . 'css/common-styles.css', array(), WORKEERA_VERSION);
        wp_enqueue_style('workeera-admin-styles', self::root_url() . 'css/admin/admin-styles.css', array('workeera-styles'), WORKEERA_VERSION);

        wp_enqueue_script('select2', self::root_url() . 'js/select2/select2.full.min.js', array('jquery'), WORKEERA_VERSION, true);
        wp_enqueue_script('workeera-scripts', self::root_url() . 'js/common-functions.js', array('jquery'), WORKEERA_VERSION, true);
        wp_localize_script('workeera-scripts', 'workeera_func_str', self::common_localize_vars());

        wp_enqueue_script('workeera-admin-scripts', self::root_url() . 'js/admin/admin-scripts.js', array('workeera-scripts', 'jquery-ui-sortable'), WORKEERA_VERSION, true);
        $localize_str = [
            'admin_url' => esc_url(admin_url('/')),
            'insert_img_lbl' => esc_html__('Insert image', 'workeera-remote-tech-job-board'),
            'use_img_lbl' => esc_html__('Use this image', 'workeera-remote-tech-job-board'),
            'use_imgs' => esc_html__('Use these pictures', 'workeera-remote-tech-job-board'),
            'insert_video_lbl' => esc_html__('Insert video', 'workeera-remote-tech-job-board'),
            'use_video_lbl' => esc_html__('Use this video', 'workeera-remote-tech-job-board'),
            'confirm_comon_title' => esc_html_x('Confirmation', 'Confirm box title', 'workeera-remote-tech-job-board'),
            'srch_post_itms' => esc_html__('Search for post items', 'workeera-remote-tech-job-board'),
            'srch_term_itms' => esc_html__('Search for terms', 'workeera-remote-tech-job-board'),
            'remove' => esc_html__('Remove', 'workeera-remote-tech-job-board'),
            'nonce' => esc_attr(wp_create_nonce('workeera_admin_nonce')),
            // Import data strings.
            'importdata_confirm_title'   => esc_html_x( 'Confirm Import', 'Import confirm title', 'workeera-remote-tech-job-board' ),
            /* translators: %s: taxonomy plural name */
            'importdata_force_confirm_msg'  => esc_html_x( 'Are you sure you want to import data? This will permanently delete all existing %s data and replace it with the imported data. This action cannot be undone.', 'Import confirm message', 'workeera-remote-tech-job-board' ),
            'importdata_confirm_msg'        => esc_html_x( 'Are you sure you want to import data?', 'Import confirm message', 'workeera-remote-tech-job-board' ),
            'importdata_confirm_btn'        => esc_html_x( 'Yes, Import', 'Import confirm button', 'workeera-remote-tech-job-board' ),
            /* translators: %s: taxonomy plural name */
            'importdata_remove_confirm_msg' => esc_html_x( 'Are you sure you want to delete data? This will permanently delete all existing %s data.', 'Delete confirm message', 'workeera-remote-tech-job-board' ),
            'importdata_confirm_remove_btn' => esc_html_x( 'Yes, Delete', 'Delete confirm button', 'workeera-remote-tech-job-board' ),
            'importdata_cancel_btn'         => esc_html_x( 'Cancel', 'Import cancel button', 'workeera-remote-tech-job-board' ),
            'importdata_processing'         => esc_html_x( 'Importing, please wait...', 'Import processing message', 'workeera-remote-tech-job-board' ),
            'importdata_success'            => esc_html_x( 'Data imported successfully. Reloading...', 'Import success message', 'workeera-remote-tech-job-board' ),
            'importdata_upload_error'       => esc_html_x( 'Upload failed. Please try again.', 'Import upload error', 'workeera-remote-tech-job-board' ),
            'importdata_no_sample'          => esc_html_x( 'No sample data file found for this taxonomy.', 'Import no sample error', 'workeera-remote-tech-job-board' ),
            'importdata_invalid_type'       => esc_html_x( 'Only CSV files are allowed.', 'Import file type error', 'workeera-remote-tech-job-board' ),
            'importdata_file_too_large'     => esc_html_x( 'File size must not exceed 5 MB.', 'Import file size error', 'workeera-remote-tech-job-board' ),
        ];
        wp_localize_script('workeera-admin-scripts', 'workeera_adminscript_str', $localize_str);
    }

    /**
     * Return the plugin's base URL with a trailing slash.
     *
     * @return string Escaped plugin root URL.
     */
    public static function root_url() {
        return esc_url(trailingslashit(plugins_url('/', WORKEERA_PLUGIN_FILE)));
    }
}

new Workeera_Plugin;
