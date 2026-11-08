<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the General tab on the Workeera options page.
 *
 * The tab has two sub-sections — "General settings" and "Salary" — but both
 * still read/write the SAME `workeera_general_settings` option (a lot of the
 * plugin and its addons read that single array). Because each sub-tab form
 * only submits its own fields, the sanitize callback MERGES the submitted
 * subset into the stored option so saving one sub-tab never wipes the other's.
 */
class Workeera_Options_General_Settings {

    private $options_name = 'workeera_general_settings';

    /**
     * Hook the settings loader and option registration into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'load_settings'], 30);

        add_action('admin_menu', [$this, 'register_settings']);
    }

    /**
     * Register the general-settings option under BOTH sub-section groups so
     * saving from either the "General settings" or the "Salary" sub-tab is
     * accepted by options.php for the same `workeera_general_settings` option.
     */
    public function register_settings() {
        foreach ( [ 'general_settings', 'salary' ] as $section_key ) {
            register_setting(
                'workeera_general_' . $section_key . '_options_group', // Option group (per sub-section)
                $this->options_name, // Option name (shared)
                array(
                    'sanitize_callback' => array( $this, 'sanitize_settings' ),
                )
            );
        }
    }

    /**
     * Per-field sanitization schema for the general-settings option group.
     *
     * Maps each saved field_name to a type-aware rule so numbers go through
     * absint, enums are whitelisted, switches normalise to on/off, and the
     * currency / salary-range composites are validated per sub-key.
     *
     * @return array field_name => rule map.
     */
    private function sanitization_schema() {
        $range_keys = array_keys( workeera_default_salary_ranges() );
        $schema     = array(
            'free_ai_credits'        => array( 'type' => 'switch' ),
            'location_filter_type'   => array( 'type' => 'choice', 'allowed' => array( 'countries', 'countries_cities', 'cities' ), 'default' => 'countries_cities' ),
            'cities_only_country'    => array( 'type' => 'int' ),
            'disable_employer_dashboard' => array( 'type' => 'choice', 'allowed' => array( 'no', 'yes' ), 'default' => 'no' ),
            'default_currency'      => array( 'type' => 'currency' ),
            'salary_display_format' => array( 'type' => 'choice', 'allowed' => array( 'full', 'k_notation' ), 'default' => '' ),
            'salary_unit_field'     => array( 'type' => 'switch' ),
            'salary_unit'           => array( 'type' => 'choice', 'allowed' => array_keys( workeera_salary_units() ), 'default' => 'hourly' ),
            'remove_data_on_uninstall' => array( 'type' => 'switch' ),
        );
        // hourly_salary_range, daily_salary_range, … each min/max/interval absint.
        foreach ( $range_keys as $range_key ) {
            $schema[ $range_key . '_salary_range' ] = array( 'type' => 'range' );
        }

        return $schema;
    }

    /**
     * Sanitize callback for register_setting().
     *
     * `sanitize_with_schema()` only returns the keys that were actually
     * submitted, so we merge that subset over the stored option — the fields
     * that live on the OTHER sub-tab (and any addon-injected values) are
     * preserved instead of being dropped on save.
     *
     * @param  mixed $value Raw option array from the Settings API (one sub-tab's fields).
     * @return array        Full, sanitised option array.
     */
    public function sanitize_settings( $value ) {
        $clean    = Field_Functions::sanitize_with_schema( $value, $this->sanitization_schema() );
        $existing = get_option( $this->options_name );
        $existing = is_array( $existing ) ? $existing : array();

        return array_merge( $existing, $clean );
    }

