<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Multi-company data layer for employers.
 *
 * Each employer's companies live as rows in the `workeera_companies` table,
 * keyed by a numeric `company_id` (BIGINT) and owned by `employer_id`. A row
 * carries name, website, tagline, employee_count, linkedin, a serialized logo
 * file array, and created_at/updated_at timestamps.
 *
 * Jobs link to a company by that numeric id (stored both in the per-job
 * `company_info` snapshot meta and in the indexed `job_fields.company_id`
 * column). Every read/write of companies must go through these helpers so the
 * table access + shape mapping stay in one place.
 */

/**
 * The company fields we persist (logo is handled separately as a file array).
 *
 * @return array
 */
function workeera_company_field_keys() {
	return array( 'name', 'website', 'tagline', 'employee_count', 'linkedin' );
}

/**
 * The saved Company Settings option (admin).
 *
 * @return array
 */
function workeera_company_settings() {
	$settings = get_option( 'workeera_company_settings' );

	return is_array( $settings ) ? $settings : array();
}

/**
 * Maximum companies a single user may manage (default 20).
 *
 * @return int
 */
function workeera_company_max_per_user() {
	$settings = workeera_company_settings();
	$max      = isset( $settings['max_companies'] ) ? (int) $settings['max_companies'] : 0;

	return $max > 0 ? $max : 20;
}

/**
 * Whether an optional company field is enabled in the front-end forms. Enabled
 * by default (only an explicit "off" hides it). Applies to: logo, website,
 * tagline, employee_count, linkedin. The name field is always shown.
 *
 * @param  string $field Field key.
 * @return bool
 */
function workeera_company_field_enabled( $field ) {
	// Name, logo and website are always shown (not configurable).
	if ( in_array( $field, array( 'name', 'logo', 'website' ), true ) ) {
		return true;
	}
	$settings = workeera_company_settings();
	$value    = isset( $settings[ $field . '_field' ] ) ? $settings[ $field . '_field' ] : 'on';

	return 'off' !== $value;
}

/**
 * How many companies a user currently has.
 *
 * @param  int $user_id User id.
 * @return int
 */
function workeera_user_company_count( $user_id ) {
	return count( workeera_get_user_companies( $user_id ) );
}

/**
 * Whether the user can add another company (under the per-user cap).
 *
 * @param  int $user_id User id.
 * @return bool
 */
function workeera_user_can_add_company( $user_id ) {
	return workeera_user_company_count( $user_id ) < workeera_company_max_per_user();
}

/**
 * Run the orphan-logo cleanup for a user at most once every few hours, throttled
 * with the plugin's own transients store. Call from the dashboard page load.
 *
 * @param  int $user_id User id.
 * @return void
 */
function workeera_maybe_cleanup_company_logos( $user_id ) {
	if ( ! class_exists( 'Workeera_Transients' ) ) {
		return;
	}

	$transient_key = 'workeera_company_logo_cleanup_' . (int) $user_id;
	if ( Workeera_Transients::get( $transient_key ) ) {
		return; // Ran recently.
	}

	// Set the throttle first so a slow/concurrent request never double-runs.
	Workeera_Transients::set(
		array(
			'name'    => $transient_key,
			'value'   => 1,
			'timeout' => 3 * HOUR_IN_SECONDS,
		)
	);

	workeera_cleanup_orphan_company_logos( $user_id );
}

/**
 * Delete a user's company-logo files that are no longer referenced by any of
 * their saved companies (e.g. after a logo was replaced or removed). Only files
 * named `user-{id}-company-*` are considered — nothing else is touched.
 *
 * @param  int $user_id User id.
 * @return void
 */
