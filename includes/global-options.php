<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Bootstraps the shared `$workeera_plugin_settings` global early on `init` so
 * downstream modules can read admin option groups without repeated DB hits.
 */

add_action('init', 'workeera_global_settings_vars', 1);

/**
 * Populate `$workeera_plugin_settings` with all plugin admin option groups.
 */
function workeera_global_settings_vars() {
    global $workeera_plugin_settings;

    $workeera_plugin_settings = [
        'general_settings' => get_option('workeera_general_settings'),
        'job_listings_settings' => get_option('workeera_job_listings_settings'),
        'job_apply_settings' => get_option('workeera_job_apply_settings'),
        'job_submission_settings' => get_option('workeera_job_submission_settings'),
        'company_settings' => get_option('workeera_company_settings'),
        'candidate_settings' => get_option('workeera_candidate_settings'),
        'pages_settings' => get_option('workeera_pages_settings'),
    ];
}
