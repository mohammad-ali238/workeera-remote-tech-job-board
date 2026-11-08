<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the candidate resume / profile editor and its AJAX endpoints.
 */
class Workeera_Candidate_Resume {

    /**
     * Register AJAX hooks for resume submission and profile picture uploads.
     */
    public function __construct() {

        add_action( 'wp_ajax_workeera_user_resume_details_submission_call', [ $this, 'resume_details_submission' ] );

        add_action( 'wp_ajax_workeera_upload_candidate_profile_picture', [ $this, 'upload_profile_picture' ] );
        add_action( 'wp_ajax_workeera_remve_uploaded_candidate_profile_picture', [ $this, 'remove_profile_picture' ] );
    }

    /**
     * Render the candidate resume / profile details form.
     */
    public static function details_form() {
        global $workeera_plugin_settings;
        $random_num = wp_rand(10000000, 99999999);

        if (!class_exists('Workeera_Form_Parts')) {
            include_once WORKEERA_ABSPATH . 'includes/common-form-sections.php';
        }

        $user_id = get_current_user_id();

        $candidate_id = workeera_is_user_candidate( $user_id );

        $user_data = get_userdata($user_id);

        $candidate_post = get_post($candidate_id);

        $photo_data = workeera_get_object_meta($user_id, 'user_profile_picture', 'user');

        $job_title = workeera_get_object_meta( $candidate_id, 'job_title' );
        $email_address = workeera_get_object_meta( $candidate_id, 'email_address' );
        $phone_number = workeera_get_object_meta( $candidate_id, 'phone_number' );
        $date_of_birth = workeera_get_object_meta( $candidate_id, 'date_of_birth' );
        $gender = workeera_get_object_meta( $candidate_id, 'gender' );
        $website_url = workeera_get_object_meta( $candidate_id, 'website_url' );
        $linkedin_url = workeera_get_object_meta( $candidate_id, 'linkedin_url' );
        ?>
        <div class="workeera-usrform-wrapper workeera-usrdshb-main-wrap">
            <div class="workeera-usrdshb-container">

                <form method="post" class="workeera-usrdshb-resume-form workeera-user-form workeera-withbtn-inline-lodr">
                    <?php
                    /**
                     * Render extra content at the very top of the candidate
                     * profile form, before the Personal details card (e.g.
                     * an addon CV drop box for AI autofill).
                     *
                     * @param int $candidate_id Candidate post ID.
                     */
                    do_action( 'workeera_candidate_form_top', $candidate_id );
                    ?>
                    <div class="workeera-usrform-section-card workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Personal details', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></span>
                        </div>
                        <div class="workeera-usrform-section-body">
                            <div class="workeera-usrform-form-panel">
                                <div class="workeera-usrform-fields-main-wrapper">
                                    <?php
                                    $photo_url = '';
                                    if (!empty($photo_data) && isset($photo_data['file']) && !empty($photo_data['file']) && file_exists($photo_data['file'])) {
                                        if (!empty($photo_data['thumb_path']) && file_exists($photo_data['thumb_path'])) {
                                            $photo_url = $photo_data['thumb_url'];
                                        } else {
                                            $photo_url = $photo_data['url'];
                                        }
                                        $photo_url = add_query_arg(['ver' => absint($photo_data['version'])], $photo_url);
                                    }
                                    ?>
                                    <div class="workeera-usrform-photo-row">
                                        <div class="workeera-usrform-photo-upload<?php echo esc_attr(!empty($photo_url) ? ' workeera-with-filled-photo' : '') ?>" data-img-for="candidate_profile_picture">
                                            <input type="file" id="candidate-photo-<?php echo absint($random_num) ?>" accept="image/*" class="workeera-hide">
                                            <div class="workeera-user-photo-imgholdr<?php echo esc_attr(empty($photo_url) ? ' workeera-hide' : '') ?>">
                                                <div class="workeera-user-photo-imgbox" style="background-image: url('<?php echo esc_attr($photo_url) ?>');"></div>
                                                <div class="workeera-user-photo-remve" title="<?php echo esc_attr_x( 'Remove Photo', 'Resume Details', 'workeera-remote-tech-job-board' ); ?>"><div class="workeera-remove-icon"></div></div>
                                            </div>
                                            <div class="workeera-photo-svgicnlbl-con<?php echo esc_attr(!empty($photo_url) ? ' workeera-hide' : '') ?>" title="<?php echo esc_attr_x( 'Upload Photo', 'Resume Details', 'workeera-remote-tech-job-board' ); ?>">
                                                <svg width="20" class="workeera-user-photo-svgicn" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                                    <circle cx="12" cy="13" r="4" />
                                                </svg>
                                                <span class="workeera-user-photo-title"><?php echo esc_html_x( 'Photo', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></span>
                                            </div>
                                        </div>
                                        <div class="workeera-usrform-photo-fields">
                                            <div class="workeera-usrform-fields-row">
                                                <div class="workeera-usrform-field-group">
                                                    <label for="given-name-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Given name', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                                    <?php
                                                    Workeera_Form_Fields::input( [
                                                        'id'               => 'given-name-' . $random_num,
                                                        'name'             => 'given_name',
                                                        'classes'          => ['workeera-usrform-field-input'],
                                                        'extra_attributes' => [
                                                            'placeholder' => esc_attr_x( 'John', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                            'required'    => '',
                                                        ],
                                                        'value'            => isset($user_data->first_name) ? $user_data->first_name : '',
                                                        'default'          => '',
                                                    ] );
                                                    ?>
                                                </div>
                                                <div class="workeera-usrform-field-group">
                                                    <label for="family-name-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Family name', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                                    <?php
                                                    Workeera_Form_Fields::input( [
                                                        'id'               => 'family-name-' . $random_num,
                                                        'name'             => 'family_name',
                                                        'classes'          => ['workeera-usrform-field-input'],
                                                        'extra_attributes' => [
                                                            'placeholder' => esc_attr_x( 'Doe', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                            'required'    => '',
                                                        ],
                                                        'value'            => isset($user_data->last_name) ? $user_data->last_name : '',
                                                        'default'          => '',
                                                    ] );
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="workeera-usrform-field-group">
                                        <label for="job-title-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Job Title', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'job-title-' . $random_num,
                                            'name'             => 'workeera_meta[job_title]',
                                            'classes'          => ['workeera-usrform-field-input'],
                                            'extra_attributes' => [
                                                'placeholder' => esc_attr_x( 'Python Developer', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                'required'    => '',
                                            ],
                                            'value'            => $job_title,
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <div class="workeera-usrform-fields-row">
                                        <div class="workeera-usrform-field-group">
                                            <label for="email-address-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Email Address', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'email-address-' . $random_num,
                                                'input_type'       => 'email',
                                                'name'             => 'workeera_meta[email_address]',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'extra_attributes' => [
                                                    'placeholder' => esc_attr_x( 'example@abc.com', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                ],
                                                'value'            => $email_address,
                                                'default'          => isset($user_data->user_email) ? $user_data->user_email : '',
                                            ] );
                                            ?>
                                        </div>
                                        <div class="workeera-usrform-field-group">
                                            <label for="phone-number-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Phone Number', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'phone-number-' . $random_num,
                                                'input_type'       => 'tel',
                                                'name'             => 'workeera_meta[phone_number]',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'value'            => $phone_number,
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                    </div>
                                    <div class="workeera-usrform-fields-row">
                                        <div class="workeera-usrform-field-group">
                                            <label for="date-of-birth-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Date of Birth', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'date-of-birth-' . $random_num,
                                                'name'             => 'workeera_meta[date_of_birth]',
                                                'classes'          => ['workeera-usrform-field-input', 'workeera-date-input'],
                                                'extra_attributes' => [ 'readonly' => '', 'data-miny' => 1920, 'data-maxy' => gmdate('Y'), 'data-max-date' => gmdate('d-m-Y') ],
                                                'value'            => $date_of_birth,
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                        <div class="workeera-usrform-field-group">
                                            <label for="gender-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Gender', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::select( [
                                                'id'               => 'gender-' . $random_num,
                                                'name'             => 'workeera_meta[gender]',
                                                'value'            => $gender,
                                                'classes'          => ['workeera-usrform-field-select', 'workeera-select2-dropdown'],
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
                                    <div class="workeera-usrform-field-group">
                                        <label for="candidate-website-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Website', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'candidate-website-' . $random_num,
                                            'name'             => 'workeera_meta[website_url]',
                                            'classes'          => ['workeera-usrform-field-input'],
                                            'extra_attributes' => [
                                                'placeholder' => 'https://exampleurl.com',
                                            ],
                                            'value'            => $website_url,
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <div class="workeera-usrform-field-group">
                                        <label for="candidate-linkedin-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'LinkedIn', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'candidate-linkedin-' . $random_num,
                                            'name'             => 'workeera_meta[linkedin_url]',
                                            'classes'          => ['workeera-usrform-field-input'],
                                            'extra_attributes' => [
                                                'placeholder' => 'https://linkedin.com/example',
                                            ],
                                            'value'            => $linkedin_url,
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <div class="workeera-usrform-field-group">
                                        <label for="short-description-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Short description', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        $short_description = self::optimize_content($candidate_post->post_content);
                                        $editr_settings = array(
                                            'textarea_rows' => 10,
                                            'editor_class'  => 'workeera-wp-editor',
                                            'textarea_name' => 'short_description',
                                            'media_buttons' => false,
                                            'quicktags'     => false,
                                            'tinymce'       => array(
                                                'toolbar1' => 'bold,italic,underline,bullist,numlist',
                                                'toolbar2' => '',
                                            ),
                                        );
                                        wp_editor($short_description, 'short-description-' . $random_num, $editr_settings);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    /**
                     * Render extra candidate-form sections right before the Further Details section.
                     *
                     * @param int $candidate_id Candidate post ID being edited.
                     */
                    do_action( 'workeera_candidate_form_before_further_details', $candidate_id );
                    ?>

                    <div class="workeera-usrform-section-card workeera-usrform-section-openclose workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Further details', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></span>
                            <div class="workeera-usrform-section-actions">
                                <a href="#" class="workeera-usrform-icon-btn" title="<?php echo esc_attr_x( 'Collapse', 'Resume Details', 'workeera-remote-tech-job-board' ); ?>">
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
                                                'label'       => esc_html_x( 'Sector', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                'placeholder' => esc_html_x( 'Select sector', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                'multi'       => false,
                                            ],
                                            'experiences' => [
                                                'label'       => esc_html_x( 'Experience Level', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                'placeholder' => esc_html_x( 'Select Experience Level', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                                'multi'       => false,
                                            ],
                                        ];
                                        self::render_taxonomy_fields( $candidate_id, $taxonomy_fields, $random_num );
                                        ?>
                                    </div>
                                    <?php
                                    $taxonomy_fields = [
                                        'skills'   => [
                                            'label'       => esc_html_x( 'Skills', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                            'placeholder' => esc_html_x( 'Select your skills', 'Resume Details', 'workeera-remote-tech-job-board' ),
                                            'multi'       => true,
                                            'checkboxes'  => true,
                                        ],
                                    ];
                                    self::render_taxonomy_fields( $candidate_id, $taxonomy_fields, $random_num );
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <?php
                    /**
                     * Render extra candidate-form sections right before the Location section.
                     *
                     * Used by addons (e.g. Workeera Custom Fields) to inject their own
                     * section cards into the front-end resume/profile form.
                     *
                     * @param int $candidate_id Candidate post ID being edited.
                     */
                    do_action( 'workeera_candidate_form_before_location', $candidate_id );
                    ?>

                    <div class="workeera-usrform-section-card workeera-usrform-section-openclose workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Location', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></span>
                            <div class="workeera-usrform-section-actions">
                                <a href="#" class="workeera-usrform-icon-btn" title="<?php echo esc_attr_x( 'Collapse', 'Resume Details', 'workeera-remote-tech-job-board' ); ?>">
                                    <svg class="workeera-usrform-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="18 15 12 9 6 15" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div class="workeera-usrform-section-body">
                            <div class="workeera-usrform-form-panel">
                                <div class="workeera-usrform-fields-main-wrapper workeera-custom-location-field">
                                    <?php Workeera_Form_Parts::location($candidate_id, $random_num, 'candidate_post') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php workeera_terms_privacy_policy() ?>

                    <div class="workeera-form-submitbtn-con">
                        <button type="submit" class="workeera-btn workeera-large-btn"><?php echo esc_html_x( 'Save Details', 'Resume Details', 'workeera-remote-tech-job-board' ); ?></button>
                        <div class="workeera-form-actions-con workeera-post-token-con workeera-hide" data-post-id="<?php echo absint($candidate_id); ?>"></div>
                        <input type="hidden" name="action" value="workeera_user_resume_details_submission_call">
                    </div>
                </form>

            </div>
        </div>
        <?php
    }

    /**
     * Sanitize and tidy resume-style HTML content using a limited allowed tag set.
     *
     * @param  string $post_content Raw post content.
     * @return string               Sanitized HTML content.
     */
    public static function optimize_content($post_content) {
        $post_content = apply_filters('the_content', $post_content);
        $post_content = str_replace('&nbsp;', ' ', $post_content);
        $attr_list = [
            'id' => array(),
            'class' => array(),
        ];

        $tags_list = [
            'div' => $attr_list,
            'p' => $attr_list,
            'span' => $attr_list,
            'small' => $attr_list,
            'strong' => $attr_list,
            'b' => $attr_list,
            'ul' => $attr_list,
            'ol' => $attr_list,
            'li' => $attr_list,
            'i' => $attr_list,
            'em' => $attr_list,
        ];

        $post_content = wp_kses($post_content, $tags_list);

        $post_content = preg_replace('/\<p><\/p>/', ' ', $post_content);
        return trim(workeera_strip_slashes($post_content));
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
     * AJAX handler that re-triggers save_post for the candidate's profile post.
     */
    public function resume_details_submission() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        $candidate_id = workeera_is_user_candidate( $user_id );
        if ( ! $candidate_id ) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to perform this action.', 'workeera-remote-tech-job-board')));
        }

        $given_name = isset( $_POST['given_name'] ) ? sanitize_text_field( wp_unslash( $_POST['given_name'] ) ) : '';
        $family_name = isset( $_POST['family_name'] ) ? sanitize_text_field( wp_unslash( $_POST['family_name'] ) ) : '';

        if ( empty( $given_name ) || empty( $family_name ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Name field cannot be empty.', 'workeera-remote-tech-job-board' ) ] );
        }

        if ( mb_strlen( $given_name ) > 100 || mb_strlen( $family_name ) > 100 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Name is too long. Please make it shorter.', 'workeera-remote-tech-job-board' ) ] );
        }

        $candidate_title = $given_name . ' ' . $family_name;

        // Update the user
        $user_data = [
            'ID'            => $user_id,
            'first_name'    => $given_name,
            'last_name'     => $family_name,
            'display_name'  => $candidate_title,
        ];
        wp_update_user($user_data);

        // Candidate description — strip tags to count plain-text length.
        $candidate_description_raw = isset( $_POST['short_description'] ) ? wp_unslash( $_POST['short_description'] ) : '';
        $candidate_description_plain = wp_strip_all_tags( $candidate_description_raw );

        if ( mb_strlen( $candidate_description_plain ) > 2000 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Description must not exceed 2000 characters.', 'workeera-remote-tech-job-board' ) ] );
        }
        // Allow only safe formatting tags (matches workeera_optimize_candidate_content allowlist).
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
        $candidate_description = wp_kses( $candidate_description_raw, $allowed_desc_tags );

        // Update the post
        $post_data = [
            'ID'           => $candidate_id,
            'post_title'   => $candidate_title,
            'post_content' => $candidate_description,
            'post_type'    => 'workeera_candidate',
        ];
        $result = wp_update_post( $post_data, true );

        if ( is_wp_error( $result ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => $result->get_error_message() ] );
        }

        // The front-end form cannot carry the per-post metabox nonce, so mint it
        // here — this request already passed the workeera_nonce check above.
        $_POST['workeera_post_save_nonce'] = wp_create_nonce( 'workeera_post_save_' . $candidate_id );
        $post_obj = get_post($candidate_id);
        do_action( 'save_post', $candidate_id, $post_obj, true );

        wp_send_json( [
            'error' => '0',
            'msg' => esc_html__( 'Details saved successfully.', 'workeera-remote-tech-job-board' ),
        ] );
    }

    /**
     * AJAX handler for uploading the candidate's profile picture.
     */
    public function upload_profile_picture() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        $candidate_id = workeera_is_user_candidate( $user_id );
        if ( ! $candidate_id ) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to perform this action.', 'workeera-remote-tech-job-board')));
        }

        if (isset($_FILES['pic_file']['size']) && $_FILES['pic_file']['size'] > 0) {

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

            global $workeera_upload_files_extpath;
            $workeera_upload_files_extpath = 'user-images';
            add_filter('upload_dir', 'workeera_public_upload_files_path');

            $userbase_filename = 'user-' . $user_id . '-photo-pic.' . $file_ext;
            $file['name'] = $userbase_filename;

            $wp_upload_dir = wp_upload_dir();
            $uplod_direc_path = isset($wp_upload_dir['path']) ? $wp_upload_dir['path'] : '';
            $uplod_direc_url = isset($wp_upload_dir['url']) ? $wp_upload_dir['url'] : '';

            $uploaded_file = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allow_pic_mimes));
            if (isset($uploaded_file['file'])) {
                $uploaded_file['ext'] = $file_ext;
            } else {
                /* translators: %s: error message */
                wp_send_json(array('error' => '1', 'msg' => sprintf(esc_html_x('Action failed. Error: %s', 'Photo upload error', 'workeera-remote-tech-job-board'), $uploaded_file['error'])));
            }

            $rand_ver = wp_rand(10000000, 99999999);

            $image_editor = wp_get_image_editor($uploaded_file['file']);

            if (!is_wp_error($image_editor)) {

                $image_editor->resize(150, 150, false);

                $userbase_filename = 'user-' . $user_id . '-photo-thumb.' . $file_ext;
                $crop_file = $uplod_direc_path . '/' . $userbase_filename;
                $crop_file_url = $uplod_direc_url . '/' . $userbase_filename;
                $image_editor->save($crop_file);

                $uploaded_file['thumb_path'] = $crop_file;
                $uploaded_file['thumb_url'] = $crop_file_url;
            }

            $uploaded_file['version'] = $rand_ver;

            $user_profile_picture = workeera_get_object_meta($user_id, 'user_profile_picture', 'user');
            if (!empty($user_profile_picture)) {
                $user_profile_picture = $uploaded_file;
            } else {
                $user_profile_picture = $uploaded_file;
            }
            workeera_update_object_meta($user_id, 'user_profile_picture', $user_profile_picture, 'user');

            $img_url = isset($uploaded_file['thumb_url']) ? $uploaded_file['thumb_url'] : $uploaded_file['url'];

            $return_response = [
                'error' => '0',
                'img_url' => add_query_arg(array('ver' => $rand_ver), $img_url),
                'msg' => esc_html__('Photo saved successfully.', 'workeera-remote-tech-job-board'),
            ];

            wp_send_json($return_response);
        }

        wp_send_json(array('error' => '1', 'msg' => esc_html__('Incorrect request.', 'workeera-remote-tech-job-board')));
    }

    /**
     * AJAX handler for removing the candidate's profile picture.
     */
    public function remove_profile_picture() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        $candidate_id = workeera_is_user_candidate( $user_id );
        if ( ! $candidate_id ) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to perform this action.', 'workeera-remote-tech-job-board')));
        }

        workeera_update_object_meta($user_id, 'user_profile_picture', '', 'user');

        $return_response = [
            'error' => '0',
            'img_url' => '',
            'msg' => esc_html__('Photo removed successfully.', 'workeera-remote-tech-job-board'),
        ];

        wp_send_json($return_response);
    }

}

new Workeera_Candidate_Resume;