function workeera_cleanup_orphan_company_logos( $user_id ) {
	$user_id = (int) $user_id;

	// Resolve the plugin's public user-images directory.
	global $workeera_upload_files_extpath;
	$prev_extpath                  = $workeera_upload_files_extpath;
	$workeera_upload_files_extpath = 'user-images';
	add_filter( 'upload_dir', 'workeera_public_upload_files_path' );
	$wp_upload_dir = wp_upload_dir();
	remove_filter( 'upload_dir', 'workeera_public_upload_files_path' );
	$workeera_upload_files_extpath = $prev_extpath;

	$dir = isset( $wp_upload_dir['path'] ) ? $wp_upload_dir['path'] : '';
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return;
	}

	// Files still in use by the user's companies.
	$in_use = array();
	foreach ( workeera_get_user_companies( $user_id ) as $company ) {
		if ( isset( $company['logo'] ) && is_array( $company['logo'] ) ) {
			if ( ! empty( $company['logo']['file'] ) ) {
				$in_use[ basename( $company['logo']['file'] ) ] = true;
			}
			if ( ! empty( $company['logo']['thumb_path'] ) ) {
				$in_use[ basename( $company['logo']['thumb_path'] ) ] = true;
			}
		}
	}

	// Remove this user's company-logo files that are no longer referenced.
	$pattern = $dir . '/user-' . $user_id . '-company-*';
	foreach ( (array) glob( $pattern ) as $path ) {
		if ( is_file( $path ) && ! isset( $in_use[ basename( $path ) ] ) ) {
			wp_delete_file( $path );
		}
	}
}

/**
 * Fully-qualified name of the companies table.
 *
 * @return string
 */
function workeera_companies_table() {
	global $wpdb;

	return $wpdb->prefix . 'workeera_companies';
}

/**
 * Map a raw companies-table row into the canonical company array shape used
 * throughout the plugin: id, name, website, tagline, employee_count, linkedin,
 * logo (file array or ''), created_at, updated_at.
 *
 * @param  array|null $row Associative DB row.
 * @return array|null
 */
function workeera_company_row_to_array( $row ) {
	if ( ! is_array( $row ) ) {
		return null;
	}

	$logo = isset( $row['logo'] ) ? maybe_unserialize( $row['logo'] ) : '';

	return array(
		'id'             => isset( $row['company_id'] ) ? (int) $row['company_id'] : 0,
		'name'           => isset( $row['name'] ) ? $row['name'] : '',
		'website'        => isset( $row['website'] ) ? $row['website'] : '',
		'tagline'        => isset( $row['tagline'] ) ? $row['tagline'] : '',
		'employee_count' => isset( $row['employee_count'] ) ? $row['employee_count'] : '',
		'linkedin'       => isset( $row['linkedin'] ) ? $row['linkedin'] : '',
		'logo'           => is_array( $logo ) ? $logo : '',
		'created_at'     => isset( $row['created_at'] ) ? $row['created_at'] : '',
		'updated_at'     => isset( $row['updated_at'] ) ? $row['updated_at'] : '',
	);
}

/**
 * Get an employer's companies (oldest first). The $persist argument is retained
 * only for backward-compatibility with the previous meta-backed signature and
 * is ignored.
 *
 * @param  int  $user_id User id (employer / owner).
 * @param  bool $persist Unused (kept for signature compatibility).
 * @return array List of company arrays.
 */
function workeera_get_user_companies( $user_id, $persist = false ) {
	global $wpdb;

	$table = workeera_companies_table();
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE employer_id = %d ORDER BY created_at ASC, company_id ASC",
			(int) $user_id
		),
		ARRAY_A
	);

	$list = array();
	foreach ( (array) $rows as $row ) {
		$company = workeera_company_row_to_array( $row );
		if ( $company ) {
			$list[] = $company;
		}
	}

	return $list;
}

/**
 * Fetch a single company owned by a user, by id.
 *
 * @param  int $user_id    User id (employer / owner).
 * @param  int $company_id Company id.
 * @return array|null
 */
function workeera_get_user_company( $user_id, $company_id ) {
	$company_id = absint( $company_id );
	if ( $company_id < 1 ) {
		return null;
	}

	global $wpdb;
	$table = workeera_companies_table();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE employer_id = %d AND company_id = %d LIMIT 1",
			(int) $user_id,
			$company_id
		),
		ARRAY_A
	);

	return $row ? workeera_company_row_to_array( $row ) : null;
}

/**
 * Normalize a website/URL for duplicate comparison (scheme + leading www +
 * trailing slash removed, lowercased).
 *
 * @param  string $url URL.
 * @return string
 */
