<?php
/**
 * Workeera — uninstall handler.
 *
 * Fires when the user deletes the plugin from the wp-admin Plugins screen
 * (NOT on simple deactivation). Loaded by WordPress instead of the main
 * plugin file when `WP_UNINSTALL_PLUGIN` is defined.
 *
 * Conservative by default:
 *   - Scheduled cron events are always cleared (their callbacks are about
 *     to disappear, so leaving them registered would log "missing schedule"
 *     warnings on every wp-cron tick).
 *
 * Destructive cleanup (drop tables, delete options, delete posts, delete
 * uploaded files) only runs when the site owner has explicitly opted in,
 * via ANY of the following:
 *   - The "Remove all data on uninstall" switch in Settings → General
 *     (stored as workeera_general_settings[remove_data_on_uninstall] = 'on').
 *   - The standalone option `workeera_remove_data_on_uninstall` = 'yes'.
 *   - The PHP constant `WORKEERA_REMOVE_ALL_DATA_ON_UNINSTALL` defined truthy
 *     in wp-config.php (developer escape hatch).
 *
 * Multisite-aware: when invoked on a network, the cleanup runs once per
 * site so each site's options + tables are dropped under their own prefix.
 *
 * @package Workeera
 */

// Exit if this file is loaded directly rather than via the WP uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'workeera_uninstall_run_for_site' ) ) {
    /**
     * Run the uninstall cleanup for a single WordPress site.
     *
     * @param bool $remove_data Whether to perform the destructive cleanup
     *                          (drop tables, delete options, posts, files).
     *                          Cron events are always cleared regardless.
     * @return void
     */
    function workeera_uninstall_run_for_site( $remove_data ) {
        global $wpdb;

        // ---------------------------------------------------------------------
        // 1. Always — clear scheduled cron events.
        // ---------------------------------------------------------------------
        $workeera_cron_hooks = array(
            'workeera_delete_past_transients',
            'workeera_check_expired_jobs',
            'workeera_clean_orphan_applications',
            'workeera_refresh_term_counts',
        );
        foreach ( $workeera_cron_hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            while ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
                $timestamp = wp_next_scheduled( $hook );
            }
            // Belt-and-braces — purge any orphaned entries by hook name.
            wp_clear_scheduled_hook( $hook );
        }

        // If the site owner hasn't opted in to data removal, stop here.
        if ( ! $remove_data ) {
            return;
        }

        // ---------------------------------------------------------------------
        // 2. Drop the plugin's custom database tables.
        // ---------------------------------------------------------------------
        $prefix = $wpdb->prefix;

        $workeera_tables = array(
            // Core data tables (see includes/class-activator.php).
            'workeera_job_fields',
            'workeera_metadata',
            'workeera_term_counts',
            'workeera_locations',
            'workeera_applications',
            'workeera_transients',
            // Taxonomy term + relation tables (see admin/taxonomies/common-taxonomy.php).
            'workeera_countries',
            'workeera_cities',
            'workeera_sectors',
            'workeera_sectors_relation',
            'workeera_skills',
            'workeera_skills_relation',
            'workeera_jobtypes',
            'workeera_jobtypes_relation',
            'workeera_timezones',
            'workeera_timezones_relation',
            'workeera_benefits',
            'workeera_benefits_relation',
            'workeera_experiences',
            'workeera_experiences_relation',
        );

        foreach ( $workeera_tables as $table ) {
            // Identifiers can't be bound through $wpdb->prepare, but the table
            // names above are hardcoded constants — not user input — so direct
            // interpolation is safe here.
            $wpdb->query( "DROP TABLE IF EXISTS `{$prefix}{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // ---------------------------------------------------------------------
        // 3. Delete plugin options (settings + taxonomy-import flags).
        // ---------------------------------------------------------------------
        $workeera_options = array(
            'workeera_general_settings',
            'workeera_pages_settings',
            'workeera_job_listings_settings',
            'workeera_job_apply_settings',
            'workeera_job_submission_settings',
            'workeera_candidate_settings',
            'workeera_remove_data_on_uninstall',
            // DB schema version stamp (class-db-updater.php).
            'workeera_db_version',
            // Setup wizard lifecycle flags.
            'workeera_setup_wizard_status',
            'workeera_setup_wizard_notice_dismissed',
        );
        foreach ( $workeera_options as $option_name ) {
            delete_option( $option_name );
            if ( is_multisite() ) {
                delete_site_option( $option_name );
            }
        }

        // Per-taxonomy import flags + uploaded-file pointers are dynamic
        // (workeera_tax_<slug>_data_imported_once / _upload_data_file).
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( 'workeera_tax_' ) . '%'
            )
        );

        // ---------------------------------------------------------------------
        // 4. Delete custom-post-type entries (and their core meta) for jobs +
        //    candidates. We use wp_delete_post( $id, true ) so the trash bin
        //    is bypassed and core also cleans wp_postmeta / wp_term_relationships.
        // ---------------------------------------------------------------------
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( %s, %s )",
                'workeera_job',
                'workeera_candidate'
            )
        );
        if ( ! empty( $post_ids ) ) {
            foreach ( $post_ids as $post_id ) {
                wp_delete_post( (int) $post_id, true );
            }
        }

        // ---------------------------------------------------------------------
        // 5. Delete user meta the plugin writes against WP user accounts.
        // ---------------------------------------------------------------------
        delete_metadata( 'user', 0, 'workeera_user_type', '', true );

        // ---------------------------------------------------------------------
        // 6. Remove the private upload folder (CV files, application snapshots,
        //    profile pictures, company logos).
        // ---------------------------------------------------------------------
        $upload_dir = wp_get_upload_dir();
        $base       = isset( $upload_dir['basedir'] ) ? trailingslashit( $upload_dir['basedir'] ) . 'wp-workeera' : '';
        if ( $base !== '' && is_dir( $base ) ) {
            workeera_uninstall_rrmdir( $base );
        }
    }
}

