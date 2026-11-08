<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Renders single job detail pages and registers the block template/render hooks.
 */
class Workeera_Post_Detail {
    /**
     * Register body class, template include and block render filters.
     */
    public function __construct() {
        add_filter( 'body_class', [ $this, 'body_class' ], 5 );
        add_filter('template_include', [$this, 'templates_include'], 10);
        add_filter('get_block_templates', [$this, 'block_templates'], 10, 3);
        add_filter('render_block', [$this, 'render_block_content'], 10, 2);
    }

    /**
     * Add a workeera-page body class on single job views.
     *
     * @param  array $classes Existing body classes.
     * @return array          Filtered body classes.
     */
    public function body_class( $classes ) {

        if (is_singular('workeera_job')) {
            $classes[] = 'workeera-page';
        }
        return $classes;
    }

    /**
     * Swap in the plugin's single-job template for classic themes.
     *
     * @param  string $template Template path resolved by WordPress.
     * @return string           Template path to load.
     */
    public function templates_include($template) {
        if (!wp_is_block_theme()) {
            // For classic themes, let handle it
            if (is_singular('workeera_job')) {
                $custom_template = WORKEERA_ABSPATH . 'includes/single-job-template.php';
                if ($custom_template) {
                    return $custom_template;
                }
            }
        }
        return $template;
    }

    /**
     * Load HTML content for a plugin block template by name.
     *
     * @param  string $template_name Template filename (without extension).
     * @return string                Template HTML or empty string.
     */
    public function block_template_content($template_name) {
        $template_path = WORKEERA_ABSPATH . 'includes/block-templates/' . $template_name . '.html';

        if ( ! file_exists( $template_path ) ) {
            return '';
        }

        // Read the bundled template via the WP_Filesystem API rather than file_get_contents().
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( empty( $wp_filesystem ) ) {
            WP_Filesystem();
        }
        if ( ! $wp_filesystem ) {
            return '';
        }

        $content = $wp_filesystem->get_contents( $template_path );

        return is_string( $content ) ? $content : '';
    }

    /**
     * Inject the single-workeera_job block template into the templates list.
     *
     * @param  array  $templates     Existing block templates.
     * @param  array  $query         Template query arguments.
     * @param  string $template_type Template type, e.g. 'wp_template'.
     * @return array                 Filtered templates list.
     */
    public function block_templates($templates, $query, $template_type) {

        if ($template_type !== 'wp_template') return $templates;

        // Check if our template is already in the list
        foreach ($templates as $t) {
            if ($t->slug === 'single-workeera_job') return $templates;
        }

        $content = $this->block_template_content('single-job');
        if (!$content) return $templates;

        if (isset($query['slug__in']) && !in_array('single-workeera_job', $query['slug__in'])) {
            return $templates;
        }
        $template = new WP_Block_Template();
        $template->type      = 'wp_template';
        $template->theme     = get_stylesheet(); // active theme slug!
        $template->slug      = 'single-workeera_job';
        $template->id        = get_stylesheet() . '//single-workeera_job';
        $template->title     = 'Single Job';
        $template->content   = $content;
        $template->status    = 'publish';
        $template->source    = 'plugin';
        $template->is_custom = false;
        $template->post_types = ['workeera_job'];

        $templates[] = $template;
        return $templates;
    }

    /**
     * Replace the core/workeera-job-details block with rendered job detail HTML.
     *
     * @param  string $block_content Block output HTML.
     * @param  array  $block         Block data array.
     * @return string                Filtered block content.
     */
    public function render_block_content($block_content, $block) {

        if (!is_singular('workeera_job')) {
            return $block_content;
        }

        if ($block['blockName'] === 'core/workeera-job-details') {

            ob_start();
            self::job_detail();
            return ob_get_clean();
        }

        return $block_content;
    }