    /**
     * Fields for the "General settings" sub-tab (everything that is not
     * salary/currency related). Addon-injected fields land here.
     *
     * @return array Field config keyed by setting ID.
     */
    private function general_fields_list() {

        $options_key_name = $this->options_name;
        $options_data     = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $list = [];

        // Master switch for the Free AI Credits feature (default ON). When off,
        // the Free AI Credits submenu, the dashboard badge and the add-ons' use
        // of free credits are all suppressed.
        $free_ai_credits_val = isset($options_data['free_ai_credits']) ? $options_data['free_ai_credits'] : 'on';
        $list['free-ai-credits'] = [
            'title' => esc_html__('Free AI Credits', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'switch_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'free-ai-credits',
                'field_name' => 'free_ai_credits',
                'default' => $free_ai_credits_val,
                'info' => esc_html__('Enable the free Hiremium AI credits feature. When on, a "Free AI Credits" page appears under Workeera and the AI add-ons can use free trial credits. Turn off to hide it everywhere.', 'workeera-remote-tech-job-board'),
            )
        ];

        $loc_filter_type = isset($options_data['location_filter_type']) ? $options_data['location_filter_type'] : '';
        $list['location-filter-type'] = [
            'title' => esc_html__('Location type', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'location-filter-type',
                'field_name' => 'location_filter_type',
                'class' => 'workeera-conditional-opt-filed',
                'options' => [
                    'countries' => esc_html__('Countries only', 'workeera-remote-tech-job-board'),
                    'countries_cities' => esc_html__('Countries with Cities', 'workeera-remote-tech-job-board'),
                    'cities' => esc_html__('Cities only', 'workeera-remote-tech-job-board'),
                ],
                'default' => 'countries_cities',
                'info' => '',
            )
        ];

        $countries = Workeera_Object_Terms::countries_list();
        $countries_list = [ '' => esc_html__( 'Select Country', 'workeera-remote-tech-job-board' ) ];
        foreach ( $countries as $country ) {
            $countries_list[ $country['country_id'] ] = $country['label'];
        }
        $list['cities-only-country'] = [
            'title' => esc_html__('Select Country', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'cities-only-country',
                'field_name' => 'cities_only_country',
                'class' => 'workeera-cities-related-filed' . ($loc_filter_type != 'cities' ? ' workeera-hide' : ''),
                'classes' => ['workeera-select2-dropdown', 'workeera-select2-wsearch'],
                'extra_attributes' => ['data-placeholder' => esc_html__('Select Country', 'workeera-remote-tech-job-board')],
                'options' => $countries_list,
                'default' => '',
                'info' => esc_html__('Cities will show in filters for selected country only.', 'workeera-remote-tech-job-board'),
            )
        ];

        // When enabled, the front-end role chooser is removed and every new user
        // is treated as a candidate (job seeker) by default — see
        // Workeera_User_Dashboard::user_dashboard_init().
        $list['disable-employer-dashboard'] = [
            'title' => esc_html__('Disable Employer Dashboard', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'disable-employer-dashboard',
                'field_name' => 'disable_employer_dashboard',
                'options' => [
                    'no'  => esc_html__('No', 'workeera-remote-tech-job-board'),
                    'yes' => esc_html__('Yes, disable', 'workeera-remote-tech-job-board'),
                ],
                'default' => 'no',
                'info' => esc_html__('When enabled, the "Job Seeker or Company" role chooser is removed and every new user is registered as a job seeker (candidate) by default. Existing accounts keep their current role.', 'workeera-remote-tech-job-board'),
            )
        ];

        // Lifecycle: when the site owner deletes the plugin from the wp-admin
        // Plugins screen, uninstall.php reads this flag and decides whether to
        // drop all custom tables + posts + uploaded CV files. Off by default
        // so an accidental delete-and-reinstall doesn't wipe years of data.
        $list['remove-data-on-uninstall'] = [
            'title'    => esc_html__('Remove all data on uninstall', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'switch_field_callback'],
            'args'     => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for'    => 'remove-data-on-uninstall',
                'field_name'   => 'remove_data_on_uninstall',
                'default'      => 'off',
                'info'         => esc_html__(
                    'When enabled, deleting Workeera from the Plugins screen will permanently drop all job listings, candidates, applications, taxonomy terms, uploaded CVs, and plugin settings. Leave OFF if you might reinstall the plugin later.',
                    'workeera-remote-tech-job-board'
                ),
            ),
        ];

