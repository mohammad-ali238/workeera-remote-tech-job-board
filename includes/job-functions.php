<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Localized list of employee-count buckets used by company profiles.
 *
 * @return array Map of slug to translated label.
 */
function workeera_company_employee_count_fields() {
    return [
        '1-10' => esc_html_x( '1-10', 'Employee count', 'workeera-remote-tech-job-board' ),
        '11-50' => esc_html_x( '11-50', 'Employee count', 'workeera-remote-tech-job-board' ),
        '51-200' => esc_html_x( '51-200', 'Employee count', 'workeera-remote-tech-job-board' ),
        '201-500' => esc_html_x( '201-500', 'Employee count', 'workeera-remote-tech-job-board' ),
        '501-1000' => esc_html_x( '501-1000', 'Employee count', 'workeera-remote-tech-job-board' ),
        '1001-5000' => esc_html_x( '1001-5000', 'Employee count', 'workeera-remote-tech-job-board' ),
        '5001-10000' => esc_html_x( '5001-10000', 'Employee count', 'workeera-remote-tech-job-board' ),
        '10000+' => esc_html_x( '10000+', 'Employee count', 'workeera-remote-tech-job-board' ),
    ];
}

/**
 * Maximum allowed plain-text length for the job description field.
 *
 * Reads the "Job Description Max Length" value from the Job Submission
 * settings; falls back to 5000 when unset. Filterable via
 * `workeera_job_description_max_len`.
 *
 * @return int Max characters allowed in the job description.
 */
function workeera_job_description_max_len() {
    global $workeera_plugin_settings;

    $settings = isset( $workeera_plugin_settings['job_submission_settings'] )
        ? $workeera_plugin_settings['job_submission_settings']
        : get_option( 'workeera_job_submission_settings' );

    $max_len = isset( $settings['job_description_max_len'] ) ? absint( $settings['job_description_max_len'] ) : 0;

    if ( $max_len < 1 ) {
        $max_len = 5000;
    }

    return absint( apply_filters( 'workeera_job_description_max_len', $max_len ) );
}

/**
 * Localized list of job location types.
 *
 * @return array Map of slug to translated label.
 */
function workeera_job_location_types() {
    return [
        'onsite' => esc_html_x( 'On-Site', 'Location type', 'workeera-remote-tech-job-board' ),
        'remote' => esc_html_x( 'Remote', 'Location type', 'workeera-remote-tech-job-board' ),
        'hybrid' => esc_html_x( 'Hybrid', 'Location type', 'workeera-remote-tech-job-board' ),
    ];
}

/**
 * Determine whether the current user may edit the given job post.
 *
 * @param  int $job_id Job post ID.
 * @return bool|null   True when the user owns the post or has edit_post capability.
 */
function workeera_is_user_can_job_post($job_id) {
    if (is_user_logged_in()) {

        $job_post = get_post($job_id);

        if ((isset($job_post->post_author) && $job_post->post_author == get_current_user_id()) || current_user_can('edit_post', $job_id)) {
            return true;
        }
    }
}

/**
 * Run job description content through `the_content` and strip disallowed markup.
 *
 * @param  string $post_content Raw post content.
 * @return string               Sanitized, slash-stripped HTML.
 */
function workeera_optimize_job_content($post_content) {
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
 * Get the human-readable labels of the terms attached to a job.
 *
 * @param  int    $job_id   Job post ID.
 * @param  string $tax_slug Taxonomy slug.
 * @return array            Map of term ID to label.
 */
function workeera_job_attched_tax_terms_labls($job_id, $tax_slug) {
    $saved_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $job_id, $tax_slug );

    return $saved_labels;
}

/**
 * Get the term IDs attached to a job for the given taxonomy.
 *
 * @param  int    $job_id   Job post ID.
 * @param  string $tax_slug Taxonomy slug.
 * @param  bool   $is_multi Return all IDs when true, otherwise the first only.
 * @return array|int|string Array of IDs, single ID, or empty string when none.
 */
function workeera_job_attched_tax_terms($job_id, $tax_slug, $is_multi = true) {
    $saved_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $job_id, $tax_slug );
    $saved_value = array_keys( $saved_labels );
    if ( ! $is_multi ) {
        $saved_value = ! empty( $saved_value ) ? $saved_value[0] : '';
    }

    return $saved_value;
}

/**
 * Resolve a job's logo URL, falling back to the bundled placeholder image.
 *
 * @param  int $job_id Job post ID.
 * @return string      Logo URL (possibly with a version query arg).
 */
