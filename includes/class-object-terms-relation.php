<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Manages post-to-term relations and location records for jobs and candidates.
 */
class Workeera_Object_Terms_Relation {

    /**
     * Register save hooks and AJAX endpoints for taxonomy and location handling.
     */
    public function __construct() {
        // Save terms and location when a post is saved.
        add_action( 'workeera_save_custom_post_data', [ $this, 'save_post_terms' ], 10, 2 );
        add_action( 'workeera_save_custom_post_data', [ $this, 'save_post_location' ], 15, 2 );

        // AJAX handlers — logged-in users only (admin context).
        add_action( 'wp_ajax_workeera_get_taxonomy_terms', [ $this, 'ajax_get_taxonomy_terms' ] );
        add_action( 'wp_ajax_nopriv_workeera_get_taxonomy_terms', [ $this, 'ajax_get_taxonomy_terms' ] );
        add_action( 'wp_ajax_workeera_add_skill_term_inline', [ $this, 'ajax_add_skill_term_inline' ] );
        add_action( 'wp_ajax_workeera_get_cities_by_country', [ $this, 'ajax_get_cities_by_country' ] );
        add_action( 'wp_ajax_nopriv_workeera_get_cities_by_country', [ $this, 'ajax_get_cities_by_country' ] );
    }

    // -------------------------------------------------------------------------
    // Taxonomy configuration
    // -------------------------------------------------------------------------

    /**
     * Returns taxonomy config for a given object type.
     *
     * @param  string $object_type 'job' or 'candidate'.
     * @return array
     */
    public static function post_type_taxonomy_map( $object_type = 'job' ) {
        $shared = [
            'sectors' => [
                'table'       => 'workeera_sectors',
                'rel_table'   => 'workeera_sectors_relation',
                'primary_key' => 'sector_id',
                'rel_key'     => 'sector_id',
                'plural_slug' => 'sectors',
                'multi'       => false,
            ],
            'skills' => [
                'table'       => 'workeera_skills',
                'rel_table'   => 'workeera_skills_relation',
                'primary_key' => 'skill_id',
                'rel_key'     => 'skill_id',
                'plural_slug' => 'skills',
                'multi'       => true,
            ],
        ];

        $job_only = [
            'jobtypes' => [
                'table'       => 'workeera_jobtypes',
                'rel_table'   => 'workeera_jobtypes_relation',
                'primary_key' => 'jobtype_id',
                'rel_key'     => 'jobtype_id',
                'plural_slug' => 'jobtypes',
                'multi'       => true,
            ],
            'timezones' => [
                'table'       => 'workeera_timezones',
                'rel_table'   => 'workeera_timezones_relation',
                'primary_key' => 'timezone_id',
                'rel_key'     => 'timezone_id',
                'plural_slug' => 'timezones',
                'multi'       => false,
            ],
            'benefits' => [
                'table'       => 'workeera_benefits',
                'rel_table'   => 'workeera_benefits_relation',
                'primary_key' => 'benefit_id',
                'rel_key'     => 'benefit_id',
                'plural_slug' => 'benefits',
                'multi'       => true,
            ],
        ];

        $candidate_only = [
            'experiences' => [
                'table'       => 'workeera_experiences',
                'rel_table'   => 'workeera_experiences_relation',
                'primary_key' => 'experience_id',
                'rel_key'     => 'experience_id',
                'plural_slug' => 'experiences',
                'multi'       => false,
            ],
        ];

        if ( $object_type === 'job' ) {
            return array_merge( $shared, $job_only );
        }

        if ( $object_type === 'candidate' ) {
            return array_merge( $shared, $candidate_only );
        }

        return $shared;
    }

    // -------------------------------------------------------------------------
    // Taxonomy read helpers
    // -------------------------------------------------------------------------