function workeera_company_normalize_url( $url ) {
	$url = strtolower( trim( (string) $url ) );
	if ( '' === $url ) {
		return '';
	}
	$url = preg_replace( '#^https?://#', '', $url );
	$url = preg_replace( '#^www\.#', '', $url );

	return untrailingslashit( $url );
}

/**
 * Find a duplicate company for this user by name (case-insensitive) or website
 * (normalized), optionally excluding one company id.
 *
 * @param  int    $user_id    User id.
 * @param  string $name       Candidate name.
 * @param  string $website    Candidate website.
 * @param  string $exclude_id Company id to exclude (when updating).
 * @return array|null The conflicting company, or null.
 */
function workeera_find_duplicate_company( $user_id, $name, $website, $exclude_id = '' ) {
	$name_cmp = strtolower( trim( (string) $name ) );
	$url_cmp  = workeera_company_normalize_url( $website );

	foreach ( workeera_get_user_companies( $user_id ) as $company ) {
		if ( (string) $company['id'] === (string) $exclude_id ) {
			continue;
		}
		if ( '' !== $name_cmp && strtolower( trim( (string) $company['name'] ) ) === $name_cmp ) {
			return $company;
		}
		if ( '' !== $url_cmp && workeera_company_normalize_url( $company['website'] ) === $url_cmp ) {
			return $company;
		}
	}

	return null;
}

/**
 * Sanitize + length-validate submitted company fields.
 *
 * @param  array $input Raw input (name, website, tagline, employee_count, linkedin).
 * @return array [ 'clean' => array, 'error' => string ] — error is '' when valid.
 */
function workeera_validate_company_input( $input ) {
	$input = is_array( $input ) ? $input : array();

	$name = isset( $input['name'] ) ? sanitize_text_field( wp_unslash( $input['name'] ) ) : '';
	$name = trim( $name );

	if ( '' === $name ) {
		return array( 'clean' => array(), 'error' => esc_html__( 'Company name is required.', 'workeera-remote-tech-job-board' ) );
	}
	if ( mb_strlen( $name ) > 100 ) {
		return array( 'clean' => array(), 'error' => esc_html__( 'Company name must be 100 characters or fewer.', 'workeera-remote-tech-job-board' ) );
	}

	$website = isset( $input['website'] ) ? esc_url_raw( trim( wp_unslash( $input['website'] ) ) ) : '';
	$tagline = isset( $input['tagline'] ) ? sanitize_text_field( wp_unslash( $input['tagline'] ) ) : '';
	if ( mb_strlen( $tagline ) > 160 ) {
		$tagline = mb_substr( $tagline, 0, 160 );
	}

	$linkedin = isset( $input['linkedin'] ) ? esc_url_raw( trim( wp_unslash( $input['linkedin'] ) ) ) : '';

	$employee_count = isset( $input['employee_count'] ) ? sanitize_text_field( wp_unslash( $input['employee_count'] ) ) : '';
	$allowed_counts = array_keys( workeera_company_employee_count_fields() );
	if ( '' !== $employee_count && ! in_array( $employee_count, $allowed_counts, true ) ) {
		$employee_count = '';
	}

	if ( mb_strlen( $website ) > 200 || mb_strlen( $linkedin ) > 200 ) {
		return array( 'clean' => array(), 'error' => esc_html__( 'A URL is longer than the 200-character limit.', 'workeera-remote-tech-job-board' ) );
	}

	return array(
		'clean' => array(
			'name'           => $name,
			'website'        => $website,
			'tagline'        => $tagline,
			'employee_count' => $employee_count,
			'linkedin'       => $linkedin,
		),
		'error' => '',
	);
}

/**
 * Create or update a company for a user (with duplicate protection).
 *
 * @param  int         $user_id    User id.
 * @param  array       $input      Raw field input.
 * @param  string      $company_id Company id when updating, '' to create.
 * @param  array|null  $logo       New logo file array to set, or null to keep.
 * @return array [ 'company' => array|null, 'error' => string ]
 */