if ( ! function_exists( 'workeera_uninstall_rrmdir' ) ) {
    /**
     * Recursively delete a directory and everything inside it.
     *
     * Uses the WP_Filesystem API rather than direct PHP filesystem calls
     * (unlink()/rmdir()), per WordPress coding standards.
     *
     * @param  string $dir Absolute path to the directory to delete.
     * @return void
     */
    function workeera_uninstall_rrmdir( $dir ) {
        if ( empty( $dir ) ) {
            return;
        }

        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        // Initialise the filesystem abstraction. The direct transport is used
        // when the target is server-writable, which it is for the plugin's own
        // upload folder.
        if ( empty( $wp_filesystem ) ) {
            WP_Filesystem();
        }

        if ( ! $wp_filesystem || ! $wp_filesystem->is_dir( $dir ) ) {
            return;
        }

        // Recursively delete the directory and all of its contents.
        $wp_filesystem->delete( $dir, true );
    }
}

if ( ! function_exists( 'workeera_uninstall_should_remove_data' ) ) {
    /**
     * Decide whether the destructive cleanup should run for the current site.
     *
     * Sources checked, in order:
     *   1. The PHP constant `WORKEERA_REMOVE_ALL_DATA_ON_UNINSTALL` (developer
     *      escape hatch — wins everywhere if defined truthy in wp-config.php).
     *   2. The Settings → General switch, stored as
     *      `workeera_general_settings[remove_data_on_uninstall] = 'on'`.
     *   3. The legacy standalone option `workeera_remove_data_on_uninstall`,
     *      kept for direct API / WP-CLI usage outside the settings screen.
     *
     * @return bool True when destructive cleanup should run.
     */
    function workeera_uninstall_should_remove_data() {
        if ( defined( 'WORKEERA_REMOVE_ALL_DATA_ON_UNINSTALL' ) && WORKEERA_REMOVE_ALL_DATA_ON_UNINSTALL ) {
            return true;
        }
        $general = get_option( 'workeera_general_settings', array() );
        if ( is_array( $general ) && isset( $general['remove_data_on_uninstall'] ) && 'on' === $general['remove_data_on_uninstall'] ) {
            return true;
        }
        if ( 'yes' === get_option( 'workeera_remove_data_on_uninstall', 'no' ) ) {
            return true;
        }
        return false;
    }
}

// -----------------------------------------------------------------------------
// Decide whether to perform the destructive cleanup for THIS site.
// -----------------------------------------------------------------------------
if ( is_multisite() ) {
    // Walk every site in the network — each has its own options + tables.
    $workeera_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    if ( ! empty( $workeera_sites ) ) {
        foreach ( $workeera_sites as $workeera_site_id ) {
            switch_to_blog( (int) $workeera_site_id );
            workeera_uninstall_run_for_site( workeera_uninstall_should_remove_data() );
            restore_current_blog();
        }
    }
} else {
    workeera_uninstall_run_for_site( workeera_uninstall_should_remove_data() );
}
