<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Plugin activation routines.
 *
 * Provisions the plugin's custom database tables and schedules the cron
 * events the plugin relies on at runtime.
 */
class Workeera_Plugin_Activator {

    /**
     * Create the plugin's custom tables via dbDelta if they don't already exist.
     */
    public static function create_custom_tables() {

        global $wpdb;

        $prefix = $wpdb->prefix;
        
        $collate = '';

        if ($wpdb->has_cap('collation')) {
            $collate = $wpdb->get_charset_collate();
        }

        $custom_tables = [
            "workeera_job_fields" => "CREATE TABLE `{$prefix}workeera_job_fields` (
                job_field_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                application_deadline TIMESTAMP NULL DEFAULT NULL,
                expiry_date TIMESTAMP NULL DEFAULT NULL,
                min_salary INT UNSIGNED NULL,
                max_salary INT UNSIGNED NULL,
                salary_type ENUM('hourly', 'daily', 'weekly', 'monthly', 'annually') DEFAULT 'hourly',
                is_featured TINYINT(1) DEFAULT 0,
                featured_till TIMESTAMP NULL DEFAULT NULL,
                is_urgent TINYINT(1) DEFAULT 0,
                urgent_till TIMESTAMP NULL DEFAULT NULL,
                location_type ENUM('remote', 'hybrid', 'onsite') DEFAULT 'remote',
                is_filled TINYINT(1) DEFAULT 0,
                listing_status VARCHAR(150) NOT NULL DEFAULT 'active',
                views BIGINT UNSIGNED DEFAULT 0,
                PRIMARY KEY  (job_field_id),
                UNIQUE KEY job_id (job_id),
                KEY listing_status (listing_status),
                KEY is_featured (is_featured),
                KEY is_urgent (is_urgent),
                KEY is_filled (is_filled),
                KEY company_id (company_id)
                ) $collate;",
            "workeera_companies" => "CREATE TABLE `{$prefix}workeera_companies` (
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
                ) $collate;",
            "workeera_metadata" => "CREATE TABLE `{$prefix}workeera_metadata` (
                meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                object_id BIGINT UNSIGNED DEFAULT 0,
                object_type VARCHAR(25) NOT NULL DEFAULT 'post',
                meta_key VARCHAR(150) NOT NULL,
                meta_value TEXT NULL,
                PRIMARY KEY  (meta_id),
                KEY object_type (object_type)
                ) $collate;",
            "workeera_term_counts" => "CREATE TABLE `{$prefix}workeera_term_counts` (
                term_count_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                term_id INT UNSIGNED NOT NULL,
                term_type VARCHAR(150) NOT NULL,
                object_type VARCHAR(150) NOT NULL,
                count INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (term_count_id),
                KEY term_id (term_id),
                KEY count (count)
                ) $collate;",
            "workeera_locations" => "CREATE TABLE `{$prefix}workeera_locations` (
                location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                country_id INT UNSIGNED NOT NULL DEFAULT 0,
                city_id BIGINT UNSIGNED NULL,
                object_id BIGINT UNSIGNED NOT NULL,
                full_address VARCHAR(255) NULL,
                lat DECIMAL(10, 8) NULL,
                lng DECIMAL(10, 8) NULL,
                PRIMARY KEY  (location_id),
                KEY country_id (country_id),
                KEY city_id (city_id)
                ) $collate;",
            "workeera_applications" => "CREATE TABLE `{$prefix}workeera_applications` (
                application_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL DEFAULT NULL,
                applicant_name VARCHAR(200) NOT NULL,
                applicant_email VARCHAR(200) NOT NULL,
                applicant_phone VARCHAR(50) NULL DEFAULT NULL,
                cover_letter TEXT NULL DEFAULT NULL,
                cv_data TEXT NULL DEFAULT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                is_withdrawn TINYINT(1) NOT NULL DEFAULT 0,
                employer_notes TEXT NULL DEFAULT NULL,
                applied_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL DEFAULT NULL,
                PRIMARY KEY  (application_id),
                KEY job_id (job_id),
                KEY status (status),
                KEY is_withdrawn (is_withdrawn),
                KEY user_id (user_id),
                KEY applicant_email (applicant_email)
            ) $collate;",
            "workeera_transients" => "CREATE TABLE `{$prefix}workeera_transients` (
                trans_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                trans_name VARCHAR(150) NOT NULL,
                trans_value LONGTEXT,
                trans_timeout BIGINT DEFAULT 0,
                PRIMARY KEY  (trans_id)
                ) $collate;",
        ];

        $custom_tables = apply_filters('workeera_custom_db_tables_list', $custom_tables, $collate);

        require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

        foreach ($custom_tables as $table_name => $table_qry) {

            $wp_track_table = $prefix . $table_name;

            if ($wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wp_track_table ) ) != $wp_track_table) {

                dbDelta($table_qry);
            }
        }
    }

    /**
     * Register the plugin's recurring cron events when they're not already scheduled.
     */
    public static function create_crones() {
        if ( ! wp_next_scheduled( 'workeera_delete_past_transients' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'workeera_delete_past_transients' );
        }

        // Expire jobs whose expiry_date has passed (or will within 30 minutes).
        if ( ! wp_next_scheduled( 'workeera_check_expired_jobs' ) ) {
            wp_schedule_event( time(), 'workeera_thirty_minutes', 'workeera_check_expired_jobs' );
        }

        // Remove applications whose target job post no longer exists.
        if ( ! wp_next_scheduled( 'workeera_clean_orphan_applications' ) ) {
            wp_schedule_event( time(), 'hourly', 'workeera_clean_orphan_applications' );
        }

        // Periodic safety-net refresh for workeera_term_counts.
        if ( ! wp_next_scheduled( 'workeera_refresh_term_counts' ) ) {
            wp_schedule_event( time(), 'workeera_six_hours', 'workeera_refresh_term_counts' );
        }
    }
}