function workeera_save_user_company( $user_id, $input, $company_id = '', $logo = null ) {
	global $wpdb;

	$validated = workeera_validate_company_input( $input );
	if ( '' !== $validated['error'] ) {
		return array( 'company' => null, 'error' => $validated['error'] );
	}
	$clean = $validated['clean'];

	$dupe = workeera_find_duplicate_company( $user_id, $clean['name'], $clean['website'], $company_id );
	if ( $dupe ) {
		return array( 'company' => null, 'error' => esc_html__( 'A company with the same name or website already exists.', 'workeera-remote-tech-job-board' ) );
	}

	$table      = workeera_companies_table();
	$now        = current_time( 'mysql' );
	$company_id = absint( $company_id );

	if ( $company_id > 0 ) {
		// Update an existing company owned by this user.
		$existing = workeera_get_user_company( $user_id, $company_id );
		if ( ! $existing ) {
			return array( 'company' => null, 'error' => esc_html__( 'That company could not be found.', 'workeera-remote-tech-job-board' ) );
		}

		$data   = array(
			'name'           => $clean['name'],
			'website'        => $clean['website'],
			'tagline'        => $clean['tagline'],
			'employee_count' => $clean['employee_count'],
			'linkedin'       => $clean['linkedin'],
			'updated_at'     => $now,
		);
		$format = array( '%s', '%s', '%s', '%s', '%s', '%s' );

		// A $logo of null means "keep current"; an array (incl. an empty array
		// to clear it) sets the logo column.
		if ( is_array( $logo ) ) {
			$data['logo'] = maybe_serialize( $logo );
			$format[]     = '%s';
		}

		$wpdb->update(
			$table,
			$data,
			array( 'employer_id' => (int) $user_id, 'company_id' => $company_id ),
			$format,
			array( '%d', '%d' )
		);

		return array( 'company' => workeera_get_user_company( $user_id, $company_id ), 'error' => '' );
	}

	// Create — enforce the per-user cap.
	$max = workeera_company_max_per_user();
	if ( workeera_user_company_count( $user_id ) >= $max ) {
		return array(
			'company' => null,
			/* translators: %d: maximum companies per user */
			'error'   => sprintf( esc_html__( 'You have reached the limit of %d companies.', 'workeera-remote-tech-job-board' ), $max ),
		);
	}

	$inserted = $wpdb->insert(
		$table,
		array(
			'employer_id'    => (int) $user_id,
			'name'           => $clean['name'],
			'website'        => $clean['website'],
			'tagline'        => $clean['tagline'],
			'employee_count' => $clean['employee_count'],
			'linkedin'       => $clean['linkedin'],
			'logo'           => maybe_serialize( is_array( $logo ) ? $logo : '' ),
			'created_at'     => $now,
			'updated_at'     => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( ! $inserted ) {
		return array( 'company' => null, 'error' => esc_html__( 'The company could not be saved. Please try again.', 'workeera-remote-tech-job-board' ) );
	}

	return array( 'company' => workeera_get_user_company( $user_id, (int) $wpdb->insert_id ), 'error' => '' );
}

/**
 * Reset the `company_id` link on every job attached to a company back to 0.
 *
 * Called when a company is removed (single delete or as part of deleting its
 * owner) so per-company counts/queries no longer match the id. The per-job
 * frozen `company_info` snapshot meta is intentionally left in place, so those
 * jobs still display the company's details via the deleted-company fallback.
 *
 * @param  int $company_id Company id whose jobs should be unlinked.
 * @return int Number of job rows unlinked.
 */
function workeera_unlink_company_jobs( $company_id ) {
	$company_id = absint( $company_id );
	if ( $company_id < 1 ) {
		return 0;
	}

	global $wpdb;

	return (int) $wpdb->update(
		$wpdb->prefix . 'workeera_job_fields',
		array( 'company_id' => 0 ),
		array( 'company_id' => $company_id ),
		array( '%d' ),
		array( '%d' )
	);
}

/**
 * Delete a company from a user's list.
 *
 * @param  int $user_id    User id.
 * @param  int $company_id Company id.
 * @return bool Whether a row was removed.
 */
function workeera_delete_user_company( $user_id, $company_id ) {
	$company_id = absint( $company_id );
	if ( $company_id < 1 ) {
		return false;
	}

	global $wpdb;
	$deleted = $wpdb->delete(
		workeera_companies_table(),
		array( 'employer_id' => (int) $user_id, 'company_id' => $company_id ),
		array( '%d', '%d' )
	);

	if ( empty( $deleted ) ) {
		return false;
	}

	workeera_unlink_company_jobs( $company_id );

	return true;
}

/**
 * Delete every company owned by a user and unlink their jobs. Hooked to
 * `delete_user` so removing an employer takes their companies with them (and
 * clears the `company_id` link on any jobs that pointed at them).
 *
 * @param  int $user_id Id of the user being deleted.
 * @return void
 */
function workeera_delete_user_companies( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return;
	}

	global $wpdb;
	$table = workeera_companies_table();

	$company_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT company_id FROM `{$table}` WHERE employer_id = %d", $user_id )
	);

	if ( empty( $company_ids ) ) {
		return;
	}

	foreach ( $company_ids as $company_id ) {
		workeera_unlink_company_jobs( $company_id );
	}

	$wpdb->delete( $table, array( 'employer_id' => $user_id ), array( '%d' ) );

	// Best-effort: remove the user's now-orphaned company logo files.
	if ( function_exists( 'workeera_cleanup_orphan_company_logos' ) ) {
		workeera_cleanup_orphan_company_logos( $user_id );
	}
}
add_action( 'delete_user', 'workeera_delete_user_companies' );

