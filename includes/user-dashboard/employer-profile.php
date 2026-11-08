<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the employer profile editor and its AJAX endpoints.
 *
 * Employers are plain WordPress users (there is no employer post type), so the
 * profile is stored entirely in user meta and mirrors only the "Personal
 * details" card of the candidate resume form — no Further details and no
 * Location sections.
 */
class Workeera_Employer_Profile {

    /**
     * Register AJAX hooks for profile submission and profile picture uploads.
     */
    public function __construct() {

        add_action( 'wp_ajax_workeera_employer_profile_details_submission_call', [ $this, 'profile_details_submission' ] );

        add_action( 'wp_ajax_workeera_upload_employer_profile_picture', [ $this, 'upload_profile_picture' ] );
        add_action( 'wp_ajax_workeera_remve_uploaded_employer_profile_picture', [ $this, 'remove_profile_picture' ] );
    }

    /**
     * Determine whether a user is an employer.
     *
     * @param  int $user_id
     * @return bool
     */
    public static function is_employer( $user_id ) {
        return workeera_get_object_meta( $user_id, 'workeera_user_type', 'user' ) === 'employer';
    }

    /**
     * Render the employer profile details form (personal details only).
     */
    public static function details_form() {
        $random_num = wp_rand(10000000, 99999999);

        if (!class_exists('Workeera_Form_Fields')) {
            include_once WORKEERA_ABSPATH . 'includes/class-form-fields.php';
        }

        $user_id = get_current_user_id();

        $user_data = get_userdata($user_id);

        $photo_data = workeera_get_object_meta($user_id, 'user_profile_picture', 'user');

        $designation = workeera_get_object_meta( $user_id, 'designation', 'user' );
        $email_address = workeera_get_object_meta( $user_id, 'email_address', 'user' );
        $phone_number = workeera_get_object_meta( $user_id, 'phone_number', 'user' );
        $website_url = workeera_get_object_meta( $user_id, 'website_url', 'user' );
        $linkedin_url = workeera_get_object_meta( $user_id, 'linkedin_url', 'user' );
        $short_description = workeera_get_object_meta( $user_id, 'short_description', 'user' );
        $short_description = !empty($short_description) ? self::optimize_content($short_description) : '';
        ?>
        <div class="workeera-usrform-wrapper workeera-usrdshb-main-wrap">
            <div class="workeera-usrdshb-container">

                <form method="post" class="workeera-usrdshb-resume-form workeera-user-form workeera-withbtn-inline-lodr">
                    <div class="workeera-usrform-section-card workeera-usrform-is-open">
                        <div class="workeera-usrform-section-header">
                            <span class="workeera-usrform-section-title"><?php echo esc_html_x( 'Personal details', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></span>
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
                                        <div class="workeera-usrform-photo-upload<?php echo esc_attr(!empty($photo_url) ? ' workeera-with-filled-photo' : '') ?>" data-img-for="employer_profile_picture">
                                            <input type="file" id="employer-photo-<?php echo absint($random_num) ?>" accept="image/*" class="workeera-hide">
                                            <div class="workeera-user-photo-imgholdr<?php echo esc_attr(empty($photo_url) ? ' workeera-hide' : '') ?>">
                                                <div class="workeera-user-photo-imgbox" style="background-image: url('<?php echo esc_attr($photo_url) ?>');"></div>
                                                <div class="workeera-user-photo-remve" title="<?php echo esc_attr_x( 'Remove Photo', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?>"><div class="workeera-remove-icon"></div></div>
                                            </div>
                                            <div class="workeera-photo-svgicnlbl-con<?php echo esc_attr(!empty($photo_url) ? ' workeera-hide' : '') ?>" title="<?php echo esc_attr_x( 'Upload Photo', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?>">
                                                <svg width="20" class="workeera-user-photo-svgicn" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                                    <circle cx="12" cy="13" r="4" />
                                                </svg>
                                                <span class="workeera-user-photo-title"><?php echo esc_html_x( 'Photo', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></span>
                                            </div>
                                        </div>
                                        <div class="workeera-usrform-photo-fields">
                                            <div class="workeera-usrform-fields-row">
                                                <div class="workeera-usrform-field-group">
                                                    <label for="given-name-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Given name', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                                    <?php
                                                    Workeera_Form_Fields::input( [
                                                        'id'               => 'given-name-' . $random_num,
                                                        'name'             => 'given_name',
                                                        'classes'          => ['workeera-usrform-field-input'],
                                                        'extra_attributes' => [
                                                            'placeholder' => esc_attr_x( 'John', 'Employer Profile', 'workeera-remote-tech-job-board' ),
                                                            'required'    => '',
                                                        ],
                                                        'value'            => isset($user_data->first_name) ? $user_data->first_name : '',
                                                        'default'          => '',
                                                    ] );
                                                    ?>
                                                </div>
                                                <div class="workeera-usrform-field-group">
                                                    <label for="family-name-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Family name', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                                    <?php
                                                    Workeera_Form_Fields::input( [
                                                        'id'               => 'family-name-' . $random_num,
                                                        'name'             => 'family_name',
                                                        'classes'          => ['workeera-usrform-field-input'],
                                                        'extra_attributes' => [
                                                            'placeholder' => esc_attr_x( 'Doe', 'Employer Profile', 'workeera-remote-tech-job-board' ),
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
                                        <label for="designation-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Designation', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'designation-' . $random_num,
                                            'name'             => 'workeera_meta[designation]',
                                            'classes'          => ['workeera-usrform-field-input'],
                                            'extra_attributes' => [
                                                'placeholder' => esc_attr_x( 'Founder/CEO', 'Employer Profile', 'workeera-remote-tech-job-board' ),
                                                'required'    => '',
                                            ],
                                            'value'            => $designation,
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <div class="workeera-usrform-fields-row">
                                        <div class="workeera-usrform-field-group">
                                            <label for="email-address-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Email Address', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'email-address-' . $random_num,
                                                'input_type'       => 'email',
                                                'name'             => 'workeera_meta[email_address]',
                                                'classes'          => ['workeera-usrform-field-input'],
                                                'extra_attributes' => [
                                                    'placeholder' => esc_attr_x( 'example@abc.com', 'Employer Profile', 'workeera-remote-tech-job-board' ),
                                                ],
                                                'value'            => $email_address,
                                                'default'          => isset($user_data->user_email) ? $user_data->user_email : '',
                                            ] );
                                            ?>
                                        </div>
                                        <div class="workeera-usrform-field-group">
                                            <label for="phone-number-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Phone Number', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
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
                                    <div class="workeera-usrform-field-group">
                                        <label for="employer-website-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Website', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'employer-website-' . $random_num,
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
                                        <label for="employer-linkedin-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'LinkedIn', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'employer-linkedin-' . $random_num,
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
                                        <label for="short-description-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Short description', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
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

                    <?php workeera_terms_privacy_policy() ?>

                    <div class="workeera-form-submitbtn-con">
                        <button type="submit" class="workeera-btn workeera-large-btn"><?php echo esc_html_x( 'Save Details', 'Employer Profile', 'workeera-remote-tech-job-board' ); ?></button>
                        <div class="workeera-form-actions-con workeera-hide"></div>
                        <input type="hidden" name="action" value="workeera_employer_profile_details_submission_call">
                    </div>
                </form>

            </div>
        </div>
        <?php
    }

    /**
     * Sanitize and tidy resume-style HTML content using a limited allowed tag set.
     *
     * @param  string $description Raw detail content.
     * @return string Sanitized HTML content.
     */
    public static function optimize_content($description) {
        $description = apply_filters('the_content', $description);
        $description = str_replace('&nbsp;', ' ', $description);
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

        $description = wp_kses($description, $tags_list);

        $description = preg_replace('/\<p><\/p>/', ' ', $description);
        return trim(workeera_strip_slashes($description));
    }

    /**
     * AJAX handler that saves the employer profile to user meta.
     */
    public function profile_details_submission() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        if ( ! self::is_employer( $user_id ) ) {
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

        $employer_title = $given_name . ' ' . $family_name;

        // Update the user account.
        $user_data = [
            'ID'            => $user_id,
            'first_name'    => $given_name,
            'last_name'     => $family_name,
            'display_name'  => $employer_title,
        ];
        wp_update_user($user_data);

        // Personal-detail meta fields (mirrors the candidate `workeera_meta` keys,
        // but persisted as user meta since employers have no profile post).
        $meta_in = isset( $_POST['workeera_meta'] ) && is_array( $_POST['workeera_meta'] ) ? wp_unslash( $_POST['workeera_meta'] ) : [];

        $designation   = isset( $meta_in['designation'] ) ? sanitize_text_field( $meta_in['designation'] ) : '';
        $email_address = isset( $meta_in['email_address'] ) ? sanitize_email( $meta_in['email_address'] ) : '';
        $phone_number = isset( $meta_in['phone_number'] ) ? sanitize_text_field( $meta_in['phone_number'] ) : '';
        $website_url  = isset( $meta_in['website_url'] ) ? esc_url_raw( $meta_in['website_url'] ) : '';
        $linkedin_url = isset( $meta_in['linkedin_url'] ) ? esc_url_raw( $meta_in['linkedin_url'] ) : '';

        workeera_update_object_meta( $user_id, 'designation', $designation, 'user' );
        workeera_update_object_meta( $user_id, 'email_address', $email_address, 'user' );
        workeera_update_object_meta( $user_id, 'phone_number', $phone_number, 'user' );
        workeera_update_object_meta( $user_id, 'website_url', $website_url, 'user' );
        workeera_update_object_meta( $user_id, 'linkedin_url', $linkedin_url, 'user' );

        // Short description — strip tags to count plain-text length, then keep a
        // limited set of safe formatting tags (matches the candidate allowlist).
        $description_raw = isset( $_POST['short_description'] ) ? wp_unslash( $_POST['short_description'] ) : '';
        $description_plain = wp_strip_all_tags( $description_raw );

        if ( mb_strlen( $description_plain ) > 2000 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Description must not exceed 2000 characters.', 'workeera-remote-tech-job-board' ) ] );
        }

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
        $short_description = wp_kses( $description_raw, $allowed_desc_tags );

        workeera_update_object_meta( $user_id, 'short_description', $short_description, 'user' );

        wp_send_json( [
            'error' => '0',
            'msg' => esc_html__( 'Details saved successfully.', 'workeera-remote-tech-job-board' ),
        ] );
    }

    /**
     * AJAX handler for uploading the employer's profile picture.
     */
    public function upload_profile_picture() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        if ( ! self::is_employer( $user_id ) ) {
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

            workeera_update_object_meta($user_id, 'user_profile_picture', $uploaded_file, 'user');

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
     * AJAX handler for removing the employer's profile picture.
     */
    public function remove_profile_picture() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();

        if ( ! self::is_employer( $user_id ) ) {
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

new Workeera_Employer_Profile;
