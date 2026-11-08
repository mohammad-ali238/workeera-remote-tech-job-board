<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Incremental database schema updater.
 *
 * The activation hook only creates tables that don't exist yet, so schema
 * changes shipped in plugin updates never reach existing installs through
 * it. This class closes that gap: it keeps the schema version an install
 * last updated to in the options table (`workeera_db_version`) and, whenever
 * the plugin code is newer, runs every pending update step in order before
 * stamping the new version.
 *
 * Adding a schema change in a future release:
 *  1. Change the CREATE TABLE statement in class-activator.php (fresh installs).
 *  2. Add a `'x.y.z' => [ 'update_callback' ]` entry to db_updates() below
 *     with an idempotent callback (existing installs).
 */
class Workeera_DB_Updater {

    /**
     * Option name storing the schema version this install last updated to.
     */
    const DB_VERSION_OPTION = 'workeera_db_version';

    /**
     * Schema version assumed for installs that predate this updater — they
     * carry the original 1.0.1 table layout and the option doesn't exist yet.
     */
    const PRE_UPDATER_VERSION = '1.0.1';

    /**
     * Watch for a plugin version change on every admin load (cheap option
     * compare; the update steps themselves only run when the code is newer).
     */
    public function __construct() {
        add_action('admin_init', [$this, 'check_version'], 5);
    }

    /**
     * The ordered map of schema update steps.
     *
     * Keys are the plugin version a step first shipped in; values are lists
     * of update callbacks (methods of this class). A step runs when the
     * stored schema version is OLDER than its key. Every callback MUST be
     * idempotent — it re-checks the actual schema before altering anything.
     *
     * @return array version => list of method names.
     */
    private static function db_updates() {
        return [
            '1.0.2' => [
                'update_102_add_applications_is_withdrawn',
            ],
            '1.0.5' => [
                'update_105_add_job_fields_company_id',
                'update_105_add_companies_table',
            ],
        ];
    }

    /**
     * admin_init callback — run any pending schema updates.
     */
    public function check_version() {
        self::maybe_update();
    }

    /**
     * Compare the stored schema version against the running plugin version
     * and execute all pending update steps in version order.
     *
     * Also called from the activation hook, so re-activating the plugin
     * brings the schema current immediately (fresh installs just stamp the
     * version — the activator already created the up-to-date tables and
     * every step re-checks the schema before touching it).
     */
    public static function maybe_update() {
        $installed = get_option(self::DB_VERSION_OPTION, '');
        if ('' === $installed || ! is_string($installed)) {
            $installed = self::PRE_UPDATER_VERSION;
        }

        // Already current (or ahead, e.g. after a rollback) — nothing to do.
        if (version_compare($installed, WORKEERA_VERSION, '>=')) {
            return;
        }

        foreach (self::db_updates() as $version => $callbacks) {
            // Run steps newer than the install's schema, up to the running
            // plugin version (never run steps of a future release).
            if (version_compare($installed, $version, '<') && version_compare($version, WORKEERA_VERSION, '<=')) {
                foreach ($callbacks as $callback) {
                    call_user_func([__CLASS__, $callback]);
                }
            }
        }

        // Flag in the options table that this install is now current, so
        // the steps above never re-run until the next plugin update.
        update_option(self::DB_VERSION_OPTION, WORKEERA_VERSION);
    }

    // -------------------------------------------------------------------------
    // Update steps
    // -------------------------------------------------------------------------

    /**
     * 1.0.2 — add the `is_withdrawn` flag (0/1) to the applications table.
     *
     * Candidates withdrawing an application no longer delete the row; the
     * flag keeps the record for employers/admins while marking it withdrawn.
     * Idempotent: skipped when the table is missing or the column exists.
     */
    private static function update_102_add_applications_is_withdrawn() {
        global $wpdb;

        $table = $wpdb->prefix . 'workeera_applications';

        // Table not created yet (never-activated install) — the activator
        // will create it with the column included.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        // Column already there (fresh install or re-run) — nothing to do.
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'is_withdrawn'));
        if (!empty($column)) {
            return;
        }

        // Table name is built from $wpdb->prefix + a fixed literal — safe identifier.
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN is_withdrawn TINYINT(1) NOT NULL DEFAULT 0 AFTER status, ADD KEY is_withdrawn (is_withdrawn)");
    }

    /**
     * 1.0.5 — add the indexed `company_id` column to the job_fields table.
     *
     * The multi-company feature links a job to one of the employer's saved
     * companies by the company's numeric id. A first-class indexed column lets
     * jobs be counted/listed per company without unserializing meta.
     *
     * Idempotent: skipped when the table is missing or the column exists.
     */
    private static function update_105_add_job_fields_company_id() {
        global $wpdb;

        $table = $wpdb->prefix . 'workeera_job_fields';

        // Table not created yet (never-activated install) — the activator
        // will create it with the column included.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        // Add the column + index only when it isn't there yet.
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'company_id'));
        if (empty($column)) {
            // Table name is built from $wpdb->prefix + a fixed literal — safe identifier.
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN company_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER job_id, ADD KEY company_id (company_id)");
        }
    }

    /**
     * 1.0.5 — create the `workeera_companies` table (employers' companies live
     * in a first-class table rather than a `user_company_data` user-meta blob)
     * and ensure the job_fields `company_id` link column is the matching BIGINT
     * type.
     *
     * Idempotent: the table is created only when missing; the column type is
     * only altered when it isn't already a BIGINT.
     */
    private static function update_105_add_companies_table() {
        global $wpdb;

        $companies_table = $wpdb->prefix . 'workeera_companies';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $companies_table)) !== $companies_table) {
            $collate = $wpdb->has_cap('collation') ? $wpdb->get_charset_collate() : '';

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            // Table name is built from $wpdb->prefix + a fixed literal — safe identifier.
            dbDelta(
                "CREATE TABLE `{$companies_table}` (
                    company_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    employer_id BIGINT UNSIGNED NOT NULL,
                    name VARCHAR(191) NOT NULL DEFAULT '',
                    website VARCHAR(255) NOT NULL DEFAULT '',
                    tagline VARCHAR(191) NOT NULL DEFAULT '',
                    employee_count VARCHAR(50) NOT NULL DEFAULT '',
                    linkedin VARCHAR(255) NOT NULL DEFAULT '',
                    logo LONGTEXT NULL,
                    created_at DATETIME NULL DEFAULT NULL,
                    updated_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY  (company_id),
                    KEY employer_id (employer_id)
                    ) $collate;"
            );
        }

        // Bring an earlier (VARCHAR) job_fields.company_id column up to BIGINT so
        // it matches the companies table key. Only alters when needed.
        $fields_table = $wpdb->prefix . 'workeera_job_fields';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $fields_table)) === $fields_table) {
            $column = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$fields_table}` LIKE %s", 'company_id'));
            if ($column && stripos($column->Type, 'bigint') === false) {
                $wpdb->query("ALTER TABLE `{$fields_table}` MODIFY company_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
            }
        }
    }
}

new Workeera_DB_Updater();
