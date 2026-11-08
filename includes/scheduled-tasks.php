<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Registers custom cron intervals and the scheduled-task handlers that
 * garbage-collect transients, expire stale jobs, prune orphan applications,
 * and refresh cached term counts.
 */
class Workeera_Scheduled_Tasks {

    /**
     * How many seconds ahead of NOW() a job is considered "soon to expire".
     * The expired-jobs cron runs every 30 minutes; matching this window means
     * a job is moved to draft up to 30 minutes before its actual expiry, so
     * we never serve listings whose expiry is imminent.
     */
    const EXPIRE_LOOKAHEAD_SECONDS = 30 * MINUTE_IN_SECONDS;

    /**
     * Per-run cap for the orphan-applications cron. Bounds the worst-case
     * cost on sites with very large workeera_applications tables.
     */
    const ORPHAN_APPS_BATCH = 200;

    /**
     * Per-run cap for the term-count refresh cron.
     */
    const TERM_COUNTS_BATCH = 250;

    /**
     * Custom-transient names used to checkpoint progress between runs.
     */
    const TRANS_ORPHAN_APPS_CHECKED = 'workeera_orphan_apps_checked';
    const TRANS_TERM_COUNTS_DONE    = 'workeera_term_counts_processed';

    /**
     * Register the cron-schedules filter and bind each cron hook to its handler.
     */
    public function __construct() {
        add_filter( 'cron_schedules', [ $this, 'custom_cron_intervals' ] );

        add_action( 'workeera_delete_past_transients',     [ $this, 'delete_past_transients' ] );
        add_action( 'workeera_check_expired_jobs',         [ $this, 'check_expired_jobs' ] );
        add_action( 'workeera_clean_orphan_applications',  [ $this, 'clean_orphan_applications' ] );
        add_action( 'workeera_refresh_term_counts',        [ $this, 'refresh_term_counts' ] );
    }

    /**
     * Add the plugin's 5-minute, 30-minute, and 6-hour cron intervals.
     *
     * @param  array $schedules Existing WP cron schedules.
     * @return array            Schedules array with plugin entries appended.
     */
    public function custom_cron_intervals( $schedules ) {
        if ( ! isset( $schedules['workeera_five_minutes'] ) ) {
            $schedules['workeera_five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => esc_html__( 'Every 5 Minutes', 'workeera-remote-tech-job-board' ),
            ];
        }
        if ( ! isset( $schedules['workeera_thirty_minutes'] ) ) {
            $schedules['workeera_thirty_minutes'] = [
                'interval' => 30 * MINUTE_IN_SECONDS,
                'display'  => esc_html__( 'Every 30 Minutes', 'workeera-remote-tech-job-board' ),
            ];
        }
        if ( ! isset( $schedules['workeera_six_hours'] ) ) {
            $schedules['workeera_six_hours'] = [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display'  => esc_html__( 'Every 6 Hours', 'workeera-remote-tech-job-board' ),
            ];
        }
        return $schedules;
    }

    /**
     * Cron handler: delegate to Workeera_Transients to purge expired transients.
     */
    public function delete_past_transients() {
        if ( class_exists( 'Workeera_Transients' ) ) {
            Workeera_Transients::delete_past_transients();
        }
    }

    // -------------------------------------------------------------------------
    // Expire jobs whose expiry_date has passed (or is within 30 minutes).
    // Hooked: workeera_check_expired_jobs (every 30 minutes).
    // -------------------------------------------------------------------------

    /**
     * Cron handler: move published workeera_job posts whose expiry_date has
     * passed (or falls within the lookahead window) to draft and mark them
     * as expired in workeera_job_fields.
     */
    public function check_expired_jobs() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'posts';
        $jf_table    = $wpdb->prefix . 'workeera_job_fields';
        $cutoff      = gmdate( 'Y-m-d H:i:s', time() + self::EXPIRE_LOOKAHEAD_SECONDS );