function workeera_job_logo($job_id, $default = true) {
    // Prefer the linked company's CURRENT logo (multi-company): if the job's
    // company_info snapshot carries a company_id and that company still exists in
    // the author's saved companies with an uploaded logo, use it. Otherwise fall
    // back to the job's own featured image / default below.
    if ( function_exists( 'workeera_get_user_company' ) ) {
        $company_info = workeera_get_object_meta( $job_id, 'company_info' );
        if ( is_array( $company_info ) && ! empty( $company_info['company_id'] ) ) {
            $job_author = (int) get_post_field( 'post_author', $job_id );
            $company    = $job_author ? workeera_get_user_company( $job_author, $company_info['company_id'] ) : null;

            if ( $company && isset( $company['logo'] ) && is_array( $company['logo'] ) ) {
                $company_logo = $company['logo'];
                $logo_exists  = ( ! empty( $company_logo['thumb_path'] ) && file_exists( $company_logo['thumb_path'] ) )
                    || ( ! empty( $company_logo['file'] ) && file_exists( $company_logo['file'] ) );

                if ( $logo_exists ) {
                    $company_logo_url = workeera_company_logo_url( $company_logo );
                    if ( '' !== $company_logo_url ) {
                        return $company_logo_url;
                    }
                }
            }
        }
    }

    $job_thumb_id = get_post_thumbnail_id($job_id);
    if (!is_wp_error($job_thumb_id) && $job_thumb_id) {
        $thumb_url = wp_get_attachment_image_url($job_thumb_id, 'thumbnail');
        $thumbnail_version = workeera_get_object_meta( $job_id, 'thumbnail_version' );
        if (!empty($thumbnail_version)) {
            $thumb_url = add_query_arg(['ver' => absint($thumbnail_version)], $thumb_url);
        }
        return $thumb_url;
    }
    if ($default) {
        return Workeera_Plugin::root_url() . 'images/company-logo.png';
    }
    return '';
}

/**
 * Modify the unique post slug to append post ID for workeera_job post type
 * 
 * @param string $slug          The desired slug (post_name).
 * @param int    $post_ID       Post ID.
 * @param string $post_status   Post status.
 * @param string $post_type     Post type.
 * @return string Modified slug.
 */
function workeera_unique_job_slug($slug, $post_ID, $post_status, $post_type) {
    // Only modify workeera_job post type
    if ($post_type !== 'workeera_job') {
        return $slug;
    }
    
    // Check if slug already ends with the post ID
    if (preg_match('/-' . $post_ID . '$/', $slug)) {
        return $slug;
    }
    
    // Append the post ID to the slug
    $slug = $slug . '-' . $post_ID;
    
    return $slug;
}

add_filter('wp_unique_post_slug', 'workeera_unique_job_slug', 10, 4);

/**
 * Render the "Apply Now" button for a job — external link or AJAX action.
 *
 * @param int    $post_id          Job post ID.
 * @param bool   $is_user_loggedin Whether the current visitor is logged in.
 * @param bool   $external_apply   True to render an external-link variant.
 * @param string $apply_job_url    External application URL.
 */
function workeera_job_apply_button($post_id, $is_user_loggedin, $external_apply, $apply_job_url = '') {
    if ( $external_apply ) : ?>
        <a class="workeera-btn workeera-btn-primary workeera-btn-full workeera-jobdetail-apply-btn workeera-jobdetail-apply-external"
            href="<?php echo esc_url( $apply_job_url ); ?>"
            target="_blank"
            rel="noopener noreferrer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
            </svg>
            <?php echo esc_html__( 'Apply Now', 'workeera-remote-tech-job-board' ); ?>
        </a>
    <?php else : ?>
        <button class="workeera-btn workeera-btn-primary workeera-btn-full workeera-job-apply-btn workeera-jobdetail-apply-btn<?php echo esc_attr(!$is_user_loggedin ? ' workeera-user-not-loggedin' : '') ?>" data-id="<?php echo absint($post_id) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
            </svg>
            <?php echo esc_html__( 'Apply Now', 'workeera-remote-tech-job-board' ); ?>
        </button>
    <?php endif;
}

add_filter( 'workeera_job_description', 'wptexturize' );
add_filter( 'workeera_job_description', 'convert_smilies' );
add_filter( 'workeera_job_description', 'convert_chars' );
add_filter( 'workeera_job_description', 'wpautop' );
add_filter( 'workeera_job_description', 'shortcode_unautop' );
add_filter( 'workeera_job_description', 'prepend_attachment' );
add_filter( 'workeera_job_description', 'strip_shortcodes' );

