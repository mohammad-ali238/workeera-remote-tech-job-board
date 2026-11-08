<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles taxonomy sample-data import functionality.
 *
 * Supports:
 *  - Importing from bundled sample CSV files.
 *  - Importing from a user-uploaded CSV file (saved to the WP uploads folder).
 *  - Chunked AJAX processing with a progress-bar response.
 *  - Two-phase import for child taxonomies (sectors → skills, countries → cities):
 *      Phase "parent" → inserts all parent rows from the parent's own CSV first.
 *      Phase "main"   → inserts child rows; parent IDs are now resolvable via SELECT.
 *  - Duplicate-slug prevention via an in-memory slug registry (no extra SELECTs per row).
 *  - Tracking whether data was ever imported via a WP option key.
 */
class Workeera_Import_Data {

    /** Rows per AJAX chunk – keeps execution time well within limits. */
    const CHUNK_SIZE = 500;

    /** Max allowed upload size in bytes (5 MB). */
    const MAX_FILE_SIZE = 5242880;

    /** Folder name inside WORKEERA_ABSPATH for sample CSV files. */
    const SAMPLE_DATA_DIR = 'admin/taxonomies/sample-data/';

    /**
     * Register AJAX endpoints for upload, chunk processing, sample download, and data removal.
     */
    public function __construct() {
        // Upload user CSV.
        add_action( 'wp_ajax_workeera_importdata_upload_csv',      [ $this, 'ajax_upload_csv' ] );

        // Process one chunk of data (parent or main phase).
        add_action( 'wp_ajax_workeera_importdata_process_chunk',   [ $this, 'ajax_process_chunk' ] );

        // Download the sample CSV file for a given taxonomy.
        add_action( 'wp_ajax_workeera_importdata_download_sample', [ $this, 'ajax_download_sample' ] );

        // Remove taxonomy terms data
        add_action( 'wp_ajax_workeera_importdata_remove_tax_data', [ $this, 'ajax_remove_tax_data' ] );
    }

    // -------------------------------------------------------------------------
    // Public helpers used by Workeera_Common_Taxonomy
    // -------------------------------------------------------------------------

    /**
     * Returns true when the "imported once" flag is set for $tax_slug.
     *
     * @param  string $tax_slug
     * @return bool
     */
    public static function is_imported( $tax_slug ) {
        return 'yes' === get_option( 'workeera_tax_' . $tax_slug . '_data_imported_once', '' );
    }

    /**
     * Returns the sample-data file path for a given taxonomy slug, or '' when absent.
     *
     * @param  string $tax_slug
     * @return string
     */
    public static function sample_file_path( $tax_slug ) {
        $path = WORKEERA_ABSPATH . self::SAMPLE_DATA_DIR . $tax_slug . '.csv';
        return file_exists( $path ) ? $path : '';
    }

    /**
     * Absolute path of the previously uploaded custom CSV for $tax_slug,
     * or '' when none has been uploaded yet.
     *
     * @param  string $tax_slug
     * @return string
     */
    public static function uploaded_file_path( $tax_slug ) {
        $path = get_option( 'workeera_tax_' . sanitize_key( $tax_slug ) . '_upload_data_file', '' );
        return ( $path && file_exists( $path ) ) ? $path : '';
    }