        return apply_filters('workeera_admin_general_settings_list', $list, $field_functions, $options_key_name, $options_data);
    }

    /**
     * Fields for the "Salary" sub-tab (currency, salary display + unit, and the
     * per-unit salary ranges).
     *
     * @return array Field config keyed by setting ID.
     */
    private function salary_fields_list() {

        $options_key_name = $this->options_name;
        $options_data     = get_option($options_key_name);

        $field_functions = new Field_Functions;

        $list = [];

        $list['default-currency'] = [
            'title' => esc_html__('Default Currency', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'currency_fields_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'field_name' => 'default_currency',
            )
        ];

        $list['salary-display-format'] = [
            'title' => esc_html__('Salary Thousands Abbreviation', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'salary-display-format',
                'field_name' => 'salary_display_format',
                'options' => [
                    'full' => esc_html__('Full digits', 'workeera-remote-tech-job-board'),
                    'k_notation' => esc_html__('Abbreviated (k notation)', 'workeera-remote-tech-job-board'),
                ],
                'default' => '',
                'info' => '',
            )
        ];

        // Whether job authors may pick a salary unit per job (in the submission
        // form and listing filters). When off, the Default Salary Unit below is
        // used everywhere. Read across the plugin via
        // $workeera_plugin_settings['general_settings']['salary_unit_field'].
        $list['salary-unit-field'] = [
            'title' => esc_html__('Salary Unit', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'salary-unit-field',
                'field_name' => 'salary_unit_field',
                'options' => [
                    'off' => esc_html__('Not allowed', 'workeera-remote-tech-job-board'),
                    'on' => esc_html__('Allowed', 'workeera-remote-tech-job-board'),
                ],
                'default' => '',
                'info' => esc_html__('Allow to choose salary unit type in submission form and filters.', 'workeera-remote-tech-job-board'),
            )
        ];

        $list['salary-unit'] = [
            'title' => esc_html__('Default Salary Unit', 'workeera-remote-tech-job-board'),
            'callback' => [$field_functions, 'select_field_callback'],
            'args' => array(
                'options_name' => $options_key_name,
                'options_data' => $options_data,
                'label_for' => 'salary-unit',
                'field_name' => 'salary_unit',
                'options' => workeera_salary_units(),
                'default' => 'hourly',
                'info' => '',
            )
        ];

        $salary_ranges = workeera_default_salary_ranges();

        foreach ($salary_ranges as $key => $range) {
            $field_name = $key . '_salary_range';
            $list[$key . '-salary-range'] = [
                /* translators: %s: salary range title (e.g. Hourly, Monthly) */
                'title' => sprintf(esc_html__('%s Salary range', 'workeera-remote-tech-job-board'), esc_html($range['title'])),
                'callback' => [$field_functions, 'range_fields_callback'],
                'args' => array(
                    'options_name' => $options_key_name,
                    'options_data' => $options_data,
                    'label_for' => $key . '-salary-range',
                    'field_name' => $field_name,
                    'default_vals' => $range['default'],
                    'info' => '',
                )
            ];
        }

        return apply_filters('workeera_admin_general_salary_settings_list', $list, $field_functions, $options_key_name, $options_data);
    }

    /**
     * Register both sub-section settings groups and their fields with the
     * WordPress Settings API.
     */
    public function load_settings() {

        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');

        $sections = [
            'general_settings' => [
                'title'  => esc_html__('General Settings', 'workeera-remote-tech-job-board'),
                'fields' => $this->general_fields_list(),
            ],
            'salary' => [
                'title'  => esc_html__('Salary', 'workeera-remote-tech-job-board'),
                'fields' => $this->salary_fields_list(),
            ],
        ];

        foreach ($sections as $section_key => $section) {
            $page_id    = 'workeera_general_' . $section_key . '_options_page';
            $section_id = 'workeera_admin_settings_section_general_' . $section_key;

            add_settings_section(
                $section_id, // ID
                '', // Title
                function () use ( $section ) {
                    $this->settings_section_callback( $section['title'] );
                },
                $page_id, // Page
                array(
                    'before_section' => '<div class="workeera-settingssec-holder">',
                    'after_section'  => '</div>',
                    'section_title'  => '',
                )
            );

            foreach ($section['fields'] as $setting_itm_id => $setting_data) {
                add_settings_field(
                    $setting_itm_id, // ID
                    $setting_data['title'], // Title
                    $setting_data['callback'], // Callback
                    $page_id, // Page
                    $section_id, // Section
                    $setting_data['args']
                );
            }
        }
    }

    /**
     * Render the section header above a sub-section's fields.
     *
     * @param string $title Heading to display.
     */
    public function settings_section_callback($title) {
        ?>
        <h3><?php echo esc_html($title) ?></h3>
        <?php
    }

}

new Workeera_Options_General_Settings;