/**
 * Displays the job description for the listing.
 *
 * @param int|WP_Post $post
 */
function workeera_get_the_job_description( $post = null ) {
	$job_description = apply_filters( 'workeera_job_description', wp_kses_post( $post->post_content ) );

    return $job_description;
}

/**
 * Returns if we output job listing structured data for a post.
 *
 * @param WP_Post|int|null $post
 * @return bool
 */
function workeera_output_job_listing_structured_data( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'workeera_job' !== $post->post_type ) {
		return false;
	}

    $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post->ID ], 'all', 1 );
    $is_filled = isset( $job_fields['is_filled'] ) ? (int) $job_fields['is_filled'] : 0;
    $listing_status = isset( $job_fields['listing_status'] ) ? sanitize_key( $job_fields['listing_status'] ) : 'active';

	// Only show structured data for un-filled, published, and active job listings.
	$output_structured_data = ! $is_filled
		&& 'publish' === $post->post_status
		&& 'active' === $listing_status;

	/**
	 * Filter if we should output structured data.
	 *
	 * @param bool             $output_structured_data True if we should show structured data for post.
	 * @param WP_Post|int|null $post
	 */
	return apply_filters( 'workeera_output_job_listing_structured_data', $output_structured_data, $post );
}

/**
 * Gets the structured data for the job listing.
 *
 * Note: This function does not check if the job listing is published, filled,
 * active and not hidden. Use workeera_output_job_listing_structured_data()
 * to check if structured data should be output for a given job listing.
 *
 * @see https://developers.google.com/search/docs/data-types/job-postings
 *
 * @param WP_Post|int|null $post
 * @return bool|array False if functionality is disabled; otherwise array of structured data.
 */
