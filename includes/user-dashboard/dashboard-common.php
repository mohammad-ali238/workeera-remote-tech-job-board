<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Shared helpers used by the user dashboard sections (pagination, topbar, stats).
 */
class Workeera_Dashboard_Common {

    /**
     * Build pagination HTML.
     *
     * @param int    $current_page
     * @param int    $total_items
     * @param int    $per_page
     * @param string $info_label  Pre-built / translated info label string.
     * @return string
     */
    public static function render_pagination( $current_page, $total_items, $per_page, $info_label = '' ) {
        if ( $total_items <= $per_page ) {
            return '';
        }

        $total_pages  = (int) ceil( $total_items / $per_page );
        $current_page = max( 1, min( $current_page, $total_pages ) );

        $prev_svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg>';
        $next_svg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>';

        $html  = '<div class="workeera-usrjobs-pagination-info">' . esc_html( $info_label ) . '</div>';
        $html .= '<div class="workeera-usrjobs-pagination-btns">';

        // Previous.
        if ( $current_page <= 1 ) {
            $html .= '<button class="workeera-usrjobs-page-btn" disabled>' . $prev_svg . '</button>';
        } else {
            $html .= '<button class="workeera-usrjobs-page-btn workeera-usrjobs-page-nav" data-page="' . absint( $current_page - 1 ) . '">' . $prev_svg . '</button>';
        }

        // Numeric page buttons (with ellipsis when many pages).
        $page_links = self::build_page_links( $current_page, $total_pages );
        foreach ( $page_links as $link ) {
            if ( 'ellipsis' === $link ) {
                $html .= '<span class="workeera-usrjobs-page-btn" style="border:none;cursor:default;">&hellip;</span>';
            } elseif ( (int) $link === $current_page ) {
                $html .= '<button class="workeera-usrjobs-page-btn workeera-usrjobs-page-active" data-page="' . absint( $link ) . '">' . absint( $link ) . '</button>';
            } else {
                $html .= '<button class="workeera-usrjobs-page-btn workeera-usrjobs-page-nav" data-page="' . absint( $link ) . '">' . absint( $link ) . '</button>';
            }
        }

        // Next.
        if ( $current_page >= $total_pages ) {
            $html .= '<button class="workeera-usrjobs-page-btn" disabled>' . $next_svg . '</button>';
        } else {
            $html .= '<button class="workeera-usrjobs-page-btn workeera-usrjobs-page-nav" data-page="' . absint( $current_page + 1 ) . '">' . $next_svg . '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Compact list of page numbers / 'ellipsis' tokens for pagination buttons.
     *
     * @param int $current
     * @param int $total
     * @return array
     */
    public static function build_page_links( $current, $total ) {
        if ( $total <= 7 ) {
            return range( 1, $total );
        }

        $links   = [];
        $links[] = 1;

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
     * Skeleton loader markup used while AJAX is loading.
     *
     * @param string $variant 'applied' | 'recommended' | 'employer-apps'
     */
    public static function loader( $variant = 'applied' ) {
        $variant_map = [
            'applied'       => 'workeera-applied-loadr',
            'recommended'   => 'workeera-recmnd-loadr',
            'employer-apps' => 'workeera-empapps-loadr',
        ];
        $variant_class = isset( $variant_map[ $variant ] ) ? $variant_map[ $variant ] : 'workeera-applied-loadr';
        ?>
        <div class="workeera-listin-loadr workeera-candidate-jobs-loder <?php echo esc_attr( $variant_class ); ?>">
            <?php for ( $i = 1; $i < 5; $i++ ) : ?>
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
                </div>
            <?php endfor; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Dashboard topbar — breadcrumb + dashboard menu trigger.
    // Rendered above each section when the user has a workeera_user_type.
    // -------------------------------------------------------------------------

    /**
     * Render the dashboard topbar (breadcrumbs on the left, menu button + dropdown
     * on the right). Use the same wrapper width as the page sections so it lines up.
     *
     * @param string $user_type    'candidate' | 'employer'
     * @param string $section_type The current ?section-type=… (empty for the dashboard root).
     */
    public static function render_topbar( $user_type, $section_type = '' ) {
        if ( ! in_array( $user_type, [ 'candidate', 'employer' ], true ) ) {
            return;
        }

        global $workeera_plugin_settings;
        $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_url  = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : home_url( '/' );

        // After logout, redirect to wp-login (wp-admin) with the dashboard page URL
        // pre-filled as redirect_to so the user lands back on the dashboard once
        // they re-authenticate.
        $logout_url = wp_logout_url( wp_login_url( $dashboard_url ) );

        $crumbs     = self::build_breadcrumbs( $user_type, $section_type, $dashboard_url );
        $menu_items = self::build_dashboard_menu_items( $user_type, $section_type, $dashboard_url );
        ?>
        <div class="workeera-dshb-topbar">
            <div class="workeera-usrdshb-container">
                <div class="workeera-dshb-topbar-inner">

                    <nav class="workeera-dshb-breadcrumb" aria-label="<?php echo esc_attr_x( 'Dashboard breadcrumb', 'dashboard topbar', 'workeera-remote-tech-job-board' ); ?>">
                        <?php foreach ( $crumbs as $i => $crumb ) : ?>
                            <?php if ( $i > 0 ) : ?>
                                <span class="workeera-dshb-breadcrumb-sep" aria-hidden="true">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </span>
                            <?php endif; ?>
                            <?php if ( ! empty( $crumb['url'] ) ) : ?>
                                <a class="workeera-dshb-breadcrumb-link" href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
                            <?php else : ?>
                                <span class="workeera-dshb-breadcrumb-current" aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>

                    <div class="workeera-dshb-menu" data-workeera-dshb-menu>
                        <button type="button"
                                class="workeera-dshb-menu-trigger workeera-btn"
                                aria-haspopup="true"
                                aria-expanded="false"
                                title="<?php echo esc_attr_x( 'Dashboard menu', 'dashboard topbar', 'workeera-remote-tech-job-board' ); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <circle cx="6" cy="6" r="1.8"/>
                                <circle cx="12" cy="6" r="1.8"/>
                                <circle cx="18" cy="6" r="1.8"/>
                                <circle cx="6" cy="12" r="1.8"/>
                                <circle cx="12" cy="12" r="1.8"/>
                                <circle cx="18" cy="12" r="1.8"/>
                                <circle cx="6" cy="18" r="1.8"/>
                                <circle cx="12" cy="18" r="1.8"/>
                                <circle cx="18" cy="18" r="1.8"/>
                            </svg>
                            <span class="workeera-dashmenu-btn-text"><?php echo esc_html_x( 'Dashboard menu', 'dashboard topbar', 'workeera-remote-tech-job-board' ); ?></span>
                        </button>
                        <div class="workeera-dshb-menu-panel workeera-hide" role="menu">
                            <?php foreach ( $menu_items as $item ) : ?>
                                <a class="workeera-dshb-menu-item<?php echo esc_attr($item['active'] ? ' workeera-dshb-menu-item--active' : ''); ?>"
                                   href="<?php echo esc_url( $item['url'] ); ?>"
                                   role="menuitem">
                                    <?php echo esc_html( $item['label'] ); ?>
                                </a>
                            <?php endforeach; ?>
                            <a class="workeera-dshb-menu-item workeera-dshb-menu-item--logout"
                               href="<?php echo esc_url( $logout_url ); ?>"
                               role="menuitem">
                                <?php echo esc_html_x( 'Logout', 'dashboard menu', 'workeera-remote-tech-job-board' ); ?>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Build the breadcrumb trail for the topbar.
     * Returns an ordered array of [ 'label' => string, 'url' => string ] items.
     * The current/last item has an empty `url` (except for the editing-job case
     * where the URL goes to its own edit screen).
     */
    private static function build_breadcrumbs( $user_type, $section_type, $dashboard_url ) {
        $labels = self::section_labels( $user_type );

        $crumbs = [
            [
                'label' => $labels['dashboard'],
                'url'   => ( $section_type === '' ) ? '' : $dashboard_url,
            ],
        ];

        if ( $section_type === '' ) {
            return $crumbs;
        }

        if ( $user_type === 'employer' ) {
            if ( $section_type === 'manage-jobs' ) {
                $crumbs[] = [ 'label' => $labels['manage-jobs'], 'url' => '' ];
            } elseif ( $section_type === 'applications' ) {
                $crumbs[] = [ 'label' => $labels['applications'], 'url' => '' ];
            } elseif ( $section_type === 'profile' ) {
                $crumbs[] = [ 'label' => $labels['profile'], 'url' => '' ];
            } elseif ( $section_type === 'job' ) {
                // Manage Jobs (linked) > Job title or "Job Posting".
                $crumbs[] = [
                    'label' => $labels['manage-jobs'],
                    'url'   => add_query_arg( [ 'section-type' => 'manage-jobs' ], $dashboard_url ),
                ];

                $job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
                if ( $job_id > 0 ) {
                    $job_post = get_post( $job_id );
                    if ( $job_post && $job_post->post_type === 'workeera_job' ) {
                        $job_title = $job_post->post_title !== '' ? $job_post->post_title : $labels['job'];
                        $crumbs[]  = [
                            'label' => $job_title,
                            'url'   => add_query_arg( [ 'section-type' => 'job', 'job_id' => $job_id ], $dashboard_url ),
                        ];
                    } else {
                        $crumbs[] = [ 'label' => $labels['job'], 'url' => '' ];
                    }
                } else {
                    $crumbs[] = [ 'label' => $labels['job'], 'url' => '' ];
                }
            }
        } else {
            // Candidate sections — single-level under Dashboard.
            if ( isset( $labels[ $section_type ] ) ) {
                $crumbs[] = [ 'label' => $labels[ $section_type ], 'url' => '' ];
            }
        }

        return $crumbs;
    }

    /**
     * Build the dashboard-menu items (excluding logout, which is appended in
     * render_topbar with red styling). Each entry includes a key so the
     * current section can be marked active.
     */
    private static function build_dashboard_menu_items( $user_type, $section_type, $dashboard_url ) {
        $items = [];

        if ( $user_type === 'employer' ) {
            $items[] = [
                'key'   => '',
                'label' => esc_html_x( 'Dashboard', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => $dashboard_url,
            ];
            $items[] = [
                'key'   => 'manage-jobs',
                'label' => esc_html_x( 'Manage Jobs', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'manage-jobs' ], $dashboard_url ),
            ];
            $items[] = [
                'key'   => 'applications',
                'label' => esc_html_x( 'Applications', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'applications' ], $dashboard_url ),
            ];
            $items[] = [
                'key'   => 'my-companies',
                'label' => esc_html_x( 'My Companies', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'my-companies' ], $dashboard_url ),
            ];
            $items[] = [
                'key'   => 'profile',
                'label' => esc_html_x( 'Profile', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'profile' ], $dashboard_url ),
            ];
        } else {
            $items[] = [
                'key'   => '',
                'label' => esc_html_x( 'Dashboard', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => $dashboard_url,
            ];
            $items[] = [
                'key'   => 'applied-jobs',
                'label' => esc_html_x( 'Applied Jobs', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'applied-jobs' ], $dashboard_url ),
            ];
            $items[] = [
                'key'   => 'recommended-jobs',
                'label' => esc_html_x( 'Recommended Jobs', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'recommended-jobs' ], $dashboard_url ),
            ];
            $items[] = [
                'key'   => 'cv-manager',
                'label' => esc_html_x( 'CV Manager', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'cv-manager' ], $dashboard_url ),
            ];
            $items[] = [
                'key'   => 'profile',
                'label' => esc_html_x( 'Profile', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'url'   => add_query_arg( [ 'section-type' => 'profile' ], $dashboard_url ),
            ];
        }

        /**
         * Filter the dashboard menu items before the active flag is computed.
         *
         * Lets addons add their own dashboard sections to the menu — items
         * added here get their `active` flag computed by the loop below.
         *
         * @param array  $items         Menu items ( each: 'key', 'label', 'url' ).
         * @param string $user_type     'candidate' | 'employer'.
         * @param string $section_type  Current ?section-type slug ('' for the dashboard root).
         * @param string $dashboard_url Dashboard page URL.
         */
        $items = apply_filters( 'workeera_dashboard_menu_items', $items, $user_type, $section_type, $dashboard_url );

        // Active flag — handle "job" being a child of "manage-jobs" so the
        // menu still highlights the parent when editing/posting a job.
        foreach ( $items as &$item ) {
            if ( $item['key'] === '' ) {
                $item['active'] = ( $section_type === '' );
            } elseif ( $item['key'] === 'manage-jobs' ) {
                $item['active'] = in_array( $section_type, [ 'manage-jobs', 'job' ], true );
            } else {
                $item['active'] = ( $item['key'] === $section_type );
            }
        }
        unset( $item );

        return $items;
    }

    /**
     * Build the translated document/browser title for the user dashboard page,
     * based on the currently active section.
     *
     * Used by Workeera_User_Dashboard::custom_page_title() to override the default
     * WordPress page title with a section-specific one (e.g. "Manage Jobs –
     * Dashboard"). All strings are fully translatable.
     *
     * @param  string $user_type       'candidate' | 'employer' (anything else falls back to the dashboard title).
     * @param  string $section_type    Current ?section-type=… slug ('' for the dashboard root).
     * @param  string $dashboard_title Display title of the dashboard page (e.g. "Dashboard").
     * @return string                  Translated title string ready for $title_parts['title'].
     */
    public static function get_page_title( $user_type, $section_type, $dashboard_title ) {
        $dashboard_title = (string) $dashboard_title;
        $section_label   = '';

        if ( in_array( $user_type, [ 'candidate', 'employer' ], true ) ) {
            $labels = self::section_labels( $user_type );

            // Root dashboard — return the dashboard page title only.
            if ( $section_type === '' ) {
                return $dashboard_title;
            }

            // Employer "job" section with a job_id → prefer the actual job title.
            if ( $user_type === 'employer' && $section_type === 'job' ) {
                $job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
                if ( $job_id > 0 ) {
                    $job_post = get_post( $job_id );
                    if ( $job_post && $job_post->post_type === 'workeera_job' && $job_post->post_title !== '' ) {
                        $section_label = $job_post->post_title;
                    }
                }
                if ( $section_label === '' && isset( $labels['job'] ) ) {
                    $section_label = $labels['job'];
                }
            } elseif ( isset( $labels[ $section_type ] ) ) {
                $section_label = $labels[ $section_type ];
            }
        }

        if ( $section_label === '' ) {
            return $dashboard_title;
        }

        /* translators: 1: dashboard section name, 2: dashboard page title */
        return sprintf( _x( '%1$s &#8211; %2$s', 'dashboard document title', 'workeera-remote-tech-job-board' ), $section_label, $dashboard_title );
    }

    /**
     * Translatable label map for breadcrumb / menu, keyed by section slug.
     *
     * @param  string $user_type 'candidate' | 'employer'.
     * @return array             Map of section slug to translated label.
     */
    private static function section_labels( $user_type ) {
        if ( $user_type === 'employer' ) {
            $labels = [
                'dashboard'    => esc_html_x( 'Dashboard',    'dashboard menu', 'workeera-remote-tech-job-board' ),
                'manage-jobs'  => esc_html_x( 'Manage Jobs',  'dashboard menu', 'workeera-remote-tech-job-board' ),
                'job'          => esc_html_x( 'Job Posting',  'dashboard menu', 'workeera-remote-tech-job-board' ),
                'applications' => esc_html_x( 'Applications', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'my-companies' => esc_html_x( 'My Companies', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'profile'      => esc_html_x( 'Profile',      'dashboard menu', 'workeera-remote-tech-job-board' ),
            ];
        } else {
            $labels = [
                'dashboard'        => esc_html_x( 'Dashboard',        'dashboard menu', 'workeera-remote-tech-job-board' ),
                'applied-jobs'     => esc_html_x( 'Applied Jobs',     'dashboard menu', 'workeera-remote-tech-job-board' ),
                'recommended-jobs' => esc_html_x( 'Recommended Jobs', 'dashboard menu', 'workeera-remote-tech-job-board' ),
                'cv-manager'       => esc_html_x( 'CV Manager',       'dashboard menu', 'workeera-remote-tech-job-board' ),
                'profile'          => esc_html_x( 'Profile',          'dashboard menu', 'workeera-remote-tech-job-board' ),
            ];
        }

        /**
         * Filter the dashboard section labels (breadcrumb / page title map).
         *
         * Lets addons register the label for their own dashboard sections so
         * breadcrumbs and the document title resolve correctly.
         *
         * @param array  $labels    Map of section slug => translated label.
         * @param string $user_type 'candidate' | 'employer'.
         */
        return apply_filters( 'workeera_dashboard_section_labels', $labels, $user_type );
    }

    // -------------------------------------------------------------------------
    // Dashboard home — welcome banner + stats grid + recent applications.
    // Common entry-point for both candidate and employer; branches internally.
    // -------------------------------------------------------------------------

    /**
     * Render the dashboard home view — welcome banner, stats grid, and recent applications.
     *
     * @param string $user_type 'candidate' or 'employer'.
     */
    public static function render_dashboard_home( $user_type ) {
        if ( ! in_array( $user_type, [ 'candidate', 'employer' ], true ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( $user_id < 1 ) {
            return;
        }

        $user_data    = get_userdata( $user_id );
        $display_name = ( $user_data && ! empty( $user_data->display_name ) ) ? $user_data->display_name : '';

        $is_employer = ( $user_type === 'employer' );

        if ( $is_employer ) {
            $stats  = self::get_employer_stats( $user_id );
            $recent = self::get_employer_recent_applications( $user_id, 5 );
        } else {
            $stats  = self::get_candidate_stats( $user_id );
            $recent = self::get_candidate_recent_applications( $user_id, 5 );
        }

        global $workeera_plugin_settings;
        $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_url  = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : home_url( '/' );

        $welcome_subtitle = $is_employer
            ? esc_html_x( "Here's what's happening with your job postings.", 'dashboard home', 'workeera-remote-tech-job-board' )
            : esc_html_x( "Here's what's happening with your job applications.", 'dashboard home', 'workeera-remote-tech-job-board' );

        $view_all_url = $is_employer
            ? add_query_arg( [ 'section-type' => 'applications' ],  $dashboard_url )
            : add_query_arg( [ 'section-type' => 'applied-jobs' ],  $dashboard_url );
        ?>
        <div class="workeera-dshb-home workeera-usrdshb-main-wrap">
            <div class="workeera-usrdshb-container">

                <!-- Welcome banner -->
                <div class="workeera-dshb-welcome">
                    <strong class="workeera-dshb-welcome-title">
                        <?php
                        if ( ! empty( $display_name ) ) {
                            /* translators: %s: user display name */
                            echo esc_html( sprintf( esc_html_x( 'Welcome back, %s!', 'dashboard home', 'workeera-remote-tech-job-board' ), $display_name ) );
                        } else {
                            echo esc_html_x( 'Welcome back!', 'dashboard home', 'workeera-remote-tech-job-board' );
                        }
                        ?>
                    </strong>
                    <span class="workeera-dshb-welcome-sub"><?php echo esc_html( $welcome_subtitle ); ?></span>
                </div>

                <div class="workeera-dshb-home-grid">

                    <!-- Stats column -->
                    <div class="workeera-dshb-home-left">
                        <div class="workeera-dshb-stats-grid">
                            <?php
                            if ( $is_employer ) {
                                self::render_stat_card( [
                                    'theme' => 'primary',
                                    'icon'  => self::stat_icon( 'briefcase' ),
                                    'label' => esc_html_x( 'Total Jobs Posted', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['total'],
                                ] );
                                self::render_stat_card( [
                                    'theme' => 'success',
                                    'icon'  => self::stat_icon( 'check-circle' ),
                                    'label' => esc_html_x( 'Active Jobs', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['active'],
                                ] );
                                self::render_stat_card( [
                                    'theme' => 'warning',
                                    'icon'  => self::stat_icon( 'clock' ),
                                    'label' => esc_html_x( 'Pending Jobs', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['pending'],
                                ] );
                                self::render_stat_card( [
                                    'theme' => 'info',
                                    'icon'  => self::stat_icon( 'users' ),
                                    'label' => esc_html_x( 'Total Applicants', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['applicants'],
                                ] );
                            } else {
                                self::render_stat_card( [
                                    'theme' => 'primary',
                                    'icon'  => self::stat_icon( 'send' ),
                                    'label' => esc_html_x( 'Applied Jobs', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['total'],
                                ] );
                                self::render_stat_card( [
                                    'theme' => 'info',
                                    'icon'  => self::stat_icon( 'eye' ),
                                    'label' => esc_html_x( 'Reviewed', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['reviewed'],
                                ] );
                                self::render_stat_card( [
                                    'theme' => 'success',
                                    'icon'  => self::stat_icon( 'star' ),
                                    'label' => esc_html_x( 'Shortlisted', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['shortlisted'],
                                ] );
                                self::render_stat_card( [
                                    'theme' => 'danger',
                                    'icon'  => self::stat_icon( 'x-circle' ),
                                    'label' => esc_html_x( 'Rejected', 'dashboard home', 'workeera-remote-tech-job-board' ),
                                    'value' => $stats['rejected'],
                                ] );
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Recent applications column -->
                    <div class="workeera-dshb-home-right">
                        <div class="workeera-dshb-recent-panel">
                            <div class="workeera-dshb-recent-header">
                                <strong class="workeera-dshb-recent-title">
                                    <?php
                                    if ( $is_employer ) {
                                        echo esc_html_x( 'Recent Applications', 'dashboard home', 'workeera-remote-tech-job-board' );
                                    } else {
                                        echo esc_html_x( 'Recently Applied', 'dashboard home', 'workeera-remote-tech-job-board' );
                                    }
                                    ?>
                                </strong>
                                <?php if ( ! empty( $recent ) ) : ?>
                                    <a class="workeera-dshb-recent-viewall" href="<?php echo esc_url( $view_all_url ); ?>">
                                        <?php echo esc_html_x( 'View all', 'dashboard home', 'workeera-remote-tech-job-board' ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ( empty( $recent ) ) : ?>
                                <div class="workeera-dshb-recent-empty">
                                    <span class="workeera-dshb-recent-empty-icon">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                                            <line x1="12" y1="12" x2="12" y2="16" />
                                            <line x1="10" y1="14" x2="14" y2="14" />
                                        </svg>
                                    </span>
                                    <p class="workeera-dshb-recent-empty-msg">
                                        <?php
                                        if ( $is_employer ) {
                                            echo esc_html_x( 'No applications received yet.', 'dashboard home', 'workeera-remote-tech-job-board' );
                                        } else {
                                            echo esc_html_x( "You haven't applied to any jobs yet.", 'dashboard home', 'workeera-remote-tech-job-board' );
                                        }
                                        ?>
                                    </p>
                                </div>
                            <?php else : ?>
                                <ul class="workeera-dshb-recent-list">
                                    <?php foreach ( $recent as $row ) : ?>
                                        <?php self::render_recent_app_item( $row, $is_employer ); ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php
                /**
                 * Fires on the dashboard home, below the stats/recent grid and
                 * still inside the dashboard container. Lets addons render an
                 * extra full-width section without adding a menu tab.
                 *
                 * @param string $user_type 'candidate' | 'employer'.
                 * @param int    $user_id   Current user ID.
                 */
                do_action( 'workeera_dashboard_home_after_grid', $user_type, $user_id );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render one stat card. $args: theme | icon (raw HTML) | label | value (int).
     */
    private static function render_stat_card( $args ) {
        $theme = isset( $args['theme'] ) ? sanitize_html_class( $args['theme'] ) : 'primary';
        $icon  = isset( $args['icon'] )  ? $args['icon']  : '';
        $label = isset( $args['label'] ) ? $args['label'] : '';
        $value = isset( $args['value'] ) ? (int) $args['value'] : 0;
        ?>
        <div class="workeera-dshb-stat-card workeera-dshb-stat-<?php echo esc_attr( $theme ); ?>">
            <div class="workeera-dshb-stat-icon"><?php echo wp_kses( (string) $icon, workeera_svg_allowed_tags() ); ?></div>
            <div class="workeera-dshb-stat-body">
                <span class="workeera-dshb-stat-label"><?php echo esc_html( $label ); ?></span>
                <span class="workeera-dshb-stat-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single recent-application card inside the panel list.
     */
    private static function render_recent_app_item( $row, $is_employer ) {
        $application_id = isset( $row['application_id'] ) ? absint( $row['application_id'] ) : 0;
        $job_id         = isset( $row['job_id'] ) ? absint( $row['job_id'] ) : 0;
        $applied_date   = isset( $row['applied_date'] ) ? $row['applied_date'] : '';
        $status         = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'pending';
        $post_title     = isset( $row['post_title'] ) ? $row['post_title'] : '';
        $post_status    = isset( $row['post_status'] ) ? sanitize_key( $row['post_status'] ) : '';

        $title = $post_title !== '' ? $post_title : esc_html_x( '(Job no longer available)', 'dashboard home', 'workeera-remote-tech-job-board' );

        // For candidate: link the title to the public single-job page (if published).
        // For employer: link the title to its job-edit screen on the dashboard.
        $title_url = '';
        if ( $is_employer ) {
            global $workeera_plugin_settings;
            $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
            if ( ! empty( $dashboard_page ) && $job_id > 0 ) {
                $title_url = add_query_arg( [ 'section-type' => 'job', 'job_id' => $job_id ], get_permalink( $dashboard_page ) );
            }
        } else {
            if ( $job_id > 0 && $post_status === 'publish' ) {
                $title_url = get_permalink( $job_id );
            }
        }

        $time_ago = '';
        if ( ! empty( $applied_date ) ) {
            $applied_ts = strtotime( $applied_date );
            if ( $applied_ts ) {
                /* translators: %s: human-readable time difference */
                $time_ago = sprintf( esc_html_x( '%s ago', 'dashboard recent app time', 'workeera-remote-tech-job-board' ), human_time_diff( $applied_ts, current_time( 'timestamp' ) ) );
            }
        }

        $badge_cls = Workeera_Employer_Job_Applications::status_badge_class( $status );
        $badge_lbl = Workeera_Employer_Job_Applications::status_badge_label( $status );

        // Employer-only — applicant identity.
        $applicant_uid      = 0;
        $applicant_name     = '';
        if ( $is_employer ) {
            $applicant_uid  = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
            $applicant_name = isset( $row['applicant_name'] ) ? $row['applicant_name'] : '';
        }
        ?>
        <li class="workeera-dshb-recent-item" data-application-id="<?php echo absint( $application_id ); ?>">
            <?php if ( $is_employer ) : ?>
                <div class="workeera-dshb-recent-pic-wrap">
                    <?php
                    if ( function_exists( 'workeera_user_profile_pic_html' ) ) {
                        workeera_user_profile_pic_html( $applicant_uid, 'workeera-dshb-recent-pic' );
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="workeera-dshb-recent-info">
                <?php if ( $is_employer && ! empty( $applicant_name ) ) : ?>
                    <strong class="workeera-dshb-recent-applicant"><?php echo esc_html( $applicant_name ); ?></strong>
                <?php endif; ?>
                <span class="workeera-dshb-recent-jobtitle">
                    <?php if ( ! empty( $title_url ) ) : ?>
                        <a href="<?php echo esc_url( $title_url ); ?>"><?php echo esc_html( $title ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $title ); ?>
                    <?php endif; ?>
                </span>
                <?php if ( $time_ago ) : ?>
                    <span class="workeera-dshb-recent-time"><?php echo esc_html( $time_ago ); ?></span>
                <?php endif; ?>
            </div>

            <span class="workeera-dshb-recent-status">
                <span class="workeera-empapps-status-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge_lbl ); ?></span>
            </span>
        </li>
        <?php
    }

    // -------------------------------------------------------------------------
    // Stats queries
    // -------------------------------------------------------------------------

    /**
     * Aggregate job-posting counters for an employer dashboard.
     *
     * @param  int $user_id Employer user ID.
     * @return array        Map of total, active, pending, and applicants counters.
     */
    private static function get_employer_stats( $user_id ) {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'posts';
        $jf_table    = $wpdb->prefix . 'workeera_job_fields';
        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $author      = absint( $user_id );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table}
             WHERE post_type = %s AND post_author = %d AND post_status NOT IN ('trash','auto-draft','inherit')",
            'workeera_job', $author
        ) );

        $active = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table} AS p
             LEFT JOIN {$jf_table} AS jf ON jf.job_id = p.ID
             WHERE p.post_type = %s AND p.post_author = %d AND p.post_status = 'publish'
               AND ( jf.expiry_date IS NULL OR jf.expiry_date >= %s )
               AND ( jf.is_filled IS NULL OR jf.is_filled = 0 )",
            'workeera_job', $author, current_time( 'mysql' )
        ) );

        $pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table}
             WHERE post_type = %s AND post_author = %d AND post_status = 'pending'",
            'workeera_job', $author
        ) );

        $applicants = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$apps_table} AS a
             INNER JOIN {$posts_table} AS p ON p.ID = a.job_id
             WHERE p.post_type = %s AND p.post_author = %d",
            'workeera_job', $author
        ) );

        return [
            'total'      => $total,
            'active'     => $active,
            'pending'    => $pending,
            'applicants' => $applicants,
        ];
    }

    /**
     * Aggregate application-status counters for a candidate dashboard.
     *
     * @param  int $user_id Candidate user ID.
     * @return array        Map of total plus per-status counts.
     */
    private static function get_candidate_stats( $user_id ) {
        global $wpdb;

        $apps_table = $wpdb->prefix . 'workeera_applications';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) AS c FROM {$apps_table} WHERE user_id = %d GROUP BY status",
            absint( $user_id )
        ), ARRAY_A );

        $by_status = [
            'pending'     => 0,
            'reviewed'    => 0,
            'shortlisted' => 0,
            'rejected'    => 0,
        ];
        $total = 0;
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $key = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : '';
                $cnt = isset( $row['c'] ) ? (int) $row['c'] : 0;
                $total += $cnt;
                if ( isset( $by_status[ $key ] ) ) {
                    $by_status[ $key ] = $cnt;
                }
            }
        }

        return [
            'total'       => $total,
            'pending'     => $by_status['pending'],
            'reviewed'    => $by_status['reviewed'],
            'shortlisted' => $by_status['shortlisted'],
            'rejected'    => $by_status['rejected'],
        ];
    }

