<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Front-end user dashboard controller — assets, routing, and shortcode rendering.
 */
class Workeera_User_Dashboard {

    /**
     * Wire up hooks for assets, body class, dashboard init, and the shortcode.
     */
    public function __construct()
    {
        $this->includes();
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 5);
        add_filter('body_class', [ $this, 'body_class' ], 5);
        add_action('wp', [ $this, 'user_dashboard_init' ], 5);
        add_shortcode( 'workeera_user_dashboard', [ $this, 'user_dashboard' ] );
        add_filter( 'document_title_parts', [ $this, 'custom_page_title' ], 20 );
    }

    /**
     * Load the dashboard sub-module files.
     */
    private function includes() {
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/dashboard-common.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/manage-jobs.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/my-companies.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/job-posting.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/resume-detail.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/employer-profile.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/candidate-jobs.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/job-applications.php';
        include_once WORKEERA_ABSPATH . 'includes/user-dashboard/cv-manager.php';
    }

    /**
     * Enqueue dashboard styles, scripts, and localized strings on the dashboard page.
     */
    public function enqueue_scripts() {
        global $workeera_plugin_settings;

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if (!empty($dashboard_page) && get_post_type($dashboard_page) == 'page' && is_page($dashboard_page)) {
            wp_enqueue_style('select2', Workeera_Plugin::root_url() . 'css/select2/select2.min.css', array(), WORKEERA_VERSION);
            wp_enqueue_style('workeera-dashboard-styles', Workeera_Plugin::root_url() . 'css/dashboard-styles.css', array('workeera-styles'), WORKEERA_VERSION);

            wp_enqueue_script('select2', Workeera_Plugin::root_url() . 'js/select2/select2.full.min.js', array('jquery'), WORKEERA_VERSION, true);
            wp_enqueue_script('workeera-dashboard-scripts', Workeera_Plugin::root_url() . 'js/dashboard-scripts.js', array('workeera-scripts'), WORKEERA_VERSION, true);
            $localize_str = [
                'pic_size_err'        => esc_html_x( 'File size too big.', 'Picture size error', 'workeera-remote-tech-job-board' ),
                'pic_mimetype_err'    => esc_html_x( 'File type is not allowed.', 'Picture mime type error', 'workeera-remote-tech-job-board' ),
                'allow_pic_size'      => workeera_picture_allowed_file_size(),
                'allow_pic_mimes'     => stripslashes( json_encode( workeera_picture_allowed_mime_types() ) ),
                'description_max_len' => workeera_job_description_max_len(),
                'confirm_comon_title' => esc_html_x('Confirmation', 'Confirm box title', 'workeera-remote-tech-job-board'),
            ];
            wp_localize_script('workeera-dashboard-scripts', 'workeera_dashboard_str', $localize_str);
            
            wp_register_script('workeera-managejobs-scripts', Workeera_Plugin::root_url() . 'js/dashboard-manage-jobs.js', array('workeera-scripts'), WORKEERA_VERSION, true);

            wp_register_style('workeera-mycompanies-styles', Workeera_Plugin::root_url() . 'css/dashboard-my-companies.css', array('workeera-dashboard-styles'), WORKEERA_VERSION);
            wp_register_script('workeera-mycompanies-scripts', Workeera_Plugin::root_url() . 'js/dashboard-my-companies.js', array('workeera-scripts'), WORKEERA_VERSION, true);
        }
    }

    /**
     * Append the plugin's body class on configured dashboard pages.
     *
     * @param  array $classes Existing body classes.
     * @return array          Classes with `workeera-page` appended when relevant.
     */
    public function body_class($classes) {
        global $workeera_plugin_settings;

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if (!empty($dashboard_page) && get_post_type($dashboard_page) == 'page') {
            $classes[] = 'workeera-page';
        }
        return $classes;
    }

    /**
     * Capture the user-type selection and ensure the candidate post exists on early `wp`.
     */
    public function user_dashboard_init() {
        global $workeera_plugin_settings;

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if (!empty($dashboard_page) && get_post_type($dashboard_page) == 'page' && is_page($dashboard_page)) {
            $is_user_logged_in = is_user_logged_in();
            $user_id = get_current_user_id();

            // When the employer dashboard is disabled, the role chooser is skipped
            // and any new user is defaulted to candidate (job seeker) on first
            // visit — set before the role-selection handler so employer can't be
            // chosen via the URL either.
            $disable_employer_dashboard = isset($workeera_plugin_settings['general_settings']['disable_employer_dashboard'])
                ? $workeera_plugin_settings['general_settings']['disable_employer_dashboard']
                : 'no';
            if (
                $is_user_logged_in
                && $disable_employer_dashboard === 'yes'
                && ! workeera_metadata_exists( 'user', $user_id, 'workeera_user_type' )
            ) {
                workeera_update_object_meta( $user_id, 'workeera_user_type', 'candidate', 'user' );
            }

            if (isset($_GET['workeera_user_type']) && $is_user_logged_in) {
                // Role selection is a state change (writes user_type meta and may
                // create a candidate post), so it must carry a valid nonce to
                // block CSRF. A missing/invalid nonce is silently ignored — the
                // dashboard still renders, the user just re-picks their role.
                $req_type = sanitize_key( wp_unslash( $_GET['workeera_user_type'] ) );
                $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
                if (
                    in_array( $req_type, array( 'employer', 'candidate' ), true )
                    && wp_verify_nonce( $nonce, 'workeera_select_user_type' )
                    && ! workeera_metadata_exists( 'user', $user_id, 'workeera_user_type' )
                ) {
                    workeera_update_object_meta( $user_id, 'workeera_user_type', $req_type, 'user' );
                    // Redirect to strip the now-consumed query args.
                    wp_safe_redirect( remove_query_arg( array( 'workeera_user_type', '_wpnonce' ) ) );
                    exit;
                }
            }
            if ($is_user_logged_in) {
                // checking candidate user attached post
                $user_type = workeera_get_object_meta($user_id, 'workeera_user_type', 'user');
                if ($user_type == 'candidate') {
                    workeera_ensure_candidate_post_for_user( $user_id );
                }
            }
        }
    }

    /**
     * Shortcode callback that renders the candidate or employer dashboard.
     *
     * @return string Buffered HTML for the dashboard shortcode output.
     */
    public function user_dashboard() {
        global $workeera_plugin_settings;

        ob_start();

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if (!empty($dashboard_page) && get_post_type($dashboard_page) == 'page') {
            $is_user_logged_in = is_user_logged_in();
            if ($is_user_logged_in) {
                $user_id = get_current_user_id();
                if (workeera_metadata_exists('user', $user_id, 'workeera_user_type')) {
                    $user_type = workeera_get_object_meta($user_id, 'workeera_user_type', 'user');

                    // Topbar (breadcrumbs + dashboard menu) — common for both
                    // candidate and employer; rendered above the section markup.
                    $topbar_section = isset($_GET['section-type']) ? sanitize_key(wp_unslash($_GET['section-type'])) : '';
                    if (in_array($user_type, ['candidate', 'employer'], true)) {
                        Workeera_Dashboard_Common::render_topbar($user_type, $topbar_section);
                    }

                    /**
                     * Filter whether an addon owns the current dashboard section.
                     *
                     * When an addon returns true for its own section-type slug,
                     * the dashboard fires `workeera_dashboard_render_section`
                     * instead of falling back to the dashboard home.
                     *
                     * @param bool   $is_addon_section Default false.
                     * @param string $section_type     Sanitized ?section-type slug.
                     * @param string $user_type        'candidate' | 'employer'.
                     */
                    $is_addon_section = ($topbar_section !== '')
                        ? apply_filters('workeera_dashboard_is_addon_section', false, $topbar_section, $user_type)
                        : false;

                    if ($user_type == 'employer') {
                        // Housekeeping: prune orphaned company-logo files (throttled ~3h).
                        if ( function_exists( 'workeera_maybe_cleanup_company_logos' ) ) {
                            workeera_maybe_cleanup_company_logos( $user_id );
                        }
                        if (isset($_GET['section-type']) && $_GET['section-type'] == 'job') {
                            Workeera_User_Job_Posting::form();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'applications') {
                            Workeera_Employer_Job_Applications::list_applications();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'manage-jobs') {
                            Workeera_User_Manage_Jobs::listings();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'my-companies') {
                            Workeera_User_My_Companies::listings();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'profile') {
                            Workeera_Employer_Profile::details_form();
                        } elseif ($is_addon_section) {
                            /**
                             * Render an addon-provided dashboard section.
                             *
                             * @param string $section_type Sanitized ?section-type slug.
                             * @param string $user_type    'candidate' | 'employer'.
                             */
                            do_action('workeera_dashboard_render_section', $topbar_section, $user_type);
                        } else {
                            Workeera_Dashboard_Common::render_dashboard_home('employer');
                        }
                    } else {
                        if (isset($_GET['section-type']) && $_GET['section-type'] == 'applied-jobs') {
                            Workeera_Candidate_Applied_Jobs::jobs();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'recommended-jobs') {
                            Workeera_Candidate_Recommended_Jobs::jobs();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'cv-manager') {
                            Workeera_Candidate_CV_Manager::files();
                        } elseif (isset($_GET['section-type']) && $_GET['section-type'] == 'profile') {
                            Workeera_Candidate_Resume::details_form();
                        } elseif ($is_addon_section) {
                            // Addon-provided section (see the employer branch docblock).
                            do_action('workeera_dashboard_render_section', $topbar_section, $user_type);
                        } else {
                            Workeera_Dashboard_Common::render_dashboard_home('candidate');
                        }
                    }
                } else {
                    // Nonce-protect the role-selection links — the target writes
                    // the user's role on load, so the request must be CSRF-safe.
                    $employer_redirect_url = wp_nonce_url(
                        add_query_arg(array('workeera_user_type' => 'employer'), get_permalink($dashboard_page)),
                        'workeera_select_user_type'
                    );
                    $candidate_redirect_url = wp_nonce_url(
                        add_query_arg(array('workeera_user_type' => 'candidate'), get_permalink($dashboard_page)),
                        'workeera_select_user_type'
                    );
                    ?>
                    <div class="workeera-usrtype-wrap workeera-usrdshb-main-wrap">
                        <div class="workeera-usrtype-inner">

                            <div class="workeera-usrtype-header">
                                <strong class="workeera-usrtype-title"><?php esc_html_e('Join as a Job Seeker or Company', 'workeera-remote-tech-job-board'); ?></strong>
                                <span class="workeera-usrtype-subtitle"><?php esc_html_e('Select your role to get started — we\'ll personalize your experience.', 'workeera-remote-tech-job-board'); ?></span>
                            </div>

                            <div class="workeera-usrtype-cards">

                                <a href="<?php echo esc_url($candidate_redirect_url); ?>" class="workeera-usrtype-card workeera-usrtype-card--active" data-role="jobseeker">
                                    <div class="workeera-usrtype-card-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                                            <circle cx="32" cy="20" r="10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <path d="M12 52c0-11.046 8.954-20 20-20s20 8.954 20 20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <path d="M38 38l4 6-4 2M26 38l-4 6 4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="32" cy="46" r="2.5" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <div class="workeera-usrtype-card-body">
                                        <strong class="workeera-usrtype-card-title"><?php echo esc_html_x('Job Seeker', 'user type', 'workeera-remote-tech-job-board'); ?></strong>
                                        <span class="workeera-usrtype-card-desc"><?php esc_html_e('I am finding my dream job', 'workeera-remote-tech-job-board'); ?></span>
                                    </div>
                                    <div class="workeera-usrtype-card-check" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </a>

                                <a href="<?php echo esc_url($employer_redirect_url); ?>" class="workeera-usrtype-card" data-role="company">
                                    <div class="workeera-usrtype-card-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                                            <rect x="8" y="24" width="48" height="32" rx="3" stroke="currentColor" stroke-width="2.5"/>
                                            <path d="M20 24V16a4 4 0 014-4h16a4 4 0 014 4v8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <path d="M8 38h48" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <rect x="26" y="33" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="2"/>
                                            <path d="M20 44v4M44 44v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div class="workeera-usrtype-card-body">
                                        <strong class="workeera-usrtype-card-title"><?php echo esc_html_x('Company', 'user type', 'workeera-remote-tech-job-board'); ?></strong>
                                        <span class="workeera-usrtype-card-desc"><?php esc_html_e('I want to hire the best talent', 'workeera-remote-tech-job-board'); ?></span>
                                    </div>
                                    <div class="workeera-usrtype-card-check" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </a>

                            </div>

                        </div>
                    </div>
                    <?php
                }
            } else {
                $redirect_url = wp_login_url(get_permalink($dashboard_page));
                ?>
                <div class="workeera-usrtype-wrap workeera-not-loggedin-user workeera-usrdshb-main-wrap">
                    <div class="workeera-usrdshb-small-container">

                        <div class="workeera-usrtype-header">
                            <strong class="workeera-usrtype-title"><?php esc_html_e('You are not logged in!', 'workeera-remote-tech-job-board'); ?></strong>
                            <span class="workeera-usrtype-subtitle"><?php esc_html_e('Kindly log in to your account to continue.', 'workeera-remote-tech-job-board'); ?></span>
                        </div>

                        <div class="workeera-usrtype-ctacon">
                            <a href="<?php echo esc_url($redirect_url) ?>" class="workeera-btn"><?php esc_html_e('Login', 'workeera-remote-tech-job-board'); ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<p>' . esc_html__('Incorrect page selected. Please contact the site administrator.', 'workeera-remote-tech-job-board') . '</p>';
        }

        return ob_get_clean();
    }

    /**
     * Override the document `<title>` on the dashboard page so each section
     * gets its own translatable title (e.g. "Manage Jobs – Dashboard"). The
     * section-aware title string itself is built by Workeera_Dashboard_Common so
     * candidate and employer flows share the same logic.
     *
     * @param  array $title_parts Document title parts from WordPress.
     * @return array              Possibly modified title parts.
     */
    public function custom_page_title( $title_parts ) {
        global $workeera_plugin_settings;

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if (!empty($dashboard_page) && get_post_type($dashboard_page) == 'page' && is_page($dashboard_page)) {
            $dashboard_title = get_the_title( $dashboard_page );

            $user_type = '';
            if ( is_user_logged_in() ) {
                $user_id   = get_current_user_id();
                $user_type = workeera_get_object_meta( $user_id, 'workeera_user_type', 'user' );
            }

            $section_type = isset( $_GET['section-type'] ) ? sanitize_key( wp_unslash( $_GET['section-type'] ) ) : '';

            $title_parts['title'] = Workeera_Dashboard_Common::get_page_title( $user_type, $section_type, $dashboard_title );
        }
        return $title_parts;
    }
}

new Workeera_User_Dashboard;