function workeera_get_job_listing_structured_data( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || 'workeera_job' !== $post->post_type ) {
		return false;
	}

    $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $post->ID ], 'all', 1 );

	$data               = [];
	$data['@context']   = 'http://schema.org/';
	$data['@type']      = 'JobPosting';
	$data['datePosted'] = get_post_datetime( $post )->format( 'c' );

	$job_expires = isset( $job_fields['expiry_date'] ) ? esc_html( $job_fields['expiry_date'] ) : '';
	if ( ! empty( $job_expires ) ) {
        $job_expires = new DateTime( $job_expires );
		$data['validThrough'] = $job_expires->format( 'c' );
	}

	$data['title']       = wp_strip_all_tags( get_the_title( $post ) );
	$data['description'] = workeera_get_the_job_description( $post );

	// Employment types — the job's attached jobtype terms mapped to Google's
	// employmentType enumeration (FULL_TIME, PART_TIME, CONTRACTOR, ...).
	$employment_types = workeera_get_job_employment_types( $post );
	if ( ! empty( $employment_types ) ) {
		$data['employmentType'] = $employment_types;
	}

	// Hiring organization — the company info saved against the job.
	$company_info = workeera_job_company_info( $post->ID );
	$company_name = isset( $company_info['name'] ) ? workeera_strip_slashes( $company_info['name'] ) : '';
	$company_url  = isset( $company_info['website'] ) ? esc_url( $company_info['website'] ) : '';

	$data['hiringOrganization']          = [];
	$data['hiringOrganization']['@type'] = 'Organization';
	$data['hiringOrganization']['name']  = $company_name;

	if ( ! empty( $company_url ) ) {
		$data['hiringOrganization']['sameAs'] = $company_url;
		$data['hiringOrganization']['url']    = $company_url;
	}

	// Company logo — only when a real logo (post thumbnail) is set; the
	// bundled placeholder image is intentionally not reported to Google.
	$company_logo_id = get_post_thumbnail_id( $post );
	$company_logo    = $company_logo_id ? wp_get_attachment_image_url( $company_logo_id, 'full' ) : '';
	if ( $company_logo ) {
		$data['hiringOrganization']['logo'] = $company_logo;
	}

	$data['identifier']          = [];
	$data['identifier']['@type'] = 'PropertyValue';
	$data['identifier']['name']  = $company_name;
	$data['identifier']['value'] = get_the_guid( $post );

	// Job location — street / city / country from the workeera_locations row.
	$location_address = workeera_get_job_listing_location_structured_data( $post );
	if ( ! empty( $location_address ) ) {
		$data['jobLocation']            = [];
		$data['jobLocation']['@type']   = 'Place';
		$data['jobLocation']['address'] = $location_address;
	}

	// Remote jobs must be marked TELECOMMUTE (independent of a saved address).
	$location_type = isset( $job_fields['location_type'] ) ? $job_fields['location_type'] : '';
	if ( 'remote' === $location_type ) {
		$data['jobLocationType'] = 'TELECOMMUTE';

		// Google requires applicantLocationRequirements for 100% remote jobs:
		// at least one Country where applicants may be located. Use the job's
		// saved country (already resolved into the address above); sites hiring
		// worldwide can supply their own list through the filter below.
		$applicant_locations = [];
		if ( is_array( $location_address ) && ! empty( $location_address['addressCountry'] ) ) {
			$applicant_locations[] = [
				'@type' => 'Country',
				'name'  => $location_address['addressCountry'],
			];
		}

		/**
		 * Filter the applicant location requirements for a remote job listing.
		 *
		 * @param array   $applicant_locations List of schema.org Country/State entries.
		 * @param WP_Post $post
		 */
		$applicant_locations = apply_filters( 'workeera_job_applicant_location_requirements', $applicant_locations, $post );

		if ( ! empty( $applicant_locations ) ) {
			$data['applicantLocationRequirements'] = ( 1 === count( $applicant_locations ) )
				? $applicant_locations[0]
				: $applicant_locations;
		}
	}

	// Whether applying happens directly on this site (internal apply flow).
	$apply_method        = workeera_get_object_meta( $post->ID, 'apply_method' );
	$data['directApply'] = ( 'external' !== $apply_method );

	// Base salary — min/max range in the configured default currency.
	$min_salary = isset( $job_fields['min_salary'] ) ? absint( $job_fields['min_salary'] ) : 0;
	$max_salary = isset( $job_fields['max_salary'] ) ? absint( $job_fields['max_salary'] ) : 0;
	if ( $min_salary > 0 || $max_salary > 0 ) {
		global $workeera_plugin_settings;
		$currency_settings = isset( $workeera_plugin_settings['general_settings']['default_currency'] ) ? $workeera_plugin_settings['general_settings']['default_currency'] : [];
		$currency          = isset( $currency_settings['currency'] ) ? $currency_settings['currency'] : 'USD';

		// Map the plugin's salary cadence to Google's unitText enumeration.
		$salary_type = isset( $job_fields['salary_type'] ) ? $job_fields['salary_type'] : 'hourly';
		$unit_map    = [
			'hourly'   => 'HOUR',
			'daily'    => 'DAY',
			'weekly'   => 'WEEK',
			'monthly'  => 'MONTH',
			'annually' => 'YEAR',
		];
		$unit_text = isset( $unit_map[ $salary_type ] ) ? $unit_map[ $salary_type ] : 'HOUR';

		$data['baseSalary']                   = [];
		$data['baseSalary']['@type']          = 'MonetaryAmount';
		$data['baseSalary']['currency']       = $currency;
		$data['baseSalary']['value']['@type'] = 'QuantitativeValue';
		if ( $min_salary > 0 && $max_salary > 0 ) {
			$data['baseSalary']['value']['minValue'] = $min_salary;
			$data['baseSalary']['value']['maxValue'] = $max_salary;
		} else {
			$data['baseSalary']['value']['value'] = max( $min_salary, $max_salary );
		}
		$data['baseSalary']['value']['unitText'] = $unit_text;
	}

	/**
	 * Filter the structured data for a job listing.
	 *
	 * @param bool|array $structured_data False if functionality is disabled; otherwise array of structured data.
	 * @param WP_Post    $post
	 */
	return apply_filters( 'workeera_get_job_listing_structured_data', $data, $post );
}

/**
 * Gets the job's employment types mapped to Google's employmentType enumeration.
 *
 * The job's attached `jobtypes` term labels (e.g. "Full Time", "Freelance")
 * are normalised and mapped to the schema.org/Google values. Unmapped labels
 * are skipped so invalid enum values are never output; use the filter to
 * extend the map for custom job types.
 *
 * @see https://developers.google.com/search/docs/data-types/job-postings
 *
 * @param  WP_Post|int|null $post
 * @return array                  List of employmentType values (may be empty).
 */
