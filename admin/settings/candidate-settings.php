<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the Candidate Settings tab on the Workeera options page.
 */
class Workeera_Options_Candidate_Settings {

    private $options_name = 'workeera_candidate_settings';

    /**
     * Hook the settings loader and option registration into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'load_settings'], 30);

        add_action('admin_menu', [$this, 'register_settings']);
    }

    /**
     * Register the candidate-settings option group with WordPress.
     *
     * "candidates" SECTION of the Candidates tab
     * (workeera_{tab}_{section}_options_group naming). The option NAME is
     * unchanged, so previously saved settings carry over untouched.
     */
    public function register_settings() {
        register_setting(
            'workeera_candidate_candidates_options_group', // Option group
            $this->options_name, // Option name
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Per-field sanitization schema for the candidate-settings option group.
     *
     * @return array field_name => rule map.
     */
    private function sanitization_schema() {
        return array(
            'default_status'       => array( 'type' => 'choice', 'allowed' => array( 'approved', 'pending' ), 'default' => 'approved' ),
            'max_job_applications' => array( 'type' => 'int' ),
            'max_cv_files'         => array( 'type' => 'int' ),
            'cv_file_types'        => array( 'type' => 'choices', 'allowed' => array( 'pdf', 'doc', 'docx', 'jpg|jpeg|jpe', 'png', 'txt' ) ),
            'max_cv_file_size'     => array( 'type' => 'choice', 'allowed' => array( '2097152', '5242880', '10485760', '20971520', '52428800' ), 'default' => '5242880' ),
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
     * Build the candidate-settings field list.
     *
     * @return array Field config keyed by setting ID.
     */
    private function settings_list() {

        $options_key_name = $this->options_name;
        $options_data = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $list = [];
        
        $list['default-status'] = [
            'title' => esc_html__('Candidate Default Status', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'default-status',
                'field_name' => 'default_status',
                'options' => [
                    'approved' => esc_html__('Approved', 'workeera-remote-tech-job-board'),
                    'pending' => esc_html__('Pending Review', 'workeera-remote-tech-job-board'),
                ],
                'default' => 'approved',
                'info' => esc_html__('Set default status for new candidates.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['max-job-applications'] = [
            'title' => esc_html__('Max job applications', 'workeera-remote-tech-job-board'),
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
                'label_for' => 'max-job-applications',
                'field_name' => 'max_job_applications',
                'default' => '20',
                'info' => esc_html__('Set how many jobs a user can apply per day. Set 0 for unlimited.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['max-cv-files'] = [
            'title' => esc_html__('Max CV file uploads', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'input_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'input_type' => 'number',
                'extra_attributes' => [
                    'placeholder' => '3',
                    'min'         => '1',
                    'step'        => '1',
                ],
                'label_for' => 'max-cv-files',
                'field_name' => 'max_cv_files',
                'default' => '3',
                'info' => esc_html__('Set the maximum number of CV files a candidate can upload to their profile.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['cv-file-types'] = [
            'title' => esc_html__('Allowed CV file types', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'option_buttons_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'cv-file-types',
                'field_name' => 'cv_file_types',
                'multiple' => true,
                'options' => [
                    'pdf' => esc_html__('PDF', 'workeera-remote-tech-job-board'),
                    'doc' => esc_html__('DOC', 'workeera-remote-tech-job-board'),
                    'docx' => esc_html__('DOCX', 'workeera-remote-tech-job-board'),
                    'jpg|jpeg|jpe' => esc_html__('Image/Jpeg', 'workeera-remote-tech-job-board'),
                    'png' => esc_html__('Image/Png', 'workeera-remote-tech-job-board'),
                    'txt' => esc_html__('TXT', 'workeera-remote-tech-job-board'),
                ],
                'default' => ['pdf', 'docx'],
                'info' => esc_html__('Select the file types that candidates are allowed to upload for their CV.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['max-cv-file-size'] = [
            'title' => esc_html__('Max CV file size', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'max-cv-file-size',
                'field_name' => 'max_cv_file_size',
                'options' => [
                    '2097152' => esc_html__('2 MB', 'workeera-remote-tech-job-board'),
                    '5242880' => esc_html__('5 MB', 'workeera-remote-tech-job-board'),
                    '10485760' => esc_html__('10 MB', 'workeera-remote-tech-job-board'),
                    '20971520' => esc_html__('20 MB', 'workeera-remote-tech-job-board'),
                    '52428800' => esc_html__('50 MB', 'workeera-remote-tech-job-board'),
                ],
                'default' => '5242880',
                'info' => esc_html__('Set the maximum file size allowed for CV uploads. Larger files may take longer to upload.', 'workeera-remote-tech-job-board'),
            )
        ];

        return apply_filters('workeera_admin_candidate_settings_list', $list, $field_functions, $options_key_name, $options_data);
    }

    /**
     * Register settings section and fields with the WordPress Settings API.
     */
    public function load_settings() {

        $settings_list = $this->settings_list();

        add_settings_section(
            'workeera_admin_settings_section_candidate', // ID
            '', // Title
            [$this, 'settings_section_callback'], // Callback
            'workeera_candidate_candidates_options_page', // Page (Candidates sub-menu)
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
                'workeera_candidate_candidates_options_page', // Page (Candidates sub-menu)
                'workeera_admin_settings_section_candidate', // Section
                $setting_data['args']
            );
        }
    }

    /**
     * Render the section header above the candidate settings fields.
     *
     * @param array $args Section args passed by WordPress.
     */
    public function settings_section_callback($args) {
        ?>
        <h3><?php echo esc_html($args['section_title']) ?><?php esc_html_e('Candidate Settings', 'workeera-remote-tech-job-board') ?></h3>
        <?php
    }

}

new Workeera_Options_Candidate_Settings;