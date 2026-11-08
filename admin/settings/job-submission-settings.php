<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the Job Submission Settings tab on the Workeera options page.
 */
class Workeera_Options_Job_Submission_Settings {

    private $options_name = 'workeera_job_submission_settings';

    /**
     * Hook the settings loader and option registration into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'load_settings'], 30);

        add_action('admin_menu', [$this, 'register_settings']);
    }

    /**
     * Register the job-submission-settings option group with WordPress.
     *
     * Registered under the "jobs" SECTION of the Job Submission tab
     * (workeera_{tab}_{section}_options_group naming) — the tab now has a
     * sub-menu so addons can append their own sections to it.
     */
    public function register_settings() {
        register_setting(
            'workeera_job_submission_jobs_options_group', // Option group (Jobs sub-menu)
            $this->options_name, // Option name
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Per-field sanitization schema for the job-submission-settings option group.
     *
     * @return array field_name => rule map.
     */
    private function sanitization_schema() {
        return array(
            'listing_duration'      => array( 'type' => 'int' ),
            'allow_published_edits' => array( 'type' => 'choice', 'allowed' => array( 'no', 'yes', 'require_approval' ), 'default' => 'no' ),
            'allow_location_type'   => array( 'type' => 'switch' ),
            'default_location_type' => array( 'type' => 'choice', 'allowed' => array_keys( workeera_job_location_types() ), 'default' => '' ),
            'max_jobs_submission'   => array( 'type' => 'int' ),
            'job_description_max_len' => array( 'type' => 'int' ),
            'job_apply_method'      => array( 'type' => 'choice', 'allowed' => array( 'internal', 'external', 'both' ), 'default' => 'internal' ),
        );
    }

    /**
     * Sanitize callback for register_setting() — validates each field per schema.
     *
     * @param  mixed $value Raw option array from the Settings API.
     * @return array        Sanitised option array.
     */
    public function sanitize_settings( $value ) {
        return Field_Functions::sanitize_with_schema( $value, $this->sanitization_schema() );
    }

    /**
     * Build the job-submission-settings field list.
     *
     * @return array Field config keyed by setting ID.
     */
    private function settings_list() {

        $options_key_name = $this->options_name;
        $options_data = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $list = [];

        $list['listing-duration'] = [
            'title' => esc_html__('Listing Duration', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'input_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'input_type' => 'number',
                'extra_attributes' => [
                    'placeholder' => '30',
                    'min'         => '1',
                    'step'        => '1',
                ],
                'label_for' => 'listing-duration',
                'field_name' => 'listing_duration',
                'default' => '30',
                'info' => esc_html__('Job expiry date will set according to this number of days.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['allow-published-edits'] = [
            'title' => esc_html__('Allow Published Edits', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'allow-published-edits',
                'field_name' => 'allow_published_edits',
                'options' => [
                    'no' => esc_html__('Users cannot edit', 'workeera-remote-tech-job-board'),
                    'yes' => esc_html__('Can edit without admin approval', 'workeera-remote-tech-job-board'),
                    'require_approval' => esc_html__('Can edit, but edits require admin approval', 'workeera-remote-tech-job-board'),
                ],
                'default' => 'yes',
                'info' => esc_html__('Choose whether published job listings can be edited and/or if edits require admin approval.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['allow-location-type'] = [
            'title' => esc_html__('Allow Location Type', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'switch_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'allow-location-type',
                'field_name' => 'allow_location_type',
                'default' => 'on',
                'info' => esc_html__('Allow location type selection.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['default-location-type'] = [
            'title' => esc_html__('Default Location Type', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'default-location-type',
                'field_name' => 'default_location_type',
                'options' => workeera_job_location_types(),
                'default' => 'remote',
                'info' => '',
            )
        ];

        $list['max-jobs-submission'] = [
            'title' => esc_html__('Max job submissions', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'input_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'input_type' => 'number',
                'extra_attributes' => [
                    'placeholder' => '20',
                    'min'         => '1',
                    'step'        => '1',
                ],
                'label_for' => 'max-jobs-submission',
                'field_name' => 'max_jobs_submission',
                'default' => '20',
                'info' => esc_html__('Set how many jobs a user can post per day. Set 0 for unlimited.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['job-description-max-len'] = [
            'title' => esc_html__('Job Description Max Length', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'input_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'input_type' => 'number',
                'extra_attributes' => [
                    'placeholder' => '5000',
                    'min'         => '1',
                    'step'        => '1',
                ],
                'label_for' => 'job-description-max-len',
                'field_name' => 'job_description_max_len',
                'default' => '5000',
                'info' => esc_html__('Maximum number of characters allowed in the job description field on the job submission form.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['job-apply-method'] = [
            'title' => esc_html__('Job Application Type', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'job-apply-method',
                'field_name' => 'job_apply_method',
                'options' => [
                    'internal' => esc_html__('Internal', 'workeera-remote-tech-job-board'),
                    'external' => esc_html__('External', 'workeera-remote-tech-job-board'),
                    'both' => esc_html__('Both', 'workeera-remote-tech-job-board'),
                ],
                'default' => 'internal',
                'info' => esc_html__('Set apply job method. Internal system applications, external site URL, OR Both.', 'workeera-remote-tech-job-board'),
            )
        ];

        return apply_filters('workeera_admin_job_submission_settings_list', $list, $field_functions, $options_key_name, $options_data);
    }

    /**
     * Register settings section and fields with the WordPress Settings API.
     */
    public function load_settings() {

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');

        $settings_list = $this->settings_list();

        add_settings_section(
            'workeera_admin_settings_section_job_submission', // ID
            '', // Title
            [$this, 'settings_section_callback'], // Callback
            'workeera_job_submission_jobs_options_page', // Page (Jobs sub-menu)
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
                'workeera_job_submission_jobs_options_page', // Page (Jobs sub-menu)
                'workeera_admin_settings_section_job_submission', // Section
                $setting_data['args']
            );
        }
    }

    /**
     * Render the section header above the job-submission settings fields.
     *
     * @param array $args Section args passed by WordPress.
     */
    public function settings_section_callback($args) {
        ?>
        <h3><?php echo esc_html($args['section_title']) ?><?php esc_html_e('Job Submission Settings', 'workeera-remote-tech-job-board') ?></h3>
        <?php
    }

}

new Workeera_Options_Job_Submission_Settings;