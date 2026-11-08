<?php

/**
 * Plugin Name:       Workeera – Remote Tech Job Board
 * Plugin URI:        https://workeera.com
 * Description:       A complete AI powered job board solution for WordPress. Turns any WordPress site into a fully featured job board. Employers can register, post jobs and review applications from a dedicated front-end dashboard. Candidates can build their profile, upload CVs, browse jobs, and apply with a single click.
 * Version:           1.0.7
 * Author:            Workeera Team
 * Author URI:        https://workeera.com/contact
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       workeera-remote-tech-job-board
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

if (!defined('WORKEERA_PLUGIN_FILE')) {
    define('WORKEERA_PLUGIN_FILE', __FILE__);
}

function workeera__activate_plugin() {
    require_once plugin_dir_path(WORKEERA_PLUGIN_FILE) . 'includes/class-system-requirements.php';
    require_once plugin_dir_path(WORKEERA_PLUGIN_FILE) . 'includes/class-activator.php';
    require_once plugin_dir_path(WORKEERA_PLUGIN_FILE) . 'includes/class-db-updater.php';

    // check requirements
    Workeera_System_Requirements::check_php_version();
    Workeera_System_Requirements::check_db_version();
    Workeera_System_Requirements::check_utf8_status();

    // add custom tables
    Workeera_Plugin_Activator::create_custom_tables();
    Workeera_Plugin_Activator::create_crones();

    // Bring pre-existing tables up to the current schema and stamp the
    // workeera_db_version option (fresh installs just get the stamp).
    Workeera_DB_Updater::maybe_update();

    // First-time install — flag the setup wizard to run on next admin load.
    if ( false === get_option( 'workeera_setup_wizard_status', false ) ) {
        add_option( 'workeera_setup_wizard_status', 'pending' );
    }
}

register_activation_hook(WORKEERA_PLUGIN_FILE, 'workeera__activate_plugin');

include_once dirname(WORKEERA_PLUGIN_FILE) . '/includes/class-plugin.php';