        $job_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$posts_table} AS p
                 INNER JOIN {$jf_table} AS jf ON jf.job_id = p.ID
                 WHERE p.post_type = %s
                   AND p.post_status = %s
                   AND jf.expiry_date IS NOT NULL
                   AND jf.expiry_date <= %s",
                'workeera_job',
                'publish',
                $cutoff
            )
        );

        if ( empty( $job_ids ) ) {
            return;
        }

        foreach ( $job_ids as $job_id_raw ) {
            $job_id = absint( $job_id_raw );
            if ( $job_id < 1 ) {
                continue;
            }

            // Move post to draft. wp_update_post fires transition_post_status,
            // which the plugin already hooks (post-types.php) to keep term
            // counts in sync — so we let it run rather than touching wp_posts
            // directly.
            $result = wp_update_post( [
                'ID'          => $job_id,
                'post_status' => 'draft',
            ], true );

            if ( is_wp_error( $result ) || (int) $result < 1 ) {
                continue;
            }

            // Mark the listing as expired in the custom-table side. Done after
            // wp_update_post so any default listing_status set during transition
            // is overridden with the explicit "expired" terminal state.
            $wpdb->update(
                $jf_table,
                [ 'listing_status' => 'expired' ],
                [ 'job_id'         => $job_id ],
                [ '%s' ],
                [ '%d' ]
            );

            /**
             * Fires after the cron drafts an expired job.
             *
             * @param int $job_id
             */
            do_action( 'workeera_after_job_auto_expired', $job_id );
        }
    }

    // -------------------------------------------------------------------------
    //  Remove applications whose target job post no longer exists.
    //  Hooked: workeera_clean_orphan_applications (hourly).
    // -------------------------------------------------------------------------

    /**
     * Cron handler: walk workeera_applications in bounded batches and delete
     * any row whose target job post no longer exists. Progress is
     * checkpointed in a custom transient so subsequent runs continue where
     * the previous one left off.
     */
    public function clean_orphan_applications() {
        global $wpdb;

        $apps_table  = $wpdb->prefix . 'workeera_applications';
        $posts_table = $wpdb->prefix . 'posts';

        $checked = Workeera_Transients::get( self::TRANS_ORPHAN_APPS_CHECKED );
        if ( ! is_array( $checked ) ) {
            $checked = [];
        }
        // Sanitise once on entry — the value comes back from a maybe_unserialize.
        $checked = array_values( array_unique( array_filter( array_map( 'absint', $checked ) ) ) );

        // Build a SELECT that excludes already-checked IDs. We pull a small
        // batch per run so the cron is bounded regardless of table size.
        $exclude_sql  = '';
        $exclude_args = [];
        if ( ! empty( $checked ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $checked ), '%d' ) );
            $exclude_sql  = " AND a.application_id NOT IN ({$placeholders}) ";
            $exclude_args = $checked;
        }

        $batch_args = array_merge( $exclude_args, [ self::ORPHAN_APPS_BATCH ] );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.application_id, a.job_id
                 FROM {$apps_table} AS a
                 WHERE 1=1 {$exclude_sql}
                 ORDER BY a.application_id ASC
                 LIMIT %d",
                $batch_args
            ),
            ARRAY_A
        );

        $rows = is_array( $rows ) ? $rows : [];
        $processed_this_run = count( $rows );

        if ( $processed_this_run < 1 ) {
            // Nothing left to inspect — clear the checkpoint so the next run
            // starts a fresh full pass. Keeps memory bounded on long-lived sites.
            Workeera_Transients::delete( self::TRANS_ORPHAN_APPS_CHECKED );
            return;
        }

        // Resolve which of the referenced job IDs actually exist.
        $job_ids = array_unique( array_filter( array_map(
            function ( $r ) { return absint( $r['job_id'] ); },
            $rows
        ) ) );

        $existing_job_ids = [];
        if ( ! empty( $job_ids ) ) {
            $job_placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
            $existing_job_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$posts_table}
                     WHERE post_type = %s AND ID IN ({$job_placeholders})",
                    array_merge( [ 'workeera_job' ], $job_ids )
                )
            );
            $existing_job_ids = array_map( 'absint', (array) $existing_job_ids );
        }
        $existing_set = array_flip( $existing_job_ids );

        $to_delete    = [];
        $newly_valid  = [];

        foreach ( $rows as $row ) {
            $app_id = absint( $row['application_id'] );
            $job_id = absint( $row['job_id'] );

            if ( $app_id < 1 ) {
                continue;
            }

            if ( $job_id > 0 && isset( $existing_set[ $job_id ] ) ) {
                $newly_valid[] = $app_id;
            } else {
                $to_delete[] = $app_id;
            }
        }

        // Delete orphans in one shot (still parameterised).
        if ( ! empty( $to_delete ) ) {
            $del_placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$apps_table} WHERE application_id IN ({$del_placeholders})",
                    $to_delete
                )
            );

            /**
             * Fires after the cron deletes orphan applications.
             *
             * @param int[] $deleted_application_ids
             */
            do_action( 'workeera_after_orphan_applications_deleted', $to_delete );
        }

        // Persist the checkpoint of valid IDs.
        if ( ! empty( $newly_valid ) ) {
            $checked = array_values( array_unique( array_merge( $checked, $newly_valid ) ) );
        }

        // Bookkeeping: if this run was partial (still pages to process), save
        // the updated checkpoint; if it was the tail end, clear so next run
        // starts fresh.
        if ( $processed_this_run >= self::ORPHAN_APPS_BATCH ) {
            Workeera_Transients::set( [
                'name'    => self::TRANS_ORPHAN_APPS_CHECKED,
                'value'   => $checked,
                'timeout' => DAY_IN_SECONDS,
            ] );
        } else {
            Workeera_Transients::delete( self::TRANS_ORPHAN_APPS_CHECKED );
        }
    }

    // -------------------------------------------------------------------------
    // Refresh workeera_term_counts by recounting actual relations.
    // Hooked: workeera_refresh_term_counts (every 6 hours).
    // -------------------------------------------------------------------------

    /**
     * Cron handler: recount object relations for each plugin taxonomy term
     * and upsert the result into workeera_term_counts. Bounded per run via
     * a checkpoint transient so very large term sets process across cycles.
     */
    public function refresh_term_counts() {
        global $wpdb;

        if ( ! class_exists( 'Workeera_Object_Terms_Relation' ) ) {
            return;
        }

        $processed = Workeera_Transients::get( self::TRANS_TERM_COUNTS_DONE );
        if ( ! is_array( $processed ) ) {
            $processed = [];
        }
        // Build a hash for quick lookup (string keys → 1).
        $processed_set = array_fill_keys( array_filter( array_map( 'strval', $processed ) ), 1 );

        // Merge job + candidate taxonomy maps into one walkable list keyed by
        // taxonomy slug. Each entry tracks which object_types apply (shared
        // taxonomies like sectors/skills count for both).
        $jobs_map  = Workeera_Object_Terms_Relation::post_type_taxonomy_map( 'job' );
        $cands_map = Workeera_Object_Terms_Relation::post_type_taxonomy_map( 'candidate' );

        $all_taxes = [];
        foreach ( $jobs_map as $slug => $cfg ) {
            $all_taxes[ $slug ] = [ 'cfg' => $cfg, 'object_types' => [ 'job' ] ];
        }
        foreach ( $cands_map as $slug => $cfg ) {
            if ( isset( $all_taxes[ $slug ] ) ) {
                $all_taxes[ $slug ]['object_types'][] = 'candidate';
            } else {
                $all_taxes[ $slug ] = [ 'cfg' => $cfg, 'object_types' => [ 'candidate' ] ];
            }
        }

        $processed_this_run = 0;
        $batch_limit        = self::TERM_COUNTS_BATCH;

        $batch_done = false;
        foreach ( $all_taxes as $tax_slug => $info ) {
            if ( $batch_done ) {
                break;
            }

            $cfg         = $info['cfg'];
            $object_types = $info['object_types'];

            // Validate the config-derived table/column identifiers before they
            // are interpolated into SQL (identifiers can't be bound via prepare).
            $tax_table_s = workeera_safe_sql_identifier( $cfg['table'] );
            $rel_table_s = workeera_safe_sql_identifier( $cfg['rel_table'] );
            $primary_key = workeera_safe_sql_identifier( $cfg['primary_key'] );
            $rel_key     = workeera_safe_sql_identifier( $cfg['rel_key'] );
            if ( $tax_table_s === '' || $rel_table_s === '' || $primary_key === '' || $rel_key === '' ) {
                continue;
            }
            $tax_table   = $wpdb->prefix . $tax_table_s;
            $rel_table   = $wpdb->prefix . $rel_table_s;

            // Pull all term IDs for this taxonomy. Term tables are typically
            // small (curated lists), so this is fine. This query has no value
            // parameters — only the (validated, backticked) identifiers — so
            // there is nothing for $wpdb->prepare() to bind here.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers validated via workeera_safe_sql_identifier() + backticked; no value params to bind.
            $term_ids = $wpdb->get_col( "SELECT `{$primary_key}` FROM `{$tax_table}`" );
            if ( empty( $term_ids ) ) {
                continue;
            }

            foreach ( $term_ids as $tid_raw ) {
                $tid = absint( $tid_raw );
                if ( $tid < 1 ) {
                    continue;
                }

                foreach ( $object_types as $object_type ) {
                    $key = $tax_slug . ':' . $object_type . ':' . $tid;
                    if ( isset( $processed_set[ $key ] ) ) {
                        continue;
                    }
                    if ( $processed_this_run >= $batch_limit ) {
                        $batch_done = true;
                        break 2;
                    }

                    $count = self::recount_term_objects( $rel_table, $rel_key, $tid, $object_type );
                    self::upsert_term_count( $tid, $cfg['plural_slug'], $object_type, $count );

                    $processed_set[ $key ] = 1;
                    $processed_this_run++;
                }
            }
        }

        // If we hit the batch limit there is more to do — save the checkpoint;
        // otherwise we finished the cycle and should reset for next time.
        if ( $processed_this_run >= $batch_limit ) {
            Workeera_Transients::set( [
                'name'    => self::TRANS_TERM_COUNTS_DONE,
                'value'   => array_keys( $processed_set ),
                'timeout' => 72 * HOUR_IN_SECONDS,
            ] );
        } else {
            Workeera_Transients::delete( self::TRANS_TERM_COUNTS_DONE );
        }
    }

    /**
     * Count published posts of a given object type that reference a term
     * through its relation table.
     *
     * @param string $rel_table   Fully prefixed relation table name.
     * @param string $rel_key     Term FK column in the relation table.
     * @param int    $term_id
     * @param string $object_type 'job' | 'candidate'
     * @return int
     */
    private static function recount_term_objects( $rel_table, $rel_key, $term_id, $object_type ) {
        global $wpdb;

        // Defensive re-validation of the identifiers at the query site (the
        // caller already validates, but identifiers can't be bound via prepare).
        $rel_table = workeera_safe_sql_identifier( $rel_table );
        $rel_key   = workeera_safe_sql_identifier( $rel_key );
        if ( $rel_table === '' || $rel_key === '' ) {
            return 0;
        }

        $post_type = ( $object_type === 'candidate' ) ? 'workeera_candidate' : 'workeera_job';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM `{$rel_table}` AS r
                 INNER JOIN {$wpdb->posts} AS p ON p.ID = r.object_id
                 WHERE r.%i = %d
                   AND r.object_type = %s
                   AND p.post_type   = %s
                   AND p.post_status = %s",
                $rel_key,
                absint( $term_id ),
                'post',
                $post_type,
                'publish'
            )
        );
    }

    /**
     * Insert-or-update the count row for one (term_id, term_type, object_type)
     * tuple in workeera_term_counts.
     */
    private static function upsert_term_count( $term_id, $term_type, $object_type, $count ) {
        global $wpdb;

        $table = $wpdb->prefix . 'workeera_term_counts';

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT term_count_id FROM `{$table}`
                 WHERE term_id = %d AND term_type = %s AND object_type = %s
                 LIMIT 1",
                absint( $term_id ),
                $term_type,
                $object_type
            )
        );

        if ( $existing_id ) {
            $wpdb->update(
                $table,
                [ 'count' => absint( $count ) ],
                [ 'term_count_id' => absint( $existing_id ) ],
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
                    'count'       => absint( $count ),
                ],
                [ '%d', '%s', '%s', '%d' ]
            );
        }
    }
}

new Workeera_Scheduled_Tasks;
