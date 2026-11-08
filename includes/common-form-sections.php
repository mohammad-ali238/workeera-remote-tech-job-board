<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Reusable form sections shared between job-posting and similar plugin forms.
 */
class Workeera_Form_Parts {

    /**
     * Render the country / city / address / location-type fieldset.
     *
     * @param int    $post_id    Owning post ID (0 for new posts).
     * @param string $random_num Unique suffix used in field IDs.
     * @param string $place      Context slug (e.g. 'job_post').
     */
    public static function location($post_id, $random_num, $place = 'job_post') {
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
        // so country / city visibility on the front-end form matches site policy.
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
        <div class="<?php echo esc_attr($location_filter_type == 'countries_cities') ? 'workeera-usrform-fields-row' : 'workeera-grid-one-col' ?>">
            <?php if ( $show_country ) : ?>
                <div class="workeera-usrform-field-group">
                    <label for="location-country-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Country', 'Locations Field', 'workeera-remote-tech-job-board' ); ?></label>
                    <div class="workeera-location-country-con">
                        <?php
                        Workeera_Form_Fields::select( [
                            'id'               => 'location-country-' . $random_num,
                            'name'             => 'workeera_location_fields[country]',
                            'options'          => $countries_list,
                            'classes'          => [ 'workeera-usrform-field-select', 'workeera-select2-dropdown', 'workeera-select2-wsearch' ],
                            'value'            => $saved_country_id,
                            'extra_attributes' => [
                                'data-placeholder' => esc_attr_x( 'Country', 'Locations Field', 'workeera-remote-tech-job-board' ),
                            ],
                            'default'          => '',
                        ] );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ( $show_city ) : ?>
                <div class="workeera-usrform-field-group">
                    <label for="location-city-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'City', 'Locations Field', 'workeera-remote-tech-job-board' ); ?></label>
                    <div class="workeera-location-city-con">
                        <?php
                        Workeera_Form_Fields::select( [
                            'id'               => 'location-city-' . $random_num,
                            'name'             => 'workeera_location_fields[city]',
                            'options'          => $city_options,
                            'classes'          => [ 'workeera-usrform-field-select', 'workeera-location-city-select2' ],
                            'value'            => $saved_city_id,
                            'extra_attributes' => [
                                'data-placeholder' => esc_attr_x( 'City', 'Locations Field', 'workeera-remote-tech-job-board' ),
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
            <div class="workeera-usrform-field-group">
                <label for="street-address-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Street address', 'Locations Field', 'workeera-remote-tech-job-board' ); ?></label>
                <div class="workeera-location-streetaddr-con">
                    <?php
                    Workeera_Form_Fields::input( [
                        'id'               => 'street-address-' . $random_num,
                        'name'             => 'workeera_location_fields[street_address]',
                        'value'            => workeera_strip_slashes($saved_street),
                        'classes'          => ['workeera-usrform-field-input'],
                        'extra_attributes' => [
                            'placeholder' => esc_attr_x( '47 W 13th St, Pennsylvania Avenue, Washington', 'Locations Field', 'workeera-remote-tech-job-board' ),
                        ],
                        'default'          => '',
                    ] );
                    ?>
                </div>
            </div>
            <div class="workeera-usrform-field-group">
                <label for="zip-code-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'ZIP code', 'Locations Field', 'workeera-remote-tech-job-board' ); ?></label>
                <div class="workeera-location-zipcode-con">
                    <?php
                    Workeera_Form_Fields::input( [
                        'id'               => 'zip-code-' . $random_num,
                        'name'             => 'workeera_location_fields[zip_code]',
                        'value'            => $saved_zip,
                        'classes'          => ['workeera-usrform-field-input'],
                        'extra_attributes' => [
                            'placeholder' => esc_attr_x( '12001', 'Locations Field', 'workeera-remote-tech-job-board' ),
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
                'id' => 'location-type-' . $random_num,
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
                <div class="workeera-usrform-field-group workeera-group-extended-gap">
                    <label for="location-type-<?php echo absint($random_num) ?>" class="workeera-usrform-field-label"><?php echo esc_html_x( 'Location Type', 'Locations Field', 'workeera-remote-tech-job-board' ); ?></label>
                    <?php
                    if (!empty($location_types)) {
                        ?>
                        <div class="workeera-grid-optbtns-main workeera-location-type-con">
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
    }
}