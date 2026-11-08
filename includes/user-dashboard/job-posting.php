<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the user-dashboard job posting form and its AJAX endpoints.
 */
class Workeera_User_Job_Posting {

    /**
     * Register AJAX hooks for job submission and company logo uploads.
     */
    public function __construct() {

        add_action( 'wp_ajax_workeera_user_job_submission_call', [ $this, 'user_job_submission' ] );

        add_action( 'wp_ajax_workeera_upload_company_logo_picture', [ $this, 'upload_company_logo_picture' ] );
        add_action( 'wp_ajax_workeera_remve_uploaded_company_logo_picture', [ $this, 'remove_company_logo_picture' ] );
    }

    /**
     * Render the job posting / editing form for the current user.
     */
    public static function form() {
        global $workeera_plugin_settings;
        $random_num = wp_rand(10000000, 99999999);

        if (!class_exists('Workeera_Form_Parts')) {
            include_once WORKEERA_ABSPATH . 'includes/common-form-sections.php';
        }

        $user_id = get_current_user_id();

        $job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;

        // Multi-company: normalized list (lazy-migrates a legacy single record).
        $user_companies = workeera_get_user_companies( $user_id, true );
        // For a NEW job the manual company fields start empty — an existing
        // company is picked from the dropdown (its data snapshotted server-side),
        // or "Add new company" reveals the empty fields. When editing, this is
        // overwritten below with the job's own company_info snapshot.
        $company_info = array();

        $salary_unit_switch = isset($workeera_plugin_settings['general_settings']['salary_unit_field']) ? $workeera_plugin_settings['general_settings']['salary_unit_field'] : '';
        $salary_unit = isset($workeera_plugin_settings['general_settings']['salary_unit']) ? $workeera_plugin_settings['general_settings']['salary_unit'] : 'hourly';
        $currency_symbol = workeera_currency_symbol();
        $salary_units = workeera_salary_units();

        $is_updating_job = $job_fields = false;
        if ($job_id > 0) {
            $job_post = get_post($job_id);
            if (isset($job_post->post_type) && $job_post->post_type == 'workeera_job' && workeera_is_user_can_job_post($job_id)) {
                $is_updating_job = true;
                $num_of_vacancies = workeera_get_object_meta( $job_id, 'num_of_vacancies' );
                $company_info = workeera_get_object_meta( $job_id, 'company_info' );

                $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );

                if (isset($job_fields['salary_type']) && !empty($job_fields['salary_type']) && isset($salary_units[$job_fields['salary_type']])) {
                    $salary_unit = $job_fields['salary_type'];
                }
            } else {
                $job_id = 0;
            }
        }
        ?>
        <div class="workeera-usrform-wrapper workeera-usrdshb-main-wrap">
            <div class="workeera-usrdshb-container">

