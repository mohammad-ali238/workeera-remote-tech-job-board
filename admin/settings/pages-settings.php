<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the Pages Settings tab on the Workeera options page.
 */
class Workeera_Options_Pages_Settings {

    private $options_name = 'workeera_pages_settings';

    /**
     * Hook the settings loader and option registration into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'load_settings'], 30);

        add_action('admin_menu', [$this, 'register_settings']);
    }

    /**
     * Register the pages-settings option group with WordPress.
     */
    public function register_settings() {
        register_setting(
            'workeera_pages_options_group', // Option group
            $this->options_name, // Option name
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Per-field sanitization schema for the pages-settings option group.
     *
     * All three fields store a WordPress page ID, so each is run through absint.
     *
     * @return array field_name => rule map.
     */
    private function sanitization_schema() {
        return array(
            'user_dashboard_page' => array( 'type' => 'int' ),
            'jobs_page'           => array( 'type' => 'int' ),
            'terms_page'          => array( 'type' => 'int' ),
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
     * Build the pages-settings field list.
     *
     * @return array Field config keyed by setting ID.
     */
    private function settings_list() {

        $options_key_name = $this->options_name;
        $options_data = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $list = [];

        $select_options = [];
        $user_dashboard_page = isset($options_data['user_dashboard_page']) ? $options_data['user_dashboard_page'] : '';
        if (!empty($user_dashboard_page) && get_post_type($user_dashboard_page) == 'page') {
            $select_options[$user_dashboard_page] = get_the_title($user_dashboard_page);
        }
        $list['user-dashboard-page'] = [
            'title' => esc_html__('Dashboard Page', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'user-dashboard-page',
                'field_name' => 'user_dashboard_page',
                'classes' => ['workeera-page-selecter'],
                'extra_attributes' => ['data-placeholder' => esc_html__('Select page', 'workeera-remote-tech-job-board')],
                'options' => $select_options,
                'default' => '',
                'info' => esc_html__('Select user dashboard page and add [workeera_user_dashboard] shortcode.', 'workeera-remote-tech-job-board'),
            )
        ];

        $select_options = [];
        $jobs_page = isset($options_data['jobs_page']) ? $options_data['jobs_page'] : '';
        if (!empty($jobs_page) && get_post_type($jobs_page) == 'page') {
            $select_options[$jobs_page] = get_the_title($jobs_page);
        }
        $list['jobs-page'] = [
            'title' => esc_html__('Jobs Page', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'jobs-page',
                'field_name' => 'jobs_page',
                'classes' => ['workeera-page-selecter'],
                'extra_attributes' => ['data-placeholder' => esc_html__('Select page', 'workeera-remote-tech-job-board')],
                'options' => $select_options,
                'default' => '',
                'info' => esc_html__('Select jobs listing page and add [workeera_jobs] shortcode.', 'workeera-remote-tech-job-board'),
            )
        ];

        $select_options = [];
        $terms_page = isset($options_data['terms_page']) ? $options_data['terms_page'] : '';
        if (!empty($terms_page) && get_post_type($terms_page) == 'page') {
            $select_options[$terms_page] = get_the_title($terms_page);
        }
        $list['terms-page'] = [
            'title' => esc_html__('Terms and Conditions Page', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'terms-page',
                'field_name' => 'terms_page',
                'classes' => ['workeera-page-selecter'],
                'extra_attributes' => ['data-placeholder' => esc_html__('Select page', 'workeera-remote-tech-job-board')],
                'options' => $select_options,
                'default' => '',
                'info' => esc_html__('Select terms and conditions page.', 'workeera-remote-tech-job-board'),
            )
        ];

        return apply_filters('workeera_admin_pages_settings_list', $list, $field_functions, $options_key_name, $options_data);
    }

    /**
     * Register settings section and fields with the WordPress Settings API.
     */
    public function load_settings() {

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');

        $settings_list = $this->settings_list();

        add_settings_section(
            'workeera_admin_settings_section_pages', // ID
            '', // Title
            [$this, 'settings_section_callback'], // Callback
            'workeera_pages_options_page', // Page
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
                'workeera_pages_options_page', // Page
                'workeera_admin_settings_section_pages', // Section
                $setting_data['args']
            );
        }
    }

    /**
     * Render the section header above the pages settings fields.
     *
     * @param array $args Section args passed by WordPress.
     */
    public function settings_section_callback($args) {
        ?>
        <h3><?php echo esc_html($args['section_title']) ?><?php esc_html_e('Pages Settings', 'workeera-remote-tech-job-board') ?></h3>
        <?php
    }

}

new Workeera_Options_Pages_Settings;