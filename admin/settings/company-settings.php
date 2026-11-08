<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Workeera_Options\Field_Functions;

/**
 * Registers the "Companies" tab (single "Company Settings" sub-section) on the
 * Workeera options page. Controls how many companies a user may manage and
 * which optional company fields are shown in the front-end forms.
 *
 * Option: workeera_company_settings.
 */
class Workeera_Options_Company_Settings {

	private $options_name = 'workeera_company_settings';

	/**
	 * Hook the settings loader and option registration into admin_menu.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'load_settings' ], 30 );
		add_action( 'admin_menu', [ $this, 'register_settings' ] );
	}

	/**
	 * Register the option under the tab's sub-section group.
	 */
	public function register_settings() {
		register_setting(
			'workeera_company_company_settings_options_group',
			$this->options_name,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Per-field sanitization schema.
	 *
	 * @return array
	 */
	private function sanitization_schema() {
		return array(
			'max_companies'        => array( 'type' => 'int' ),
			'tagline_field'        => array( 'type' => 'switch' ),
			'employee_count_field' => array( 'type' => 'switch' ),
			'linkedin_field'       => array( 'type' => 'switch' ),
		);
	}

	/**
	 * Sanitize callback — merge the submitted subset over the stored option.
	 *
	 * @param  mixed $value Raw option array.
	 * @return array
	 */
	public function sanitize_settings( $value ) {
		$clean    = Field_Functions::sanitize_with_schema( $value, $this->sanitization_schema() );
		$existing = get_option( $this->options_name );
		$existing = is_array( $existing ) ? $existing : array();

		return array_merge( $existing, $clean );
	}

	/**
	 * Field configuration for the Company Settings section.
	 *
	 * @return array
	 */
	private function fields_list() {
		$options_key_name = $this->options_name;
		$options_data     = get_option( $options_key_name );

		$field_functions = new Field_Functions();

		$list = array();

		$list['max-companies'] = array(
			'title'    => esc_html__( 'Max companies per user', 'workeera-remote-tech-job-board' ),
			'callback' => array( $field_functions, 'input_field_callback' ),
			'args'     => array(
				'options_name'     => $options_key_name,
				'options_data'     => $options_data,
				'label_for'        => 'max-companies',
				'field_name'       => 'max_companies',
				'input_type'       => 'number',
				'extra_attributes' => array( 'min' => 1, 'step' => 1 ),
				'default'          => 20,
				'info'             => esc_html__( 'How many companies a user can create or manage. Default: 20.', 'workeera-remote-tech-job-board' ),
			),
		);

		$toggles = array(
			'tagline_field'        => esc_html__( 'Tagline field', 'workeera-remote-tech-job-board' ),
			'employee_count_field' => esc_html__( 'Number of employees field', 'workeera-remote-tech-job-board' ),
			'linkedin_field'       => esc_html__( 'LinkedIn field', 'workeera-remote-tech-job-board' ),
		);

		foreach ( $toggles as $field_name => $title ) {
			$list[ str_replace( '_', '-', $field_name ) ] = array(
				'title'    => $title,
				'callback' => array( $field_functions, 'switch_field_callback' ),
				'args'     => array(
					'options_name' => $options_key_name,
					'options_data' => $options_data,
					'label_for'    => str_replace( '_', '-', $field_name ),
					'field_name'   => $field_name,
					'default'      => 'on',
					'info'         => esc_html__( 'Show this field in the company forms (job posting + My Companies).', 'workeera-remote-tech-job-board' ),
				),
			);
		}

		return apply_filters( 'workeera_admin_company_settings_list', $list, $field_functions, $options_key_name, $options_data );
	}

	/**
	 * Register the settings section and its fields.
	 */
	public function load_settings() {
		$page_id    = 'workeera_company_company_settings_options_page';
		$section_id = 'workeera_admin_settings_section_company_company_settings';

		add_settings_section(
			$section_id,
			'',
			function () {
				$this->settings_section_callback( esc_html__( 'Company Settings', 'workeera-remote-tech-job-board' ) );
			},
			$page_id,
			array(
				'before_section' => '<div class="workeera-settingssec-holder">',
				'after_section'  => '</div>',
				'section_title'  => '',
			)
		);

		foreach ( $this->fields_list() as $setting_itm_id => $setting_data ) {
			add_settings_field(
				$setting_itm_id,
				$setting_data['title'],
				$setting_data['callback'],
				$page_id,
				$section_id,
				$setting_data['args']
			);
		}
	}

	/**
	 * Render the section header.
	 *
	 * @param string $title Heading to display.
	 */
	public function settings_section_callback( $title ) {
		?>
		<h3><?php echo esc_html( $title ); ?></h3>
		<?php
	}
}

new Workeera_Options_Company_Settings;
