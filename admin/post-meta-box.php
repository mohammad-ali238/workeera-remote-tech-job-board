<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Registers and renders the admin meta boxes for Workeera job and candidate posts.
 */
class Workeera_Post_Meta_Boxes {

    /**
     * Hook meta-box registration and post-save callbacks.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'post_types_meta' ], 15 );
        add_action( 'save_post', [ $this, 'post_save' ], 5, 3 );
    }

    /**
     * Detect whether the Job Status side metabox was submitted on this request.
     *
     * @return bool True when the hidden marker is present in $_POST.
     */
    private static function job_status_metabox_was_submitted() {
        return isset( $_POST['workeera_job_status_metabox_marker'] );
    }

    /**
     * Register all job/candidate meta boxes with WordPress.
     */
    public function post_types_meta() {
        add_meta_box(
            'workeera_job_metabox',
            esc_html__( 'Job Settings', 'workeera-remote-tech-job-board' ),
            [ $this, 'job_metabox_callback' ],
            'workeera_job',
            'normal',
            'default'
        );
        add_meta_box(
            'workeera_job_company_metabox',
            esc_html__( 'Company Info', 'workeera-remote-tech-job-board' ),
            [ $this, 'job_company_metabox_callback' ],
            'workeera_job',
            'normal',
            'default'
        );
        add_meta_box(
            'workeera_job_terms_metabox',
            esc_html__( 'Further Details', 'workeera-remote-tech-job-board' ),
            [ $this, 'job_terms_metabox_callback' ],
            'workeera_job',
            'normal',
            'default'
        );
        /**
         * Register extra job meta boxes right before the Location box.
         *
         * Addons hooking here get their boxes rendered between "Further
         * Details" and "Location" in the default metabox order.
         */
        do_action( 'workeera_job_metaboxes_before_location' );

        add_meta_box(
            'workeera_job_location_metabox',
            esc_html__( 'Location', 'workeera-remote-tech-job-board' ),
            [ $this, 'job_location_metabox_callback' ],
            'workeera_job',
            'normal',
            'default'
        );
        add_meta_box(
            'workeera_candidate_metabox',
            esc_html__( 'Candidate Fields', 'workeera-remote-tech-job-board' ),
            [ $this, 'candidate_metabox_callback' ],
            'workeera_candidate',
            'normal',
            'default'
        );

        /**
         * Register extra candidate meta boxes right before the Location box.
         *
         * Addons hooking here get their boxes rendered between "Candidate
         * Fields" and "Location" in the default metabox order.
         */
        do_action( 'workeera_candidate_metaboxes_before_location' );

        add_meta_box(
            'workeera_candidate_location_metabox',
            esc_html__( 'Location', 'workeera-remote-tech-job-board' ),
            [ $this, 'candidate_location_metabox_callback' ],
            'workeera_candidate',
            'normal',
            'default'
        );

        // Side metaboxes.
        add_meta_box(
            'workeera_candidate_cv_files_metabox',
            esc_html__( 'CV Files', 'workeera-remote-tech-job-board' ),
            [ $this, 'candidate_cv_files_metabox_callback' ],
            'workeera_candidate',
            'side',
            'default'
        );
    }

