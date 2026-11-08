<?php

defined( 'ABSPATH' ) || exit;

/**
 * Workeera setup wizard.
 *
 * Runs a 3-step branded setup flow on first install:
 *   1. Welcome screen.
 *   2. Checklist of default settings / pages / sample taxonomy data.
 *   3. Done screen with thank-you and customisation links.
 *
 * Lifecycle stored in option `workeera_setup_wizard_status`:
 *     pending   → first install, redirect into the wizard on next admin load.
 *     skipped   → user clicked "Skip" — show the admin notice until dismissed.
 *     completed → wizard finished successfully — no more notices or redirects.
 *
 * The admin notice can be dismissed permanently via the option
 * `workeera_setup_wizard_notice_dismissed` (`1` = dismissed).
 */
class Workeera_Setup_Wizard {

    /** Slug for the hidden submenu / wizard page. */
    const PAGE_SLUG = 'workeera-setup-wizard';

    /** Option name tracking the wizard lifecycle. */
    const STATUS_OPTION = 'workeera_setup_wizard_status';

    /** Option name marking the admin notice as permanently dismissed. */
    const NOTICE_DISMISSED_OPTION = 'workeera_setup_wizard_notice_dismissed';

    /** Nonce action shared by the wizard's AJAX endpoints. */
    const NONCE_ACTION = 'workeera_setup_wizard';

    /**
     * Register WordPress hooks for the wizard (admin-only).
     */
    public function __construct() {
        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_init', [ $this, 'maybe_redirect_to_wizard' ], 1 );
        add_action( 'admin_menu', [ $this, 'register_hidden_submenu' ], 99 );
        add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_wizard_assets' ] );

        // AJAX endpoints.
        add_action( 'wp_ajax_workeera_setup_wizard_apply',           [ $this, 'ajax_apply' ] );
        add_action( 'wp_ajax_workeera_setup_wizard_skip',            [ $this, 'ajax_skip' ] );
        add_action( 'wp_ajax_workeera_setup_wizard_dismiss_notice',  [ $this, 'ajax_dismiss_notice' ] );
    }

    // -------------------------------------------------------------------------
    // Redirect + menu + assets
    // -------------------------------------------------------------------------

