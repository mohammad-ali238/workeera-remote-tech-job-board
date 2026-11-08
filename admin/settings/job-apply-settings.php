<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the Job Applications settings — the "Job Applications" sub-menu
 * of the Job Listings options tab. Controls which apply-form fields are
 * shown and whether they are required.
 */
class Workeera_Options_Job_Apply_Settings {

    private $options_name = 'workeera_job_apply_settings';

    /**
     * Hook the settings loader and option registration into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'load_settings'], 30);

        add_action('admin_menu', [$this, 'register_settings']);
    }

    /**
     * Register the job-apply-settings option group with WordPress.
     */
    public function register_settings() {
        // "applications" SECTION of the Job Listings tab
        // (workeera_{tab}_{section}_options_group naming).
        register_setting(
            'workeera_job_listings_applications_options_group', // Option group
            $this->options_name, // Option name
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * The apply-form fields this settings group controls.
     *
     * @return array field_name => translated title.
     */
    public static function form_fields() {
        return array(
            'phone_number_field' => esc_html__( 'Phone Number', 'workeera-remote-tech-job-board' ),
            'upload_cv_field'    => esc_html__( 'Upload CV', 'workeera-remote-tech-job-board' ),
            'cover_letter_field' => esc_html__( 'Cover Letter', 'workeera-remote-tech-job-board' ),
        );
    }

    /**
     * Allowed values for each field mode dropdown.
     *
     * @return array value => translated label.
     */
    public static function field_modes() {
        return array(
            'on'          => esc_html__( 'On', 'workeera-remote-tech-job-board' ),
            'off'         => esc_html__( 'Off', 'workeera-remote-tech-job-board' ),
            'on_required' => esc_html__( 'On & Required', 'workeera-remote-tech-job-board' ),
        );
    }

    /**
     * Sanitize the submitted settings — every field is whitelisted against
     * the fixed mode list; anything unknown falls back to 'on' (the default).
     *
     * @param  mixed $value Raw submitted option value.
     * @return array        Clean settings.
     */
    public function sanitize_settings( $value ) {
        $clean = array();
        $value = is_array( $value ) ? $value : array();
        $modes = array_keys( self::field_modes() );

        foreach ( array_keys( self::form_fields() ) as $field_name ) {
            $raw                  = isset( $value[ $field_name ] ) ? sanitize_key( wp_unslash( (string) $value[ $field_name ] ) ) : 'on';
            $clean[ $field_name ] = in_array( $raw, $modes, true ) ? $raw : 'on';
        }

        return $clean;
    }

    /**
     * Build the settings field list for the section.
     *
     * @return array Field config keyed by setting ID.
     */
    private function settings_list() {

        $options_key_name = $this->options_name;
        $options_data = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $modes = self::field_modes();

        $list = [];

        $list['phone-number-field'] = [
            'title' => esc_html__('Phone Number', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'phone-number-field',
                'field_name' => 'phone_number_field',
                'options' => $modes,
                'default' => 'on',
                'info' => esc_html__('Show the phone number field on the job apply form. When enabled, the phone also appears in the applicants lists.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['upload-cv-field'] = [
            'title' => esc_html__('Upload CV', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'upload-cv-field',
                'field_name' => 'upload_cv_field',
                'options' => $modes,
                'default' => 'on',
                'info' => esc_html__('Show the CV / Resume selection on the job apply form.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['cover-letter-field'] = [
            'title' => esc_html__('Cover Letter', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'cover-letter-field',
                'field_name' => 'cover_letter_field',
                'options' => $modes,
                'default' => 'on',
                'info' => esc_html__('Show the cover letter field on the job apply form.', 'workeera-remote-tech-job-board'),
            )
        ];

        return apply_filters('workeera_admin_job_apply_settings_list', $list, $options_data);
    }

    /**
     * Register settings section and fields with the WordPress Settings API.
     */
    public function load_settings() {

        $settings_list = $this->settings_list();

        add_settings_section(
            'workeera_admin_settings_section_job_apply', // ID
            '', // Title
            [$this, 'settings_section_callback'], // Callback
            'workeera_job_listings_applications_options_page', // Page (Job Applications sub-menu)
            array(
                'before_section' => '<div class="workeera-settingssec-holder">',
                'after_section' => '</div>',
                'section_title' => ''
            )
        );

        foreach ($settings_list as $setting_itm_id => $setting_data) {
            add_settings_field(
                $setting_itm_id, // ID
                $setting_data['title'], // Title
                $setting_data['callback'], // Callback
                'workeera_job_listings_applications_options_page', // Page (Job Applications sub-menu)
                'workeera_admin_settings_section_job_apply', // Section
                $setting_data['args']
            );
        }
    }

    /**
     * Render the section header above the job-apply settings fields.
     *
     * @param array $args Section args passed by WordPress.
     */
    public function settings_section_callback($args) {
        ?>
        <h3><?php echo esc_html($args['section_title']) ?><?php esc_html_e('Job Applications Settings', 'workeera-remote-tech-job-board') ?></h3>
        <p class="description"><?php esc_html_e('Control which fields appear on the job apply form and whether they are required.', 'workeera-remote-tech-job-board') ?></p>
        <?php
    }

}

new Workeera_Options_Job_Apply_Settings;
