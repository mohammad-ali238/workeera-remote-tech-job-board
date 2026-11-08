<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the Job Listings Settings tab on the Workeera options page.
 */
class Workeera_Options_Job_Listings_Settings {

    private $options_name = 'workeera_job_listings_settings';

    /**
     * Hook the settings loader and option registration into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'load_settings'], 30);

        add_action('admin_menu', [$this, 'register_settings']);
    }

    /**
     * Register the job-listings-settings option group with WordPress.
     */
    public function register_settings() {
        // Registered under the "jobs" SECTION of the Job Listings tab
        // (workeera_{tab}_{section}_options_group naming) — the tab now has
        // Jobs / Job Applications sub-menus. The option NAME is unchanged,
        // so existing saved settings carry over untouched.
        register_setting(
            'workeera_job_listings_jobs_options_group', // Option group
            $this->options_name, // Option name
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Per-field sanitization schema for the job-listings-settings option group.
     *
     * @return array field_name => rule map.
     */
    private function sanitization_schema() {
        return array(
            'items_per_page'         => array( 'type' => 'int' ),
            'allow_listing_filters'  => array( 'type' => 'switch' ),
            'listing_active_filters' => array( 'type' => 'sortable_switch', 'allowed' => array( 'location', 'salary', 'sectors', 'jobtypes', 'timezones', 'skills' ) ),
            'remote_jobs_filter'     => array( 'type' => 'switch' ),
            'urgent_jobs_filter'     => array( 'type' => 'switch' ),
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
     * Build the job-listings-settings field list.
     *
     * @return array Field config keyed by setting ID.
     */
    private function settings_list() {

        $options_key_name = $this->options_name;
        $options_data = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $list = [];

        $list['items-per-page'] = [
            'title' => esc_html__('Listings per page', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'input_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'input_type' => 'number',
                'extra_attributes' => [
                    'placeholder' => '1',
                    'min'         => '1',
                    'step'        => '1',
                ],
                'label_for' => 'items-per-page',
                'field_name' => 'items_per_page',
                'default' => '10',
                'info' => '',
            )
        ];

        $list['allow-listing-filters'] = [
            'title' => esc_html__('Job Filters', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'switch_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'allow-listing-filters',
                'field_name' => 'allow_listing_filters',
                'default' => 'on',
                'info' => esc_html__('Enable/Disable jobs filters.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['active-filters'] = [
            'title' => esc_html__('Active Filters', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'sortable_switch_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'active-filters',
                'field_name' => 'listing_active_filters',
                'options' => [
                    'location' => esc_html__('Location', 'workeera-remote-tech-job-board'),
                    'salary' => esc_html__('Salary', 'workeera-remote-tech-job-board'),
                    'sectors' => esc_html__('Category', 'workeera-remote-tech-job-board'),
                    'jobtypes' => esc_html__('Job types', 'workeera-remote-tech-job-board'),
                    'timezones' => esc_html__('Timezones', 'workeera-remote-tech-job-board'),
                    'skills' => esc_html__('Skills', 'workeera-remote-tech-job-board'),
                ],
                // Salary is off by default (matches the previous default set).
                'default_off' => ['salary'],
                'info' => esc_html__('Enable/disable and reorder the filters shown on the jobs listing. Drag a row to change its position and use the switch to turn it on or off — changes apply after you press Save.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['remote-jobs-filter'] = [
            'title' => esc_html__('Remote jobs filter', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'switch_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'remote-jobs-filter',
                'field_name' => 'remote_jobs_filter',
                'default' => 'on',
                'info' => '',
            )
        ];

        $list['urgent-jobs-filter'] = [
            'title' => esc_html__('Urgent Hiring filter', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'switch_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'urgent-jobs-filter',
                'field_name' => 'urgent_jobs_filter',
                'default' => 'on',
                'info' => '',
            )
        ];

        return apply_filters('workeera_admin_job_listings_settings_list', $list, $field_functions, $options_key_name, $options_data);
    }

    /**
     * Register settings section and fields with the WordPress Settings API.
     */
    public function load_settings() {

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');

        $settings_list = $this->settings_list();

        add_settings_section(
            'workeera_admin_settings_section_job_listings', // ID
            '', // Title
            [$this, 'settings_section_callback'], // Callback
            'workeera_job_listings_jobs_options_page', // Page (Jobs sub-menu)
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
                'workeera_job_listings_jobs_options_page', // Page (Jobs sub-menu)
                'workeera_admin_settings_section_job_listings', // Section
                $setting_data['args']
            );
        }
    }

    /**
     * Render the section header above the job-listings settings fields.
     *
     * @param array $args Section args passed by WordPress.
     */
    public function settings_section_callback($args) {
        ?>
        <h3><?php echo esc_html($args['section_title']) ?><?php esc_html_e('Job Listings Settings', 'workeera-remote-tech-job-board') ?></h3>
        <?php
    }

}

new Workeera_Options_Job_Listings_Settings;