/**
 * Flatten a company into the per-job `company_info` snapshot shape (+ id).
 * Logo is excluded — jobs carry their logo as the featured image.
 *
 * @param  array $company Company array.
 * @return array
 */
function workeera_company_snapshot( $company ) {
	$company = is_array( $company ) ? $company : array();

	return array(
		'name'           => isset( $company['name'] ) ? $company['name'] : '',
		'website'        => isset( $company['website'] ) ? $company['website'] : '',
		'tagline'        => isset( $company['tagline'] ) ? $company['tagline'] : '',
		'employee_count' => isset( $company['employee_count'] ) ? $company['employee_count'] : '',
		'linkedin'       => isset( $company['linkedin'] ) ? $company['linkedin'] : '',
		'company_id'     => isset( $company['id'] ) ? $company['id'] : '',
	);
}

/**
 * Resolve a job's company details for DISPLAY, preferring the linked company's
 * CURRENT data (name, website, tagline, employee_count, linkedin) when its
 * company_id still maps to a saved company. Falls back to the per-job snapshot
 * (e.g. when the company was deleted). Use this everywhere company data is shown
 * so the display stays consistent with the live logo.
 *
 * @param  int $job_id Job post id.
 * @return array
 */
function workeera_job_company_info( $job_id ) {
	$snapshot = workeera_get_object_meta( $job_id, 'company_info' );
	$snapshot = is_array( $snapshot ) ? $snapshot : array();

	if ( ! empty( $snapshot['company_id'] ) ) {
		$author  = (int) get_post_field( 'post_author', $job_id );
		$company = $author ? workeera_get_user_company( $author, $snapshot['company_id'] ) : null;
		if ( $company ) {
			foreach ( array( 'name', 'website', 'tagline', 'employee_count', 'linkedin' ) as $field ) {
				$snapshot[ $field ] = isset( $company[ $field ] ) ? $company[ $field ] : '';
			}
		}
	}

	return $snapshot;
}

/**
 * Count the published/live jobs linked to a given company.
 *
 * Reads the indexed `company_id` column on the job_fields table (populated on
 * job save and backfilled from legacy jobs), joined to the posts table so only
 * real, non-trashed job posts are counted. No WP_Query — a single prepared
 * COUNT keyed on the indexed column.
 *
 * @param  int $company_id Company id to count jobs for.
 * @return int             Number of matching jobs.
 */
function workeera_company_jobs_count( $company_id ) {
	global $wpdb;

	$company_id = absint( $company_id );
	if ( $company_id < 1 ) {
		return 0;
	}

	$fields_table = $wpdb->prefix . 'workeera_job_fields';

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM `{$fields_table}` jf
			 INNER JOIN {$wpdb->posts} p ON p.ID = jf.job_id
			 WHERE jf.company_id = %d
			 AND p.post_type = %s
			 AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
			$company_id,
			'workeera_job'
		)
	);
}

/**
 * The best display URL for a company logo file array (thumb first).
 *
 * @param  mixed $logo Logo file array or ''.
 * @return string
 */