    /**
     * Get term IDs attached to a post for a given taxonomy slug.
     *
     * @param  int    $object_id
     * @param  string $tax_slug
     * @return int[]
     */
    public static function get_object_term_ids( $object_id, $tax_slug ) {
        global $wpdb;

        $all_map = array_merge(
            self::post_type_taxonomy_map( 'job' ),
            self::post_type_taxonomy_map( 'candidate' )
        );

        if ( ! isset( $all_map[ $tax_slug ] ) ) {
            return [];
        }

        $cfg         = $all_map[ $tax_slug ];
        $rel_table_s = workeera_safe_sql_identifier( $cfg['rel_table'] );
        $rel_key     = workeera_safe_sql_identifier( $cfg['rel_key'] );
        if ( $rel_table_s === '' || $rel_key === '' ) {
            return [];
        }
        $rel_table = $wpdb->prefix . $rel_table_s;

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT %i FROM `{$rel_table}` WHERE object_id = %d",
                $rel_key,
                absint( $object_id )
            )
        );

        return ! empty( $results ) ? array_map( 'absint', $results ) : [];
    }

    /**
     * Get term IDs + labels attached to a post for a given taxonomy slug.
     * Returns [ term_id => label, ... ]
     *
     * @param  int    $object_id
     * @param  string $tax_slug
     * @return array
     */
    public static function get_object_term_labels( $object_id, $tax_slug ) {
        global $wpdb;

        $all_map = array_merge(
            self::post_type_taxonomy_map( 'job' ),
            self::post_type_taxonomy_map( 'candidate' )
        );

        if ( ! isset( $all_map[ $tax_slug ] ) ) {
            return [];
        }

        $cfg         = $all_map[ $tax_slug ];
        $rel_table_s = workeera_safe_sql_identifier( $cfg['rel_table'] );
        $table_sfx   = workeera_safe_sql_identifier( $cfg['table'] );
        $primary_key = workeera_safe_sql_identifier( $cfg['primary_key'] );
        $rel_key     = workeera_safe_sql_identifier( $cfg['rel_key'] );
        if ( $rel_table_s === '' || $table_sfx === '' || $primary_key === '' || $rel_key === '' ) {
            return [];
        }
        $rel_table   = $wpdb->prefix . $rel_table_s;
        $terms_table = $wpdb->prefix . $table_sfx;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.%i AS term_id, t.label
                 FROM `{$rel_table}` AS r
                 INNER JOIN `{$terms_table}` AS t ON t.%i = r.%i
                 WHERE r.object_id = %d",
                $primary_key,
                $primary_key,
                $rel_key,
                absint( $object_id )
            ),
            ARRAY_A
        );

        $labels = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $labels[ absint( $row['term_id'] ) ] = $row['label'];
            }
        }

        return $labels;
    }

    /**
     * Get term labels for given taxonomy slugs.
     * Returns [ slug => label, ... ]
     *
     * @param  array  $slugs    Array of term slugs (e.g., ['slug1', 'slug2'])
     * @param  string $tax_slug Taxonomy slug
     * @return array
     */
    public static function get_term_labels_by_slugs( $slugs, $tax_slug ) {
        global $wpdb;

        if ( empty( $slugs ) || ! is_array( $slugs ) ) {
            return [];
        }

        $all_map = array_merge(
            self::post_type_taxonomy_map( 'job' ),
            self::post_type_taxonomy_map( 'candidate' )
        );

        if ( ! isset( $all_map[ $tax_slug ] ) ) {
            return [];
        }

        $cfg         = $all_map[ $tax_slug ];
        $table_sfx   = workeera_safe_sql_identifier( $cfg['table'] );
        $primary_key = workeera_safe_sql_identifier( $cfg['primary_key'] );
        if ( $table_sfx === '' || $primary_key === '' ) {
            return [];
        }
        $terms_table = $wpdb->prefix . $table_sfx;

        // Sanitize slugs
        $slugs = array_map( 'sanitize_title', $slugs );
        $placeholders = implode( "','", $slugs );

        $rows = $wpdb->get_results(
            wp_unslash($wpdb->prepare(
                "SELECT %i AS term_id, label, slug
                FROM `{$terms_table}`
                WHERE slug IN ( %s )",
                $primary_key,
                $placeholders
            )),
            ARRAY_A
        );

        $labels = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $labels[ $row['slug'] ] = $row['label'];
            }
        }

        return $labels;
    }

    // -------------------------------------------------------------------------
    // Location read helper
    // -------------------------------------------------------------------------

    /**
     * Get the saved location row for a post from workeera_locations.
     * Returns an associative array of the row, or an empty array when not found.
     *
     * @param  int $post_id
     * @return array
     */
    public static function get_post_location( $post_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'workeera_locations';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE object_id = %d LIMIT 1",
                absint( $post_id )
            ),
            ARRAY_A
        );

        return ! empty( $row ) ? $row : [];
    }

    // -------------------------------------------------------------------------
    // Save terms
    // -------------------------------------------------------------------------

    /**
     * Hooked to workeera_save_custom_post_data (priority 10).
     * Reads $_POST['workeera_terms'] and persists all taxonomy relation records.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function save_post_terms( $post_id, $post ) {
        if ( ! in_array( $post->post_type, [ 'workeera_job', 'workeera_candidate' ], true ) ) {
            return;
        }

        if ( ! isset( $_POST['workeera_terms'] ) || ! is_array( $_POST['workeera_terms'] ) ) {
            return;
        }

        $object_type  = $post->post_type === 'workeera_job' ? 'job' : 'candidate';
        $taxonomy_map = self::post_type_taxonomy_map( $object_type );

        // Capture old term IDs before we wipe them (needed for count diff).
        $old_term_ids_by_tax = [];
        foreach ( $taxonomy_map as $tax_slug => $cfg ) {
            $old_term_ids_by_tax[ $tax_slug ] = self::get_object_term_ids( $post_id, $tax_slug );
        }

        // Unslash once here; save_terms_for_taxonomy() sanitizes each term value
        // (as an int or slug) before it is used in a query.
        $submitted = isset( $_POST['workeera_terms'] ) && is_array( $_POST['workeera_terms'] )
            ? ( array_map( 'workeera_array_esc_str', wp_unslash( $_POST['workeera_terms'] ) ) )
            : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- per-field sanitization happens in save_terms_for_taxonomy()

        foreach ( $taxonomy_map as $tax_slug => $cfg ) {
            $this->save_terms_for_taxonomy(
                $post_id,
                $object_type,
                $tax_slug,
                $cfg,
                isset( $submitted[ $tax_slug ] ) ? $submitted[ $tax_slug ] : [],
                $old_term_ids_by_tax[ $tax_slug ]
            );
        }
    }

    /**
     * Save term relations for one taxonomy and update term counts.
     *
     * @param int    $object_id
     * @param string $object_type  'job' | 'candidate'
     * @param string $tax_slug
     * @param array  $cfg
     * @param mixed  $submitted    Raw submitted value (string or array).
     * @param int[]  $old_term_ids Previously saved term IDs.
     */
    private function save_terms_for_taxonomy( $object_id, $object_type, $tax_slug, $cfg, $submitted, $old_term_ids ) {
        global $wpdb;

        // Validate the config-derived table/column identifiers — $wpdb->delete()
        // and $wpdb->insert() do not escape the table name or the data keys.
        $rel_table_s = workeera_safe_sql_identifier( $cfg['rel_table'] );
        $rel_key     = workeera_safe_sql_identifier( $cfg['rel_key'] );
        if ( $rel_table_s === '' || $rel_key === '' ) {
            return;
        }
        $rel_table = $wpdb->prefix . $rel_table_s;

        // Sanitise submitted IDs.
        $new_term_ids = [];
        if ( ! empty( $submitted ) ) {
            $raw = is_array( $submitted ) ? $submitted : [ $submitted ];
            foreach ( $raw as $val ) {
                $id = absint( $val );
                if ( $id > 0 ) {
                    $new_term_ids[] = $id;
                }
            }
            $new_term_ids = array_unique( $new_term_ids );

            // Single-select: keep only the first value.
            if ( ! $cfg['multi'] && ! empty( $new_term_ids ) ) {
                $new_term_ids = [ $new_term_ids[0] ];
            }
        }

        $removed_ids = array_diff( $old_term_ids, $new_term_ids );
        $added_ids   = array_diff( $new_term_ids, $old_term_ids );

        // Delete all existing relations then re-insert.
        $wpdb->delete(
            $rel_table,
            [ 'object_id' => $object_id, 'object_type' => 'post' ],
            [ '%d', '%s' ]
        );

        foreach ( $new_term_ids as $term_id ) {
            $wpdb->insert(
                $rel_table,
                [
                    $rel_key      => $term_id,
                    'object_id'   => $object_id,
                    'object_type' => 'post',
                ],
                [ '%d', '%d', '%s' ]
            );
        }

        // Update counts only for published posts.
        if ( get_post_status( $object_id ) !== 'publish' ) {
            return;
        }

        foreach ( $removed_ids as $term_id ) {
            Workeera_Object_Terms::update_term_count( $term_id, $cfg['plural_slug'], $object_type, 'minus' );
        }

        foreach ( $added_ids as $term_id ) {
            Workeera_Object_Terms::update_term_count( $term_id, $cfg['plural_slug'], $object_type, 'plus' );
        }
    }

    // -------------------------------------------------------------------------
    // Save location
    // -------------------------------------------------------------------------

    /**
     * Hooked to workeera_save_custom_post_data (priority 15).
     * Reads $_POST['workeera_location_fields'] and upserts one row in workeera_locations.
     * street_address and zip_code are stored as a PHP-serialized array in full_address.
     * Also updates city and country term counts in workeera_term_counts.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function save_post_location( $post_id, $post ) {
        if ( ! in_array( $post->post_type, [ 'workeera_job', 'workeera_candidate' ], true ) ) {
            return;
        }

        if ( ! isset( $_POST['workeera_location_fields'] ) || ! is_array( $_POST['workeera_location_fields'] ) ) {
            return;
        }

        global $wpdb;

        $object_type   = $post->post_type === 'workeera_job' ? 'job' : 'candidate';
        $location_table = $wpdb->prefix . 'workeera_locations';

        // Unslash once here, then sanitize each sub-field with the type-appropriate WP core sanitizer.
        $raw            = isset( $_POST['workeera_location_fields'] ) && is_array( $_POST['workeera_location_fields'] )
            ? array_map( 'sanitize_text_field',  wp_unslash( $_POST['workeera_location_fields'] ) )
            : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $new_country_id = isset( $raw['country'] ) ? absint( $raw['country'] ) : 0;
        $new_city_id    = isset( $raw['city'] ) ? absint( $raw['city'] ) : 0;
        $street_address = isset( $raw['street_address'] ) ? sanitize_text_field( $raw['street_address'] ) : '';
        $zip_code       = isset( $raw['zip_code'] ) ? sanitize_text_field( $raw['zip_code'] ) : '';

        // Serialize street + zip into full_address.
        $full_address = maybe_serialize( [
            'street_address' => $street_address,
            'zip_code'       => $zip_code,
        ] );

        // Fetch the existing location row to diff country/city for count updates.
        $existing = self::get_post_location( $post_id );

        $old_country_id = ! empty( $existing['country_id'] ) ? absint( $existing['country_id'] ) : 0;
        $old_city_id    = ! empty( $existing['city_id'] ) ? absint( $existing['city_id'] ) : 0;

        $location_data = [
            'country_id'   => $new_country_id,
            'city_id'      => $new_city_id > 0 ? $new_city_id : null,
            'object_id'    => $post_id,
            'full_address' => $full_address,
        ];

        $location_formats = [ '%d', $new_city_id > 0 ? '%d' : null, '%d', '%s' ];
        // Remove null format for null city_id.
        $location_formats = array_filter( $location_formats );
        $location_formats = array_values( $location_formats );

        if ( ! empty( $existing['location_id'] ) ) {
            // Update existing row.
            $wpdb->update(
                $location_table,
                $location_data,
                [ 'location_id' => absint( $existing['location_id'] ) ],
                [ '%d', ( $new_city_id > 0 ? '%d' : '%s' ), '%d', '%s' ],
                [ '%d' ]
            );
        } else {
            // Insert new row.
            $wpdb->insert(
                $location_table,
                $location_data,
                [ '%d', ( $new_city_id > 0 ? '%d' : '%s' ), '%d', '%s' ]
            );
        }

        // Update term counts only for published posts.
        if ( get_post_status( $post_id ) !== 'publish' ) {
            return;
        }

        // Country count.
        if ( $old_country_id !== $new_country_id ) {
            if ( $old_country_id > 0 ) {
                Workeera_Object_Terms::update_term_count( $old_country_id, 'countries', $object_type, 'minus' );
            }
            if ( $new_country_id > 0 ) {
                Workeera_Object_Terms::update_term_count( $new_country_id, 'countries', $object_type, 'plus' );
            }
        }

        // City count.
        if ( $old_city_id !== $new_city_id ) {
            if ( $old_city_id > 0 ) {
                Workeera_Object_Terms::update_term_count( $old_city_id, 'cities', $object_type, 'minus' );
            }
            if ( $new_city_id > 0 ) {
                Workeera_Object_Terms::update_term_count( $new_city_id, 'cities', $object_type, 'plus' );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Delete location helper (called from post-types.php)
    // -------------------------------------------------------------------------

    /**
     * Delete the location row for a post and decrement country/city counts
     * when the post was published.
     *
     * @param int    $post_id
     * @param string $object_type 'job' | 'candidate'
     * @param bool   $update_counts Whether to decrement term counts.
     */
    public static function delete_post_location( $post_id, $object_type, $update_counts = false ) {
        global $wpdb;

        $location_table = $wpdb->prefix . 'workeera_locations';

        if ( $update_counts ) {
            $existing = self::get_post_location( $post_id );
            if ( ! empty( $existing ) ) {
                $old_country_id = ! empty( $existing['country_id'] ) ? absint( $existing['country_id'] ) : 0;
                $old_city_id    = ! empty( $existing['city_id'] ) ? absint( $existing['city_id'] ) : 0;

                if ( $old_country_id > 0 ) {
                    Workeera_Object_Terms::update_term_count( $old_country_id, 'countries', $object_type, 'minus' );
                }
                if ( $old_city_id > 0 ) {
                    Workeera_Object_Terms::update_term_count( $old_city_id, 'cities', $object_type, 'minus' );
                }
            }
        }

        $wpdb->delete(
            $location_table,
            [ 'object_id' => absint( $post_id ) ],
            [ '%d' ]
        );
    }

    // -------------------------------------------------------------------------
    // Location count helpers (called from post-types.php on status change)
    // -------------------------------------------------------------------------

    /**
     * Update country and city counts for a post's saved location.
     *
     * @param int    $post_id
     * @param string $object_type 'job' | 'candidate'
     * @param string $action      'plus' | 'minus'
     */
    public static function update_post_location_counts( $post_id, $object_type, $action = 'plus' ) {
        $existing = self::get_post_location( $post_id );
        if ( empty( $existing ) ) {
            return;
        }

        $country_id = ! empty( $existing['country_id'] ) ? absint( $existing['country_id'] ) : 0;
        $city_id    = ! empty( $existing['city_id'] ) ? absint( $existing['city_id'] ) : 0;

        if ( $country_id > 0 ) {
            Workeera_Object_Terms::update_term_count( $country_id, 'countries', $object_type, $action );
        }
        if ( $city_id > 0 ) {
            Workeera_Object_Terms::update_term_count( $city_id, 'cities', $object_type, $action );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX — get paginated taxonomy terms
    // -------------------------------------------------------------------------

    /**
     * AJAX handler: workeer_get_taxonomy_terms
     * Returns 10 terms per page, searchable by label.
     *
     * Expected POST params: workeera_nonce, tax_slug, page, q.
     */
    public function ajax_get_taxonomy_terms() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        global $wpdb;

        $tax_slug   = isset( $_POST['tax_slug'] ) ? sanitize_key( wp_unslash( $_POST['tax_slug'] ) ) : '';
        $with_slugs = isset( $_POST['with_slugs'] ) ? sanitize_key( wp_unslash( $_POST['with_slugs'] ) ) : '';
        $page       = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $search     = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

        $all_map = array_merge(
            self::post_type_taxonomy_map( 'job' ),
            self::post_type_taxonomy_map( 'candidate' )
        );

        if ( ! isset( $all_map[ $tax_slug ] ) ) {
            wp_send_json( [ 'results' => [], 'pagination' => [ 'more' => false ] ] );
        }

        $cfg         = $all_map[ $tax_slug ];
        $table_sfx   = workeera_safe_sql_identifier( $cfg['table'] );
        $primary_key = workeera_safe_sql_identifier( $cfg['primary_key'] );
        if ( $table_sfx === '' || $primary_key === '' ) {
            wp_send_json( [ 'results' => [], 'pagination' => [ 'more' => false ] ] );
        }
        $terms_table = $wpdb->prefix . $table_sfx;
        $per_page    = 10;
        $offset      = ( $page - 1 ) * $per_page;

        $placeholder_arr = [ 1 ];
        $where           = ' WHERE 1 = %d';

        if ( ! empty( $search ) ) {
            $where            .= ' AND label LIKE %s';
            $placeholder_arr[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Skills can be filtered by their parent sector. Skills with no
        // sector_ids stored are always included (orphan skills are universal).
        if ( $tax_slug === 'skills' ) {
            $sector_id = isset( $_POST['sector_id'] ) ? absint( $_POST['sector_id'] ) : 0;
            if ( $sector_id > 0 ) {
                $where            .= ' AND ( sector_ids IS NULL OR sector_ids = %s OR sector_ids NOT LIKE %s OR sector_ids LIKE %s )';
                $placeholder_arr[] = '';
                $placeholder_arr[] = '%term(%';
                $placeholder_arr[] = '%term(' . $sector_id . ')%';
            }
        }

        $count_qry = "SELECT COUNT(*) FROM `{$terms_table}`";
        $count_qry .= $where;
        $total = (int) $wpdb->get_var(
            $wpdb->prepare( $count_qry, $placeholder_arr )
        );

        $placeholder_arr[] = $per_page;
        $placeholder_arr[] = $offset;

        $placeholder_arr = array_merge([$primary_key], $placeholder_arr);
        $res_qry = "SELECT %i AS term_id, slug, label FROM `{$terms_table}`";
        $res_qry .= $where;
        $res_qry .= " ORDER BY label ASC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $res_qry,
                $placeholder_arr
            ),
            ARRAY_A
        );

        $results = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $results[] = [
                    'id'   => $with_slugs == 'yes' ? esc_attr( $row['slug'] ) : absint( $row['term_id'] ),
                    'text' => esc_html( $row['label'] ),
                ];
            }
        }

        wp_send_json( [
            'results'    => $results,
            'pagination' => [ 'more' => ( $offset + $per_page ) < $total ],
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — get paginated cities filtered by country
    // -------------------------------------------------------------------------

    /**
     * AJAX handler: workeera_ajax_get_cities_by_country
     * Returns 10 cities per page filtered by country_id, searchable by label.
     *
     * Expected POST params: workeera_nonce, country_id, page, q.
     */
    public function ajax_get_cities_by_country() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        global $wpdb;

        $country_id = isset( $_POST['country_id'] ) ? absint( $_POST['country_id'] ) : 0;
        $page       = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $search     = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $per_page   = 10;
        $offset     = ( $page - 1 ) * $per_page;

        $placeholder_arr = [ 1 ];
        $where           = 'WHERE 1 = %d';

        // Filter by country when one is selected.
        if ( $country_id > 0 ) {
            $where            .= ' AND country_id = %d';
            $placeholder_arr[] = $country_id;
        }

        if ( ! empty( $search ) ) {
            $where            .= ' AND label 
                     LIKE %s';
            $placeholder_arr[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}workeera_cities $where", $placeholder_arr )
        );

        $placeholder_arr[] = $per_page;
        $placeholder_arr[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT city_id AS term_id, label FROM {$wpdb->prefix}workeera_cities $where ORDER BY label ASC LIMIT %d OFFSET %d",
                $placeholder_arr
            ),
            ARRAY_A
        );

        $results = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $results[] = [
                    'id'   => absint( $row['term_id'] ),
                    'text' => esc_html( $row['label'] ),
                ];
            }
        }

        wp_send_json( [
            'results'    => $results,
            'pagination' => [ 'more' => ( $offset + $per_page ) < $total ],
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — inline skill creation (skills only)
    // -------------------------------------------------------------------------

    /**
     * AJAX handler: workeera_ajax_add_skill_term_inline
     *
     * Expected POST params: workeera_nonce, term_name.
     */
    public function ajax_add_skill_term_inline() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        // Capability gate — this writes to the global skills taxonomy table and
        // is only triggered from the wp-admin job/candidate metaboxes.
        // Restrict the write to taxonomy managers.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'You are not allowed to create skills.', 'workeera-remote-tech-job-board' ) ) );
        }

        global $wpdb;

        $term_name = isset( $_POST['term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';

        if ( $term_name === '' ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Skill name cannot be empty.', 'workeera-remote-tech-job-board' ) ] );
        }

        if ( strlen( $term_name ) > 70 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Name is too long. Please make it shorter.', 'workeera-remote-tech-job-board' ) ] );
        }

        $slug = workeera_make_title_slug( $term_name );

        // Return existing skill if slug already exists.
        $existing = $wpdb->get_var(
            $wpdb->prepare( "SELECT skill_id FROM {$wpdb->prefix}workeera_skills WHERE slug = %s LIMIT 1", $slug )
        );

        if ( $existing ) {
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT skill_id, label FROM {$wpdb->prefix}workeera_skills WHERE skill_id = %d LIMIT 1", $existing ),
                ARRAY_A
            );
            wp_send_json( [
                'error'  => '0',
                'result' => [
                    'id'   => absint( $row['skill_id'] ),
                    'text' => esc_html( $row['label'] ),
                ],
            ] );
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'workeera_skills',
            [ 'label' => $term_name, 'slug' => $slug, 'sector_ids' => '' ],
            [ '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'There was an error adding this skill.', 'workeera-remote-tech-job-board' ) ] );
        }

        wp_send_json( [
            'error'  => '0',
            'result' => [
                'id'   => absint( $wpdb->insert_id ),
                'text' => esc_html( $term_name ),
            ],
        ] );
    }
}

new Workeera_Object_Terms_Relation;