    /**
     * Fetch the latest applications received across all jobs owned by an employer.
     *
     * @param  int $user_id Employer user ID.
     * @param  int $limit   Maximum rows to return.
     * @return array        List of application rows joined with job titles.
     */
    private static function get_employer_recent_applications( $user_id, $limit = 5 ) {
        global $wpdb;

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.application_id, a.job_id, a.user_id, a.applicant_name, a.applicant_email, a.status, a.applied_date,
                    p.post_title, p.post_status
             FROM {$apps_table} AS a
             INNER JOIN {$posts_table} AS p ON p.ID = a.job_id
             WHERE p.post_type = %s AND p.post_author = %d
             ORDER BY a.applied_date DESC
             LIMIT %d",
            'workeera_job', absint( $user_id ), absint( $limit )
        ), ARRAY_A );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Fetch the candidate's latest application rows joined with job titles.
     *
     * @param  int $user_id Candidate user ID.
     * @param  int $limit   Maximum rows to return.
     * @return array        List of application rows.
     */
    private static function get_candidate_recent_applications( $user_id, $limit = 5 ) {
        global $wpdb;

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.application_id, a.job_id, a.status, a.applied_date,
                    p.post_title, p.post_status
             FROM {$apps_table} AS a
             LEFT JOIN {$posts_table} AS p ON p.ID = a.job_id
             WHERE a.user_id = %d
             ORDER BY a.applied_date DESC
             LIMIT %d",
            absint( $user_id ), absint( $limit )
        ), ARRAY_A );

        return is_array( $rows ) ? $rows : [];
    }

    // -------------------------------------------------------------------------
    // Inline SVG icons used by stat cards.
    // -------------------------------------------------------------------------

    /**
     * Return the inline SVG markup for a stat-card icon key.
     *
     * @param  string $key Icon identifier (e.g. 'briefcase', 'star').
     * @return string      SVG markup, or empty string when unknown.
     */
    private static function stat_icon( $key ) {
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
            case 'eye':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
            case 'star':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
            case 'x-circle':
                return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        }
        return '';
    }
}