    /**
     * Redirect to the wizard on the next admin load when status is "pending".
     *
     * Skips AJAX, cron, multisite-network activation, REST and pages where the
     * user is already on the wizard. Capability-gated to manage_options.
     */
    public function maybe_redirect_to_wizard() {
        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( is_multisite() && is_network_admin() ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = get_option( self::STATUS_OPTION, '' );
        if ( 'pending' !== $status ) {
            return;
        }

        // Already on the wizard page.
        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( self::PAGE_SLUG === $current_page ) {
            return;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        exit;
    }

    /**
     * Register the hidden submenu page under the Workeera options menu.
     */
    public function register_hidden_submenu() {
        add_submenu_page(
            'workeera-options',
            esc_html_x( 'Workeera Setup Wizard', 'Setup wizard page title', 'workeera-remote-tech-job-board' ),
            esc_html_x( 'Setup Wizard', 'Setup wizard menu title', 'workeera-remote-tech-job-board' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_wizard_page' ]
        );
    }

    /**
     * Enqueue wizard CSS / JS, but only on the wizard screen itself.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_wizard_assets( $hook ) {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, self::PAGE_SLUG ) ) {
            return;
        }

        wp_enqueue_style(
            'workeera-setup-wizard',
            Workeera_Plugin::root_url() . 'css/admin/setup-wizard.css',
            [],
            WORKEERA_VERSION
        );

        wp_enqueue_script(
            'workeera-setup-wizard',
            Workeera_Plugin::root_url() . 'js/admin/setup-wizard.js',
            [ 'jquery' ],
            WORKEERA_VERSION,
            true
        );

        wp_localize_script(
            'workeera-setup-wizard',
            'workeera_setup_wizard_data',
            [
                'ajax_url'      => esc_url( admin_url( 'admin-ajax.php' ) ),
                'nonce'         => esc_attr( wp_create_nonce( self::NONCE_ACTION ) ),
                'dashboard_url' => esc_url( admin_url( 'index.php' ) ),
                'settings_url'  => esc_url( admin_url( 'admin.php?page=workeera-options' ) ),
                'strings'       => [
                    'running'        => esc_html__( 'Running setup, please wait...', 'workeera-remote-tech-job-board' ),
                    'generic_error'  => esc_html__( 'Something went wrong. Please try again.', 'workeera-remote-tech-job-board' ),
                    'network_error'  => esc_html__( 'Network error. Please check your connection and try again.', 'workeera-remote-tech-job-board' ),
                    'log_empty'      => esc_html__( 'Nothing was changed — every action was already in place.', 'workeera-remote-tech-job-board' ),
                ],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Admin notice
    // -------------------------------------------------------------------------

    /**
     * Render the "complete the setup wizard" admin notice across the dashboard.
     *
     * The notice appears on every admin screen except the wizard page itself,
     * and only when the wizard status is still pending / skipped AND the user
     * has not dismissed the notice permanently.
     */
    public function render_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( self::PAGE_SLUG === $current_page ) {
            return;
        }

        $status = get_option( self::STATUS_OPTION, '' );
        if ( 'pending' !== $status && 'skipped' !== $status ) {
            return;
        }

        $dismissed = get_option( self::NOTICE_DISMISSED_OPTION, '' );
        if ( '1' === (string) $dismissed ) {
            return;
        }

        $wizard_url  = esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        $dismiss_lbl = esc_html__( 'Dismiss permanently', 'workeera-remote-tech-job-board' );
        $run_lbl     = esc_html__( 'Run Setup', 'workeera-remote-tech-job-board' );
        $msg         = esc_html__( 'Welcome to Workeera! Finish the quick setup to get your job board ready in minutes.', 'workeera-remote-tech-job-board' );
        ?>
        <div class="notice notice-info is-dismissible workeera-setup-wizard-notice">
            <p><strong><?php echo esc_html( $msg ); ?></strong></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( $wizard_url ); ?>">
                    <?php echo esc_html( $run_lbl ); ?>
                </a>
                <a href="#" class="workeera-setup-wizard-notice-dismiss" style="margin-left:8px;">
                    <?php echo esc_html( $dismiss_lbl ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Wizard page renderer
    // -------------------------------------------------------------------------

    /**
     * Render the full-bleed setup wizard page.
     *
     * Outputs a single root container with three sibling step panes; only one
     * is visible at a time, switched via the `workeera-sw-step--active` class
     * by the wizard JS.
     */
    public function render_wizard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'workeera-remote-tech-job-board' ) );
        }

        $sample_taxonomies = $this->get_sample_taxonomy_options();
        ?>
        <div class="workeera-sw-page">
            <div class="workeera-sw">
                <div class="workeera-sw-card">

                    <div class="workeera-sw-stepper" aria-hidden="true">
                        <div class="workeera-sw-stepper-item workeera-sw-stepper-item--active" data-step-marker="1">
                            <span class="workeera-sw-stepper-dot">1</span>
                            <span class="workeera-sw-stepper-label"><?php echo esc_html_x( 'Welcome', 'Setup wizard step label', 'workeera-remote-tech-job-board' ); ?></span>
                        </div>
                        <div class="workeera-sw-stepper-item" data-step-marker="2">
                            <span class="workeera-sw-stepper-dot">2</span>
                            <span class="workeera-sw-stepper-label"><?php echo esc_html_x( 'Setup', 'Setup wizard step label', 'workeera-remote-tech-job-board' ); ?></span>
                        </div>
                        <div class="workeera-sw-stepper-item" data-step-marker="3">
                            <span class="workeera-sw-stepper-dot">3</span>
                            <span class="workeera-sw-stepper-label"><?php echo esc_html_x( 'Done', 'Setup wizard step label', 'workeera-remote-tech-job-board' ); ?></span>
                        </div>
                    </div>

                    <div class="workeera-sw-error" style="display:none;" role="alert"></div>

                    <?php $this->render_step_welcome(); ?>
                    <?php $this->render_step_checklist( $sample_taxonomies ); ?>
                    <?php $this->render_step_done(); ?>

                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render step 1 — welcome screen.
     */
    private function render_step_welcome() {
        ?>
        <div class="workeera-sw-step workeera-sw-step--active" data-step="1">
            <div class="workeera-sw-logo">
                <?php echo wp_kses( (string) $this->svg_logo(), workeera_svg_allowed_tags() ); ?>
            </div>
            <h1 class="workeera-sw-title"><?php echo esc_html__( 'Welcome to Workeera', 'workeera-remote-tech-job-board' ); ?></h1>
            <p class="workeera-sw-subtitle">
                <?php echo esc_html__( "Let's set up your job board in 3 quick steps. We'll create default settings, the dashboard / jobs pages, and a starter set of skills and sectors.", 'workeera-remote-tech-job-board' ); ?>
            </p>
            <div class="workeera-sw-actions">
                <button type="button" class="workeera-sw-btn workeera-sw-next" data-target-step="2">
                    <?php echo esc_html__( "Let's Get Started", 'workeera-remote-tech-job-board' ); ?>
                </button>
            </div>
            <div class="workeera-sw-secondary">
                <a href="#" class="workeera-sw-link workeera-sw-skip">
                    <?php echo esc_html__( 'Skip for now', 'workeera-remote-tech-job-board' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render step 2 — configuration checklist.
     *
     * @param array $sample_taxonomies Map of taxonomy slug → plural label
     *                                 to render as the sample-data checklist.
     */
    private function render_step_checklist( $sample_taxonomies ) {
        ?>
        <div class="workeera-sw-step" data-step="2">
            <h1 class="workeera-sw-title"><?php echo esc_html__( 'What would you like to set up?', 'workeera-remote-tech-job-board' ); ?></h1>
            <p class="workeera-sw-subtitle">
                <?php echo esc_html__( "Uncheck anything you'd rather configure manually later. All items are recommended.", 'workeera-remote-tech-job-board' ); ?>
            </p>

            <form class="workeera-sw-form" onsubmit="return false;">

                <fieldset class="workeera-sw-checklist-group">
                    <legend class="workeera-sw-checklist-group-title">
                        <?php echo esc_html__( 'Default plugin settings', 'workeera-remote-tech-job-board' ); ?>
                    </legend>
                    <label class="workeera-sw-checklist-item">
                        <input type="checkbox" name="settings_defaults" value="1" checked />
                        <span class="workeera-sw-checklist-body">
                            <span class="workeera-sw-checklist-label">
                                <?php echo esc_html__( 'Apply default plugin settings', 'workeera-remote-tech-job-board' ); ?>
                            </span>
                            <span class="workeera-sw-checklist-help">
                                <?php echo esc_html__( 'Salary unit, currency, listing options, candidate limits, etc.', 'workeera-remote-tech-job-board' ); ?>
                            </span>
                        </span>
                    </label>
                </fieldset>

                <fieldset class="workeera-sw-checklist-group">
                    <legend class="workeera-sw-checklist-group-title">
                        <?php echo esc_html__( 'Default pages', 'workeera-remote-tech-job-board' ); ?>
                    </legend>

                    <label class="workeera-sw-checklist-item">
                        <input type="checkbox" name="page_user_dashboard" value="1" checked />
                        <span class="workeera-sw-checklist-body">
                            <span class="workeera-sw-checklist-label">
                                <?php echo esc_html__( 'User Dashboard page (with [workeera_user_dashboard] shortcode)', 'workeera-remote-tech-job-board' ); ?>
                            </span>
                        </span>
                    </label>

                    <label class="workeera-sw-checklist-item">
                        <input type="checkbox" name="page_jobs" value="1" checked />
                        <span class="workeera-sw-checklist-body">
                            <span class="workeera-sw-checklist-label">
                                <?php echo esc_html__( 'Jobs listing page (with [workeera_jobs] shortcode)', 'workeera-remote-tech-job-board' ); ?>
                            </span>
                        </span>
                    </label>

                    <label class="workeera-sw-checklist-item">
                        <input type="checkbox" name="page_terms" value="1" checked />
                        <span class="workeera-sw-checklist-body">
                            <span class="workeera-sw-checklist-label">
                                <?php echo esc_html__( 'Terms & Conditions page', 'workeera-remote-tech-job-board' ); ?>
                            </span>
                        </span>
                    </label>
                </fieldset>

                <?php if ( ! empty( $sample_taxonomies ) ) : ?>
                    <fieldset class="workeera-sw-checklist-group">
                        <legend class="workeera-sw-checklist-group-title">
                            <?php echo esc_html__( 'Sample taxonomy data', 'workeera-remote-tech-job-board' ); ?>
                        </legend>

                        <?php foreach ( $sample_taxonomies as $slug => $label ) : ?>
                            <label class="workeera-sw-checklist-item">
                                <input type="checkbox" name="sample_data[]" value="<?php echo esc_attr( $slug ); ?>" checked />
                                <span class="workeera-sw-checklist-body">
                                    <span class="workeera-sw-checklist-label">
                                        <?php echo esc_html( $label ); ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <div class="workeera-sw-actions">
                    <button type="button" class="workeera-sw-btn workeera-sw-run">
                        <?php echo esc_html__( 'Run Setup', 'workeera-remote-tech-job-board' ); ?>
                    </button>
                </div>
                <div class="workeera-sw-secondary">
                    <a href="#" class="workeera-sw-link workeera-sw-back" data-target-step="1">
                        <?php echo esc_html__( 'Back', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                    <span class="workeera-sw-sep">·</span>
                    <a href="#" class="workeera-sw-link workeera-sw-skip">
                        <?php echo esc_html__( 'Skip for now', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                </div>

            </form>
        </div>
        <?php
    }

    /**
     * Render step 3 — completion screen with thank-you + customisation panel.
     */
    private function render_step_done() {
        $dashboard_url = esc_url( admin_url( 'admin.php?page=workeera-dashboard' ) );
        $settings_url  = esc_url( admin_url( 'admin.php?page=workeera-options' ) );
        ?>
        <div class="workeera-sw-step" data-step="3">
            <div class="workeera-sw-done-icon">
                <?php echo wp_kses( (string) $this->svg_check(), workeera_svg_allowed_tags() ); ?>
            </div>
            <h1 class="workeera-sw-title"><?php echo esc_html__( 'Setup Complete!', 'workeera-remote-tech-job-board' ); ?></h1>
            <p class="workeera-sw-subtitle">
                <?php echo esc_html__( 'Thanks for installing Workeera. Your job board is ready — you can start adding jobs, accepting candidates, or fine-tune your settings whenever you like.', 'workeera-remote-tech-job-board' ); ?>
            </p>

            <div class="workeera-sw-log" aria-live="polite"></div>

            <div class="workeera-sw-custom-panel">
                <h2 class="workeera-sw-custom-title">
                    <?php echo esc_html__( 'Customisation requests?', 'workeera-remote-tech-job-board' ); ?>
                </h2>
                <p class="workeera-sw-custom-body">
                    <?php echo esc_html__( 'Need design tweaks, integrations, or custom features? Our team can help build them out for your job board.', 'workeera-remote-tech-job-board' ); ?>
                </p>
                <div class="workeera-sw-custom-links">
                    <a class="workeera-sw-btn workeera-sw-btn--ghost" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://workeera.com/' ); ?>">
                        <?php echo esc_html__( 'Visit Workeera', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                    <a class="workeera-sw-btn workeera-sw-btn--ghost" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://workeera.com/contact/' ); ?>">
                        <?php echo esc_html__( 'Contact Us', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                </div>
            </div>

            <div class="workeera-sw-actions workeera-sw-actions--row">
                <a class="workeera-sw-btn" href="<?php echo esc_url( $dashboard_url ); ?>">
                    <?php echo esc_html__( 'Go to Workeera Dashboard', 'workeera-remote-tech-job-board' ); ?>
                </a>
                <a class="workeera-sw-btn workeera-sw-btn--ghost" href="<?php echo esc_url( $settings_url ); ?>">
                    <?php echo esc_html__( 'Open Settings', 'workeera-remote-tech-job-board' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /**
     * AJAX: apply the user's checklist selections.
     *
     * Creates default settings, default pages, and runs sample taxonomy
     * imports based on the selected checkboxes. Always idempotent —
     * existing options/pages are reused rather than overwritten.
     */
    public function ajax_apply() {
        check_ajax_referer( self::NONCE_ACTION, 'workeera_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [
                'error' => '1',
                'msg'   => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ),
            ] );
        }

        $settings_defaults    = isset( $_POST['settings_defaults'] )    && '1' === sanitize_key( wp_unslash( $_POST['settings_defaults'] ) );
        $page_user_dashboard  = isset( $_POST['page_user_dashboard'] )  && '1' === sanitize_key( wp_unslash( $_POST['page_user_dashboard'] ) );
        $page_jobs            = isset( $_POST['page_jobs'] )            && '1' === sanitize_key( wp_unslash( $_POST['page_jobs'] ) );
        $page_terms           = isset( $_POST['page_terms'] )           && '1' === sanitize_key( wp_unslash( $_POST['page_terms'] ) );

        $sample_data_raw = isset( $_POST['sample_data'] ) ? (array) wp_unslash( $_POST['sample_data'] ) : [];
        $sample_data     = array_values( array_filter( array_map( 'sanitize_key', $sample_data_raw ) ) );

        $log = [];

        if ( $settings_defaults ) {
            $log = array_merge( $log, $this->apply_default_settings() );
        }

        if ( $page_user_dashboard ) {
            $log = array_merge( $log, $this->apply_default_page( 'user_dashboard' ) );
        }
        if ( $page_jobs ) {
            $log = array_merge( $log, $this->apply_default_page( 'jobs' ) );
        }
        if ( $page_terms ) {
            $log = array_merge( $log, $this->apply_default_page( 'terms' ) );
        }

        if ( ! empty( $sample_data ) ) {
            $allowed = array_keys( $this->get_sample_taxonomy_options() );
            foreach ( $sample_data as $tax_slug ) {
                if ( ! in_array( $tax_slug, $allowed, true ) ) {
                    continue;
                }
                $log = array_merge( $log, $this->apply_sample_taxonomy( $tax_slug ) );
            }
        }

        // Mark wizard as completed.
        update_option( self::STATUS_OPTION, 'completed' );

        wp_send_json( [
            'error' => '0',
            'log'   => array_map( 'sanitize_text_field', $log ),
        ] );
    }

    /**
     * AJAX: mark the wizard as skipped for now.
     */
    public function ajax_skip() {
        check_ajax_referer( self::NONCE_ACTION, 'workeera_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [
                'error' => '1',
                'msg'   => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ),
            ] );
        }

        update_option( self::STATUS_OPTION, 'skipped' );

        wp_send_json( [ 'error' => '0' ] );
    }

    /**
     * AJAX: dismiss the admin notice permanently.
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( self::NONCE_ACTION, 'workeera_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [
                'error' => '1',
                'msg'   => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ),
            ] );
        }

        update_option( self::NOTICE_DISMISSED_OPTION, 1 );

        wp_send_json( [ 'error' => '0' ] );
    }

    // -------------------------------------------------------------------------
    // Apply implementations
    // -------------------------------------------------------------------------

    /**
     * Seed the four default plugin-settings option groups when missing.
     *
     * Each group is only written when the option doesn't already exist —
     * we never overwrite values the admin may have already configured.
     *
     * @return array<int, string> Log lines describing what was written.
     */
    private function apply_default_settings() {
        $log = [];

        $defaults = [
            'workeera_general_settings' => [
                'default_currency'         => 'USD',
                'salary_display_format'    => 'full',
                'salary_unit_field'        => '',
                'salary_unit'              => 'hourly',
                'remove_data_on_uninstall' => 'off',
            ],
            'workeera_job_listings_settings' => [
                'items_per_page'        => 10,
                'location_filter_type'  => 'countries_cities',
                'allow_listing_filters' => 'on',
            ],
            'workeera_job_submission_settings' => [
                'max_jobs_submission'     => 20,
                'job_apply_method'        => 'both',
                'listing_duration'        => 30,
                'allow_location_type'     => 'on',
                'job_description_max_len' => 5000,
            ],
            'workeera_candidate_settings' => [
                'max_job_applications'      => 20,
                'max_cv_files'              => 3,
                'max_cv_file_size'          => 5242880,
                'cv_file_types'             => [ 'pdf', 'docx' ],
                'candidate_default_status'  => 'approved',
            ],
        ];

        foreach ( $defaults as $option_name => $values ) {
            $existing = get_option( $option_name, false );
            if ( false !== $existing ) {
                /* translators: %s: option name */
                $log[] = sprintf( esc_html__( 'Skipped %s (already saved).', 'workeera-remote-tech-job-board' ), $option_name );
                continue;
            }
            add_option( $option_name, $values );
            /* translators: %s: option name */
            $log[] = sprintf( esc_html__( 'Created default settings: %s', 'workeera-remote-tech-job-board' ), $option_name );
        }

        return $log;
    }

    /**
     * Create a default plugin page when one isn't already linked / present.
     *
     * @param  string $key One of 'user_dashboard', 'jobs', 'terms'.
     * @return array<int, string> Log lines describing the action.
     */
    private function apply_default_page( $key ) {
        $log = [];

        $config = $this->page_blueprint( $key );
        if ( empty( $config ) ) {
            return $log;
        }

        $settings_key = $config['settings_key'];
        $slug         = $config['slug'];
        $title        = $config['title'];
        $content      = $config['content'];

        $pages = get_option( 'workeera_pages_settings', [] );
        if ( ! is_array( $pages ) ) {
            $pages = [];
        }

        $existing_id = isset( $pages[ $settings_key ] ) ? absint( $pages[ $settings_key ] ) : 0;
        if ( $existing_id > 0 && 'page' === get_post_type( $existing_id ) ) {
            /* translators: 1: page label, 2: page ID */
            $log[] = sprintf( esc_html__( 'Page already configured: %1$s (#%2$d)', 'workeera-remote-tech-job-board' ), $title, $existing_id );
            return $log;
        }

        // Reuse an existing page that already lives at the desired slug, if any.
        $existing_page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $existing_page && isset( $existing_page->ID ) ) {
            $pages[ $settings_key ] = (int) $existing_page->ID;
            update_option( 'workeera_pages_settings', $pages );
            /* translators: 1: page title, 2: page ID */
            $log[] = sprintf( esc_html__( 'Linked existing page: %1$s (#%2$d)', 'workeera-remote-tech-job-board' ), $title, (int) $existing_page->ID );
            return $log;
        }

        $new_id = wp_insert_post( [
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => $slug,
            'post_content' => $content,
        ] );

        if ( is_wp_error( $new_id ) || empty( $new_id ) ) {
            /* translators: %s: page title */
            $log[] = sprintf( esc_html__( 'Failed to create page: %s', 'workeera-remote-tech-job-board' ), $title );
            return $log;
        }

        $pages[ $settings_key ] = (int) $new_id;
        update_option( 'workeera_pages_settings', $pages );

        /* translators: 1: page title, 2: page ID */
        $log[] = sprintf( esc_html__( 'Created page: %1$s (#%2$d)', 'workeera-remote-tech-job-board' ), $title, (int) $new_id );

        return $log;
    }

    /**
     * Run a synchronous sample-data import for one taxonomy.
     *
     * @param  string $tax_slug Taxonomy slug (e.g. 'sectors').
     * @return array<int, string> Log lines describing the action.
     */
    private function apply_sample_taxonomy( $tax_slug ) {
        $log = [];

        if ( ! class_exists( 'Workeera_Import_Data' ) ) {
            return $log;
        }

        $tax_list = Workeera_Custom_Taxonomies::list();
        $label    = isset( $tax_list[ $tax_slug ]['plural_name'] ) ? $tax_list[ $tax_slug ]['plural_name'] : $tax_slug;

        $result = Workeera_Import_Data::run_sample_import_sync( $tax_slug );

        if ( ! empty( $result['error'] ) ) {
            /* translators: 1: taxonomy plural name, 2: error message */
            $log[] = sprintf( esc_html__( 'Failed to import %1$s: %2$s', 'workeera-remote-tech-job-board' ), $label, $result['error'] );
            return $log;
        }

        $imported = isset( $result['imported'] ) ? (int) $result['imported'] : 0;
        /* translators: 1: taxonomy plural name, 2: number of terms imported */
        $log[] = sprintf( esc_html__( 'Imported %1$s (%2$d terms)', 'workeera-remote-tech-job-board' ), $label, $imported );

        return $log;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the ordered list of taxonomies that have a bundled sample CSV.
     *
     * @return array<string, string> Map of taxonomy slug → plural label.
     */
    private function get_sample_taxonomy_options() {
        $order = [ 'sectors', 'skills', 'jobtypes', 'timezones', 'benefits', 'experiences', 'countries', 'cities' ];

        if ( ! class_exists( 'Workeera_Custom_Taxonomies' ) || ! class_exists( 'Workeera_Import_Data' ) ) {
            return [];
        }

        $tax_list = Workeera_Custom_Taxonomies::list();
        $options  = [];

        foreach ( $order as $slug ) {
            if ( ! isset( $tax_list[ $slug ] ) ) {
                continue;
            }
            $path = Workeera_Import_Data::sample_file_path( $slug );
            if ( empty( $path ) ) {
                continue;
            }
            $options[ $slug ] = isset( $tax_list[ $slug ]['plural_name'] ) ? $tax_list[ $slug ]['plural_name'] : $slug;
        }

        // Append any other taxonomies that have a CSV but weren't in $order.
        foreach ( $tax_list as $slug => $cfg ) {
            if ( isset( $options[ $slug ] ) ) {
                continue;
            }
            $path = Workeera_Import_Data::sample_file_path( $slug );
            if ( empty( $path ) ) {
                continue;
            }
            $options[ $slug ] = isset( $cfg['plural_name'] ) ? $cfg['plural_name'] : $slug;
        }

        return $options;
    }

    /**
     * Return the page-creation blueprint for a given default-page key.
     *
     * @param  string $key One of 'user_dashboard', 'jobs', 'terms'.
     * @return array       Empty array when the key is unknown.
     */
    private function page_blueprint( $key ) {
        switch ( $key ) {
            case 'user_dashboard':
                return [
                    'settings_key' => 'user_dashboard_page',
                    'slug'         => 'user-dashboard',
                    'title'        => esc_html__( 'User Dashboard', 'workeera-remote-tech-job-board' ),
                    'content'      => '[workeera_user_dashboard]',
                ];

            case 'jobs':
                return [
                    'settings_key' => 'jobs_page',
                    'slug'         => 'jobs',
                    'title'        => esc_html__( 'Jobs', 'workeera-remote-tech-job-board' ),
                    'content'      => '[workeera_jobs]',
                ];

            case 'terms':
                return [
                    'settings_key' => 'terms_page',
                    'slug'         => 'terms-and-conditions',
                    'title'        => esc_html__( 'Terms and Conditions', 'workeera-remote-tech-job-board' ),
                    'content'      => __( 'These terms and conditions outline the rules and regulations for the use of this website and its services. Please review and edit this page to fit your organisation before publishing.', 'workeera-remote-tech-job-board' ),
                ];
        }

        return [];
    }

    /**
     * Inline SVG used as the Workeera logo on step 1.
     *
     * @return string SVG markup.
     */
    private function svg_logo() {
        return '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>';
    }

    /**
     * Inline SVG used as the done-step success icon.
     *
     * @return string SVG markup.
     */
    private function svg_check() {
        return '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    }
}

new Workeera_Setup_Wizard();
