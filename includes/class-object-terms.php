<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Read and count helpers for plugin taxonomy term tables.
 */
class Workeera_Object_Terms {

    /**
     * Get the full list of country rows.
     *
     * @return array Array of country rows as associative arrays.
     */
    public static function countries_list() {
        global $wpdb;

        $countries_table = $wpdb->prefix . 'workeera_countries';

        return $wpdb->get_results("SELECT * FROM $countries_table ORDER BY label ASC", ARRAY_A);
    }

    /**
     * Validate a taxonomy slug against the registered taxonomy map.
     *
     * Used to gate any code path that builds a table name by interpolating
     * a `$tax_slug` value. MySQL's prepared-statement protocol can't bind
     * identifiers, so we must restrict the slug to a known-good set before
     * concatenating it into the table name.
     *
     * @param  string $tax_slug Candidate taxonomy slug.
     * @return bool             True when the slug is a registered taxonomy key.
     */
    private static function is_valid_tax_slug( $tax_slug ) {
        if ( ! is_string( $tax_slug ) || $tax_slug === '' ) {
            return false;
        }
        if ( ! class_exists( 'Workeera_Taxonomies' ) ) {
            include_once WORKEERA_ABSPATH . 'admin/taxonomies/taxonomy.php';
            include_once WORKEERA_ABSPATH . 'admin/taxonomies/common-taxonomy.php';
        }
        if ( ! class_exists( 'Workeera_Custom_Taxonomies' ) ) {
            return false;
        }
        $registered = Workeera_Custom_Taxonomies::list();
        return is_array( $registered ) && isset( $registered[ $tax_slug ] );
    }

    /**
     * Count rows in a plugin taxonomy table.
     *
     * @param  string $tax_slug Taxonomy slug (without prefix), e.g. 'skills'.
     * @return string Total row count returned by the database, or '0' when
     *                $tax_slug is not a registered taxonomy.
     */
    public static function total_found( $tax_slug ) {
        global $wpdb;

        if ( ! self::is_valid_tax_slug( $tax_slug ) ) {
            return '0';
        }

        $tax_table = $wpdb->prefix . 'workeera_' . $tax_slug;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $tax_table is built from a whitelisted slug above; $wpdb->prepare() cannot bind table identifiers.
        return $wpdb->get_var( "SELECT COUNT(*) FROM {$tax_table}" );
    }