    /**
     * Build and return the full single job detail page HTML.
     *
     * @return string Rendered job detail markup.
     */
    public static function job_detail() {
        global $workeera_plugin_settings;

        $post_id = get_the_ID();

        if ( ! $post_id ) {
            return '';
        }

        $is_user_loggedin = is_user_logged_in();

        // --- Core post data ---
        $post        = get_post( $post_id );
        $job_title   = get_the_title( $post_id );
        $job_content = workeera_optimize_job_content( $post->post_content );

        // --- Custom job fields (salary, status flags, dates) ---
        $job_fields  = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post_id ], 'all', 1 );
        $is_featured = isset( $job_fields['is_featured'] ) ? (int) $job_fields['is_featured'] : 0;
        $is_urgent   = isset( $job_fields['is_urgent'] )   ? (int) $job_fields['is_urgent']   : 0;
        $is_filled   = isset( $job_fields['is_filled'] )   ? (int) $job_fields['is_filled']   : 0;
        $min_salary  = isset( $job_fields['min_salary'] )  ? absint( $job_fields['min_salary'] ) : 0;
        $max_salary  = isset( $job_fields['max_salary'] )  ? absint( $job_fields['max_salary'] ) : 0;
        $salary_type = isset( $job_fields['salary_type'] ) ? $job_fields['salary_type'] : '';
        $loc_type    = isset( $job_fields['location_type'] ) ? $job_fields['location_type'] : '';
        $deadline    = isset( $job_fields['application_deadline'] ) ? $job_fields['application_deadline'] : '';
        $expiry      = isset( $job_fields['expiry_date'] )          ? $job_fields['expiry_date']          : '';

        // --- Salary units label ---
        $salary_units      = workeera_salary_units();
        $salary_unit_label = isset( $salary_units[ $salary_type ] ) ? $salary_units[ $salary_type ] : '';

        // --- Apply method ---
        // When the site is configured for external applications (or the
        // employer chose external on a "both" site), the Apply button below
        // becomes an outbound link to the employer's URL in a new tab.
        $apply_method   = workeera_get_object_meta( $post_id, 'apply_method' );
        $apply_job_url  = workeera_get_object_meta( $post_id, 'apply_job_url' );
        $external_apply = ( $apply_method === 'external' && ! empty( $apply_job_url ) );

        // --- Company info ---
        $company_info = workeera_job_company_info( $post_id );
        $company_name = isset( $company_info['name'] )    ? workeera_strip_slashes( $company_info['name'] )    : '';
        $company_desc = ( workeera_company_field_enabled( 'tagline' ) && isset( $company_info['tagline'] ) ) ? workeera_strip_slashes( $company_info['tagline'] ) : '';
        $company_url  = isset( $company_info['website'] ) ? esc_url( $company_info['website'] )                : '';
        $company_linkedin = ( workeera_company_field_enabled( 'linkedin' ) && isset( $company_info['linkedin'] ) ) ? esc_url( $company_info['linkedin'] ) : '';

        // --- Logo ---
        $logo_url = workeera_job_logo( $post_id );

        // --- Taxonomy terms ---
        $sector_labels  = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, 'sectors' );
        $jobtype_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, 'jobtypes' );
        $timezone_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, 'timezones' );
        $skill_labels   = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, 'skills' );
        $benefit_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, 'benefits' );

        // --- Location ---
        $location_row = Workeera_Object_Terms_Relation::get_post_location( $post_id );
        $location_str = self::get_location_string( $location_row );

        // --- Location type display ---
        $allow_location_type = isset( $workeera_plugin_settings['job_submission_settings']['allow_location_type'] ) ? $workeera_plugin_settings['job_submission_settings']['allow_location_type'] : 'on';
        $loc_type_data = self::get_location_type_display( $loc_type );
        if ($allow_location_type != 'on') {
            $loc_type_data = null;
        }

        // --- Vacancies ---
        $num_of_vacancies = absint( workeera_get_object_meta( $post_id, 'num_of_vacancies' ) );

        // --- Posted time ---
        $posted_ago   = human_time_diff( get_post_time( 'U', false, $post ), current_time( 'timestamp' ) );
        /* translators: %s: human-readable time difference */
        $posted_label = sprintf( esc_html__( 'Posted %s ago', 'workeera-remote-tech-job-board' ), esc_html( $posted_ago ) );

        // --- Related jobs (by shared sector IDs first, then skill IDs as fallback) ---
        $related_jobs = self::get_related_jobs( $post_id, $sector_labels, $skill_labels );

        ?>
        <div class="workeera-common-wrapper workeera-jobdetail-wrapper">
            <div class="workeera-container">

                <div class="workeera-jobdetail-header">
                    <div class="workeera-jobdetail-header-top">
                        <h1 class="workeera-jobdetail-title"><?php echo esc_html( $job_title ); ?></h1>

                        <?php if ( $is_featured || $is_urgent ) : ?>
                            <div class="workeera-jobdetail-badges">
                                <?php if ( $is_featured ) : ?>
                                    <span class="workeera-badge workeera-badge-featured">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                        </svg>
                                        <?php echo esc_html__( 'Featured', 'workeera-remote-tech-job-board' ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $is_urgent ) : ?>
                                    <span class="workeera-badge workeera-badge-urgent">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                        </svg>
                                        <?php echo esc_html__( 'Urgent', 'workeera-remote-tech-job-board' ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="workeera-jobdetail-meta">
                        <?php if ( $company_name ) : ?>
                            <span class="workeera-jobdetail-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                                </svg>
                                <span>
                                    <strong><?php echo esc_html__( 'Company:', 'workeera-remote-tech-job-board' ); ?></strong>
                                    <?php echo esc_html( $company_name ); ?>
                                </span>
                            </span>
                        <?php endif; ?>

                        <?php if ( $location_str || $loc_type_data ) : ?>
                            <span class="workeera-jobdetail-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                <span>
                                    <?php
                                    $location_parts = [];
                                    if ( $location_str ) {
                                        $location_parts[] = esc_html( $location_str );
                                    }
                                    if ( $loc_type_data ) {
                                        $location_parts[] = esc_html( $loc_type_data['label'] );
                                    }
                                    echo esc_html( implode( ' &mdash; ', $location_parts ) );
                                    ?>
                                </span>
                            </span>
                        <?php endif; ?>

                        <span class="workeera-jobdetail-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            <span><?php echo esc_html( $posted_label ); ?></span>
                        </span>

                        <?php if ( $deadline ) : ?>
                            <span class="workeera-jobdetail-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                <span>
                                    <strong><?php echo esc_html__( 'Deadline:', 'workeera-remote-tech-job-board' ); ?></strong>
                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $deadline ) ) ); ?>
                                </span>
                            </span>
                        <?php endif; ?>

                        <?php if ( $num_of_vacancies > 0 ) : ?>
                            <span class="workeera-jobdetail-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <span>
                                    <strong><?php echo esc_html__( 'Vacancies:', 'workeera-remote-tech-job-board' ); ?></strong>
                                    <?php echo absint( $num_of_vacancies ); ?>
                                </span>
                            </span>
                        <?php endif; ?>

                        <?php if ( $is_filled ) : ?>
                            <span class="workeera-jobdetail-meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                    <polyline points="22 4 12 14.01 9 11.01" />
                                </svg>
                                <span><?php echo esc_html__( 'Position Filled', 'workeera-remote-tech-job-board' ); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $min_salary || $max_salary ) : ?>
                        <div class="workeera-jobdetail-salary-card">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="6" width="20" height="12" rx="2" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="M6 12h.01M18 12h.01" />
                            </svg>
                            <span>
                                <?php
                                if ( $min_salary && $max_salary && $min_salary !== $max_salary ) {
                                    echo wp_kses_post( workeera_formatted_salary( $min_salary ) ) . ' &ndash; ' . wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                                } elseif ( $max_salary ) {
                                    echo wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                                } else {
                                    echo wp_kses_post( workeera_formatted_salary( $min_salary, '', $salary_unit_label ) );
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="workeera-mobile-apply-container">
                    <div class="workeera-jobdetail-company-card">
                        <div class="workeera-jobdetail-company-logo">
                            <div class="workeera-job-logobox" style="width:80px; height:80px;<?php echo ( $logo_url ? ' background-image: url(\'' . esc_url( $logo_url ) . '\');' : '' ); ?>"></div>
                        </div>
                        <?php if ( $company_name ) : ?>
                            <h3 class="workeera-jobdetail-company-name"><?php echo esc_html( $company_name ); ?></h3>
                        <?php endif; ?>
                        <?php if ( $company_desc ) : ?>
                            <p class="workeera-jobdetail-company-desc"><?php echo esc_html( $company_desc ); ?></p>
                        <?php endif; ?>
                        <?php if ( $company_url ) : ?>
                            <a href="<?php echo esc_url( $company_url ); ?>" class="workeera-jobdetail-company-website" target="_blank" rel="noopener noreferrer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="2" y1="12" x2="22" y2="12" />
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                </svg>
                                <?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $company_url, '/' ) ) ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $company_linkedin ) : ?>
                            <a href="<?php echo esc_url( $company_linkedin ); ?>" class="workeera-jobdetail-company-website workeera-jobdetail-company-linkedin" target="_blank" rel="noopener noreferrer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                                    <rect x="2" y="9" width="4" height="12" />
                                    <circle cx="4" cy="4" r="2" />
                                </svg>
                                <?php echo esc_html_x( 'LinkedIn', 'job detail', 'workeera-remote-tech-job-board' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Apply Button (sticky on scroll via CSS) -->
                    <div class="workeera-jobdetail-apply-container workeera-jobdetail-apply-container-sticky">
                        <?php workeera_job_apply_button($post_id, $is_user_loggedin, $external_apply, $apply_job_url) ?>
                    </div>
                </div>

                <div class="workeera-jobdetail-content">
                    <!-- MAIN -->
                    <div class="workeera-jobdetail-main">

                        <?php if ( $job_content ) : ?>
                            <div class="workeera-jobdetail-section">
                                <h2 class="workeera-jobdetail-section-title"><?php echo esc_html__( 'Job Description', 'workeera-remote-tech-job-board' ); ?></h2>
                                <div class="workeera-jobdetail-description">
                                    <?php echo wp_kses_post( $job_content ); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        $has_taxonomy_details = ( ! empty( $jobtype_labels ) || ! empty( $sector_labels ) || ! empty( $timezone_labels ) );

                        /**
                         * Filter whether the "Job Details" section should render.
                         *
                         * Lets addons (e.g. Workeera Custom Fields) force the section
                         * on when they have their own items to show for this job.
                         *
                         * @param bool $has_taxonomy_details Whether any detail items exist.
                         * @param int  $post_id              Job post ID.
                         */
                        $has_taxonomy_details = apply_filters( 'workeera_job_detail_has_details', $has_taxonomy_details, $post_id );
                        if ( $has_taxonomy_details ) :
                        ?>
                            <div class="workeera-jobdetail-section">
                                <h2 class="workeera-jobdetail-section-title"><?php echo esc_html__( 'Job Details', 'workeera-remote-tech-job-board' ); ?></h2>
                                <div class="workeera-jobdetail-tags">

                                    <?php if ( ! empty( $jobtype_labels ) ) : ?>
                                        <div class="workeera-jobdetail-tag-group">
                                            <span class="workeera-jobdetail-tag-label"><?php echo esc_html__( 'Job Type:', 'workeera-remote-tech-job-board' ); ?></span>
                                            <?php foreach ( $jobtype_labels as $jt_label ) : ?>
                                                <span class="workeera-badge workeera-badge-info"><?php echo esc_html( $jt_label ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $sector_labels ) ) : ?>
                                        <div class="workeera-jobdetail-tag-group">
                                            <span class="workeera-jobdetail-tag-label"><?php echo esc_html__( 'Sector:', 'workeera-remote-tech-job-board' ); ?></span>
                                            <?php foreach ( $sector_labels as $sec_label ) : ?>
                                                <span class="workeera-badge workeera-badge-info"><?php echo esc_html( $sec_label ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $timezone_labels ) ) : ?>
                                        <div class="workeera-jobdetail-tag-group">
                                            <span class="workeera-jobdetail-tag-label"><?php echo esc_html__( 'Timezone:', 'workeera-remote-tech-job-board' ); ?></span>
                                            <?php foreach ( $timezone_labels as $tz_label ) : ?>
                                                <span class="workeera-badge workeera-badge-info"><?php echo esc_html( $tz_label ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ( $loc_type_data ) : ?>
                                        <div class="workeera-jobdetail-tag-group">
                                            <span class="workeera-jobdetail-tag-label"><?php echo esc_html__( 'Work Type:', 'workeera-remote-tech-job-board' ); ?></span>
                                            <span class="workeera-badge workeera-badge-info"><?php echo esc_html( $loc_type_data['label'] ); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    /**
                                     * Render extra "Job Details" items after the Work Type item.
                                     *
                                     * Used by addons (e.g. Workeera Custom Fields) to append their
                                     * own tag-group items to the Job Details section.
                                     *
                                     * @param int $post_id Job post ID.
                                     */
                                    do_action( 'workeera_job_detail_after_work_type', $post_id );
                                    ?>

                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $skill_labels ) ) : ?>
                            <div class="workeera-jobdetail-section">
                                <h2 class="workeera-jobdetail-section-title"><?php echo esc_html__( 'Required Skills', 'workeera-remote-tech-job-board' ); ?></h2>
                                <div class="workeera-jobdetail-skills">
                                    <?php foreach ( $skill_labels as $skill_label ) : ?>
                                        <span class="workeera-jobdetail-skill"><?php echo esc_html( $skill_label ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $benefit_labels ) ) : ?>
                            <div class="workeera-jobdetail-section">
                                <h2 class="workeera-jobdetail-section-title"><?php echo esc_html__( 'Benefits', 'workeera-remote-tech-job-board' ); ?></h2>
                                <div class="workeera-jobdetail-skills">
                                    <?php foreach ( $benefit_labels as $benefit_label ) : ?>
                                        <span class="workeera-jobdetail-skill"><?php echo esc_html( $benefit_label ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="workeera-jobdetail-apply-second-con">
                            <?php workeera_job_apply_button($post_id, $is_user_loggedin, $external_apply, $apply_job_url) ?>
                        </div>

                        <?php if ( ! empty( $related_jobs ) ) : ?>
                            <div class="workeera-jobdetail-section">
                                <h2 class="workeera-jobdetail-section-title"><?php echo esc_html__( 'Related Jobs', 'workeera-remote-tech-job-board' ); ?></h2>
                                <div class="workeera-jobdetail-related">
                                    <?php foreach ( $related_jobs as $related_job ) : ?>
                                        <?php self::render_related_job_card( $related_job ); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- SIDEBAR -->
                    <aside class="workeera-jobdetail-sidebar">
                        <!-- Company Card -->
                        <div class="workeera-jobdetail-company-card">
                            <div class="workeera-jobdetail-company-logo">
                                <div class="workeera-job-logobox" style="width:80px; height:80px;<?php echo ( $logo_url ? ' background-image: url(\'' . esc_url( $logo_url ) . '\');' : '' ); ?>"></div>
                            </div>
                            <?php if ( $company_name ) : ?>
                                <h3 class="workeera-jobdetail-company-name"><?php echo esc_html( $company_name ); ?></h3>
                            <?php endif; ?>
                            <?php if ( $company_desc ) : ?>
                                <p class="workeera-jobdetail-company-desc"><?php echo esc_html( $company_desc ); ?></p>
                            <?php endif; ?>
                            <?php if ( $company_url ) : ?>
                                <a href="<?php echo esc_url( $company_url ); ?>" class="workeera-jobdetail-company-website" target="_blank" rel="noopener noreferrer">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="2" y1="12" x2="22" y2="12" />
                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                    </svg>
                                    <?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $company_url, '/' ) ) ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $company_linkedin ) : ?>
                                <a href="<?php echo esc_url( $company_linkedin ); ?>" class="workeera-jobdetail-company-website workeera-jobdetail-company-linkedin" target="_blank" rel="noopener noreferrer">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                                        <rect x="2" y="9" width="4" height="12" />
                                        <circle cx="4" cy="4" r="2" />
                                    </svg>
                                    <?php echo esc_html_x( 'LinkedIn', 'job detail', 'workeera-remote-tech-job-board' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Apply Button (sticky on scroll via CSS) -->
                        <div class="workeera-jobdetail-apply-container">
                            <?php workeera_job_apply_button($post_id, $is_user_loggedin, $external_apply, $apply_job_url) ?>
                        </div>

                        <!-- Salary highlight sidebar -->
                        <?php if ( $min_salary || $max_salary ) : ?>
                            <div class="workeera-jobdetail-sidebar-salary">
                                <div class="workeera-jobdetail-sidebar-salary-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--workeera-success-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="6" width="20" height="12" rx="2" />
                                        <circle cx="12" cy="12" r="2" />
                                        <path d="M6 12h.01M18 12h.01" />
                                    </svg>
                                </div>
                                <div class="workeera-jobdetail-sidebar-salary-info">
                                    <span class="workeera-jobdetail-sidebar-salary-amount">
                                        <?php
                                        if ( $min_salary && $max_salary && $min_salary !== $max_salary ) {
                                            echo wp_kses_post( workeera_formatted_salary( $min_salary ) ) . ' &ndash; ' . wp_kses_post( workeera_formatted_salary( $max_salary ) );
                                        } elseif ( $max_salary ) {
                                            echo wp_kses_post( workeera_formatted_salary( $max_salary ) );
                                        } else {
                                            echo wp_kses_post( workeera_formatted_salary( $min_salary ) );
                                        }
                                        ?>
                                    </span>
                                    <?php if ( $salary_unit_label ) : ?>
                                        <span class="workeera-jobdetail-sidebar-salary-unit">
                                            <?php
                                            /* translators: %s: salary period label e.g. Hour / Month */
                                            echo esc_html( sprintf( __( '/ %s', 'workeera-remote-tech-job-board' ), $salary_unit_label ) );
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        /**
                         * Fires at the bottom of the single-job detail sidebar.
                         *
                         * @param int $post_id Current job post ID.
                         */
                        do_action( 'workeera_job_detail_sidebar_bottom', $post_id );
                        ?>
                    </aside>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Build a human-readable location string from a location row.
     *
     * @param  array $location_row
     * @return string
     */
    private static function get_location_string( $location_row ) {
        global $wpdb;

        if ( empty( $location_row ) ) {
            return '';
        }

        $parts = [];

        $city_id    = ! empty( $location_row['city_id'] )    ? absint( $location_row['city_id'] )    : 0;
        $country_id = ! empty( $location_row['country_id'] ) ? absint( $location_row['country_id'] ) : 0;

        if ( $city_id > 0 ) {
            $cities_table = $wpdb->prefix . 'workeera_cities';
            $city_label   = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$cities_table} WHERE city_id = %d LIMIT 1", $city_id ) );
            if ( $city_label ) {
                $parts[] = $city_label;
            }
        }

        if ( $country_id > 0 ) {
            $countries_table = $wpdb->prefix . 'workeera_countries';
            $country_label   = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$countries_table} WHERE country_id = %d LIMIT 1", $country_id ) );
            if ( $country_label ) {
                $parts[] = $country_label;
            }
        }

        return implode( ', ', $parts );
    }

    /**
     * Return label for a location type.
     *
     * @param  string $loc_type
     * @return array|null
     */
    private static function get_location_type_display( $loc_type ) {
        $types = [
            'remote' => [ 'label' => esc_html__( 'Remote', 'workeera-remote-tech-job-board' ) ],
            'hybrid' => [ 'label' => esc_html__( 'Hybrid', 'workeera-remote-tech-job-board' ) ],
            'onsite' => [ 'label' => esc_html__( 'On-Site', 'workeera-remote-tech-job-board' ) ],
        ];

        return isset( $types[ $loc_type ] ) ? $types[ $loc_type ] : null;
    }

    /**
     * Fetch related published jobs.
     *
     * Strategy (mirrors common job platforms):
     *  1. Match by shared sector IDs (primary signal — same industry).
     *  2. Fall back to shared skill IDs when no sector match exists.
     *  3. Exclude the current job; limit to 4 results; order newest first.
     *
     * @param  int   $post_id       Current job post ID.
     * @param  array $sector_labels Sector labels keyed by term ID.
     * @param  array $skill_labels  Skill labels keyed by term ID.
     * @return WP_Post[]
     */
    private static function get_related_jobs( $post_id, $sector_labels, $skill_labels ) {
        global $wpdb;

        $related_limit = apply_filters( 'workeera_related_jobs_limit', 4 );
        $jf_table      = $wpdb->prefix . 'workeera_job_fields';
        $posts_table   = $wpdb->prefix . 'posts';

        // Prefer sector IDs; fall back to skill IDs.
        $sector_ids = array_map( 'absint', array_keys( $sector_labels ) );
        $skill_ids  = array_map( 'absint', array_keys( $skill_labels ) );

        $rel_ids = ! empty( $sector_ids ) ? $sector_ids : $skill_ids;

        if ( empty( $rel_ids ) ) {
            return [];
        }

        $use_sectors = ! empty( $sector_ids );
        $rel_table   = $wpdb->prefix . ( $use_sectors ? 'workeera_sectors_relation' : 'workeera_skills_relation' );
        $rel_col     = $use_sectors ? 'sector_id' : 'skill_id';

        $placeholders = implode( ', ', array_fill( 0, count( $rel_ids ), '%d' ) );

        // Build prepared values: rel_ids + post_id + limit.
        $prepare_values = array_merge( $rel_ids, [ absint( $post_id ), $related_limit ] );

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$posts_table} AS p
                 INNER JOIN {$rel_table} AS rel ON rel.object_id = p.ID AND rel.object_type = 'post'
                 INNER JOIN {$jf_table} AS jf ON jf.job_id = p.ID
                 WHERE rel.{$rel_col} IN ( {$placeholders} )
                   AND p.post_type   = 'workeera_job'
                   AND p.post_status = 'publish'
                   AND p.ID          != %d
                   AND jf.is_filled  = 0
                 ORDER BY p.post_date DESC
                 LIMIT %d",
                $prepare_values
            )
        );

        if ( empty( $ids ) ) {
            return [];
        }

        $jobs = [];
        foreach ( $ids as $id ) {
            $p = get_post( absint( $id ) );
            if ( $p ) {
                $jobs[] = $p;
            }
        }

        return $jobs;
    }

    /**
     * Render a compact job card for the Related Jobs section.
     * Reuses the same card markup/classes as class-listings.php.
     *
     * @param WP_Post $related_post
     */
    private static function render_related_job_card( WP_Post $related_post ) {
        $job_id  = absint( $related_post->ID );
        $job_url = get_permalink( $job_id );

        $job_fields  = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $min_salary  = isset( $job_fields['min_salary'] )  ? absint( $job_fields['min_salary'] )  : 0;
        $max_salary  = isset( $job_fields['max_salary'] )  ? absint( $job_fields['max_salary'] )  : 0;
        $salary_type = isset( $job_fields['salary_type'] ) ? $job_fields['salary_type']            : '';

        $salary_units      = workeera_salary_units();
        $salary_unit_label = isset( $salary_units[ $salary_type ] ) ? $salary_units[ $salary_type ] : '';

        $logo_url     = workeera_job_logo( $job_id );
        $company_info = workeera_job_company_info( $job_id );
        $company_name = isset( $company_info['name'] ) ? workeera_strip_slashes( $company_info['name'] ) : '';

        $location_row = Workeera_Object_Terms_Relation::get_post_location( $job_id );
        $location_str = self::get_location_string( $location_row );

        $posted_ago   = human_time_diff( get_post_time( 'U', false, $related_post ), current_time( 'timestamp' ) );
        /* translators: %s: human-readable time difference */
        $posted_label = sprintf( esc_html__( 'Posted %s ago', 'workeera-remote-tech-job-board' ), esc_html( $posted_ago ) );
        ?>
        <div class="workeera-job-card">
            <div class="workeera-job-body">
                <div class="workeera-job-left">
                    <div class="workeera-job-logo">
                        <a href="<?php echo esc_url( $job_url ); ?>">
                            <div class="workeera-job-logobox"<?php echo ( $logo_url ? ' style="background-image: url(\'' . esc_url( $logo_url ) . '\');"' : '' ); ?>></div>
                        </a>
                    </div>
                    <div class="workeera-job-info">
                        <div class="workeera-job-title">
                            <a href="<?php echo esc_url( $job_url ); ?>"><?php echo esc_html( get_the_title( $job_id ) ); ?></a>
                        </div>
                        <?php if ( $company_name ) : ?>
                            <p class="workeera-job-company"><?php echo esc_html( $company_name ); ?></p>
                        <?php endif; ?>
                        <div class="workeera-job-meta">
                            <?php if ( $location_str ) : ?>
                                <span class="workeera-job-meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <?php echo esc_html( $location_str ); ?>
                                </span>
                            <?php endif; ?>
                            <span class="workeera-job-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <?php echo esc_html( $posted_label ); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="workeera-job-right">
                    <?php if ( $min_salary || $max_salary ) : ?>
                        <span class="workeera-job-salary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="6" width="20" height="12" rx="2"/>
                                <circle cx="12" cy="12" r="2"/>
                                <path d="M6 12h.01M18 12h.01"/>
                            </svg>
                            <?php
                            if ( $min_salary && $max_salary && $min_salary !== $max_salary ) {
                                echo wp_kses_post( workeera_formatted_salary( $min_salary ) ) . ' - ' . wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                            } elseif ( $max_salary ) {
                                echo wp_kses_post( workeera_formatted_salary( $max_salary, '', $salary_unit_label ) );
                            } else {
                                echo wp_kses_post( workeera_formatted_salary( $min_salary, '', $salary_unit_label ) );
                            }
                            ?>
                        </span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $job_url ); ?>" class="workeera-btn workeera-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        <?php echo esc_html__( 'Apply Now', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}

new Workeera_Post_Detail();