function workeera_company_logo_url( $logo ) {
	if ( ! is_array( $logo ) ) {
		return '';
	}
	$url = isset( $logo['thumb_url'] ) && $logo['thumb_url'] ? $logo['thumb_url'] : ( isset( $logo['url'] ) ? $logo['url'] : '' );
	if ( '' === $url ) {
		return '';
	}
	$ver = isset( $logo['version'] ) ? $logo['version'] : '';

	return $ver ? add_query_arg( array( 'ver' => $ver ), $url ) : $url;
}

/**
 * Validate + store an uploaded company logo into the user-images directory,
 * returning a logo file array (same shape used everywhere: file/url/ext/
 * thumb_path/thumb_url/version).
 *
 * @param  array  $file       A single $_FILES[...] entry.
 * @param  int    $user_id    User id.
 * @param  string $company_id Company id (for the filename).
 * @return array  [ 'error' => '0'|'1', 'msg' => string, 'logo' => array ]
 */
function workeera_process_company_logo( $file, $user_id, $company_id ) {
	if ( empty( $file ) || ! isset( $file['size'] ) || (int) $file['size'] <= 0 || ! empty( $file['error'] ) ) {
		return array( 'error' => '1', 'msg' => esc_html__( 'No logo file was uploaded.', 'workeera-remote-tech-job-board' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$file = array_map( 'sanitize_text_field', $file );

	$allow_pic_mimes = workeera_picture_allowed_mime_types();
	$allow_pic_size  = function_exists( 'workeera_picture_allowed_file_size' ) ? workeera_picture_allowed_file_size() : 2;
	$max_bytes       = $allow_pic_size * 1024 * 1024;

	if ( (int) $file['size'] > $max_bytes ) {
		return array( 'error' => '1', 'msg' => esc_html_x( 'File size too big.', 'Picture size error', 'workeera-remote-tech-job-board' ) );
	}

	$file_name = sanitize_file_name( $file['name'] );
	$filetype  = wp_check_filetype( $file_name );
	if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allow_pic_mimes, true ) ) {
		return array( 'error' => '1', 'msg' => esc_html__( 'Action failed. File type not allowed to upload.', 'workeera-remote-tech-job-board' ) );
	}
	$file_ext = $filetype['ext'];

	// Store in the plugin's public user-images directory with a per-company name.
	global $workeera_upload_files_extpath;
	$workeera_upload_files_extpath = 'user-images';
	add_filter( 'upload_dir', 'workeera_public_upload_files_path' );

	$safe_company = sanitize_key( $company_id );
	$file['name'] = 'user-' . absint( $user_id ) . '-company-' . $safe_company . '-logo.' . $file_ext;

	$wp_upload_dir     = wp_upload_dir();
	$uplod_direc_path  = isset( $wp_upload_dir['path'] ) ? $wp_upload_dir['path'] : '';
	$uplod_direc_url   = isset( $wp_upload_dir['url'] ) ? $wp_upload_dir['url'] : '';

	$uploaded = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $allow_pic_mimes ) );

	remove_filter( 'upload_dir', 'workeera_public_upload_files_path' );

	if ( ! isset( $uploaded['file'] ) ) {
		$err = isset( $uploaded['error'] ) ? $uploaded['error'] : esc_html__( 'Upload failed.', 'workeera-remote-tech-job-board' );
		return array( 'error' => '1', 'msg' => $err );
	}

	$uploaded['ext']     = $file_ext;
	$uploaded['version'] = wp_rand( 10000000, 99999999 );

	$editor = wp_get_image_editor( $uploaded['file'] );
	if ( ! is_wp_error( $editor ) ) {
		$editor->resize( 150, 150, false );
		$thumb_name = 'user-' . absint( $user_id ) . '-company-' . $safe_company . '-logo-thumb.' . $file_ext;
		$thumb_path = $uplod_direc_path . '/' . $thumb_name;
		$thumb_url  = $uplod_direc_url . '/' . $thumb_name;
		$editor->save( $thumb_path );
		$uploaded['thumb_path'] = $thumb_path;
		$uploaded['thumb_url']  = $thumb_url;
	}

	return array( 'error' => '0', 'msg' => '', 'logo' => $uploaded );
}