    /**
     * AJAX handler that wipes all term data for a taxonomy.
     */
    public function ajax_remove_tax_data() {
        // Nonce check.
        $nonce = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Security check failed.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Capability check.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ) ] );
        }

        $tax_slug = isset( $_POST['tax_slug'] ) ? sanitize_key( wp_unslash( $_POST['tax_slug'] ) ) : '';
        if ( empty( $tax_slug ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid taxonomy.', 'workeera-remote-tech-job-board' ) ] );
        }

        $all_taxes   = Workeera_Custom_Taxonomies::list();

        if ( ! isset( $all_taxes[ $tax_slug ] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid taxonomy.', 'workeera-remote-tech-job-board' ) ] );
        }

        $tax_cfg     = isset( $all_taxes[ $tax_slug ] ) ? $all_taxes[ $tax_slug ] : [];

        global $wpdb;
        $table_name = $wpdb->prefix . 'workeera_' . $tax_cfg['table_name'];

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
            $this->clear_taxonomy_data( $tax_slug, false );
            /* translators: %s: taxonomy plural name */
            wp_send_json( [ 'error' => '0', 'msg' => sprintf(esc_html__( '%s data removed successfully.', 'workeera-remote-tech-job-board' ), $tax_cfg['plural_name']) ] );
        }

        wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Nothing found to remove.', 'workeera-remote-tech-job-board' ) ] );
    }

    /**
     * AJAX handler that validates and stores a user-uploaded CSV file.
     */
    public function ajax_upload_csv() {
        // Nonce check.
        $nonce = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Security check failed.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Capability check.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ) ] );
        }

        $tax_slug = isset( $_POST['tax_slug'] ) ? sanitize_key( wp_unslash( $_POST['tax_slug'] ) ) : '';
        if ( empty( $tax_slug ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid taxonomy.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Whitelist the taxonomy slug against the registered list so the user-supplied
        // value cannot become an arbitrary suffix in the option key written below.
        $all_taxes = Workeera_Custom_Taxonomies::list();
        $valid_slugs = array_keys( $all_taxes );
        $matched_idx = array_search( $tax_slug, $valid_slugs, true );
        if ( false === $matched_idx ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid taxonomy.', 'workeera-remote-tech-job-board' ) ] );
        }
        // Re-bind to the whitelisted key so the value flowing into update_option() below
        // provably comes from the registered list (not from $_POST).
        $tax_slug = $valid_slugs[ $matched_idx ];

        if ( empty( $_FILES['workeera_importdata_csv'] ) || empty( $_FILES['workeera_importdata_csv']['name'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No file was uploaded.', 'workeera-remote-tech-job-board' ) ] );
        }

        // File-type check.
        $file     = $_FILES['workeera_importdata_csv']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $filetype = wp_check_filetype( $file['name'], [ 'csv' => 'text/csv' ] );
        if ( empty( $filetype['ext'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Only CSV files are allowed.', 'workeera-remote-tech-job-board' ) ] );
        }

        // File-size check.
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'File size must not exceed 5 MB.', 'workeera-remote-tech-job-board' ) ] );
        }

        // Use WP filesystem / wp_handle_upload.
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Override upload directory so the file lands in wp-workeera/import-data/.
        global $workeera_upload_files_extpath, $workeera_upload_subdir_extpath;
        $workeera_upload_files_extpath  = 'import-data';
        $workeera_upload_subdir_extpath = '';

        add_filter( 'upload_dir', 'workeera_public_upload_files_path' );

        $overrides = [
            'test_form' => false,
            'mimes'     => [ 'csv' => 'text/csv' ],
        ];

        $uploaded = wp_handle_upload( $file, $overrides );

        remove_filter( 'upload_dir', 'workeera_public_upload_files_path' );

        // Reset globals.
        $workeera_upload_files_extpath  = '';
        $workeera_upload_subdir_extpath = '';

        if ( isset( $uploaded['error'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html( $uploaded['error'] ) ] );
        }

        // Save the file path to options.
        update_option( 'workeera_tax_' . $tax_slug . '_upload_data_file', sanitize_text_field( $uploaded['file'] ) );

        // Return total rows for progress tracking (main CSV only; parent phase handled separately).
        $total = self::count_csv_rows( $uploaded['file'] );

        wp_send_json( [
            'error'     => '0',
            'msg'       => esc_html__( 'File uploaded successfully.', 'workeera-remote-tech-job-board' ),
            'total'     => $total,
            'file_path' => esc_html( $uploaded['file'] ),
        ] );
    }

    /**
     * Handles a single import chunk.
     *
     * POST params:
     *   tax_slug   - target taxonomy slug (e.g. 'skills')
     *   source     - 'sample' | 'upload'
     *   phase      - 'parent' | 'main'
     *              'parent' → import rows into the parent taxonomy table
     *                         (e.g. sectors when the user is importing skills).
     *              'main'   → import rows into the main taxonomy table.
     *   offset     - 0-based data-row offset within the CSV being read.
     *   is_first   - '1' only on the very first request of the entire import
     *                (triggers DB wipe of existing data).
     *
     * Response JSON:
     *   error      - '0' on success, '1' on failure
     *   processed  - cumulative data rows processed so far in this phase
     *   total      - total data rows in the CSV for this phase
     *   phase_done - true when this phase is complete
     *   done       - true when the whole import (all phases) is complete
     */
    public function ajax_process_chunk() {
        // Nonce & capability.
        $nonce = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Security check failed.', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ) ] );
        }

        $tax_slug = isset( $_POST['tax_slug'] ) ? sanitize_key( wp_unslash( $_POST['tax_slug'] ) ) : '';
        $source   = isset( $_POST['source'] )   ? sanitize_key( wp_unslash( $_POST['source'] ) )   : 'sample';
        $phase    = isset( $_POST['phase'] )     ? sanitize_key( wp_unslash( $_POST['phase'] ) )    : 'main';
        $offset   = isset( $_POST['offset'] )    ? absint( $_POST['offset'] )                       : 0;
        $is_first = isset( $_POST['is_first'] )  ? ( '1' === sanitize_key( wp_unslash( $_POST['is_first'] ) ) ) : false;

        if ( empty( $tax_slug ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid taxonomy.', 'workeera-remote-tech-job-board' ) ] );
        }

        $all_taxes = Workeera_Custom_Taxonomies::list();
        if ( ! isset( $all_taxes[ $tax_slug ] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Unknown taxonomy.', 'workeera-remote-tech-job-board' ) ] );
        }

        $tax_cfg     = $all_taxes[ $tax_slug ];
        $has_parents = ! empty( $tax_cfg['parents'] );

        // ── Step 1: On the very first chunk, wipe everything. ─────────────────
        if ( $is_first && 'upload' !== $source ) {
            $this->clear_taxonomy_data( $tax_slug );
        }

        // ── Step 2: Route to the correct phase. ───────────────────────────────
        if ( 'parent' === $phase && $has_parents ) {
            $this->handle_parent_phase( $tax_slug, $tax_cfg, $source, $offset );
        } else {
            $this->handle_main_phase( $tax_slug, $tax_cfg, $source, $offset );
        }
    }

    /**
     * Import one chunk of rows into the PARENT taxonomy table.
     *
     * For skills  → inserts into workeera_sectors  (using sectors.csv).
     * For cities  → inserts into workeera_countries (using countries.csv).
     *
     * @param string $tax_slug  Child taxonomy slug (e.g. 'skills').
     * @param array  $tax_cfg   Child taxonomy config.
     * @param string $source    'sample' | 'upload' (used to decide fallback).
     * @param int    $offset    0-based data-row offset in the parent CSV.
     */
    private function handle_parent_phase( $tax_slug, $tax_cfg, $source, $offset ) {
        // We always use the first declared parent (skills/cities each have one).
        $parent_slug = array_key_first( $tax_cfg['parents'] );
        $parent_cfg  = $tax_cfg['parents'][ $parent_slug ];

        // Resolve the parent CSV path.
        $csv_path = ( 'upload' === $source )
            ? self::uploaded_file_path( $tax_slug )
            : self::sample_file_path( $parent_slug );

        if ( empty( $csv_path ) ) {
            // No parent sample file — skip the parent phase silently.
            wp_send_json( [
                'error'      => '0',
                'imported'   => 0,
                'processed'  => 0,
                'total'      => 0,
                'phase_done' => true,
                'done'       => false,
            ] );
        }

        $rows = self::read_csv_chunk( $csv_path, $offset, self::CHUNK_SIZE, 'upload' === $source );
        if ( false === $rows ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Could not read the parent CSV file.', 'workeera-remote-tech-job-board' ) ] );
        }

        $total      = 'upload' === $source ? count($rows) : self::count_csv_rows( $csv_path );
        $imported   = $this->import_rows_for_taxonomy( $parent_slug, $parent_cfg, $rows, $source );
        $processed  = $offset + count( $rows );
        $phase_done = ( $processed >= $total );

        // Mark the parent taxonomy as imported when its phase finishes.
        if ( $phase_done ) {
            update_option( 'workeera_tax_' . $parent_slug . '_data_imported_once', 'yes' );
        }

        wp_send_json( [
            'error'      => '0',
            'imported'   => $imported,
            'processed'  => $processed,
            'total'      => $total,
            'phase_done' => $phase_done,
            'done'       => false, // main phase still to come
        ] );
    }

    /**
     * Import one chunk of rows into the MAIN taxonomy table.
     * At this point, any parent rows are already in the DB.
     *
     * @param string $tax_slug  Taxonomy slug (e.g. 'skills', 'sectors').
     * @param array  $tax_cfg   Taxonomy config.
     * @param string $source    'sample' | 'upload'.
     * @param int    $offset    0-based data-row offset in the main CSV.
     */
    private function handle_main_phase( $tax_slug, $tax_cfg, $source, $offset ) {
        $csv_path = ( 'upload' === $source )
            ? self::uploaded_file_path( $tax_slug )
            : self::sample_file_path( $tax_slug );

        if ( empty( $csv_path ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No data file found.', 'workeera-remote-tech-job-board' ) ] );
        }

        $rows = self::read_csv_chunk( $csv_path, $offset, self::CHUNK_SIZE );
        if ( false === $rows ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Could not read the CSV file.', 'workeera-remote-tech-job-board' ) ] );
        }

        $total      = self::count_csv_rows( $csv_path );
        $imported   = $this->import_rows_for_taxonomy( $tax_slug, $tax_cfg, $rows, $source );
        $processed  = $offset + count( $rows );
        $done       = ( $processed >= $total );

        if ( $done ) {
            update_option( 'workeera_tax_' . $tax_slug . '_data_imported_once', 'yes' );
        }

        wp_send_json( [
            'error'      => '0',
            'imported'   => $imported,
            'processed'  => $processed,
            'total'      => $total,
            'phase_done' => $done,
            'done'       => $done,
        ] );
    }

    /**
     * AJAX handler that streams a bundled sample CSV file as a download.
     */
    public function ajax_download_sample() {
        $nonce = isset( $_GET['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_GET['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'workeera_importdata_download' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'workeera-remote-tech-job-board' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ) );
        }

        $tax_slug = isset( $_GET['tax_slug'] ) ? sanitize_key( wp_unslash( $_GET['tax_slug'] ) ) : '';

        // Whitelist the taxonomy slug against the registered list so the user-supplied
        // value cannot be used to construct an arbitrary file path below.
        $all_taxes = Workeera_Custom_Taxonomies::list();
        $valid_slugs = array_keys( $all_taxes );
        $matched_idx = array_search( $tax_slug, $valid_slugs, true );
        if ( false === $matched_idx ) {
            wp_die( esc_html__( 'Invalid taxonomy.', 'workeera-remote-tech-job-board' ) );
        }
        // Re-bind to the whitelisted key so the value flowing into sample_file_path()
        // and the Content-Disposition header below provably comes from the registered
        // list (not from $_GET).
        $tax_slug = $valid_slugs[ $matched_idx ];

        $file = self::sample_file_path( $tax_slug );

        if ( empty( $file ) ) {
            wp_die( esc_html__( 'Sample file not found for this taxonomy.', 'workeera-remote-tech-job-board' ) );
        }

        // Read via WP_Filesystem.
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        if ( ! $wp_filesystem->is_readable( $file ) ) {
            wp_die( esc_html__( 'Could not read the sample file.', 'workeera-remote-tech-job-board' ) );
        }

        $filename = $tax_slug . '-sample.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        readfile( $file );
        exit;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Wipe all rows from the taxonomy table (and relation / term_counts tables).
     * For child taxonomies (skills, cities) also wipes the parent taxonomy first
     * so it can be fully re-populated in the parent phase.
     *
     * @param string $tax_slug
     */
    protected function clear_taxonomy_data( $tax_slug, $delete_parent = true ) {
        $all_taxes = Workeera_Custom_Taxonomies::list();
        if ( ! isset( $all_taxes[ $tax_slug ] ) ) {
            return;
        }
        $cfg = $all_taxes[ $tax_slug ];

        if ( $delete_parent ) {
            // For taxonomies that have parents, clear the parent taxonomy first.
            $parents = isset( $cfg['parents'] ) ? $cfg['parents'] : [];
            foreach ( $parents as $parent_slug => $parent_cfg ) {
                $this->clear_single_taxonomy( $parent_slug, $parent_cfg );
            }
        }

        // Clear the main taxonomy itself.
        $this->clear_single_taxonomy( $tax_slug, $cfg );
    }

    /**
     * Truncate the taxonomy table + relation table + term_counts rows
     * for a single taxonomy, and delete its "imported once" option.
     *
     * @param string $tax_slug  e.g. 'sectors'
     * @param array  $cfg       Taxonomy config from Workeera_Custom_Taxonomies::list()
     */
    protected function clear_single_taxonomy( $tax_slug, $cfg ) {
        global $wpdb;

        $table_name   = $wpdb->prefix . 'workeera_' . $cfg['table_name'];
        $rel_table    = $wpdb->prefix . 'workeera_' . $cfg['table_name'] . '_relation';
        $counts_table = $wpdb->prefix . 'workeera_term_counts';

        // Truncate the main terms table (resets AUTO_INCREMENT; fastest wipe).
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
            $wpdb->query( "TRUNCATE TABLE `{$table_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        }

        // Truncate the relation table when it exists.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rel_table ) ) === $rel_table ) {
            $wpdb->query( "TRUNCATE TABLE `{$rel_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        }

        // Remove term_counts rows for this taxonomy slug.
        $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $counts_table,
            [ 'term_type' => $tax_slug ],
            [ '%s' ]
        );

        // Remove the "imported once" flag so the import section reappears.
        delete_option( 'workeera_tax_' . $tax_slug . '_data_imported_once' );
    }

    /**
     * Import a slice of parsed CSV rows into the correct taxonomy table.
     *
     * Key behaviours:
     *  - Builds a unique slug in-memory (fetches existing slugs once per chunk
     *    from the DB, then tracks newly added ones) to prevent duplicate-key
     *    errors on the UNIQUE slug column without needing an extra SELECT per row.
     *  - Resolves single and multi-value parent foreign keys via label→ID
     *    lookups (parent rows are already inserted at this point).
     *
     * @param  string  $tax_slug  Target taxonomy slug (e.g. 'sectors', 'skills').
     * @param  array   $cfg       Taxonomy config from Workeera_Custom_Taxonomies::list().
     * @param  array[] $rows      Parsed CSV rows (associative arrays keyed by header).
     * @return int     Number of rows successfully inserted.
     */
    protected function import_rows_for_taxonomy( $tax_slug, $cfg, $rows, $source = 'sample' ) {
        global $wpdb;

        if ( empty( $rows ) ) {
            return 0;
        }

        $table_name  = $wpdb->prefix . 'workeera_' . $cfg['table_name'];
        $has_parents = ! empty( $cfg['parents'] );
        $parents     = $has_parents ? $cfg['parents'] : [];

        /*
         * In-memory slug registry.
         * Pre-load every slug already stored in the DB (from prior chunks)
         * so we never create a duplicate across chunk boundaries.
         * After that we track each newly generated slug within this chunk.
         */
        $used_slugs = $this->fetch_existing_slugs( $table_name );

        $inserted = 0;

        foreach ( $rows as $row ) {
            $label = isset( $row['Titles'] ) ? sanitize_text_field( $row['Titles'] ) : '';
            if ( '' === $label ) {
                continue;
            }

            if ( 'upload' === $source ) {
                $tax_pk = $cfg['primary_key'];
                $term_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT %i FROM `{$table_name}` WHERE `label` = %s LIMIT 1",
                        $tax_pk,
                        $label
                    )
                );
                if ( $term_id ) {
                    continue;
                }
            }

            $base_slug = workeera_make_title_slug( $label );
            $slug      = $this->unique_slug( $base_slug, $used_slugs );
            if ( '' === $slug ) {
                continue; // label produced an empty base slug — skip.
            }

            // Register slug immediately so subsequent rows in this chunk
            // cannot collide with it before it is persisted to the DB.
            $used_slugs[ $slug ] = true;

            $data   = [ 'label' => $label, 'slug' => $slug ];
            $format = [ '%s', '%s' ];

            // ── Resolve parent foreign keys ───────────────────────────────────
            if ( $has_parents ) {
                foreach ( $parents as $parent_slug => $parent_cfg ) {
                    $is_multi         = ! empty( $parent_cfg['is_multi'] );
                    $parent_pk        = $parent_cfg['primary_key'];
                    $parent_table     = $wpdb->prefix . 'workeera_' . $parent_cfg['table_name'];
                    $parent_raw_value = isset( $row['Parent'] ) ? trim( $row['Parent'] ) : '';

                    if ( $is_multi ) {
                        /*
                         * Multi-parent (skills → sectors).
                         * CSV "Parent" column contains sector names separated by "|".
                         * Stored as serialised array: ['term(1)','term(3)'].
                         * Column name = parent_pk + 's'  (e.g. sector_ids).
                         */
                        $parent_names   = array_filter( array_map( 'trim', explode( '|', $parent_raw_value ) ) );
                        $parent_id_refs = [];

                        foreach ( $parent_names as $parent_name ) {
                            $parent_name_clean = sanitize_text_field( $parent_name );
                            if ( '' === $parent_name_clean ) {
                                continue;
                            }
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
                            $parent_id = (int) $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT %i FROM `{$parent_table}` WHERE `label` = %s LIMIT 1",
                                    $parent_pk,
                                    $parent_name_clean
                                )
                            );
                            if ( $parent_id > 0 ) {
                                $parent_id_refs[] = 'term(' . $parent_id . ')';
                            }
                        }

                        $col_name          = $parent_pk . 's'; // e.g. sector_ids
                        $data[ $col_name ] = ! empty( $parent_id_refs )
                            ? maybe_serialize( $parent_id_refs )
                            : '';
                        $format[]          = '%s';

                    } else {
                        /*
                         * Single parent (cities → countries).
                         * CSV "Parent" column contains one country name.
                         * Stored as a plain integer FK (e.g. country_id).
                         */
                        $parent_name_clean = sanitize_text_field( $parent_raw_value );
                        $parent_id         = 0;

                        if ( '' !== $parent_name_clean ) {
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
                            $parent_id = (int) $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT %i FROM `{$parent_table}` WHERE `label` = %s LIMIT 1",
                                    $parent_pk,
                                    $parent_name_clean
                                )
                            );
                        }

                        $data[ $parent_pk ] = $parent_id;
                        $format[]           = '%d';
                    }
                }
            }

            $result = $wpdb->insert( $table_name, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if ( false !== $result ) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Fetch all slugs that already exist in a taxonomy table as a
     * slug → true map for O(1) in-memory lookup.
     *
     * @param  string $table_name  Fully-qualified table name (with DB prefix).
     * @return array<string, bool>
     */
    protected function fetch_existing_slugs( $table_name ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $slugs = $wpdb->get_col( "SELECT `slug` FROM `{$table_name}`" );

        $map = [];
        if ( ! empty( $slugs ) ) {
            foreach ( $slugs as $slug ) {
                $map[ (string) $slug ] = true;
            }
        }
        return $map;
    }

    /**
     * Return a slug that does not exist in $used_slugs.
     * Appends -2, -3, … until a free slot is found (mirrors the existing
     * Workeera_Taxonomies::verify_slug() logic but operates purely in memory).
     *
     * $used_slugs is passed by reference so the caller can register the
     * returned slug immediately without an extra call.
     *
     * @param  string               $base_slug  Raw slug from workeera_make_title_slug().
     * @param  array<string, bool>  $used_slugs In-memory registry (passed by reference).
     * @return string  Unique slug, or '' when $base_slug itself is empty.
     */
    protected function unique_slug( $base_slug, array &$used_slugs ) {
        if ( '' === $base_slug ) {
            return '';
        }

        $candidate = $base_slug;
        $suffix    = 2;

        while ( isset( $used_slugs[ $candidate ] ) ) {
            $candidate = $base_slug . '-' . $suffix;
            $suffix++;

            // Safety valve — should never be reached with real-world data.
            if ( $suffix > 1000 ) {
                $candidate = $base_slug . '-' . uniqid();
                break;
            }
        }

        return $candidate;
    }

    // -------------------------------------------------------------------------
    // CSV helpers
    // -------------------------------------------------------------------------

    /**
     * Count data rows in a CSV (excluding the header row).
     *
     * @param  string $file_path
     * @return int
     */
    public static function count_csv_rows( $file_path ) {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return 0;
        }

        $content = $wp_filesystem->get_contents( $file_path );
        if ( false === $content ) {
            return 0;
        }

        // Normalise line endings.
        $content = str_replace( [ "\r\n", "\r" ], "\n", $content );
        $lines   = array_filter( explode( "\n", $content ), 'strlen' );

        // Subtract 1 for the header row.
        return max( 0, count( $lines ) - 1 );
    }

    /**
     * Read a chunk of CSV rows from $file_path.
     *
     * @param  string    $file_path
     * @param  int       $offset    0-based data-row offset (0 = first data row after header).
     * @param  int       $limit     Max rows to return.
     * @return array[]|false  Associative arrays keyed by CSV header, or false on error.
     */
    public static function read_csv_chunk( $file_path, $offset = 0, $limit = 50, $parent_titles = false ) {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        if ( ! $wp_filesystem->exists( $file_path ) ) {
            return false;
        }

        $content = $wp_filesystem->get_contents( $file_path );
        if ( false === $content ) {
            return false;
        }

        // Normalise line endings.
        $content = str_replace( [ "\r\n", "\r" ], "\n", $content );
        $lines   = array_values( array_filter( explode( "\n", $content ), 'strlen' ) );

        if ( empty( $lines ) ) {
            return [];
        }

        // Parse header row.
        $header = str_getcsv( array_shift( $lines ) );
        $header = array_map( 'trim', $header );

        if ( $parent_titles ) {
            $parent_index = array_search( 'Parent', $header );
            if ( false === $parent_index ) {
                return [ 'Titles' => [] ]; // Return empty array if 'Parent' column doesn't exist
            }

            $titles = [];
            foreach ( $lines as $line ) {
                $values = str_getcsv( $line );
                
                // Skip if the row doesn't have enough columns
                if ( count( $values ) <= $parent_index ) {
                    continue;
                }
                
                $parent_value = trim( $values[ $parent_index ] );
                
                // Skip empty values
                if ( '' !== $parent_value ) {
                    if ( strpos($parent_value, '|') !== false ) {
                        $parent_names_arr = array_filter( array_map( 'trim', explode( '|', $parent_value ) ) );
                        foreach ($parent_names_arr as $parent_name) {
                            $titles[] = $parent_name;
                        }
                    } else {
                        $titles[] = $parent_value;
                    }
                }
            }

            $rows = [];
            if ( ! empty($titles) ) {
                $unique_rows = array_values( array_unique( $titles ) );
                foreach ( $unique_rows as $unique_row ) {
                    $rows[] = ['Titles' => $unique_row];
                }
            }

            // Return unique values
            return $rows;
            
        } else {

            // Apply offset and limit to the remaining data rows.
            $chunk = array_slice( $lines, $offset, $limit );

            $rows = [];
            foreach ( $chunk as $line ) {
                $values = str_getcsv( $line );
                // Pad to header length to guard against ragged rows.
                $padded = array_pad( $values, count( $header ), '' );
                // Trim excess columns if the row has more than the header.
                $padded = array_slice( $padded, 0, count( $header ) );
                // Skip completely empty rows.
                if ( '' === trim( $padded[0] ) ) {
                    continue;
                }
                $rows[] = array_combine( $header, array_map( 'trim', $padded ) );
            }

            return $rows;
        }
    }

    /**
     * Run a full sample-data import for one taxonomy synchronously.
     *
     * Used by the setup wizard. Wipes the taxonomy table (and parent table when
     * applicable), reads the bundled sample CSV in chunks via read_csv_chunk(),
     * and inserts every row via import_rows_for_taxonomy(). Returns a summary
     * array containing the total rows imported and any error message.
     *
     * @param  string $tax_slug Taxonomy slug (e.g. 'sectors').
     * @return array            [ 'imported' => int, 'error' => string ].
     */
    public static function run_sample_import_sync( $tax_slug ) {
        $tax_slug = sanitize_key( $tax_slug );

        if ( empty( $tax_slug ) ) {
            return [ 'imported' => 0, 'error' => esc_html__( 'Invalid taxonomy slug.', 'workeera-remote-tech-job-board' ) ];
        }

        $all_taxes = Workeera_Custom_Taxonomies::list();
        if ( ! isset( $all_taxes[ $tax_slug ] ) ) {
            return [ 'imported' => 0, 'error' => esc_html__( 'Unknown taxonomy.', 'workeera-remote-tech-job-board' ) ];
        }

        $tax_cfg     = $all_taxes[ $tax_slug ];
        $has_parents = ! empty( $tax_cfg['parents'] );

        // Sample CSV must exist for this taxonomy.
        $csv_path = self::sample_file_path( $tax_slug );
        if ( empty( $csv_path ) ) {
            return [ 'imported' => 0, 'error' => esc_html__( 'No sample data file found for this taxonomy.', 'workeera-remote-tech-job-board' ) ];
        }

        // We need access to the protected helpers — use an instance.
        $importer = new self();

        // Wipe existing data (parent + main) so the import is idempotent.
        $importer->clear_taxonomy_data( $tax_slug );

        $imported_total = 0;

        // ── Parent phase ─────────────────────────────────────────────────────
        if ( $has_parents ) {
            $parent_slug = array_key_first( $tax_cfg['parents'] );
            $parent_cfg  = $tax_cfg['parents'][ $parent_slug ];
            $parent_csv  = self::sample_file_path( $parent_slug );

            if ( ! empty( $parent_csv ) ) {
                $parent_total = self::count_csv_rows( $parent_csv );
                $offset       = 0;

                while ( $offset < $parent_total ) {
                    $rows = self::read_csv_chunk( $parent_csv, $offset, self::CHUNK_SIZE );
                    if ( false === $rows ) {
                        return [ 'imported' => $imported_total, 'error' => esc_html__( 'Could not read the parent CSV file.', 'workeera-remote-tech-job-board' ) ];
                    }
                    if ( empty( $rows ) ) {
                        break;
                    }
                    $imported_total += (int) $importer->import_rows_for_taxonomy( $parent_slug, $parent_cfg, $rows, 'sample' );
                    $offset += count( $rows );
                }

                update_option( 'workeera_tax_' . $parent_slug . '_data_imported_once', 'yes' );
            }
        }

        // ── Main phase ───────────────────────────────────────────────────────
        $total  = self::count_csv_rows( $csv_path );
        $offset = 0;

        while ( $offset < $total ) {
            $rows = self::read_csv_chunk( $csv_path, $offset, self::CHUNK_SIZE );
            if ( false === $rows ) {
                return [ 'imported' => $imported_total, 'error' => esc_html__( 'Could not read the CSV file.', 'workeera-remote-tech-job-board' ) ];
            }
            if ( empty( $rows ) ) {
                break;
            }
            $imported_total += (int) $importer->import_rows_for_taxonomy( $tax_slug, $tax_cfg, $rows, 'sample' );
            $offset += count( $rows );
        }

        update_option( 'workeera_tax_' . $tax_slug . '_data_imported_once', 'yes' );

        return [ 'imported' => $imported_total, 'error' => '' ];
    }
}

new Workeera_Import_Data;