    /**
     * Build the skill ↔ sector relationship maps used to filter the Skills
     * field whenever a Sector field is paired with it.
     *
     * `sector_ids` on the skills table is a serialised PHP array of strings
     * formatted as `term(N)` where N is the sector_id (see class-importdata.php
     * "Multi-parent (skills → sectors)" branch).
     *
     * The result is shared by both id-keyed contexts (admin metaboxes,
     * front-end forms) and slug-keyed contexts (public listings filter).
     *
     * Returns:
     *   [
     *     'map_id'            => [ skill_id (string)   => [ sector_id, ... ], ... ],
     *     'map_slug'          => [ skill_slug          => [ sector_slug, ... ], ... ],
     *     'sector_slug_to_id' => [ sector_slug => sector_id, ... ],
     *   ]
     *
     * Statically cached for the duration of the request.
     */
    public static function skills_sector_map_for_localize() {
        static $cached = null;
        if ( $cached !== null ) {
            return $cached;
        }

        global $wpdb;

        $skills_table  = $wpdb->prefix . 'workeera_skills';
        $sectors_table = $wpdb->prefix . 'workeera_sectors';

        // Only build the maps when both tables exist (some installs may not
        // have one of them yet on activation).
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $skills_table ) ) !== $skills_table
            || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sectors_table ) ) !== $sectors_table ) {
            $cached = [
                'map_id'            => (object) [],
                'map_slug'          => (object) [],
                'sector_slug_to_id' => (object) [],
            ];
            return $cached;
        }

        $sector_rows = $wpdb->get_results( "SELECT sector_id, slug FROM {$sectors_table}", ARRAY_A );
        $sector_slug_to_id = [];
        $sector_id_to_slug = [];
        if ( ! empty( $sector_rows ) ) {
            foreach ( $sector_rows as $row ) {
                $sid  = absint( $row['sector_id'] );
                $slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
                if ( $sid > 0 && $slug !== '' ) {
                    $sector_slug_to_id[ $slug ] = $sid;
                    $sector_id_to_slug[ $sid ]  = $slug;
                }
            }
        }

        $skill_rows = $wpdb->get_results( "SELECT skill_id, slug, sector_ids FROM {$skills_table}", ARRAY_A );
        $map_id   = [];
        $map_slug = [];
        if ( ! empty( $skill_rows ) ) {
            foreach ( $skill_rows as $row ) {
                $skid       = absint( $row['skill_id'] );
                $skill_slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
                if ( $skid < 1 ) {
                    continue;
                }

                $sector_ids = self::parse_skill_sector_ids( isset( $row['sector_ids'] ) ? $row['sector_ids'] : '' );

                $map_id[ (string) $skid ] = $sector_ids;

                if ( $skill_slug !== '' ) {
                    $sector_slugs = [];
                    foreach ( $sector_ids as $sid ) {
                        if ( isset( $sector_id_to_slug[ $sid ] ) ) {
                            $sector_slugs[] = $sector_id_to_slug[ $sid ];
                        }
                    }
                    $map_slug[ $skill_slug ] = $sector_slugs;
                }
            }
        }

        $cached = [
            'map_id'            => empty( $map_id ) ? (object) [] : $map_id,
            'map_slug'          => empty( $map_slug ) ? (object) [] : $map_slug,
            'sector_slug_to_id' => empty( $sector_slug_to_id ) ? (object) [] : $sector_slug_to_id,
        ];

        return $cached;
    }

    /**
     * Parse the `sector_ids` column value (serialised array of "term(N)"
     * strings) into a plain int[] of sector IDs.
     */
    public static function parse_skill_sector_ids( $raw ) {
        if ( empty( $raw ) ) {
            return [];
        }

        $arr = maybe_unserialize( $raw );
        if ( ! is_array( $arr ) ) {
            return [];
        }

        $ids = [];
        foreach ( $arr as $entry ) {
            if ( preg_match( '/term\((\d+)\)/', (string) $entry, $m ) ) {
                $ids[] = (int) $m[1];
            } elseif ( is_numeric( $entry ) ) {
                $ids[] = (int) $entry;
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Build an id/slug => label options array for a taxonomy.
     *
     * @param  string $tax_slug    Taxonomy slug (without prefix).
     * @param  bool   $slug_key    When true, key the result by term slug instead of ID.
     * @param  string $object_type Object type context, 'job' or 'candidate'.
     * @return array               Map of term id or slug to label.
     */
    public static function terms_options( $tax_slug, $slug_key = false, $object_type = 'job' ) {
        global $wpdb;

        if ( ! self::is_valid_tax_slug( $tax_slug ) ) {
            return [];
        }

        $tax_table = $wpdb->prefix . 'workeera_' . $tax_slug;

        $id_column_name = Workeera_Taxonomies::taxonomy_id_col_name($tax_slug);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $tax_table is built from a whitelisted slug above; $wpdb->prepare() cannot bind table identifiers.
        $results = $wpdb->get_results( "SELECT * FROM {$tax_table} ORDER BY label ASC", ARRAY_A );
        $options = [];
        foreach ( $results as $row ) {
            if ($slug_key) {
                $options[ $row['slug'] ] = $row['label'];
            } else {
                $options[ $row[ $id_column_name ] ] = $row['label'];
            }
        }
        return $options;
    }

    /**
     * Update term count in workeera_term_counts table.
     *
     * @param int    $term_id     Term ID.
     * @param string $term_type   Plural slug of the taxonomy (e.g. 'sectors', 'skills').
     * @param string $object_type Object type: 'job' or 'candidate'.
     * @param string $action      'plus' to increment, 'minus' to decrement.
     */
    public static function update_term_count( $term_id, $term_type, $object_type, $action = 'plus' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'workeera_term_counts';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT term_count_id, count FROM $table WHERE term_id = %d AND term_type = %s AND object_type = %s LIMIT 1",
                $term_id,
                $term_type,
                $object_type
            )
        );

        if ( $action === 'minus' ) {
            if ( ! empty( $row->term_count_id ) ) {
                $new_count = max( 0, absint( $row->count ) - 1 );
                $wpdb->update(
                    $table,
                    [ 'count' => $new_count ],
                    [ 'term_count_id' => $row->term_count_id ],
                    [ '%d' ],
                    [ '%d' ]
                );
            }
        } else {
            if ( ! empty( $row->term_count_id ) ) {
                $wpdb->update(
                    $table,
                    [ 'count' => absint( $row->count ) + 1 ],
                    [ 'term_count_id' => $row->term_count_id ],
                    [ '%d' ],
                    [ '%d' ]
                );
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'term_id'     => absint( $term_id ),
                        'term_type'   => $term_type,
                        'object_type' => $object_type,
                        'count'       => 1,
                    ],
                    [ '%d', '%s', '%s', '%d' ]
                );
            }
        }
    }

    /**
     * Update counts for all terms attached to a post, in bulk.
     *
     * @param int    $post_id     Post ID.
     * @param string $object_type 'job' or 'candidate'.
     * @param string $action      'plus' or 'minus'.
     */
    public static function update_post_terms_count( $post_id, $object_type, $action = 'plus' ) {
        $taxonomy_map = Workeera_Object_Terms_Relation::post_type_taxonomy_map( $object_type );

        foreach ( $taxonomy_map as $tax_slug => $tax_cfg ) {
            $term_ids = Workeera_Object_Terms_Relation::get_object_term_ids( $post_id, $tax_slug );
            if ( empty( $term_ids ) ) {
                continue;
            }
            foreach ( $term_ids as $term_id ) {
                self::update_term_count( $term_id, $tax_cfg['plural_slug'], $object_type, $action );
            }
        }

        // Also update location (country / city) counts.
        Workeera_Object_Terms_Relation::update_post_location_counts( $post_id, $object_type, $action );
    }
}