    /**
     * Render a labelled toggle-switch (checkbox) field.
     *
     * @param array $args Field args: name, id, value and label.
     */
    private static function render_switch_field( $args ) {
        $name  = isset( $args['name'] ) ? $args['name'] : '';
        $id    = isset( $args['id'] ) ? $args['id'] : '';
        $value = ! empty( $args['value'] ) ? 1 : 0;
        $label = isset( $args['label'] ) ? $args['label'] : '';
        ?>
        <div class="workeera-formseting-field workeera-job-status-row">
            <div class="workeera-job-status-toggle-row">
                <label class="workeera-job-status-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
                <label class="workeera-toggle-switch" for="<?php echo esc_attr( $id ); ?>">
                    <input type="checkbox"
                           id="<?php echo esc_attr( $id ); ?>"
                           name="<?php echo esc_attr( $name ); ?>"
                           value="1"
                           <?php checked( $value, 1 ); ?>
                           class="workeera-job-status-switch-input" />
                    <span class="workeera-toggle-slider"></span>
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * Render a toggle-switch paired with a date field (e.g. Featured + featured_till).
     *
     * @param array $args Field args: switch_name, switch_id, switch_value, date_name, date_value, label, date_label.
     */
    private static function render_switch_with_date_field( $args ) {
        $switch_name  = isset( $args['switch_name'] ) ? $args['switch_name'] : '';
        $switch_id    = isset( $args['switch_id'] ) ? $args['switch_id'] : '';
        $switch_value = ! empty( $args['switch_value'] ) ? 1 : 0;
        $date_name    = isset( $args['date_name'] ) ? $args['date_name'] : '';
        $date_value   = isset( $args['date_value'] ) ? $args['date_value'] : '';
        $label        = isset( $args['label'] ) ? $args['label'] : '';
        $date_label   = isset( $args['date_label'] ) ? $args['date_label'] : '';
        ?>
        <div class="workeera-formseting-field workeera-job-status-row<?php echo esc_attr( $switch_value ? ' workeera-job-status-row--on' : '' ); ?>">
            <div class="workeera-job-status-toggle-row">
                <label class="workeera-job-status-label" for="<?php echo esc_attr( $switch_id ); ?>"><?php echo esc_html( $label ); ?></label>
                <label class="workeera-toggle-switch" for="<?php echo esc_attr( $switch_id ); ?>">
                    <input type="checkbox"
                           id="<?php echo esc_attr( $switch_id ); ?>"
                           name="<?php echo esc_attr( $switch_name ); ?>"
                           value="1"
                           <?php checked( $switch_value, 1 ); ?>
                           class="workeera-job-status-switch-input" />
                    <span class="workeera-toggle-slider"></span>
                </label>
            </div>
            <div class="workeera-job-status-date-row">
                <label class="workeera-job-status-date-label" for="<?php echo esc_attr( $switch_id . '-till' ); ?>"><?php echo esc_html( $date_label ); ?></label>
                <?php
                Workeera_Form_Fields::input( [
                    'id'               => $switch_id . '-till',
                    'name'             => $date_name,
                    'classes'          => [ 'workeera-date-input', 'workeera-job-status-date-input' ],
                    'extra_attributes' => [
                        'readonly'      => '',
                        'data-miny'     => gmdate( 'Y' ),
                        'data-maxy'     => gmdate( 'Y' ) + 5,
                        'data-min-date' => gmdate( 'd-m-Y' ),
                        'placeholder'   => esc_attr_x( 'dd/mm/yyyy', 'Job status date placeholder', 'workeera-remote-tech-job-board' ),
                    ],
                    'value'            => $date_value,
                    'default'          => '',
                ] );
                ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Candidate side metabox — list of CV files with download buttons.
    // -------------------------------------------------------------------------

    /**
     * Render the candidate side metabox listing CV files with download buttons.
     */
    public function candidate_cv_files_metabox_callback() {
        global $post;

        $candidate_id = absint( $post->ID );
        $cv_files     = workeera_get_object_meta( $candidate_id, 'candidate_cv_files' );

        // Drop any rows whose file is missing on disk to avoid broken download links.
        $valid_files = [];
        if ( is_array( $cv_files ) ) {
            foreach ( $cv_files as $cv ) {
                if ( ! empty( $cv['file'] ) && file_exists( $cv['file'] ) ) {
                    $valid_files[] = $cv;
                }
            }
        }
        ?>
        <div class="workeera-candidate-cvfiles-side">
            <?php if ( empty( $valid_files ) ) : ?>
                <p class="workeera-candidate-cvfiles-empty"><?php echo esc_html_x( 'No CV files found.', 'admin candidate cv files', 'workeera-remote-tech-job-board' ); ?></p>
            <?php else : ?>
                <ul class="workeera-candidate-cvfiles-list">
                    <?php foreach ( $valid_files as $cv ) : ?>
                        <?php
                        $file_id   = isset( $cv['file_id'] ) ? $cv['file_id'] : '';
                        $file_name = isset( $cv['name'] )    ? $cv['name']    : '';
                        $file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

                        $download_url = empty( $file_id ) ? '' : add_query_arg(
                            [
                                'workeera_files_action' => 'download',
                                'obj_id'              => absint( $candidate_id ),
                                'file_id'             => $file_id,
                                'nonce'               => wp_create_nonce( 'workeera_nonce' ),
                            ],
                            home_url( '/' )
                        );
                        ?>
                        <li class="workeera-candidate-cvfiles-item">
                            <span class="workeera-candidate-cvfiles-icon">
                                <?php echo wp_kses( (string) Workeera_Job_Applications::cv_file_type_svg( $file_ext ), workeera_svg_allowed_tags() ); // safe SVG markup ?>
                            </span>
                            <span class="workeera-candidate-cvfiles-name" title="<?php echo esc_attr( $file_name ); ?>">
                                <?php echo esc_html( $file_name ); ?>
                            </span>
                            <?php if ( $download_url ) : ?>
                                <a href="<?php echo esc_url( $download_url ); ?>"
                                   class="button button-small workeera-candidate-cvfiles-download"
                                   title="<?php echo esc_attr_x( 'Download CV', 'admin candidate cv files', 'workeera-remote-tech-job-board' ); ?>">
                                    <span class="dashicons dashicons-download"></span>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Job metaboxes
    // -------------------------------------------------------------------------

    /**
     * Render the main Job Settings meta box (deadlines, salary, vacancies, status).
     */
    public function job_metabox_callback() {
        global $post, $workeera_plugin_settings;

        $job_id = $post->ID;

        // Defence-in-depth nonce — verified in post_save() before any DB
        // write happens. WP core's own edit-screen nonce is still the primary
        // gate; this one protects against custom POST flows.
        wp_nonce_field( 'workeera_post_save_' . $job_id, 'workeera_post_save_nonce' );

        $salary_unit_switch = isset($workeera_plugin_settings['general_settings']['salary_unit_field']) ? $workeera_plugin_settings['general_settings']['salary_unit_field'] : '';
        $salary_unit = isset($workeera_plugin_settings['general_settings']['salary_unit']) ? $workeera_plugin_settings['general_settings']['salary_unit'] : 'hourly';
        $currency_symbol = workeera_currency_symbol();
        $salary_units = workeera_salary_units();

        $num_of_vacancies = workeera_get_object_meta( $job_id, 'num_of_vacancies' );
        $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );

        if (isset($job_fields['salary_type']) && !empty($job_fields['salary_type']) && isset($salary_units[$job_fields['salary_type']])) {
            $salary_unit = $job_fields['salary_type'];
        }

        $author_options = [];
        if (isset($post->post_author) && $post->post_author) {
            $author_data = get_userdata($post->post_author);
            $author_options[$post->post_author] = $author_data->display_name;
        }
        ?>
        <div class="workeera-formfields-con">
            <div class="workeera-grid-three-cols">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Job Posted by', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::select( [
                            'name'             => 'workeera_job_posted_by',
                            'classes'          => [ 'workeera-users-dropdown' ],
                            'extra_attributes' => [ 'data-placeholder' => esc_html__( 'Select user', 'workeera-remote-tech-job-board' ), 'data-user-type' => 'employer' ],
                            'options'          => $author_options,
                            'value'            => isset($post->post_author) ? $post->post_author : '',
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Application Deadline', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_field_deadline_date',
                            'classes'          => ['workeera-date-input'],
                            'extra_attributes' => [ 'readonly' => '', 'data-miny' => gmdate('Y'), 'data-maxy' => gmdate('Y') + 5, 'data-min-date' => gmdate('d-m-Y') ],
                            'value'            => !empty($job_fields['application_deadline']) ? gmdate('d/m/Y', strtotime($job_fields['application_deadline'])) : '',
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Expiry Date', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_field_expiry_date',
                            'classes'          => ['workeera-date-input'],
                            'extra_attributes' => [ 'readonly' => '', 'data-miny' => gmdate('Y'), 'data-maxy' => gmdate('Y') + 5, 'data-min-date' => gmdate('d-m-Y') ],
                            'value'            => !empty($job_fields['expiry_date']) ? gmdate('d/m/Y', strtotime($job_fields['expiry_date'])) : '',
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="<?php echo esc_attr($salary_unit_switch == 'on' ? 'workeera-grid-three-cols' : 'workeera-grid-two-cols') ?>">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Min Salary', 'workeera-remote-tech-job-board' ) ?> (<?php echo esc_html($currency_symbol) ?>)</strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_field_min_salary',
                            'input_type'       => 'number',
                            'extra_attributes' => [ 'min' => '1', 'step' => '1' ],
                            'value'            => isset($job_fields['min_salary']) && !empty($job_fields['min_salary']) ? workeera_absint($job_fields['min_salary']) : '',
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Max Salary', 'workeera-remote-tech-job-board' ) ?> (<?php echo esc_html($currency_symbol) ?>)</strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_field_max_salary',
                            'input_type'       => 'number',
                            'extra_attributes' => [ 'min' => '1', 'step' => '1' ],
                            'value'            => isset($job_fields['max_salary']) && !empty($job_fields['max_salary']) ? workeera_absint($job_fields['max_salary']) : '',
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <?php
                if ($salary_unit_switch == 'on') {
                    ?>
                    <div class="workeera-formseting-field">
                        <div class="formseting-field-label">
                            <strong><?php esc_html_e( 'Salary Unit', 'workeera-remote-tech-job-board' ) ?></strong>
                        </div>
                        <div class="formseting-field-entry">
                            <?php
                            Workeera_Form_Fields::select( [
                                'name'             => 'workeera_field_salary_type',
                                'classes'          => ['workeera-select2-dropdown'],
                                'extra_attributes' => [ 'placeholder' => esc_html__( 'Select', 'workeera-remote-tech-job-board' ) ],
                                'options'          => workeera_salary_units(),
                                'value'            => $salary_unit,
                                'default'          => '',
                            ] );
                            ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            <div class="workeera-formseting-field">
                <div class="formseting-field-label">
                    <strong><?php esc_html_e( 'Number of Vacancies', 'workeera-remote-tech-job-board' ) ?></strong>
                </div>
                <div class="formseting-field-entry">
                    <?php
                    Workeera_Form_Fields::input( [
                        'name'             => 'workeera_meta[num_of_vacancies]',
                        'input_type'       => 'number',
                        'value'            => $num_of_vacancies,
                        'extra_attributes' => [ 'placeholder' => '1' ],
                        'default'          => '',
                    ] );
                    ?>
                </div>
            </div>

            <?php
            // Apply-method field — driven by the site-wide
            // 'job_apply_method' setting in Job Submission options.
            $job_apply_method_setting = isset( $workeera_plugin_settings['job_submission_settings']['job_apply_method'] )
                ? $workeera_plugin_settings['job_submission_settings']['job_apply_method']
                : 'internal';

            $saved_apply_method = workeera_get_object_meta( $job_id, 'apply_method' );
            $saved_apply_url    = workeera_get_object_meta( $job_id, 'apply_job_url' );

            if ( $job_apply_method_setting !== 'internal' ) :
                $current_method = ! empty( $saved_apply_method )
                    ? $saved_apply_method
                    : ( $job_apply_method_setting === 'external' ? 'external' : 'internal' );
                $show_url_field = ( $current_method === 'external' );
                ?>
                <div class="workeera-grid-two-cols workeera-apply-method-row">
                    <?php if ( $job_apply_method_setting === 'both' ) : ?>
                        <div class="workeera-formseting-field">
                            <div class="formseting-field-label">
                                <strong><?php echo esc_html_x( 'Apply method', 'job metabox', 'workeera-remote-tech-job-board' ); ?></strong>
                            </div>
                            <div class="formseting-field-entry">
                                <?php
                                Workeera_Form_Fields::select( [
                                    'name'    => 'workeera_meta[apply_method]',
                                    'classes' => [ 'workeera-select2-dropdown', 'workeera-apply-method-select' ],
                                    'value'   => $current_method,
                                    'options' => [
                                        'internal' => esc_html_x( 'Apply on this site', 'apply method option', 'workeera-remote-tech-job-board' ),
                                        'external' => esc_html_x( 'Apply on external URL', 'apply method option', 'workeera-remote-tech-job-board' ),
                                    ],
                                    'default' => 'internal',
                                ] );
                                ?>
                            </div>
                        </div>
                    <?php else : ?>
                        <input type="hidden" name="workeera_meta[apply_method]" value="external" />
                    <?php endif; ?>

                    <div class="workeera-formseting-field workeera-apply-method-url-field<?php echo esc_attr( $show_url_field ? '' : ' workeera-hide' ); ?>">
                        <div class="formseting-field-label">
                            <strong><?php echo esc_html_x( 'Job apply URL', 'job metabox', 'workeera-remote-tech-job-board' ); ?></strong>
                        </div>
                        <div class="formseting-field-entry">
                            <?php
                            Workeera_Form_Fields::input( [
                                'name'             => 'workeera_meta[apply_job_url]',
                                'input_type'       => 'url',
                                'value'            => $saved_apply_url,
                                'extra_attributes' => [
                                    'placeholder' => 'https://example.com/jobs/apply',
                                ],
                                'default'          => '',
                            ] );
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $is_featured    = ! empty( $job_fields['is_featured'] ) ? 1 : 0;
        $is_urgent      = ! empty( $job_fields['is_urgent'] )   ? 1 : 0;
        $is_filled      = ! empty( $job_fields['is_filled'] )   ? 1 : 0;
        $featured_till  = ! empty( $job_fields['featured_till'] ) ? gmdate( 'd/m/Y', strtotime( $job_fields['featured_till'] ) ) : '';
        $urgent_till    = ! empty( $job_fields['urgent_till'] )   ? gmdate( 'd/m/Y', strtotime( $job_fields['urgent_till'] ) )   : '';
        ?>
        <div class="workeera-formfields-con workeera-job-status-side">
            <input type="hidden" name="workeera_job_status_metabox_marker" value="1" />

            <?php
            self::render_switch_with_date_field( [
                'switch_name' => 'workeera_field_is_featured',
                'switch_id'   => 'workeera-field-is-featured',
                'switch_value'=> $is_featured,
                'date_name'   => 'workeera_field_featured_till',
                'date_value'  => $featured_till,
                'label'       => esc_html__( 'Featured Job?', 'workeera-remote-tech-job-board' ),
                'date_label'  => esc_html__( 'Featured till', 'workeera-remote-tech-job-board' ),
            ] );

            self::render_switch_with_date_field( [
                'switch_name' => 'workeera_field_is_urgent',
                'switch_id'   => 'workeera-field-is-urgent',
                'switch_value'=> $is_urgent,
                'date_name'   => 'workeera_field_urgent_till',
                'date_value'  => $urgent_till,
                'label'       => esc_html__( 'Urgent hiring?', 'workeera-remote-tech-job-board' ),
                'date_label'  => esc_html__( 'Urgent till', 'workeera-remote-tech-job-board' ),
            ] );

            self::render_switch_field( [
                'name'  => 'workeera_field_is_filled',
                'id'    => 'workeera-field-is-filled',
                'value' => $is_filled,
                'label' => esc_html__( 'Is Filled', 'workeera-remote-tech-job-board' ),
            ] );
            ?>
        </div>
        <?php
    }

    /**
     * Render the Job Company Info meta box.
     */
    public function job_company_metabox_callback() {
        global $post;

        $job_id = $post->ID;

        $company_info = workeera_get_object_meta( $job_id, 'company_info' );
        ?>
        <div class="workeera-formfields-con">
            <div class="workeera-grid-two-cols">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Company name', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[company_info][name]',
                            'value'            => isset($company_info['name']) ? workeera_strip_slashes($company_info['name']) : '',
                            'extra_attributes' => [ 'placeholder' => esc_attr__( 'e.g. Digi Soft', 'workeera-remote-tech-job-board' ), 'required' => '' ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Website', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[company_info][website]',
                            'value'            => isset($company_info['website']) ? $company_info['website'] : '',
                            'extra_attributes' => [ 'placeholder' => 'https://exampleurl.com' ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="workeera-grid-two-one">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Tagline', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[company_info][tagline]',
                            'value'            => isset($company_info['tagline']) ? workeera_strip_slashes($company_info['tagline']) : '',
                            'extra_attributes' => [ 'placeholder' => esc_attr__( 'Brief description about the company', 'workeera-remote-tech-job-board' ) ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Number of employees', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::select( [
                            'name'             => 'workeera_meta[company_info][employee_count]',
                            'value'            => isset($company_info['employee_count']) ? $company_info['employee_count'] : '',
                            'classes'          => ['workeera-select2-dropdown'],
                            'extra_attributes' => [ 'data-placeholder' => esc_attr__( 'Select number of employees', 'workeera-remote-tech-job-board' ) ],
                            'options'          => workeera_company_employee_count_fields(),
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="workeera-formseting-field">
                <div class="formseting-field-label">
                    <strong><?php esc_html_e( 'LinkedIn', 'workeera-remote-tech-job-board' ) ?></strong>
                </div>
                <div class="formseting-field-entry">
                    <?php
                    Workeera_Form_Fields::input( [
                        'name'             => 'workeera_meta[company_info][linkedin]',
                        'value'            => isset($company_info['linkedin']) ? $company_info['linkedin'] : '',
                        'extra_attributes' => [ 'placeholder' => 'https://linkedin.com/company/example' ],
                        'default'          => '',
                    ] );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Job "Further Details" meta box with taxonomy term selectors.
     */
    public function job_terms_metabox_callback() {
        global $post;

        $job_id = $post->ID;

        $taxonomy_fields = [
            'sectors'  => [
                'label'       => esc_html_x( 'Sector', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'placeholder' => esc_html_x( 'Select sector', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'multi'       => false,
            ],
            'skills'   => [
                'label'       => esc_html_x( 'Required Skills', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'placeholder' => esc_html_x( 'Select or add skills', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'multi'       => true,
                'creatable'   => true,
            ],
            'jobtypes' => [
                'label'       => esc_html_x( 'Job Types', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'placeholder' => esc_html_x( 'Select job types', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'multi'       => true,
            ],
            'timezones' => [
                'label'       => esc_html_x( 'Timezone', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'placeholder' => esc_html_x( 'Select timezone', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'multi'       => false,
            ],
            'benefits'  => [
                'label'       => esc_html_x( 'Benefits', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'placeholder' => esc_html_x( 'Select benefits', 'Job metabox', 'workeera-remote-tech-job-board' ),
                'multi'       => true,
            ],
        ];

        echo '<div class="workeera-formfields-con">';
        self::render_taxonomy_fields( $job_id, $taxonomy_fields );
        echo '</div>';
    }

    /**
     * Render the Job Location meta box.
     */
    public function job_location_metabox_callback() {
        global $post;

        $job_id = $post->ID;

        ?>
        <div class="workeera-formfields-con">
            <?php self::location_fields( $job_id ) ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Candidate metaboxes
    // -------------------------------------------------------------------------

    /**
     * Render the Candidate Fields meta box (user, contact, profile, taxonomies).
     */
    public function candidate_metabox_callback() {
        global $post;

        $candidate_id = $post->ID;

        // Defence-in-depth nonce — verified in post_save() before any DB
        // write happens. WP core's own edit-screen nonce is still the primary
        // gate; this one protects against custom POST flows.
        wp_nonce_field( 'workeera_post_save_' . $candidate_id, 'workeera_post_save_nonce' );

        $author_options = [];
        if (isset($post->post_author) && $post->post_author) {
            $author_data = get_userdata($post->post_author);
            $author_options[$post->post_author] = $author_data->display_name;
        }

        $job_title = workeera_get_object_meta( $candidate_id, 'job_title' );
        $email_address = workeera_get_object_meta( $candidate_id, 'email_address' );
        $phone_number = workeera_get_object_meta( $candidate_id, 'phone_number' );
        $date_of_birth = workeera_get_object_meta( $candidate_id, 'date_of_birth' );
        $gender = workeera_get_object_meta( $candidate_id, 'gender' );
        $website_url = workeera_get_object_meta( $candidate_id, 'website_url' );
        $linkedin_url = workeera_get_object_meta( $candidate_id, 'linkedin_url' );
        ?>
        <div class="workeera-formfields-con">
            <div class="workeera-formseting-field">
                <div class="formseting-field-label">
                    <strong><?php esc_html_e( 'Candidate User', 'workeera-remote-tech-job-board' ) ?></strong>
                </div>
                <div class="formseting-field-entry">
                    <?php
                    Workeera_Form_Fields::select( [
                        'name'             => 'workeera_candidate_user',
                        'classes'          => [ 'workeera-users-dropdown' ],
                        'extra_attributes' => [ 'data-placeholder' => esc_html__( 'Select user', 'workeera-remote-tech-job-board' ), 'data-user-type' => 'candidate' ],
                        'options'          => $author_options,
                        'value'            => isset($post->post_author) ? $post->post_author : '',
                        'default'          => '',
                    ] );
                    ?>
                </div>
            </div>
            <div class="workeera-formseting-field">
                <div class="formseting-field-label">
                    <strong><?php esc_html_e( 'Job Title', 'workeera-remote-tech-job-board' ) ?></strong>
                </div>
                <div class="formseting-field-entry">
                    <?php
                    Workeera_Form_Fields::input( [
                        'name'             => 'workeera_meta[job_title]',
                        'value'            => $job_title,
                        'extra_attributes' => [ 'placeholder' => esc_html_x( 'Python Developer', 'candidate job title', 'workeera-remote-tech-job-board' ) ],
                        'default'          => '',
                    ] );
                    ?>
                </div>
            </div>
            <div class="workeera-grid-two-cols">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Email Address', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[email_address]',
                            'value'            => $email_address,
                            'extra_attributes' => [ 'placeholder' => esc_html__( 'example@abc.com', 'workeera-remote-tech-job-board' ) ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Phone Number', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[phone_number]',
                            'value'            => $phone_number,
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="workeera-grid-two-cols">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Date of Birth', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[date_of_birth]',
                            'classes'          => ['workeera-date-input'],
                            'value'            => $date_of_birth,
                            'extra_attributes' => [ 'readonly' => '', 'data-miny' => 1920, 'data-maxy' => gmdate('Y'), 'data-max-date' => gmdate('d-m-Y') ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Gender', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::select( [
                            'name'             => 'workeera_meta[gender]',
                            'value'            => $gender,
                            'classes'          => ['workeera-select2-dropdown'],
                            'extra_attributes' => [
                                'data-placeholder' => esc_attr_x( 'Select gender', 'Resume Details', 'workeera-remote-tech-job-board' ),
                            ],
                            'options'          => [
                                'male'        => esc_attr__( 'Male', 'workeera-remote-tech-job-board' ),
                                'female'      => esc_attr__( 'Female', 'workeera-remote-tech-job-board' ),
                                'prefer_not'  => esc_attr__( 'Prefer not to say', 'workeera-remote-tech-job-board' ),
                            ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="workeera-grid-two-cols">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'Website URL', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[website_url]',
                            'value'            => $website_url,
                            'extra_attributes' => [ 'placeholder' => 'https://exampleurl.com' ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php esc_html_e( 'LinkedIn', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_meta[linkedin_url]',
                            'value'            => $linkedin_url,
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <?php
            $taxonomy_fields = [
                'sectors'     => [
                    'label'       => esc_html_x( 'Sector', 'Candidate metabox', 'workeera-remote-tech-job-board' ),
                    'placeholder' => esc_html_x( 'Select sector', 'Candidate metabox', 'workeera-remote-tech-job-board' ),
                    'multi'       => false,
                ],
                'skills'      => [
                    'label'       => esc_html_x( 'Skills', 'Candidate metabox', 'workeera-remote-tech-job-board' ),
                    'placeholder' => esc_html_x( 'Select or add skills', 'Candidate metabox', 'workeera-remote-tech-job-board' ),
                    'multi'       => true,
                    'creatable'   => true,
                ],
                'experiences' => [
                    'label'       => esc_html_x( 'Experience Level', 'Candidate metabox', 'workeera-remote-tech-job-board' ),
                    'placeholder' => esc_html_x( 'Select experience level', 'Candidate metabox', 'workeera-remote-tech-job-board' ),
                    'multi'       => false,
                ],
            ];

            self::render_taxonomy_fields( $candidate_id, $taxonomy_fields );
            ?>
        </div>
        <?php
    }

    /**
     * Render the Candidate Location meta box.
     */
    public function candidate_location_metabox_callback() {
        global $post;

        $candidate_id = $post->ID;
        ?>
        <div class="workeera-formfields-con">
            <?php self::location_fields( $candidate_id, 'candidate_post' ) ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Shared taxonomy field renderer
    // -------------------------------------------------------------------------

    /**
     * Render select2-powered taxonomy select fields for a post.
     *
     * @param int   $post_id         Post ID.
     * @param array $taxonomy_fields Config array keyed by tax_slug.
     */
    public static function render_taxonomy_fields( $post_id, $taxonomy_fields ) {
        foreach ( $taxonomy_fields as $tax_slug => $field_cfg ) {
            $label       = isset( $field_cfg['label'] ) ? $field_cfg['label'] : '';
            $placeholder = isset( $field_cfg['placeholder'] ) ? $field_cfg['placeholder'] : '';
            $is_multi    = ! empty( $field_cfg['multi'] );
            $creatable   = ! empty( $field_cfg['creatable'] );

            // Total terms in this taxonomy
            $total_terms = Workeera_Object_Terms::total_found( $tax_slug );

            if ( $total_terms < 1 ) {
                continue;
            }

            // Load currently saved terms as pre-selected options.
            $saved_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $post_id, $tax_slug );

            $options = [ '' => $placeholder ];
            if ( !$creatable && WORKEERA_TERMS_COUNT_LITE >= $total_terms ) {
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
            $classes = [ 'workeera-tax-term-selectr' ];
            if ( $creatable ) {
                $classes[] = 'workeera-tax-term-creatable';
            } else {
                if ( WORKEERA_TERMS_COUNT_LITE >= $total_terms ) {
                    $classes = [ 'workeera-select2-dropdown', 'workeera-select2-wsearch' ];
                }
            }

            $field_name = 'workeera_terms[' . $tax_slug . ']' . ( $is_multi ? '[]' : '' );

            $extra_attrs = [
                'data-placeholder' => $placeholder,
                'data-tax-slug'    => $tax_slug,
            ];
            if ( $is_multi ) {
                $extra_attrs['data-multi'] = '1';
            }
            if ( $creatable ) {
                $extra_attrs['data-creatable'] = '1';
            }
            ?>
            <div class="workeera-formseting-field">
                <div class="formseting-field-label">
                    <strong><?php echo esc_html( $label ) ?></strong>
                </div>
                <div class="formseting-field-entry">
                    <?php
                    Workeera_Form_Fields::select( [
                        'name'             => $field_name,
                        'options'          => $options,
                        'classes'          => $classes,
                        'value'            => $saved_value,
                        'multiple'         => $is_multi,
                        'extra_attributes' => $extra_attrs,
                        'default'          => '',
                    ] );
                    ?>
                </div>
            </div>
            <?php
        }
    }

    // -------------------------------------------------------------------------
    // Location fields
    // -------------------------------------------------------------------------

    /**
     * Render the shared country/city/address location fields for a post.
     *
     * @param int    $post_id Post ID. 0 when creating a new post.
     * @param string $place   Context flag; "job_post" enables the location-type buttons.
     */
    public static function location_fields( $post_id = 0, $place = 'job_post' ) {
        global $wpdb, $workeera_plugin_settings;

        // Full country list for the dropdown.
        $countries      = Workeera_Object_Terms::countries_list();
        $countries_list = [ '' => esc_html__( 'Select Country', 'workeera-remote-tech-job-board' ) ];
        foreach ( $countries as $country ) {
            $countries_list[ $country['country_id'] ] = $country['label'];
        }

        // Saved values — populated below when a location row exists.
        $saved_country_id = '';
        $saved_city_id    = '';
        $saved_city_label = '';
        $saved_street     = '';
        $saved_zip        = '';

        if ( $post_id > 0 ) {
            $location_row = Workeera_Object_Terms_Relation::get_post_location( $post_id );
            if ( ! empty( $location_row ) ) {
                $saved_country_id = ! empty( $location_row['country_id'] ) ? absint( $location_row['country_id'] ) : '';
                $saved_city_id    = ! empty( $location_row['city_id'] ) ? absint( $location_row['city_id'] ) : '';

                // Resolve city label for the pre-populated option.
                if ( $saved_city_id > 0 ) {
                    $cities_table     = $wpdb->prefix . 'workeera_cities';
                    $saved_city_label = (string) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT label FROM $cities_table WHERE city_id = %d LIMIT 1",
                            $saved_city_id
                        )
                    );
                }

                // Unserialize full_address to recover street + zip.
                if ( ! empty( $location_row['full_address'] ) ) {
                    $address_data = maybe_unserialize( $location_row['full_address'] );
                    if ( is_array( $address_data ) ) {
                        $saved_street = isset( $address_data['street_address'] ) ? $address_data['street_address'] : '';
                        $saved_zip    = isset( $address_data['zip_code'] ) ? $address_data['zip_code'] : '';
                    }
                }
            }
        }

        // Pre-build city option only when one is already saved (AJAX loads the rest).
        $city_options = [];
        if ( $saved_city_id > 0 && $saved_city_label !== '' ) {
            $city_options[ $saved_city_id ] = $saved_city_label;
        }

        // Apply the same Location filter-type setting used by the public listings,
        // so country / city visibility on the post-edit form matches site policy.
        $location_filter_type = isset( $workeera_plugin_settings['general_settings']['location_filter_type'] )
            ? $workeera_plugin_settings['general_settings']['location_filter_type']
            : 'countries_cities';

        $show_country = in_array( $location_filter_type, [ 'countries_cities', 'countries' ], true );
        $show_city    = in_array( $location_filter_type, [ 'countries_cities', 'cities' ], true );

        // Cities-only — pin the saved country to the configured value so the
        // city AJAX search has the right scope and the country still gets
        // persisted alongside the city via the existing save handler.
        if ( $location_filter_type === 'cities' ) {
            $forced_country_id = isset( $workeera_plugin_settings['general_settings']['cities_only_country'] )
                ? absint( $workeera_plugin_settings['general_settings']['cities_only_country'] )
                : 0;
            if ( $forced_country_id > 0 ) {
                $saved_country_id = $forced_country_id;
            }
        }
        ?>
        <div class="workeera-custom-location-field">
            <div class="<?php echo esc_attr($location_filter_type == 'countries_cities') ? 'workeera-grid-two-cols' : 'workeera-grid-one-col' ?>">
                <?php if ( $show_country ) : ?>
                    <div class="workeera-formseting-field">
                        <div class="formseting-field-label">
                            <strong><?php echo esc_html_x( 'Country', 'Locations Field', 'workeera-remote-tech-job-board' ) ?></strong>
                        </div>
                        <div class="formseting-field-entry workeera-location-country-con">
                            <?php
                            Workeera_Form_Fields::select( [
                                'name'             => 'workeera_location_fields[country]',
                                'options'          => $countries_list,
                                'classes'          => [ 'workeera-select2-dropdown', 'workeera-select2-wsearch' ],
                                'value'            => $saved_country_id,
                                'extra_attributes' => [
                                    'data-placeholder' => esc_html_x( 'Country', 'Locations Field', 'workeera-remote-tech-job-board' ),
                                ],
                                'default'          => '',
                            ] );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ( $show_city ) : ?>
                    <div class="workeera-formseting-field">
                        <div class="formseting-field-label">
                            <strong><?php echo esc_html_x( 'City', 'Locations Field', 'workeera-remote-tech-job-board' ) ?></strong>
                        </div>
                        <div class="formseting-field-entry workeera-location-city-con">
                            <?php
                            Workeera_Form_Fields::select( [
                                'name'             => 'workeera_location_fields[city]',
                                'options'          => $city_options,
                                'classes'          => [ 'workeera-location-city-select2' ],
                                'value'            => $saved_city_id,
                                'extra_attributes' => [
                                    'data-placeholder' => esc_html_x( 'City', 'Locations Field', 'workeera-remote-tech-job-board' ),
                                    'data-country-id'  => $saved_country_id,
                                ],
                                'default'          => '',
                            ] );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( ! $show_country && $location_filter_type === 'cities' ) : ?>
                <input type="hidden" name="workeera_location_fields[country]" value="<?php echo esc_attr( $saved_country_id ); ?>" class="workeera-location-country-hidden" />
            <?php endif; ?>
            <div class="workeera-grid-two-one">
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php echo esc_html_x( 'Street Address', 'Locations Field', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry workeera-location-streetaddr-con">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_location_fields[street_address]',
                            'value'            => workeera_strip_slashes($saved_street),
                            'extra_attributes' => [
                                'placeholder' => esc_html_x( '47 W 13th St, Pennsylvania Avenue, Washington', 'Locations Field', 'workeera-remote-tech-job-board' ),
                            ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
                <div class="workeera-formseting-field">
                    <div class="formseting-field-label">
                        <strong><?php echo esc_html_x( 'Zip Code', 'Locations Field', 'workeera-remote-tech-job-board' ) ?></strong>
                    </div>
                    <div class="formseting-field-entry workeera-location-zipcode-con">
                        <?php
                        Workeera_Form_Fields::input( [
                            'name'             => 'workeera_location_fields[zip_code]',
                            'value'            => $saved_zip,
                            'extra_attributes' => [
                                'placeholder' => esc_html_x( '12001', 'Locations Field', 'workeera-remote-tech-job-board' ),
                            ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <?php
            if ( $place == 'job_post' ) {
                $default_location_type = isset($workeera_plugin_settings['job_submission_settings']['default_location_type']) ? $workeera_plugin_settings['job_submission_settings']['default_location_type'] : '';
                $allow_location_type = isset($workeera_plugin_settings['job_submission_settings']['allow_location_type']) ? $workeera_plugin_settings['job_submission_settings']['allow_location_type'] : 'on';
                if ( empty($default_location_type) ) {
                    $default_location_type = 'remote';
                }
                Workeera_Form_Fields::input( [
                    'name' => 'workeera_field_location_type',
                    'input_type' => 'hidden',
                    'classes' => ['workeera-hide'],
                    'value' => $default_location_type,
                    'default' => '',
                ] );

                if ( $allow_location_type == 'on' ) {
                    $location_types = workeera_job_location_types();
                    $saved_type = '';
                    if ( $post_id > 0 ) {
                        $job_fields_row = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post_id ], 'all', 1 );
                        if ( isset($job_fields_row['location_type']) ) {
                            $saved_type = $job_fields_row['location_type'];
                        }
                    }
                    ?>
                    <div class="workeera-formseting-field">
                        <div class="formseting-field-label">
                            <strong><?php echo esc_html_x( 'Location Type', 'Locations Field', 'workeera-remote-tech-job-board' ) ?></strong>
                        </div>
                        <?php
                        if (!empty($location_types)) {
                            ?>
                            <div class="formseting-field-entry workeera-grid-optbtns-main workeera-location-type-con">
                                <?php
                                Workeera_Form_Fields::option_buttons( [
                                    'name' => 'workeera_field_location_type',
                                    'value' => $saved_type,
                                    'options' => $location_types,
                                    'default' => $default_location_type,
                                ] );
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Post save (terms are handled via workeera_save_custom_post_data)
    // -------------------------------------------------------------------------

    /**
     * Persist meta-box submissions for workeera_job and workeera_candidate posts.
     *
     * @param int     $post_id Post ID being saved.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing-post update.
     */
    public function post_save( $post_id, $post, $update ) {

        global $wpdb, $workeera_plugin_settings;

        $author_id = $post->post_author;

        if (isset($post->post_status) && $post->post_status == 'auto-draft') {
            return;
        }

        // Only act on this plugin's post types.
        if ( ! in_array( $post->post_type, [ 'workeera_job', 'workeera_candidate' ], true ) ) {
            return;
        }

        // Skip autosave / cron / REST contexts — they aren't editing the
        // metabox form, so they have no nonce to present.
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return;
        }

        // Defence-in-depth nonce check — the metabox callbacks render
        // `workeera_post_save_nonce` and we require it before writing any
        // meta. The capability check stays as the primary access gate.
        $nonce = isset( $_POST['workeera_post_save_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['workeera_post_save_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'workeera_post_save_' . $post_id ) ) {
            return;
        }

        $can_edit_post = false;

        if ( current_user_can( 'edit_post', $post_id ) ) {
            $can_edit_post = true;
        }

        if ( (int) $author_id === get_current_user_id() ) {
            $can_edit_post = true;
        }

        if ( ! $can_edit_post ) {
            return;
        }

        if ( $post->post_type == 'workeera_job' ) {

            $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post_id ], 'all', 1 );

            $job_field_id = isset($job_fields['job_field_id']) ? $job_fields['job_field_id'] : '';

            $job_field_data = [ 'job_id' => $post_id ];

            // assign author
            if (isset($_POST['workeera_job_posted_by']) && current_user_can('edit_post', $post_id)) {
                $wpdb->update($wpdb->prefix . 'posts', ['post_author' => absint( wp_unslash( $_POST['workeera_job_posted_by'] ) )], ['ID' => $post_id], ['%d']);
            }

            // salary fields
            if (isset($_POST['workeera_field_min_salary'])) {
                $job_field_data['min_salary'] = absint( wp_unslash( $_POST['workeera_field_min_salary'] ) );
            }
            if (isset($_POST['workeera_field_max_salary'])) {
                $job_field_data['max_salary'] = absint( wp_unslash( $_POST['workeera_field_max_salary'] ) );
            }
            $salary_units = workeera_salary_units();
            $salary_unit_switch = isset($workeera_plugin_settings['general_settings']['salary_unit_field']) ? $workeera_plugin_settings['general_settings']['salary_unit_field'] : '';
            if ($salary_unit_switch == 'on' && isset($_POST['workeera_field_salary_type'])) {
                $salary_type = sanitize_key( wp_unslash( $_POST['workeera_field_salary_type'] ) );
                if (isset($salary_units[$salary_type])) {
                    $job_field_data['salary_type'] = $salary_type;
                }
            } else {
                $default_salary_unit = isset($workeera_plugin_settings['general_settings']['salary_unit']) ? $workeera_plugin_settings['general_settings']['salary_unit'] : 'hourly';
                if (!empty($default_salary_unit) && isset($salary_units[$default_salary_unit])) {
                    $job_field_data['salary_type'] = $default_salary_unit;
                }
            }

            // NOTE: The employer's saved companies list (`user_company_data`) is
            // intentionally NOT written from the admin metabox. That key is now a
            // multi-company list managed from the front-end "My Companies" page +
            // job posting; merging the flat admin fields here would corrupt it.
            // The per-job `company_info` snapshot is still saved by the generic
            // meta loop below, so the job keeps its own company data.

            // deadline date
            if (isset($_POST['workeera_field_deadline_date'])) {
                $deadline_date = workeera_validate_date_input( sanitize_text_field( wp_unslash( $_POST['workeera_field_deadline_date'] ) ) );
                if (!empty($deadline_date)) {
                    $deadline_date = gmdate('Y-m-d', strtotime(str_replace('/', '-', $deadline_date))) . ' 23:59:59'; // till the end of the date
                    $job_field_data['application_deadline'] = $deadline_date;
                }
            }

            // expiry date
            if (isset($_POST['workeera_field_expiry_date']) && current_user_can('edit_post', $post_id)) { // only admin users can set expiry date from backend
                $expiry_date = workeera_validate_date_input( sanitize_text_field( wp_unslash( $_POST['workeera_field_expiry_date'] ) ) );
                if (!empty($expiry_date)) {
                    $expiry_date = gmdate('Y-m-d', strtotime(str_replace('/', '-', $expiry_date))) . ' 23:59:59'; // till the end of the date
                    $job_field_data['expiry_date'] = $expiry_date;
                }
            }

            // Location type field
            if (isset($_POST['workeera_field_location_type'])) {
                $location_types = workeera_job_location_types();
                $location_type = sanitize_key( wp_unslash( $_POST['workeera_field_location_type'] ) );
                if ( isset($location_types[$location_type]) ) {
                    $job_field_data['location_type'] = $location_type;
                }
            }

            // Job status side metabox — Featured / Urgent / Is Filled.
            // The metabox renders for users who can edit the job; we still gate
            // the save with edit_post just to be safe.
            if ( current_user_can( 'edit_post', $post_id ) && self::job_status_metabox_was_submitted() ) {
                $job_field_data['is_featured'] = ! empty( $_POST['workeera_field_is_featured'] ) ? 1 : 0;
                $job_field_data['is_urgent']   = ! empty( $_POST['workeera_field_is_urgent'] )   ? 1 : 0;
                $job_field_data['is_filled']   = ! empty( $_POST['workeera_field_is_filled'] )   ? 1 : 0;

                // Featured-till date — saved only when Featured is on; otherwise cleared.
                if ( $job_field_data['is_featured'] ) {
                    $featured_till = isset( $_POST['workeera_field_featured_till'] ) ? workeera_validate_date_input( sanitize_text_field( wp_unslash( $_POST['workeera_field_featured_till'] ) ) ) : '';
                    if ( ! empty( $featured_till ) ) {
                        $job_field_data['featured_till'] = gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $featured_till ) ) ) . ' 23:59:59';
                    } else {
                        $job_field_data['featured_till'] = null;
                    }
                } else {
                    $job_field_data['featured_till'] = null;
                }

                // Urgent-till date — saved only when Urgent is on; otherwise cleared.
                if ( $job_field_data['is_urgent'] ) {
                    $urgent_till = isset( $_POST['workeera_field_urgent_till'] ) ? workeera_validate_date_input( sanitize_text_field( wp_unslash( $_POST['workeera_field_urgent_till'] ) ) ) : '';
                    if ( ! empty( $urgent_till ) ) {
                        $job_field_data['urgent_till'] = gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $urgent_till ) ) ) . ' 23:59:59';
                    } else {
                        $job_field_data['urgent_till'] = null;
                    }
                } else {
                    $job_field_data['urgent_till'] = null;
                }
            }

            if ( empty( $job_field_id ) ) {
                Workeera_Query_Handler::save_item_data( 'job_fields', 'job_id', $post_id, $job_field_data, false );
            } else {
                Workeera_Query_Handler::save_item_data( 'job_fields', 'job_field_id', $job_field_id, $job_field_data );
            }
        }

        if ( $post->post_type == 'workeera_candidate' ) {
            // assign author
            if (isset($_POST['workeera_candidate_user']) && current_user_can('edit_post', $post_id)) {
                $post_user_id = absint( wp_unslash( $_POST['workeera_candidate_user'] ) );
                if ($post_user_id) {
                    $wpdb->update($wpdb->prefix . 'posts', ['post_author' => $post_user_id], ['ID' => $post_id], ['%d']);
                    if (!workeera_metadata_exists('user', $post_user_id, 'workeera_user_type')) {
                        workeera_update_object_meta($post_user_id, 'workeera_user_type', 'candidate', 'user');
                    }
                    if (!workeera_metadata_exists('user', $post_user_id, 'workeera_candidate_id')) {
                        workeera_update_object_meta($post_user_id, 'workeera_candidate_id', $post_id, 'user');
                    }
                }
            }
        }

        // Apply-method + apply-job-url — normalise according to the site-wide
        // setting before the generic workeera_meta save loop picks them up.
        if ( $post->post_type === 'workeera_job' ) {
            $job_apply_method_setting = isset( $workeera_plugin_settings['job_submission_settings']['job_apply_method'] )
                ? $workeera_plugin_settings['job_submission_settings']['job_apply_method']
                : 'internal';

            if ( $job_apply_method_setting === 'internal' ) {
                if ( isset( $_POST['workeera_meta']['apply_method'] ) )   { unset( $_POST['workeera_meta']['apply_method'] ); }
                if ( isset( $_POST['workeera_meta']['apply_job_url'] ) )  { unset( $_POST['workeera_meta']['apply_job_url'] ); }
            } elseif ( isset( $_POST['workeera_meta'] ) && is_array( $_POST['workeera_meta'] ) ) {
                $submitted_method = isset( $_POST['workeera_meta']['apply_method'] )
                    ? sanitize_key( wp_unslash( $_POST['workeera_meta']['apply_method'] ) )
                    : '';

                if ( $job_apply_method_setting === 'external' ) {
                    $effective_method = 'external';
                } else { // 'both' — allowlist the choice.
                    $effective_method = in_array( $submitted_method, [ 'internal', 'external' ], true ) ? $submitted_method : 'internal';
                }
                $_POST['workeera_meta']['apply_method'] = $effective_method;

                if ( $effective_method === 'external' ) {
                    $raw_url = isset( $_POST['workeera_meta']['apply_job_url'] ) ? wp_unslash( $_POST['workeera_meta']['apply_job_url'] ) : '';
                    $clean   = esc_url_raw( trim( (string) $raw_url ) );
                    if ( ! empty( $clean ) && preg_match( '#^https?://#i', $clean ) ) {
                        $_POST['workeera_meta']['apply_job_url'] = $clean;
                    } else {
                        $_POST['workeera_meta']['apply_job_url'] = '';
                    }
                } else {
                    $_POST['workeera_meta']['apply_job_url'] = '';
                }
            }
        }

        if ( in_array( $post->post_type, [ 'workeera_job', 'workeera_candidate' ], true ) ) {
            if ( isset( $_POST['workeera_meta'] ) && ! empty( $_POST['workeera_meta'] ) && is_array( $_POST['workeera_meta'] ) ) {

                // Allowlist of the meta keys each post type's forms legitimately
                // set. Anything else in the payload — notably candidate_cv_files,
                // which is written only by the dedicated CV upload/delete handlers
                // — is discarded, so a crafted request cannot inject arbitrary
                // filesystem paths (or any other unexpected key) into post meta.
                $allowed_meta_keys = [
                    'workeera_job'       => [ 'num_of_vacancies', 'apply_method', 'apply_job_url', 'company_info' ],
                    'workeera_candidate' => [ 'job_title', 'email_address', 'phone_number', 'date_of_birth', 'gender', 'website_url', 'linkedin_url', 'designation' ],
                ];
                $post_type_keys = isset( $allowed_meta_keys[ $post->post_type ] ) ? $allowed_meta_keys[ $post->post_type ] : [];

                /**
                 * Filter the meta keys the profile / job forms are allowed to write
                 * via the generic workeera_meta save loop. Addons that add their own
                 * form fields should append their keys here. Keys not present are
                 * silently ignored (security allowlist — do not return unknown keys
                 * that originate from request input).
                 *
                 * @param array   $post_type_keys Allowed meta keys for this post type.
                 * @param string  $post_type      The post type being saved.
                 * @param int     $post_id        The post ID being saved.
                 */
                $post_type_keys = (array) apply_filters( 'workeera_allowed_meta_keys', $post_type_keys, $post->post_type, $post_id );

                $meta_arr = map_deep( wp_unslash( $_POST['workeera_meta'] ), 'sanitize_text_field' );
                foreach ( $meta_arr as $meta_key => $meta_val ) {
                    if ( ! in_array( $meta_key, $post_type_keys, true ) ) {
                        continue; // Not a form-writable key — ignore it.
                    }
                    workeera_update_object_meta( $post_id, $meta_key, $meta_val );
                }
            }
        }

        do_action( 'workeera_save_custom_post_data', $post_id, $post );
    }
}

new Workeera_Post_Meta_Boxes;