                <form method="post" class="workeera-usrdshb-job-form workeera-user-form workeera-withbtn-inline-lodr">
                    <div class="workeera-usrform-section-card workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Basic details', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                        </div>
                        <div class="workeera-usrform-section-body">
                            <div class="workeera-usrform-form-panel">
                                <div class="workeera-usrform-fields-main-wrapper">
                                    <div class="workeera-usrform-field-group">
                                        <label for="job-title-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Title', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'job-title-' . $random_num,
                                            'name'             => 'job_title',
                                            'classes'          => ['workeera-usrform-field-input'],
                                            'extra_attributes' => [
                                                'placeholder' => esc_attr_x( 'e.g. Senior Python Developer', 'job post form', 'workeera-remote-tech-job-board' ),
                                                'required'    => '',
                                                'autofocus'   => '',
                                            ],
                                            'value'            => ($is_updating_job ? workeera_strip_slashes($job_post->post_title) : ''),
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <div class="workeera-usrform-field-group">
                                        <label for="job-description-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Job description', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        $job_description = $is_updating_job ? workeera_optimize_job_content($job_post->post_content) : '';
                                        $editr_settings = array(
                                            'textarea_rows' => 10,
                                            'editor_class'  => 'workeera-wp-editor workeera-job-desc-editor',
                                            'textarea_name' => 'job_description',
                                            'media_buttons' => false,
                                            'quicktags'     => false,
                                            'tinymce'       => array(
                                                'toolbar1' => 'bold,italic,underline,bullist,numlist',
                                                'toolbar2' => '',
                                            ),
                                        );
                                        wp_editor($job_description, 'job-description-' . $random_num, $editr_settings);
                                        ?>
                                        <div class="workeera-desc-charcount-wrap">
                                            <span class="workeera-desc-charcount">0</span>/<?php echo absint(workeera_job_description_max_len()) ?>
                                        </div>
                                    </div>
                                    <div class="<?php echo esc_attr($salary_unit_switch == 'on' ? 'workeera-grid-three-cols' : 'workeera-grid-two-cols') ?>">
                                        <div class="workeera-usrform-field-group">
                                            <label for="min-salary-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Min Salary', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'min-salary-' . $random_num,
                                                'name'             => 'workeera_field_min_salary',
                                                'input_type'       => 'number',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'extra_attributes' => [
                                                    'min'         => '1',
                                                    'step'        => '1',
                                                ],
                                                'value'            => isset($job_fields['min_salary']) && !empty($job_fields['min_salary']) ? workeera_absint($job_fields['min_salary']) : '',
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                        <div class="workeera-usrform-field-group">
                                            <label for="max-salary-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Max Salary', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'max-salary-' . $random_num,
                                                'name'             => 'workeera_field_max_salary',
                                                'input_type'       => 'number',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'extra_attributes' => [
                                                    'min'         => '1',
                                                    'step'        => '1',
                                                ],
                                                'value'            => isset($job_fields['max_salary']) && !empty($job_fields['max_salary']) ? workeera_absint($job_fields['max_salary']) : '',
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                        <?php
                                        if ($salary_unit_switch == 'on') {
                                            ?>
                                            <div class="workeera-usrform-field-group">
                                                <label for="salary-unit-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Salary Unit', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                                <?php
                                                Workeera_Form_Fields::select( [
                                                    'id'               => 'salary-unit-' . $random_num,
                                                    'name'             => 'workeera_field_salary_type',
                                                    'classes'          => ['workeera-usrform-field-select', 'workeera-select2-dropdown'],
                                                    'extra_attributes' => [ 'data-placeholder' => esc_html__( 'Select', 'workeera-remote-tech-job-board' ) ],
                                                    'options'          => workeera_salary_units(),
                                                    'value'            => $salary_unit,
                                                    'default'          => '',
                                                ] );
                                                ?>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="workeera-usrform-fields-row">
                                        <div class="workeera-usrform-field-group">
                                            <label for="num-vacancies-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Total Positions', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'num-vacancies-' . $random_num,
                                                'name'             => 'workeera_meta[num_of_vacancies]',
                                                'input_type'       => 'number',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'extra_attributes' => [
                                                    'placeholder' => '1',
                                                    'min'         => '1',
                                                    'step'        => '1',
                                                ],
                                                'value'            => isset($num_of_vacancies) ? absint($num_of_vacancies) : '1',
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                        <div class="workeera-usrform-field-group">
                                            <label for="deadline-date-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Application Deadline', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'deadline-date-' . $random_num,
                                                'name'             => 'workeera_field_deadline_date',
                                                'classes'          => ['workeera-usrform-field-input', 'workeera-date-input'],
                                                'extra_attributes' => [
                                                    'readonly'       => '',
                                                    'data-miny'      => gmdate('Y'),
                                                    'data-maxy'      => gmdate('Y') + 5,
                                                    'data-min-date'  => gmdate('d-m-Y'),
                                                ],
                                                'value'            => isset($job_fields['application_deadline']) && !empty($job_fields['application_deadline']) ? gmdate('d/m/Y', strtotime($job_fields['application_deadline'])) : '',
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                    </div>

                                    <?php
                                    // Apply-method field — driven by the site-wide
                                    // 'job_apply_method' setting in Job Submission options.
                                    // - 'internal' → no field, default behaviour (popup form).
                                    // - 'external' → URL field only, method forced to external.
                                    // - 'both'     → select + URL field, URL hidden until "External".
                                    $job_apply_method_setting = isset( $workeera_plugin_settings['job_submission_settings']['job_apply_method'] )
                                        ? $workeera_plugin_settings['job_submission_settings']['job_apply_method']
                                        : 'internal';

                                    $saved_apply_method = $is_updating_job ? workeera_get_object_meta( $job_id, 'apply_method' ) : '';
                                    $saved_apply_url    = $is_updating_job ? workeera_get_object_meta( $job_id, 'apply_job_url' ) : '';

                                    if ( $job_apply_method_setting !== 'internal' ) :
                                        $current_method = ! empty( $saved_apply_method )
                                            ? $saved_apply_method
                                            : ( $job_apply_method_setting === 'external' ? 'external' : 'internal' );
                                        $show_url_field = ( $current_method === 'external' );
                                        ?>
                                        <div class="workeera-usrform-fields-row workeera-apply-method-row">
                                            <?php if ( $job_apply_method_setting === 'both' ) : ?>
                                                <div class="workeera-usrform-field-group">
                                                    <label for="apply-method-<?php echo absint( $random_num ); ?>" class="workeera-usrform-field-label">
                                                        <?php echo esc_html_x( 'Apply method', 'job post form', 'workeera-remote-tech-job-board' ); ?>
                                                    </label>
                                                    <?php
                                                    Workeera_Form_Fields::select( [
                                                        'id'      => 'apply-method-' . $random_num,
                                                        'name'    => 'workeera_meta[apply_method]',
                                                        'classes' => [ 'workeera-usrform-field-select', 'workeera-select2-dropdown', 'workeera-apply-method-select' ],
                                                        'value'   => $current_method,
                                                        'options' => [
                                                            'internal' => esc_html_x( 'Apply on this site', 'apply method option', 'workeera-remote-tech-job-board' ),
                                                            'external' => esc_html_x( 'Apply on external URL', 'apply method option', 'workeera-remote-tech-job-board' ),
                                                        ],
                                                        'default' => 'internal',
                                                    ] );
                                                    ?>
                                                </div>
                                            <?php else : ?>
                                                <input type="hidden" name="workeera_meta[apply_method]" value="external" />
                                            <?php endif; ?>

                                            <div class="workeera-usrform-field-group workeera-apply-method-url-field<?php echo esc_attr( $show_url_field ? '' : ' workeera-hide' ); ?>">
                                                <label for="apply-url-<?php echo absint( $random_num ); ?>" class="workeera-usrform-field-label">
                                                    <?php echo esc_html_x( 'Job apply URL', 'job post form', 'workeera-remote-tech-job-board' ); ?>
                                                </label>
                                                <?php
                                                Workeera_Form_Fields::input( [
                                                    'id'               => 'apply-url-' . $random_num,
                                                    'name'             => 'workeera_meta[apply_job_url]',
                                                    'input_type'       => 'url',
                                                    'classes'          => [ 'workeera-usrform-field-input' ],
                                                    'extra_attributes' => [
                                                        'placeholder' => 'https://example.com/jobs/apply',
                                                    ],
                                                    'value'            => $saved_apply_url,
                                                    'default'          => '',
                                                ] );
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="workeera-usrform-section-card workeera-usrform-section-openclose workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Company details', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                            <div class="workeera-usrform-section-actions">
                                <a href="#" class="workeera-usrform-icon-btn" title="<?php echo esc_attr_x( 'Collapse', 'job post form', 'workeera-remote-tech-job-board' ); ?>">
                                    <svg class="workeera-usrform-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="18 15 12 9 6 15" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div class="workeera-usrform-section-body">
                            <div class="workeera-usrform-form-panel">
                                <?php
                                $has_companies = ! empty( $user_companies );
                                // "Add new company" only when posting a new job AND under the per-user cap.
                                $allow_add_new = ! $is_updating_job && workeera_user_can_add_company( $user_id );

                                // On edit: is the job's stored company_id still a saved company?
                                $job_company_id    = ( $is_updating_job && ! empty( $company_info['company_id'] ) ) ? $company_info['company_id'] : '';
                                $job_company_saved = ( '' !== $job_company_id ) ? workeera_get_user_company( $user_id, $job_company_id ) : null;

                                // The job's company was deleted from the saved list — show its stored
                                // snapshot (already in job meta) as a read-only box, dropdown unselected.
                                $show_current_company_box = ( $is_updating_job && $has_companies && ! $job_company_saved && ! empty( $company_info['name'] ) );

                                // Dropdown pre-selection: first company (new job), the job's saved
                                // company (edit), or nothing (orphaned edit).
                                if ( ! $is_updating_job ) {
                                    $selected_company_id = $has_companies ? $user_companies[0]['id'] : '';
                                } elseif ( $job_company_saved ) {
                                    $selected_company_id = $job_company_id;
                                } else {
                                    $selected_company_id = '';
                                }

                                // The manual fields render as the add-new form (new job + has companies)
                                // or as the plain first-company form (no companies). On edit WITH
                                // companies the dropdown alone drives the choice — no manual fields.
                                $fields_collapsible    = ( $has_companies && $allow_add_new );
                                $render_fields_wrapper = ( ! $has_companies ) || $fields_collapsible;
                                ?>
                                <?php if ( $show_current_company_box ) :
                                    $cur_host = ! empty( $company_info['website'] ) ? preg_replace( '#^https?://#', '', untrailingslashit( $company_info['website'] ) ) : '';
                                    $cur_logo = workeera_job_logo( $job_id, false );
                                    ?>
                                    <div class="workeera-usrform-company-current">
                                        <div class="workeera-usrform-company-current-logo">
                                            <?php if ( $cur_logo ) : ?>
                                                <img src="<?php echo esc_url( $cur_logo ); ?>" alt="">
                                            <?php else : ?>
                                                <span class="workeera-usrform-company-current-ph"><?php echo esc_html( mb_strtoupper( mb_substr( $company_info['name'], 0, 1 ) ) ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="workeera-usrform-company-current-info">
                                            <strong><?php echo esc_html( $company_info['name'] ); ?></strong>
                                            <?php if ( $cur_host ) : ?><a href="<?php echo esc_url( $company_info['website'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $cur_host ); ?></a><?php endif; ?>
                                            <?php if ( ! empty( $company_info['tagline'] ) ) : ?><span class="workeera-usrform-company-current-tag"><?php echo esc_html( $company_info['tagline'] ); ?></span><?php endif; ?>
                                        </div>
                                        <span class="workeera-usrform-company-current-badge"><?php echo esc_html_x( 'Current company', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                                    </div>
                                    <p class="workeera-usrform-company-current-note"><?php echo esc_html_x( 'This company is no longer in your saved list but stays on this job. To change it, pick another company below.', 'job post form', 'workeera-remote-tech-job-board' ); ?></p>
                                <?php endif; ?>
                                <?php if ( $has_companies ) : ?>
                                    <div class="workeera-usrform-company-picker">
                                        <div class="workeera-usrform-field-group">
                                            <label class="workeera-usrform-field-label"><?php echo esc_html_x( 'Select company', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            $company_options = array('' => esc_html_x( 'Select a company', 'job post form', 'workeera-remote-tech-job-board' ));
                                            foreach ( $user_companies as $uc ) {
                                                $company_options[ $uc['id'] ] = $uc['name'];
                                            }
                                            Workeera_Form_Fields::select( array(
                                                'name'             => 'workeera_company_select',
                                                'classes'          => array( 'workeera-usrform-field-select', 'workeera-select2-dropdown', 'workeera-company-select' ),
                                                'extra_attributes' => array( 'data-placeholder' => esc_attr_x( 'Choose a company', 'job post form', 'workeera-remote-tech-job-board' ) ),
                                                'options'          => $company_options,
                                                'value'            => $selected_company_id,
                                                'default'          => '',
                                            ) );
                                            ?>
                                        </div>
                                        <?php if ( $allow_add_new ) : ?>
                                            <div class="workeera-usrform-company-addnew-con">
                                                <button type="button" class="workeera-usrform-btn workeera-usrform-company-addnew-btn">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                                                    <?php echo esc_html_x( 'Add new company', 'job post form', 'workeera-remote-tech-job-board' ); ?>
                                                </button>
                                                <button type="button" class="workeera-usrform-btn workeera-usrform-company-cancel-btn workeera-hide">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                                                    <?php echo esc_html_x( 'Cancel', 'job post form', 'workeera-remote-tech-job-board' ); ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="workeera_company_mode" class="workeera-company-mode" value="existing">
                                    <input type="hidden" name="workeera_meta[company_info][company_id]" class="workeera-company-selected-id" value="<?php echo esc_attr( $selected_company_id ); ?>">
                                <?php endif; ?>
                                <?php if ( $render_fields_wrapper ) : ?>
                                <div class="workeera-usrform-fields-main-wrapper<?php echo $fields_collapsible ? ' workeera-company-fields-collapsible workeera-hide' : ''; ?>">
                                    <?php
                                    $logo_url = '';
                                    if (!$is_updating_job) {
                                        if (!empty($company_info) && isset($company_info['logo']['file']) && !empty($company_info['logo']['file']) && file_exists($company_info['logo']['file'])) {
                                            if (!empty($company_info['logo']['thumb_path']) && file_exists($company_info['logo']['thumb_path'])) {
                                                $logo_url = $company_info['logo']['thumb_url'];
                                            } else {
                                                $logo_url = $company_info['logo']['url'];
                                            }
                                            $logo_url = add_query_arg(['ver' => absint($company_info['logo']['version'])], $logo_url);
                                        }
                                    } else {
                                        $logo_url = workeera_job_logo($job_id, false);
                                    }
                                    ?>
                                    <div class="workeera-usrform-photo-row">
                                        <?php if ( workeera_company_field_enabled( 'logo' ) ) : ?>
                                        <div class="workeera-usrform-photo-upload<?php echo esc_attr(!empty($logo_url) ? ' workeera-with-filled-photo' : '') ?>" data-job_id="<?php echo esc_attr($job_id) ?>" data-img-for="company_logo_picture">
                                            <input type="file" id="company-logo-<?php echo absint($random_num) ?>" accept="image/*" class="workeera-hide">
                                            <div class="workeera-user-photo-imgholdr<?php echo esc_attr(empty($logo_url) ? ' workeera-hide' : '') ?>">
                                                <div class="workeera-user-photo-imgbox" style="background-image: url('<?php echo esc_attr($logo_url) ?>');"></div>
                                                <div class="workeera-user-photo-remve" title="<?php echo esc_attr_x( 'Remove Logo', 'job post form', 'workeera-remote-tech-job-board' ); ?>"><div class="workeera-remove-icon"></div></div>
                                            </div>
                                            <div class="workeera-photo-svgicnlbl-con<?php echo esc_attr(!empty($logo_url) ? ' workeera-hide' : '') ?>" title="<?php echo esc_attr_x( 'Upload Logo', 'job post form', 'workeera-remote-tech-job-board' ); ?>">
                                                <svg width="20" class="workeera-user-photo-svgicn" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                                    <circle cx="12" cy="13" r="4" />
                                                </svg>
                                                <span class="workeera-user-photo-title"><?php echo esc_html_x( 'Company Logo', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="workeera-usrform-photo-fields">
                                            <div class="workeera-usrform-fields-row">
                                                <div class="workeera-usrform-field-group">
                                                    <label for="company-name-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Company name', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                                    <?php
                                                    Workeera_Form_Fields::input( [
                                                        'id'               => 'company-name-' . $random_num,
                                                        'name'             => 'workeera_meta[company_info][name]',
                                                        'classes'          => ['workeera-usrform-field-input'],
                                                        'extra_attributes' => [
                                                            'placeholder' => esc_attr_x( 'e.g. Digi Soft', 'job post form', 'workeera-remote-tech-job-board' ),
                                                            'required'    => '',
                                                        ],
                                                        'value'            => isset($company_info['name']) ? workeera_strip_slashes($company_info['name']) : '',
                                                        'default'          => '',
                                                    ] );
                                                    ?>
                                                </div>
                                                <?php if ( workeera_company_field_enabled( 'website' ) ) : ?>
                                                <div class="workeera-usrform-field-group">
                                                    <label for="company-website-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Website', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                                    <?php
                                                    Workeera_Form_Fields::input( [
                                                        'id'               => 'company-website-' . $random_num,
                                                        'name'             => 'workeera_meta[company_info][website]',
                                                        'classes'          => ['workeera-usrform-field-input'],
                                                        'extra_attributes' => [
                                                            'placeholder' => 'https://exampleurl.com',
                                                        ],
                                                        'value'            => isset($company_info['website']) ? esc_url($company_info['website']) : '',
                                                        'default'          => '',
                                                    ] );
                                                    ?>
                                                </div>
                                                <?php else : ?>
                                                    <input type="hidden" name="workeera_meta[company_info][website]" value="<?php echo esc_attr( isset( $company_info['website'] ) ? $company_info['website'] : '' ); ?>">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="workeera-grid-two-one">
                                        <?php if ( workeera_company_field_enabled( 'tagline' ) ) : ?>
                                        <div class="workeera-usrform-field-group">
                                            <label for="company-tagline-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Tagline', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'company-tagline-' . $random_num,
                                                'name'             => 'workeera_meta[company_info][tagline]',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'extra_attributes' => [
                                                    'placeholder' => esc_attr_x( 'Brief description about the company', 'job post form', 'workeera-remote-tech-job-board' ),
                                                ],
                                                'value'            => isset($company_info['tagline']) ? workeera_strip_slashes($company_info['tagline']) : '',
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                        <?php else : ?>
                                            <input type="hidden" name="workeera_meta[company_info][tagline]" value="<?php echo esc_attr( isset( $company_info['tagline'] ) ? $company_info['tagline'] : '' ); ?>">
                                        <?php endif; ?>
                                        <?php if ( workeera_company_field_enabled( 'employee_count' ) ) : ?>
                                        <div class="workeera-usrform-field-group">
                                            <label for="company-employee-count-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Number of employees', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::select( [
                                                'id'               => 'company-employee-count-' . $random_num,
                                                'name'             => 'workeera_meta[company_info][employee_count]',
                                                'value'            => isset($company_info['employee_count']) ? $company_info['employee_count'] : '',
                                                'classes'          => ['workeera-usrform-field-select', 'workeera-select2-dropdown'],
                                                'extra_attributes' => [
                                                    'data-placeholder' => esc_attr_x( 'Select number of employees', 'job post form', 'workeera-remote-tech-job-board' ),
                                                ],
                                                'options'          => workeera_company_employee_count_fields(),
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                        <?php else : ?>
                                            <input type="hidden" name="workeera_meta[company_info][employee_count]" value="<?php echo esc_attr( isset( $company_info['employee_count'] ) ? $company_info['employee_count'] : '' ); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( workeera_company_field_enabled( 'linkedin' ) ) : ?>
                                    <div class="workeera-usrform-field-group">
                                        <label for="company-linkedin-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'LinkedIn', 'job post form', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'company-linkedin-' . $random_num,
                                            'name'             => 'workeera_meta[company_info][linkedin]',
                                            'classes'          => ['workeera-usrform-field-input'],
                                            'extra_attributes' => [
                                                'placeholder' => 'https://linkedin.com/company/example',
                                            ],
                                            'value'            => isset($company_info['linkedin']) ? esc_url($company_info['linkedin']) : '',
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <?php else : ?>
                                        <input type="hidden" name="workeera_meta[company_info][linkedin]" value="<?php echo esc_attr( isset( $company_info['linkedin'] ) ? $company_info['linkedin'] : '' ); ?>">
                                    <?php endif; ?>
                                    <?php if ( $fields_collapsible ) : ?>
                                        <div class="workeera-usrform-company-addnew-note">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                            <span><?php echo esc_html_x( 'This company will be saved to your companies and assigned to this job when you post it.', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="workeera-usrform-section-card workeera-usrform-section-openclose workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Further details', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                            <div class="workeera-usrform-section-actions">
                                <a href="#" class="workeera-usrform-icon-btn" title="<?php echo esc_attr_x( 'Collapse', 'job post form', 'workeera-remote-tech-job-board' ); ?>">
                                    <svg class="workeera-usrform-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="18 15 12 9 6 15" />
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <div class="workeera-usrform-section-body">
                            <div class="workeera-usrform-form-panel">
                                <div class="workeera-usrform-fields-main-wrapper">
                                    <div class="workeera-usrform-fields-row">
                                        <?php
                                        $taxonomy_fields = [
                                            'sectors'  => [
                                                'label'       => esc_html_x( 'Sector', 'job post form', 'workeera-remote-tech-job-board' ),
                                                'placeholder' => esc_html_x( 'Select sector', 'job post form', 'workeera-remote-tech-job-board' ),
                                                'multi'       => false,
                                            ],
                                            'timezones' => [
                                                'label'       => esc_html_x( 'Timezone', 'job post form', 'workeera-remote-tech-job-board' ),
                                                'placeholder' => esc_html_x( 'Select timezone', 'job post form', 'workeera-remote-tech-job-board' ),
                                                'multi'       => false,
                                            ],
                                        ];
                                        self::render_taxonomy_fields( $job_id, $taxonomy_fields, $random_num );
                                        ?>
                                    </div>
                                    <?php
                                    $taxonomy_fields = [
                                        'jobtypes' => [
                                            'label'       => esc_html_x( 'Job Types', 'job post form', 'workeera-remote-tech-job-board' ),
                                            'placeholder' => esc_html_x( 'Select job types', 'job post form', 'workeera-remote-tech-job-board' ),
                                            'multi'       => true,
                                        ],
                                        'skills'   => [
                                            'label'       => esc_html_x( 'Required Skills', 'job post form', 'workeera-remote-tech-job-board' ),
                                            'placeholder' => esc_html_x( 'Select or add skills', 'job post form', 'workeera-remote-tech-job-board' ),
                                            'multi'       => true,
                                            'checkboxes'  => true,
                                        ],
                                        'benefits'  => [
                                            'label'       => esc_html_x( 'Benefits', 'job post form', 'workeera-remote-tech-job-board' ),
                                            'placeholder' => esc_html_x( 'Select benefits', 'job post form', 'workeera-remote-tech-job-board' ),
                                            'multi'       => true,
                                            'checkboxes'  => true,
                                        ],
                                    ];
                                    self::render_taxonomy_fields( $job_id, $taxonomy_fields, $random_num );
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <?php
                    /**
                     * Render extra job-form sections right before the Location section.
                     *
                     * Used by addons (e.g. Workeera Custom Fields) to inject their own
                     * section cards into the front-end job posting form.
                     *
                     * @param int $job_id Job post ID being edited (0 for a new job).
                     */
                    do_action( 'workeera_job_form_before_location', $job_id );
                    ?>

                    <div class="workeera-usrform-section-card workeera-usrform-section-openclose workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Location', 'job post form', 'workeera-remote-tech-job-board' ); ?></span>
                            <div class="workeera-usrform-section-actions">
                                <a href="#" class="workeera-usrform-icon-btn" title="<?php echo esc_attr_x( 'Collapse', 'job post form', 'workeera-remote-tech-job-board' ); ?>">
                                    <svg class="workeera-usrform-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="18 15 12 9 6 15" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div class="workeera-usrform-section-body">
                            <div class="workeera-usrform-form-panel">
                                <div class="workeera-usrform-fields-main-wrapper workeera-custom-location-field">
                                    <?php Workeera_Form_Parts::location($job_id, $random_num) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    if (!$is_updating_job) {
                        workeera_terms_privacy_policy();
                    }
                    ?>
                    <div class="workeera-form-submitbtn-con">
                        <?php
                        if ($is_updating_job) {
                            ?>
                            <input type="hidden" name="job_id" value="<?php echo absint($job_id) ?>">
                            <button type="submit" class="workeera-btn workeera-large-btn"><?php echo esc_html_x( 'Update Job', 'job post form', 'workeera-remote-tech-job-board' ); ?></button>
                            <?php
                        } else {
                            ?>
                            <button type="submit" class="workeera-btn workeera-large-btn"><?php echo esc_html_x( 'Post Job', 'job post form', 'workeera-remote-tech-job-board' ); ?></button>
                            <?php
                        }
                        ?>
                        <div class="workeera-form-actions-con workeera-post-token-con workeera-hide" data-post-id="<?php echo absint($job_id); ?>"></div>
                        <input type="hidden" name="action" value="workeera_user_job_submission_call">
                    </div>
                </form>

            </div>
        </div>
        <?php
    }
    
    /**
     * Render select2-powered taxonomy select fields for a post.
     *
     * @param int   $post_id         Post ID.
     * @param array $taxonomy_fields Config array keyed by tax_slug.
     */
    public static function render_taxonomy_fields( $post_id, $taxonomy_fields, $random_num ) {
        foreach ( $taxonomy_fields as $tax_slug => $field_cfg ) {
            $label       = isset( $field_cfg['label'] ) ? $field_cfg['label'] : '';
            $placeholder = isset( $field_cfg['placeholder'] ) ? $field_cfg['placeholder'] : '';
            $is_multi    = ! empty( $field_cfg['multi'] );
            $checkboxes  = isset($field_cfg['checkboxes']) && ! empty( $field_cfg['checkboxes'] );

            // Total terms in this taxonomy
            $total_terms = Workeera_Object_Terms::total_found( $tax_slug );

            if ( $total_terms < 1 ) {
                continue;
            }

            // Load currently saved terms as pre-selected options.
            $saved_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, $tax_slug );

            $options = [ '' => $placeholder ];
            if ( WORKEERA_TERMS_COUNT_LITE >= $total_terms ) {

                if ( $checkboxes ) {
                    $options = [];
                }

                $terms_options = Workeera_Object_Terms::terms_options( $tax_slug );
                if ( !empty($terms_options) ) {
                    foreach ( $terms_options as $term_id => $term_label ) {
                        $options[ $term_id ] = $term_label;
                    }
                }
            } else {
                foreach ( $saved_labels as $term_id => $term_label ) {
                    $options[ $term_id ] = $term_label;
                }
            }

            $saved_value = array_keys( $saved_labels );
            if ( ! $is_multi ) {
                $saved_value = ! empty( $saved_value ) ? $saved_value[0] : '';
            }

            // Build classes.
            if ( WORKEERA_TERMS_COUNT_LITE >= $total_terms ) {
                $classes = [ 'workeera-usrform-field-select', 'workeera-select2-dropdown', 'workeera-select2-wsearch' ];
            } else {
                $classes = [ 'workeera-usrform-field-select', 'workeera-tax-term-selectr' ];
            }

            $field_name = 'workeera_terms[' . $tax_slug . ']' . ( $is_multi ? '[]' : '' );

            $extra_attrs = [
                'data-placeholder' => $placeholder,
                'data-tax-slug'    => $tax_slug,
            ];
            if ( $is_multi ) {
                $extra_attrs['data-multi'] = '1';
            }
            ?>
            <div class="workeera-usrform-field-group workeera-group-extended-gap">
                <label for="<?php echo esc_attr($tax_slug) ?>-field-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html( $label ) ?></label>
                <?php
                if ( WORKEERA_TERMS_COUNT_LITE >= $total_terms && $checkboxes ) {
                    ?>
                    <div class="workeera-grid-optbtns-main" data-tax-slug="<?php echo esc_attr( $tax_slug ); ?>">
                        <?php
                        Workeera_Form_Fields::option_buttons( [
                            'name'          => $field_name,
                            'value'         => $saved_value,
                            'multiple'      => $is_multi,
                            'options'       => $options,
                            'default'       => '',
                        ] );
                        ?>
                    </div>
                    <?php
                } else {
                    Workeera_Form_Fields::select( [
                        'id'               => $tax_slug . '-field-' . $random_num,
                        'name'             => $field_name,
                        'options'          => $options,
                        'classes'          => $classes,
                        'value'            => $saved_value,
                        'multiple'         => $is_multi,
                        'extra_attributes' => $extra_attrs,
                        'default'          => '',
                    ] );
                }
                ?>
            </div>
            <?php
        }
    }

    /**
     * Create or replace the company logo attachment for a job listing.
     *
     * @param  int    $job_id         Job post ID the logo belongs to.
     * @param  string $file_path      Absolute path to the uploaded file.
     * @param  string $file_url       Public URL of the uploaded file.
     * @param  array  $file_mime_type Mime info array returned by wp_check_filetype().
     * @param  string $file_name      Original file name.
     * @param  int    $user_id        Owner user ID.
     * @param  bool   $is_update      Whether this replaces an existing logo.
     * @return string                 Thumbnail URL of the saved attachment.
     */
    private static function generate_logo_attachment($job_id, $file_path, $file_url, $file_mime_type, $file_name, $user_id, $is_update = false) {
        global $workeera_plugin_settings;
        // Prepare attachment data
        $attachment = array(
            'guid'           => $file_url,
            'post_mime_type' => $file_mime_type['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content'   => '',
            'post_author'    => $user_id,
            'post_status'    => 'inherit'
        );
        
        // Insert attachment into database
        $attachment_id = wp_insert_attachment($attachment, $file_path, $job_id);

        $allow_published_edits = isset($workeera_plugin_settings['job_submission_settings']['allow_published_edits']) ? $workeera_plugin_settings['job_submission_settings']['allow_published_edits'] : '';
        
        if (!is_wp_error($attachment_id) && $allow_published_edits == 'require_approval' && $is_update) {
            if (get_post_status( $job_id ) == 'publish') {
                $post_data = [
                    'ID' => $job_id,
                    'post_status' => 'draft'
                ];
                wp_update_post( $post_data, true );
                $job_field_data = [
                    'listing_status' => 'moderated'
                ];
                Workeera_Query_Handler::save_item_data( 'job_fields', 'job_id', $job_id, $job_field_data );
            }
        }
        // Include necessary file for wp_generate_attachment_metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Generate attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // delete previous thumbnail
        $post_thumb_id = get_post_thumbnail_id($job_id);
        if (!is_wp_error($attachment_id) && $post_thumb_id) {
            wp_delete_attachment($post_thumb_id, true);
        }
        // set post thumbnail/featured image
        set_post_thumbnail($job_id, $attachment_id);

        $rand_ver = wp_rand(10000000, 99999999);
        workeera_update_object_meta( $job_id, 'thumbnail_version', $rand_ver );

        $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        return $thumb_url;
    }

    /**
     * AJAX handler for uploading the company logo from the job form.
     */
    public function upload_company_logo_picture() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        $user_type = workeera_get_object_meta($user_id, 'workeera_user_type', 'user');
        if ($user_type != 'employer') {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to perform this action.', 'workeera-remote-tech-job-board')));
        }

        if (isset($_FILES['pic_file']['size']) && $_FILES['pic_file']['size'] > 0) {

            $job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
            $is_updating = false;

            if ( $job_id > 0 ) {
                $job_post = get_post( $job_id );
                if (isset($job_post->post_type) && $job_post->post_type == 'workeera_job' && workeera_is_user_can_job_post($job_id)) {
                    $is_updating = true;
                }
            }

            $file = array_map( 'sanitize_text_field', $_FILES['pic_file'] );

            $file_size = absint($file['size']);

            $allow_pic_mimes = workeera_picture_allowed_mime_types();
            $allow_pic_size = workeera_picture_allowed_file_size();
            $allow_size_in_mbs = $allow_pic_size * 1024 * 1024;

            if ($file_size > $allow_size_in_mbs) {
                wp_send_json(array('error' => '1', 'msg' => esc_html_x('File size too big.', 'Picture size error', 'workeera-remote-tech-job-board')));
            }

            $file_name = sanitize_file_name($file['name']);

            $file_mime_type = wp_check_filetype($file_name);

            if (!isset($file_mime_type['type']) || !in_array($file_mime_type['type'], $allow_pic_mimes)) {
                wp_send_json(array('error' => '1', 'msg' => esc_html__('Action failed. File type not allowed to upload.', 'workeera-remote-tech-job-board')));
            }

            $file_ext = $file_mime_type['ext'];

            if (!$is_updating) {
                global $workeera_upload_files_extpath;
                $workeera_upload_files_extpath = 'user-images';
                add_filter('upload_dir', 'workeera_public_upload_files_path');

                $userbase_filename = 'user-' . $user_id . '-logo-pic.' . $file_ext;
                $file['name'] = $userbase_filename;
            }

            $wp_upload_dir = wp_upload_dir();
            $uplod_direc_path = isset($wp_upload_dir['path']) ? $wp_upload_dir['path'] : '';
            $uplod_direc_url = isset($wp_upload_dir['url']) ? $wp_upload_dir['url'] : '';

            $uploaded_file = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allow_pic_mimes));
            if (isset($uploaded_file['file'])) {
                $uploaded_file['ext'] = $file_ext;
            } else {
                /* translators: %s: error message */
                wp_send_json(array('error' => '1', 'msg' => sprintf(esc_html_x('Action failed. Error: %s', 'Logo upload error', 'workeera-remote-tech-job-board'), $uploaded_file['error'])));
            }

            $rand_ver = wp_rand(10000000, 99999999);

            if ($is_updating) {
                $file_path = $uploaded_file['file'];

                $img_url = self::generate_logo_attachment($job_id, $file_path, $uploaded_file['url'], $file_mime_type, $file_name, $user_id, true);
            } else {

                $image_editor = wp_get_image_editor($uploaded_file['file']);

                if (!is_wp_error($image_editor)) {

                    $image_editor->resize(150, 150, false);

                    $userbase_filename = 'user-' . $user_id . '-logo-thumb.' . $file_ext;
                    $crop_file = $uplod_direc_path . '/' . $userbase_filename;
                    $crop_file_url = $uplod_direc_url . '/' . $userbase_filename;
                    $image_editor->save($crop_file);

                    $uploaded_file['thumb_path'] = $crop_file;
                    $uploaded_file['thumb_url'] = $crop_file_url;
                }

                $uploaded_file['version'] = $rand_ver;

                // Multi-company: for a NEW job the logo is held in a pending slot
                // and consumed when the company is created on submit (it is not
                // written into the companies list here).
                workeera_update_object_meta($user_id, 'company_pending_logo', $uploaded_file, 'user');

                $img_url = isset($uploaded_file['thumb_url']) ? $uploaded_file['thumb_url'] : $uploaded_file['url'];
            }

            $return_response = [
                'error' => '0',
                'img_url' => add_query_arg(array('ver' => $rand_ver), $img_url),
                'msg' => esc_html__('Logo saved successfully.', 'workeera-remote-tech-job-board'),
            ];

            wp_send_json($return_response);
        }

        wp_send_json(array('error' => '1', 'msg' => esc_html__('Incorrect request.', 'workeera-remote-tech-job-board')));
    }

    /**
     * AJAX handler for removing the uploaded company logo.
     */
    public function remove_company_logo_picture() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        $user_type = workeera_get_object_meta($user_id, 'workeera_user_type', 'user');
        if ($user_type != 'employer') {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to perform this action.', 'workeera-remote-tech-job-board')));
        }

        $job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
        $is_updating = false;

        if ( $job_id > 0 ) {
            $job_post = get_post( $job_id );
            if (isset($job_post->post_type) && $job_post->post_type == 'workeera_job' && workeera_is_user_can_job_post($job_id)) {
                $is_updating = true;
            } else {
                wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to perform this action.', 'workeera-remote-tech-job-board')));
            }
        }

        if ($is_updating) {
            $post_thumb_id = get_post_thumbnail_id($job_id);
            if (!is_wp_error($post_thumb_id) && $post_thumb_id) {
                wp_delete_attachment($post_thumb_id, true);
            }
        } else {
            // New job: clear the pending logo slot (multi-company).
            workeera_update_object_meta($user_id, 'company_pending_logo', '', 'user');
        }

        $return_response = [
            'error' => '0',
            'msg' => esc_html__('Logo removed.', 'workeera-remote-tech-job-board'),
        ];
        wp_send_json($return_response);
    }

    /**
     * AJAX handler for job post / update submission from the user dashboard.
     *
     * Reuses the same save_post hook path that post-meta-box.php uses:
     * after wp_insert_post / wp_update_post, Workeera_Post_Meta_Boxes::post_save()
     * fires automatically via the save_post action, so we only need to populate
     * $_POST with the expected keys and let that hook do the heavy lifting for
     * meta, job_fields, terms, and location.
     */
    public function user_job_submission() {

        global $workeera_plugin_settings, $wpdb;

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        // Must be a logged-in employer.
        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You must be logged in.', 'workeera-remote-tech-job-board' ) ] );
        }

        $user_id   = get_current_user_id();
        $user_type = workeera_get_object_meta( $user_id, 'workeera_user_type', 'user' );

        if ( $user_type !== 'employer' ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ] );
        }
        
        $is_modified = false;

        $allow_published_edits = isset($workeera_plugin_settings['job_submission_settings']['allow_published_edits']) ? $workeera_plugin_settings['job_submission_settings']['allow_published_edits'] : '';
        $max_jobs_submission = isset($workeera_plugin_settings['job_submission_settings']['max_jobs_submission']) ? absint($workeera_plugin_settings['job_submission_settings']['max_jobs_submission']) : 20;

        $job_apply_method = isset($workeera_plugin_settings['job_submission_settings']['job_apply_method']) ? $workeera_plugin_settings['job_submission_settings']['job_apply_method'] : 'internal';

        // Determine new vs update
        $job_id        = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
        $is_update     = false;

        if ( $job_id > 0 ) {
            // if is not allowed to edit submitted jobs
            if ( $allow_published_edits == 'no' ) {
                wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Not allowed to edit/update already submitted jobs.', 'workeera-remote-tech-job-board' ) ] );
            }
            if ( ! workeera_is_user_can_job_post( $job_id ) ) {
                wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to edit this job.', 'workeera-remote-tech-job-board' ) ] );
            }
            $existing_post = get_post( $job_id );
            if ( ! $existing_post || $existing_post->post_type !== 'workeera_job' ) {
                wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Job not found.', 'workeera-remote-tech-job-board' ) ] );
            }
            $is_update = true;

            $company_info = workeera_get_object_meta( $job_id, 'company_info' );

            $job_fields_row = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
            $expiry = isset( $job_fields['expiry_date'] ) ? $job_fields['expiry_date'] : '';

            if ( $existing_post->post_status == 'pending' ) {
                wp_send_json( [ 'error' => '2', 'msg' => esc_html__( 'This job is pending review. You can make changes after approval.', 'workeera-remote-tech-job-board' ) ] );
            } elseif ( $allow_published_edits == 'require_approval' && $existing_post->post_status != 'publish' && isset($job_fields_row['listing_status']) && $job_fields_row['listing_status'] == 'moderated' ) {
                wp_send_json( [ 'error' => '2', 'msg' => esc_html__( 'This job is under review. You can make changes after approval.', 'workeera-remote-tech-job-board' ) ] );
            } elseif ( !empty($expiry) && current_time( 'timestamp' ) > strtotime($expiry) ) {
                wp_send_json( [ 'error' => '2', 'msg' => esc_html__( 'This job is expired already.', 'workeera-remote-tech-job-board' ) ] );
            }
        }

        if ( ! $is_update && $max_jobs_submission > 0 ) {
            $jobs_submitted_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$wpdb->posts}
                    WHERE post_author = %d
                    AND post_type = 'workeera_job'
                    AND post_status NOT IN ('auto-draft')
                    AND post_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                    $user_id
                )
            );

            if ( absint($jobs_submitted_count) >= $max_jobs_submission ) {
                /* translators: %d: maximum job count */
                wp_send_json( [ 'error' => '2', 'msg' => sprintf(esc_html__( 'Limit reached. You can only post %d jobs in 24 hours.', 'workeera-remote-tech-job-board' ), $max_jobs_submission) ] );
            }
        }

        // ── Mandatory fields
        $job_title = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
        if ( $job_title === '' ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Job title is required.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( mb_strlen( $job_title ) > 150 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Job title is too long (max 150 characters).', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( mb_strlen( $job_title ) < 2 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Job title is too short.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( $is_update && $job_title != $existing_post->post_title ) {
            $is_modified = true;
        }

        // Job description — strip tags to count plain-text length.
        $job_description_raw = isset( $_POST['job_description'] ) ? wp_unslash( $_POST['job_description'] ) : '';
        $job_description_plain = wp_strip_all_tags( $job_description_raw );
        if ( trim( $job_description_plain ) === '' ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Job description is required.', 'workeera-remote-tech-job-board' ) ] );
        }
        $description_max_len = workeera_job_description_max_len();
        if ( mb_strlen( $job_description_plain ) > $description_max_len ) {
            /* translators: %d: maximum job description length */
            wp_send_json( [ 'error' => '1', 'msg' => sprintf( esc_html__( 'Job description must not exceed %d characters.', 'workeera-remote-tech-job-board' ), $description_max_len ) ] );
        }
        // Allow only safe formatting tags (matches workeera_optimize_job_content allowlist).
        $allowed_desc_tags = [
            'p'      => [],
            'br'     => [],
            'strong' => [],
            'b'      => [],
            'em'     => [],
            'i'      => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
        ];
        $job_description = wp_kses( $job_description_raw, $allowed_desc_tags );

        if ( $is_update && trim( $job_description_plain ) != trim(wp_strip_all_tags($existing_post->post_content)) ) {
            $is_modified = true;
        }

        // --- Multi-company resolution -----------------------------------------
        // Decide which company this job uses: an existing one picked from the
        // dropdown (on new posts AND when editing), or — on a new job with no
        // dropdown — a brand-new company created from the posted fields. Replace
        // the posted company_info with the canonical snapshot (incl. company_id).
        $job_company_logo    = '';
        $company_mode        = isset( $_POST['workeera_company_mode'] ) ? sanitize_key( wp_unslash( $_POST['workeera_company_mode'] ) ) : '';
        $selected_company_id = isset( $_POST['workeera_meta']['company_info']['company_id'] ) ? sanitize_text_field( wp_unslash( $_POST['workeera_meta']['company_info']['company_id'] ) ) : '';

        if ( 'existing' === $company_mode && '' !== $selected_company_id ) {
            // A saved company was chosen from the dropdown.
            $chosen_company = workeera_get_user_company( $user_id, $selected_company_id );
            if ( ! $chosen_company ) {
                wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Please choose a valid company for this job.', 'workeera-remote-tech-job-board' ) ] );
            }
            $_POST['workeera_meta']['company_info'] = workeera_company_snapshot( $chosen_company );
            $job_company_logo                       = isset( $chosen_company['logo'] ) ? $chosen_company['logo'] : '';
        } elseif ( 'existing' === $company_mode && ! $is_update ) {
            // New job: the company dropdown is shown but nothing was selected
            // (and "Add new company" was not used) — guide the user clearly.
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Please select a company for this job, or click “Add new company” to create one.', 'workeera-remote-tech-job-board' ) ] );
        } elseif ( ! $is_update ) {
            // New job with no saved companies → create one from the posted fields.
            $pending_logo = workeera_get_object_meta( $user_id, 'company_pending_logo', 'user' );
            $pending_logo = is_array( $pending_logo ) ? $pending_logo : null;

            $new_company_input = ( isset( $_POST['workeera_meta']['company_info'] ) && is_array( $_POST['workeera_meta']['company_info'] ) )
                ? $_POST['workeera_meta']['company_info']
                : array();

            $save_result = workeera_save_user_company( $user_id, $new_company_input, '', $pending_logo );
            if ( '' !== $save_result['error'] ) {
                wp_send_json( [ 'error' => '1', 'msg' => $save_result['error'] ] );
            }

            $_POST['workeera_meta']['company_info'] = workeera_company_snapshot( $save_result['company'] );
            $job_company_logo                       = isset( $save_result['company']['logo'] ) ? $save_result['company']['logo'] : '';

            if ( $pending_logo ) {
                workeera_update_object_meta( $user_id, 'company_pending_logo', '', 'user' );
            }
        } elseif ( 'existing' === $company_mode ) {
            // Editing with the dropdown shown but nothing selected → keep the
            // job's existing company snapshot so it is never wiped by an empty
            // company_id-only payload.
            $_POST['workeera_meta']['company_info'] = is_array( $company_info ) ? $company_info : ( isset( $_POST['workeera_meta']['company_info'] ) ? $_POST['workeera_meta']['company_info'] : array() );
        }
        // ----------------------------------------------------------------------

        // Company name (mandatory).
        $company_name = '';
        if ( isset( $_POST['workeera_meta']['company_info']['name'] ) ) {
            $company_name = sanitize_text_field( wp_unslash( $_POST['workeera_meta']['company_info']['name'] ) );
        }
        if ( $company_name === '' ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Company name is required.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( mb_strlen( $company_name ) > 150 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Company name is too long (max 150 characters).', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( $is_update && !empty($company_name) && isset($company_info['name']) && $company_name != $company_info['name'] ) {
            $is_modified = true;
        }

        // Company tagline
        $company_tagline = '';
        if ( isset( $_POST['workeera_meta']['company_info']['tagline'] ) ) {
            $company_tagline = sanitize_text_field( wp_unslash( $_POST['workeera_meta']['company_info']['tagline'] ) );
        }
        if ( mb_strlen( $company_tagline ) > 250 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Company tagline is too long (max 250 characters).', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( $is_update && !empty($company_tagline) && isset($company_info['tagline']) && $company_tagline != $company_info['tagline'] ) {
            $is_modified = true;
        }

        // Apply-method + apply-job-url — normalise & validate before the
        // generic workeera_meta save loop picks them up.
        if ( $job_apply_method === 'internal' ) {
            // Site-wide internal-only — strip anything the client may have sent.
            unset( $_POST['workeera_meta']['apply_method'], $_POST['workeera_meta']['apply_job_url'] );
        } else {
            $submitted_method = isset( $_POST['workeera_meta']['apply_method'] )
                ? sanitize_key( wp_unslash( $_POST['workeera_meta']['apply_method'] ) )
                : '';

            if ( $job_apply_method === 'external' ) {
                $effective_method = 'external';
            } else { // 'both' — trust the user's choice but allowlist it.
                $effective_method = in_array( $submitted_method, [ 'internal', 'external' ], true ) ? $submitted_method : 'internal';
            }
            $_POST['workeera_meta']['apply_method'] = $effective_method;

            if ( $effective_method === 'external' ) {
                $raw_url = isset( $_POST['workeera_meta']['apply_job_url'] ) ? wp_unslash( $_POST['workeera_meta']['apply_job_url'] ) : '';
                $clean   = esc_url_raw( trim( (string) $raw_url ) );
                if ( empty( $clean ) || ! preg_match( '#^https?://#i', $clean ) ) {
                    wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Please enter a valid external apply URL (must start with http:// or https://).', 'workeera-remote-tech-job-board' ) ] );
                }
                $_POST['workeera_meta']['apply_job_url'] = $clean;
            } else {
                // Internal flow chosen on a "both" site — clear any stale URL
                // so it doesn't keep applying after the change.
                $_POST['workeera_meta']['apply_job_url'] = '';
            }
        }

        // Deadline date — validate format dd/mm/yyyy.
        if ( isset( $_POST['workeera_field_deadline_date'] ) ) {
            $deadline = workeera_validate_date_input( sanitize_text_field( wp_unslash( $_POST['workeera_field_deadline_date'] ) ) );
            if ( empty( $deadline ) ) {
                unset( $_POST['workeera_field_deadline_date'] );
            } else {
                $deadline_date = DateTime::createFromFormat('d/m/Y', $deadline)->setTime(0, 0);
                $new_date = new DateTime();
                $current_date = $new_date->setTime(0, 0);
                if ($deadline_date <= $current_date) {
                    wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Deadline date should be a future date.', 'workeera-remote-tech-job-board' ) ] );
                }
                $_POST['workeera_field_deadline_date'] = $deadline;
            }
        }

        // Insert or update the post
        $post_data = [
            'post_title'   => $job_title,
            'post_content' => $job_description,
            'post_type'    => 'workeera_job',
            'post_status'  => 'pending', // require admin review before publishing
        ];

        if ( $is_update ) {
            $post_data['ID'] = $job_id;

            if ( $allow_published_edits == 'require_approval' && $is_modified ) {
                $post_data['post_status'] = 'draft';
            } else {
                // Keep existing post_status when updating.
                $post_data['post_status'] = get_post_status( $job_id );
            }

            $result = wp_update_post( $post_data, true );
        } else {
            $post_data['post_author'] = $user_id;
            $result = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => $result->get_error_message() ] );
        }

        $job_id = absint( $result );

        // ── Populate $_POST so the existing save_post hook handles everything ─
        // Workeera_Post_Meta_Boxes::post_save() is hooked to save_post and fires
        // on both wp_insert_post and wp_update_post. It reads directly from
        // $_POST, so we normalise the data here.

        // Salary fields.
        if ( isset( $_POST['workeera_field_min_salary'] ) ) {
            $_POST['workeera_field_min_salary'] = absint( wp_unslash( $_POST['workeera_field_min_salary'] ) );
        }
        if ( isset( $_POST['workeera_field_max_salary'] ) ) {
            $_POST['workeera_field_max_salary'] = absint( wp_unslash( $_POST['workeera_field_max_salary'] ) );
        }

        // Salary type.
        if ( isset( $_POST['workeera_field_salary_type'] ) ) {
            $salary_units = workeera_salary_units();
            $salary_type  = sanitize_key( wp_unslash( $_POST['workeera_field_salary_type'] ) );
            if ( ! isset( $salary_units[ $salary_type ] ) ) {
                unset( $_POST['workeera_field_salary_type'] );
            }
        }

        // Meta array: sanitize each value.
        if ( isset( $_POST['workeera_meta'] ) && is_array( $_POST['workeera_meta'] ) ) {
            $_POST['workeera_meta'] = map_deep( wp_unslash( $_POST['workeera_meta'] ), 'sanitize_text_field' );
        }

        // Terms array: sanitize IDs. Unslash once at the top, then per-value sanitize as int.
        if ( isset( $_POST['workeera_terms'] ) && is_array( $_POST['workeera_terms'] ) ) {
            $sanitized_terms   = [];
            $raw_workeera_terms = wp_unslash( $_POST['workeera_terms'] );
            foreach ( $raw_workeera_terms as $tax_slug => $term_val ) {
                $tax_slug = sanitize_key( $tax_slug );
                if ( is_array( $term_val ) ) {
                    $sanitized_terms[ $tax_slug ] = array_map( 'absint', $term_val );
                } else {
                    $sanitized_terms[ $tax_slug ] = absint( $term_val );
                }
            }
            $_POST['workeera_terms'] = $sanitized_terms;
        }

        // Location fields: unslash once at the top, then sanitize each sub-field
        // with the type-appropriate WP core sanitizer (absint for IDs, sanitize_text_field for text).
        if ( isset( $_POST['workeera_location_fields'] ) && is_array( $_POST['workeera_location_fields'] ) ) {
            $loc = wp_unslash( $_POST['workeera_location_fields'] );
            $_POST['workeera_location_fields'] = [
                'country'        => isset( $loc['country'] ) ? absint( $loc['country'] ) : 0,
                'city'           => isset( $loc['city'] ) ? absint( $loc['city'] ) : 0,
                'street_address' => isset( $loc['street_address'] ) ? sanitize_text_field( $loc['street_address'] ) : '',
                'zip_code'       => isset( $loc['zip_code'] ) ? sanitize_text_field( $loc['zip_code'] ) : '',
            ];
        }

        // Trigger the save_post hook manually so Workeera_Post_Meta_Boxes::post_save()
        // runs with our sanitised $_POST data (it was already triggered by
        // wp_insert_post/wp_update_post above, but $_POST was not yet normalised
        // at that point — fire it again now that everything is in order).
        // The front-end form cannot carry the per-post metabox nonce (for a new
        // job the post ID does not exist until wp_insert_post runs), so mint it
        // here — this request already passed the workeera_nonce check and the
        // ownership gates above.
        $_POST['workeera_post_save_nonce'] = wp_create_nonce( 'workeera_post_save_' . $job_id );
        $post_obj = get_post( $job_id );
        do_action( 'save_post', $job_id, $post_obj, true );

        // Attach the resolved company's logo as the job's featured image. Runs
        // for new posts and for edits where a (different) company was picked;
        // skipped when no company logo was resolved (keeps the existing image).
        if ( is_array( $job_company_logo ) && ! empty( $job_company_logo['file'] ) && file_exists( $job_company_logo['file'] ) ) {
            global $wp_filesystem;
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();

            $file_path        = $job_company_logo['file'];
            $wp_upload_dir    = wp_upload_dir();
            $uplod_direc_path = isset( $wp_upload_dir['path'] ) ? $wp_upload_dir['path'] : '';
            $uplod_direc_url  = isset( $wp_upload_dir['url'] ) ? $wp_upload_dir['url'] : '';

            $wp_filesystem->copy( $file_path, $uplod_direc_path . '/' . basename( $file_path ), true );

            $copied_file_path = $uplod_direc_path . '/' . basename( $file_path );
            $copied_file_url  = $uplod_direc_url . '/' . basename( $file_path );
            $file_mime_type   = wp_check_filetype( basename( $file_path ) );
            $file_name        = sanitize_title( $company_name );

            self::generate_logo_attachment( $job_id, $copied_file_path, $copied_file_url, $file_mime_type, $file_name, $user_id );
        }

        $job_fields_row = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );
        $job_field_id = isset($job_fields_row['job_field_id']) ? $job_fields_row['job_field_id'] : '';

        // Persist the resolved company link on the job_fields table so jobs can
        // be counted/listed per company without unserializing meta. The id was
        // normalised into company_info above; only write when one is present so
        // an unlinked edit never clobbers an existing link.
        $resolved_company_id = isset( $_POST['workeera_meta']['company_info']['company_id'] )
            ? absint( wp_unslash( $_POST['workeera_meta']['company_info']['company_id'] ) )
            : 0;
        if ( $resolved_company_id > 0 ) {
            global $wpdb;
            $workeera_job_fields_table = $wpdb->prefix . 'workeera_job_fields';
            if ( ! empty( $job_field_id ) ) {
                $wpdb->update( $workeera_job_fields_table, [ 'company_id' => $resolved_company_id ], [ 'job_field_id' => $job_field_id ], [ '%d' ], [ '%d' ] );
            } else {
                $wpdb->insert( $workeera_job_fields_table, [ 'job_id' => $job_id, 'company_id' => $resolved_company_id ], [ '%d', '%d' ] );
                $job_field_id = $wpdb->insert_id;
            }
        }

        if ( $is_update ) {

            $msg = esc_html__( 'Job updated successfully.', 'workeera-remote-tech-job-board' );
            $error = '0';

            if ( $allow_published_edits == 'require_approval' && $is_modified ) {
                $job_field_data = [
                    'listing_status' => 'moderated'
                ];
                // Key on job_id when no job_fields row exists yet, so an insert
                // always carries the real job ID (never the 0 default).
                if ( empty( $job_field_id ) ) {
                    Workeera_Query_Handler::save_item_data( 'job_fields', 'job_id', $job_id, $job_field_data );
                } else {
                    Workeera_Query_Handler::save_item_data( 'job_fields', 'job_field_id', $job_field_id, $job_field_data );
                }

                $msg = esc_html__( 'Changes saved successfully. They will be published after approval.', 'workeera-remote-tech-job-board' );
                $error = '2';
            }

            wp_send_json( [
                'error' => $error,
                'msg' => $msg,
                'view' => self::job_submission_success_msg($job_id, true),
                'callback' => 'workeera_after_job_submission_hook'
            ] );
        } else {
            // update job expiry date
            $listing_duration = isset($workeera_plugin_settings['job_submission_settings']['listing_duration']) ? absint($workeera_plugin_settings['job_submission_settings']['listing_duration']) : 0;
            $listing_duration = $listing_duration > 0 ? $listing_duration : 30;
            $expiry_date = strtotime("+" . $listing_duration . " days", current_time('timestamp'));

            $expiry_date = gmdate('Y-m-d', $expiry_date) . ' 23:59:59'; // till the end of the date
            $job_field_data = [
                'expiry_date' => $expiry_date,
                'listing_status' => 'pending' // for new job submission
            ];
            // Key on job_id when no job_fields row exists yet, so an insert
            // always carries the real job ID (never the 0 default).
            if ( empty( $job_field_id ) ) {
                Workeera_Query_Handler::save_item_data( 'job_fields', 'job_id', $job_id, $job_field_data );
            } else {
                Workeera_Query_Handler::save_item_data( 'job_fields', 'job_field_id', $job_field_id, $job_field_data );
            }

            wp_send_json( [
                'error' => '0',
                'msg' => esc_html__( 'Job submitted successfully and is pending review.', 'workeera-remote-tech-job-board' ),
                'view' => self::job_submission_success_msg($job_id),
                'callback' => 'workeera_after_job_submission_hook'
            ] );
        }
    }

    /**
     * Render the success message HTML displayed after a job submission.
     *
     * @param  int|null $job_id    The job post ID just saved.
     * @param  bool     $is_update Whether the submission was an update.
     * @return string              Rendered HTML for the success notice.
     */
    public static function job_submission_success_msg($job_id = null, $is_update = false) {
        global $workeera_plugin_settings;

        ob_start();

        $job_status = get_post_status($job_id);

        $job_fields_row = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );

        $dashboard_page     = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_page_url = get_permalink( $dashboard_page );
        $job_submission_url = add_query_arg( [ 'section-type' => 'job' ], $dashboard_page_url );
        $manage_jobs_url    = add_query_arg( [ 'section-type' => 'manage-jobs' ], $dashboard_page_url );
        ?>
        <div class="workeera-usrdshb-small-container">
            <div class="workeera-usrjobs-success-wrap">
                <div class="workeera-usrjobs-success-card">

                    <div class="workeera-usrjobs-success-icon-ring">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <?php $heading = $is_update ? esc_html_x('Job Updated Successfully!', 'Job submission message', 'workeera-remote-tech-job-board') : esc_html_x('Job Submitted Successfully!', 'Job submission message', 'workeera-remote-tech-job-board'); ?>
                    <strong class="workeera-usrjobs-success-title"><?php echo esc_html($heading); ?></strong>

                    <p class="workeera-usrjobs-success-sub">
                        <?php
                        /* translators: %s: job title */
                        $msg = wp_kses_post(_x('Your job listing for <strong>"%s"</strong> is now live and visible to everyone.', 'Job submission message', 'workeera-remote-tech-job-board'));
                        if ( $is_update ) {
                            if ( $job_status === 'publish' ) {
                                /* translators: %s: job title */
                                $msg = wp_kses_post(_x('Your changes saved for job <strong>"%s"</strong> and now visible to everyone.', 'Job submission message', 'workeera-remote-tech-job-board'));
                            } else {
                                /* translators: %s: job title */
                                $msg = wp_kses_post(_x('Your changes saved for job <strong>"%s"</strong> and visible to everyone after admin approval.', 'Job submission message', 'workeera-remote-tech-job-board'));
                            }
                        } else {
                            if ( $job_status !== 'publish' ) {
                                /* translators: %s: job title */
                                $msg = wp_kses_post(_x('Your submitted job <strong>"%s"</strong> is pending review and will be published after admin approval.', 'Job submission message', 'workeera-remote-tech-job-board'));
                            }
                        }
                        echo sprintf(wp_kses($msg, ['strong' => []]), esc_html(get_the_title($job_id))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $msg pre-escaped via wp_kses_post, title via esc_html
                        ?>
                    </p>

                    <div class="workeera-usrjobs-success-chips">
                        <?php
                        $status_label = $is_update ? esc_html_x('Under Review', 'Job submission message', 'workeera-remote-tech-job-board') : esc_html_x('Pending Review', 'Job submission message', 'workeera-remote-tech-job-board');
                        $status_label_final = $job_status === 'publish' ? esc_html_x('Listing is live', 'Job submission message', 'workeera-remote-tech-job-board') : $status_label;
                        ?>
                        <span class="workeera-usrjobs-success-chip">
                            <?php
                            if ( $job_status === 'publish' ) {
                                ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                    <polyline points="22 4 12 14.01 9 11.01" />
                                </svg>
                                <?php
                            } else {
                                ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#F59E0B"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <?php
                            }
                            echo esc_html($status_label_final);
                            ?>
                        </span>
                        <?php
                        $date_format = get_option('date_format');
                        $application_deadline = isset($job_fields_row['application_deadline']) ? sanitize_text_field( wp_unslash( $job_fields_row['application_deadline'] ) ) : '';
                        if ( !empty($application_deadline) ) {
                            ?>
                            <span class="workeera-usrjobs-success-chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                <?php /* translators: %s: formatted date */ ?>
                                <?php printf(esc_html_x('Deadline: %s', 'Job submission message', 'workeera-remote-tech-job-board'), esc_html(date_i18n($date_format, strtotime($application_deadline)))) ?>
                            </span>
                            <?php
                        }
                        $expiry_date = isset($job_fields_row['expiry_date']) ? sanitize_text_field( wp_unslash( $job_fields_row['expiry_date'] ) ) : '';
                        if ( !empty($expiry_date) ) {
                            ?>
                            <span class="workeera-usrjobs-success-chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                <?php /* translators: %s: formatted date */ ?>
                                <?php printf(esc_html_x('Expires: %s', 'Job submission message', 'workeera-remote-tech-job-board'), esc_html(date_i18n($date_format, strtotime($expiry_date)))) ?>
                            </span>
                            <?php
                        }
                        ?>
                    </div>

                    <div class="workeera-usrjobs-success-divider"></div>

                    <div class="workeera-usrjobs-success-actions">
                        <?php
                        $post_another_btn_class = 'workeera-usrjobs-success-btn-ghost';
                        if ( $job_status === 'publish' ) {
                            ?>
                            <a href="<?php echo esc_url(get_permalink($job_id)) ?>" target="_blank" class="workeera-usrjobs-success-btn workeera-usrjobs-success-btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <?php echo esc_html_x('View Job Listing', 'Job submission message', 'workeera-remote-tech-job-board') ?>
                            </a>
                            <?php
                        } else {
                            $post_another_btn_class = 'workeera-usrjobs-success-btn-primary';
                        }
                        ?>
                        <a href="<?php echo esc_url($job_submission_url) ?>" class="workeera-usrjobs-success-btn <?php echo esc_attr($post_another_btn_class) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <?php echo esc_html_x('Post Another Job', 'Job submission message', 'workeera-remote-tech-job-board') ?>
                        </a>
                        <a href="<?php echo esc_url($manage_jobs_url) ?>" class="workeera-usrjobs-success-btn workeera-usrjobs-success-btn-ghost">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                                <line x1="8" y1="21" x2="16" y2="21" />
                                <line x1="12" y1="17" x2="12" y2="21" />
                            </svg>
                            <?php echo esc_html_x('Manage Jobs', 'Job submission message', 'workeera-remote-tech-job-board') ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Workeera_User_Job_Posting;