function workeera_get_job_employment_types( $post = null ) {
	$post = get_post( $post );

	$employment_types = [];

	if ( $post && class_exists( 'Workeera_Object_Terms_Relation' ) ) {
		/**
		 * Filter the normalised-jobtype-label => employmentType map.
		 *
		 * @param array $map Map of normalised label (lowercase, underscores) to Google employmentType value.
		 */
		$map = apply_filters( 'workeera_employment_types_map', [
			'full_time'  => 'FULL_TIME',
			'part_time'  => 'PART_TIME',
			'contract'   => 'CONTRACTOR',
			'contractor' => 'CONTRACTOR',
			'freelance'  => 'CONTRACTOR',
			'temporary'  => 'TEMPORARY',
			'internship' => 'INTERN',
			'intern'     => 'INTERN',
			'volunteer'  => 'VOLUNTEER',
			'per_diem'   => 'PER_DIEM',
			'other'      => 'OTHER',
		] );

		$jobtype_labels = Workeera_Object_Terms_Relation::get_object_term_labels( $post->ID, 'jobtypes' );
		foreach ( $jobtype_labels as $label ) {
			// Normalise "Full Time" / "Full-Time" / "full  time" to "full_time".
			$key = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( (string) $label ) ), '_' );
			if ( isset( $map[ $key ] ) ) {
				$employment_types[] = $map[ $key ];
			}
		}

		$employment_types = array_values( array_unique( $employment_types ) );
	}

	/**
	 * Filter the employment types for a job listing.
	 *
	 * @param array            $employment_types List of employmentType values.
	 * @param WP_Post|int|null $post
	 */
	return apply_filters( 'workeera_get_job_employment_types', $employment_types, $post );
}

/**
 * Gets the job listing location structured data (schema.org/PostalAddress).
 *
 * Built from the job's `workeera_locations` row: street address and zip code
 * from the serialised `full_address` blob, plus the city / country term labels.
 *
 * @see http://schema.org/PostalAddress
 *
 * @param  WP_Post|int|null $post
 * @return array|bool             PostalAddress array, or false when no address parts exist.
 */
function workeera_get_job_listing_location_structured_data( $post = null ) {
	global $wpdb;

	$post = get_post( $post );

	if ( ! $post || 'workeera_job' !== $post->post_type || ! class_exists( 'Workeera_Object_Terms_Relation' ) ) {
		return false;
	}

	$location_row = Workeera_Object_Terms_Relation::get_post_location( $post->ID );
	if ( empty( $location_row ) ) {
		return false;
	}

	$address          = [];
	$address['@type'] = 'PostalAddress';

	$full_address = ! empty( $location_row['full_address'] ) ? maybe_unserialize( $location_row['full_address'] ) : [];
	if ( ! empty( $full_address['street_address'] ) ) {
		$address['streetAddress'] = workeera_strip_slashes( $full_address['street_address'] );
	}
	if ( ! empty( $full_address['zip_code'] ) ) {
		$address['postalCode'] = workeera_strip_slashes( $full_address['zip_code'] );
	}

	$city_id = ! empty( $location_row['city_id'] ) ? absint( $location_row['city_id'] ) : 0;
	if ( $city_id > 0 ) {
		$city_label = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$wpdb->prefix}workeera_cities WHERE city_id = %d LIMIT 1", $city_id ) );
		if ( $city_label ) {
			$address['addressLocality'] = $city_label;
		}
	}

	$country_id = ! empty( $location_row['country_id'] ) ? absint( $location_row['country_id'] ) : 0;
	if ( $country_id > 0 ) {
		$country_label = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$wpdb->prefix}workeera_countries WHERE country_id = %d LIMIT 1", $country_id ) );
		if ( $country_label ) {
			$address['addressCountry'] = $country_label;
		}
	}

	// No address parts were found beyond the @type marker.
	if ( 1 === count( $address ) ) {
		return false;
	}

	/**
	 * Filter the location structured data for a job listing.
	 *
	 * @param array   $address PostalAddress structured data.
	 * @param WP_Post $post
	 */
	return apply_filters( 'workeera_get_job_listing_location_structured_data', $address, $post );
}

add_action( 'wp_footer', function() {
    $post = get_post();

    if ( ! workeera_output_job_listing_structured_data( $post ) ) {
        return;
    }

    $structured_data = workeera_get_job_listing_structured_data( $post );

    if ( ! $structured_data ) {
        return;
    }

    echo '<!-- Job Structured Data -->' . "\r\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $structured_data ) . '</script>';
} );
