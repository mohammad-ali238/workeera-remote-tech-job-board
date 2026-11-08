<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Allowed-tags map for escaping the plugin's inline SVG icons via wp_kses().
 *
 * wp_kses() lowercases attribute names, so `viewBox` is emitted as `viewbox`;
 * that still renders for inline SVG in an HTML5 document (the parser's
 * foreign-content adjustment maps it back), hence the lowercase keys here.
 *
 * @return array Tag => allowed-attributes map for wp_kses().
 */
function workeera_svg_allowed_tags() {
    // Shared presentational attributes used across the plugin's inline icons.
    $common = array(
        'class' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true,
        'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-dasharray' => true,
        'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true,
        'fill-rule' => true, 'clip-rule' => true, 'transform' => true, 'aria-hidden' => true,
    );
    $tags = array(
        'svg'      => array_merge( $common, array( 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'role' => true, 'focusable' => true ) ),
        'g'        => $common,
        'path'     => array_merge( $common, array( 'd' => true ) ),
        'rect'     => array_merge( $common, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ) ),
        'circle'   => array_merge( $common, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
        'ellipse'  => array_merge( $common, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) ),
        'line'     => array_merge( $common, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ) ),
        'polyline' => array_merge( $common, array( 'points' => true ) ),
        'polygon'  => array_merge( $common, array( 'points' => true ) ),
        'text'     => array_merge( $common, array( 'x' => true, 'y' => true, 'font-size' => true, 'font-weight' => true, 'font-family' => true, 'text-anchor' => true ) ),
        'tspan'    => array_merge( $common, array( 'x' => true, 'y' => true ) ),
        'defs'     => array(),
        'title'    => array(),
    );

    return apply_filters( 'workeera_svg_allowed_tags', $tags );
}

/**
 * Assert that a string is a bare SQL identifier (table or column name).
 *
 * SQL identifiers cannot be bound through $wpdb->prepare(), so any value
 * interpolated into a query as a table/column name must be validated against a
 * strict character allowlist first. Returns the identifier unchanged when it is
 * a plain [A-Za-z0-9_] token, or '' otherwise (callers must bail on '').
 *
 * @param  string $identifier Raw identifier (column name or table-name suffix).
 * @return string             The identifier when safe, otherwise ''.
 */
function workeera_safe_sql_identifier( $identifier ) {
    return ( is_string( $identifier ) && preg_match( '/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $identifier ) )
        ? $identifier
        : '';
}

/**
 * Purify a prepared SQL query by unquoting safe identifiers.
 *
 * When a column name or table name is bound into a query, $wpdb->prepare()
 * will quote it as a string literal. This function reverses that quoting for
 * any identifiers that have been validated as safe, so they can be used in
 * ORDER BY, GROUP BY, or JOIN clauses.
 *
 * @param  string $prepared_query    The prepared SQL query with placeholders.
 * @param  array  $safe_identifiers  List of identifiers that are safe to unquote.
 * @return string                    The purified query with safe identifiers unquoted.
 */
function workeera_purify_prepared_qry($prepared_query, $safe_identifiers = []) {
    if (!empty($safe_identifiers) && is_array($safe_identifiers)) {
        foreach ($safe_identifiers as $identifier) {
            if (!empty($identifier)) {
                $prepared_query = str_replace(["'" . $identifier . "'", "`" . $identifier . "`"], [$identifier, $identifier], $prepared_query);
            }
        }
    }
    return $prepared_query;
}

add_filter('workeera_after_prepared_qry', 'workeera_purify_prepared_qry', 10, 2);

/**
 * Validate a dd/mm/yyyy date string and return it if valid.
 *
 * @param  string $date Date string in dd/mm/yyyy format.
 * @return string       The validated date string, or an empty string when invalid.
 */
function workeera_validate_date_input($date) {
    // Define the regular expression pattern to match the format dd/mm/yyyy
    $pattern = '/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/(19|20)\d\d$/';

    $date = sanitize_text_field(wp_unslash($date));
    $date_val = '';

    // Check if the date matches the pattern
    if ($date != '' && preg_match($pattern, $date)) {

        list($day, $month, $year) = explode('/', $date);

        if (checkdate($month, $day, $year)) {
            $date_val = $date;
        }
    }

    return $date_val;
}

/**
 * Validate a 24-hour HH:MM time string.
 *
 * @param  string $time Time string to validate.
 * @return int          1 if the string matches the HH:MM pattern, 0 otherwise.
 */
function workeera_validate_time_input($time) {
    return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time);
}

/**
 * Convert a dd/mm/yyyy (or dd-mm-yyyy) date into a UNIX timestamp.
 *
 * @param  string $date     Date string using `/` or `-` separators.
 * @param  bool   $add_time Whether to append the current time before parsing.
 * @return int|false        UNIX timestamp on success, false on failure.
 */
function workeera_date_strtotime($date, $add_time = false) {
    $date = str_replace('/', '-', $date);

    if ($add_time) {
        $date .= ' ' . current_time('H:i:s');
    }

    $date = strtotime($date);

    return $date;
}

/**
 * Recursively sanitize and escape a string or array of strings.
 *
 * @param  string|array $input Value to sanitize.
 * @return string|array        Sanitized value mirroring the input structure.
 */
function workeera_array_esc_str($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = workeera_array_esc_str($value); // Recursive call
        }
        return $input;
    } else {
        return sanitize_text_field(trim($input));
    }
}

/**
 * Strip backslashes from a string value.
 *
 * @param  string $content String to unslash.
 * @return string          Unslashed string (empty input is returned untouched).
 */
function workeera_strip_slashes($content = '') {
    if (!empty($content)) {
        $content = stripslashes($content);
    }
    return $content;
}

/**
 * Maybe-unserialize a value stored in plugin tables.
 *
 * @param  mixed $data Possibly serialized value.
 * @return mixed       Unserialized value or the original input.
 */
function workeera_unserialize_data($data) {
    return maybe_unserialize($data);
}

/**
 * Build a URL-safe slug from a title.
 *
 * @param  string $title Source title.
 * @return string        Sanitized slug.
 */
function workeera_make_title_slug($title) {
    $slug = sanitize_title($title);

    return $slug;
}

/**
 * URL-encode a sanitized slug in lowercase form.
 *
 * @param  string $slug Slug to encode.
 * @return string       Lowercase URL-encoded value.
 */
function workeera_get_url_encode_val($slug) {
    return strtolower(urlencode(sanitize_text_field(trim($slug))));
}

/**
 * Get the current WordPress timestamp.
 *
 * @return int Current UTC-corrected timestamp.
 */
function workeera_current_time_str() {
    $time_str = current_time('timestamp');

    return $time_str;
}

/**
 * Strip leading Markdown code-fence markers from a JSON-like string.
 *
 * @param  string $content Raw content possibly wrapped in code fences.
 * @return string          Cleaned content.
 */
function workeera_purify_json($content) {
    $content = preg_replace('/```\s*\w*\s*/', '', $content);

    return $content;
}

add_action('wp_loaded', 'workeera_process_custom_ajax', 90);

/**
 * Handle Workeera-prefixed custom AJAX actions early in wp_loaded.
 *
 * Routes `workeera_action` POSTs to `workeera_ajax_*` and `workeera_ajax_nopriv_*` hooks.
 */
function workeera_process_custom_ajax() {
    if (isset($_POST['workeera_action']) && !empty($_POST['workeera_action'])) {

        $ajax_action = esc_html(sanitize_key($_POST['workeera_action']));

        // Static whitelist — fixed action names that don't vary at runtime.
        // These are the values literally hard-coded in the plugin's JS.
        $whitelist = [
            // Front-end (listing-scripts.js).
            'workeera_ajax_applyjob_form_call',

            // Applications list — cover-letter popup (job-applications.js,
            // used on both the front dashboard and in wp-admin).
            'workeera_ajax_get_cover_letter',

            // Admin Select2 lookups (admin-scripts.js).
            'workeera_ajax_get_page_results',
            'workeera_ajax_get_posts_results',
            'workeera_ajax_get_users_results',
            'workeera_ajax_get_jobs_employers',
            'workeera_ajax_get_terms_results',
        ];

        // Per-taxonomy dynamic actions — `workeera_ajax_update_<single_slug>_form`
        // (term edit modal) and `workeera_ajax_parent_tax_<plural_slug>_drpdwn`
        // (parent-term Select2). These hooks are registered in
        // admin/taxonomies/common-taxonomy.php from the same registered list,
        // so we derive the whitelist from it — adding a new taxonomy
        // automatically extends both the hooks and the whitelist together.
        if ( class_exists( 'Workeera_Custom_Taxonomies' ) ) {
            $registered_taxes = Workeera_Custom_Taxonomies::list();
            if ( is_array( $registered_taxes ) ) {
                foreach ( $registered_taxes as $tax_cfg ) {
                    if ( ! empty( $tax_cfg['single_slug'] ) ) {
                        $whitelist[] = 'workeera_ajax_update_' . sanitize_key( $tax_cfg['single_slug'] ) . '_form';
                    }
                    if ( ! empty( $tax_cfg['parents'] ) && is_array( $tax_cfg['parents'] ) ) {
                        foreach ( $tax_cfg['parents'] as $parent_cfg ) {
                            if ( ! empty( $parent_cfg['plural_slug'] ) ) {
                                $whitelist[] = 'workeera_ajax_parent_tax_' . sanitize_key( $parent_cfg['plural_slug'] ) . '_drpdwn';
                            }
                        }
                    }
                }
            }
        }

        // Allow third-party / future-feature extensions to register additional
        // custom AJAX action names without touching this file.
        $whitelist = apply_filters( 'workeera_custom_ajax_actions_whitelist', array_values( array_unique( $whitelist ) ) );

        // Strict whitelist check. If $_POST['workeera_action'] is not one of
        // the explicitly allowed action names, bail out.
        $matched_idx = array_search( $ajax_action, $whitelist, true );
        if ( $matched_idx === false ) {
            return;
        }

        // Re-bind $ajax_action to the developer-controlled whitelist entry,
        // so the value passed to do_action() below provably comes from the
        // whitelist array (not from $_POST). This breaks the taint flow
        // from user input into the dynamic do_action() call site.
        $ajax_action = $whitelist[ $matched_idx ];

        if (strpos($ajax_action, 'workeera_ajax_nopriv_') !== false) {
            define("WORKEERA_IS_AJAX", true);

            do_action($ajax_action);

            exit();
        } else if (strpos($ajax_action, 'workeera_ajax_') !== false) {
            if (is_user_logged_in()) {
                define("WORKEERA_IS_AJAX", true);

                do_action($ajax_action);

                exit();
            }
        }
    }
}

/**
 * Resolve the current visitor's IP address from request headers.
 *
 * @return string IPv4 or IPv6 address; empty string when none can be determined.
 */
function workeera_user_ip_address() {
    $ip_address = '';
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip_address = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip_address = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip_address = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
    }

    return $ip_address;
}

/**
 * Render the Terms and Privacy acknowledgement block for plugin forms.
 *
 * Outputs HTML when either configured page exists; no-op otherwise.
 */
function workeera_terms_privacy_policy() {
    global $workeera_plugin_settings;

    $terms_page_url = '';
    $terms_page = isset($workeera_plugin_settings['pages_settings']['terms_page']) ? absint($workeera_plugin_settings['pages_settings']['terms_page']) : 0;
    if (!empty($terms_page) && get_post_type($terms_page) == 'page' && get_post_status($terms_page) == 'publish') {
        $terms_page_url = get_permalink($terms_page);
        $terms_page_title = get_the_title($terms_page);
    }
    
    $privacy_page_id = get_option('wp_page_for_privacy_policy');
    $privacy_page_url = '';
    if (!empty($privacy_page_id) && get_post_type($privacy_page_id) == 'page' && get_post_status($privacy_page_id) == 'publish') {
        $privacy_page_url = get_permalink($privacy_page_id);
        $privacy_page_title = get_the_title($privacy_page_id);
    }
    
    // Check if both pages exist
    $has_terms = !empty($terms_page_url);
    $has_privacy = !empty($privacy_page_url);
    
    // Only show if at least one page exists
    if (!$has_terms && !$has_privacy) {
        return;
    }
    ?>
    <div class="workeera-form-termscond-con">
        <strong>
            <?php
            if ($has_terms && $has_privacy) {
                // Both pages exist
                printf(
                    /* translators: 1: terms and conditions link, 2: privacy policy link */
                    esc_html__('By continuing, you acknowledge our %1$s and %2$s.', 'workeera-remote-tech-job-board'),
                    '<a href="' . esc_url($terms_page_url) . '" target="_blank">' . esc_html($terms_page_title) . '</a>',
                    '<a href="' . esc_url($privacy_page_url) . '" target="_blank">' . esc_html($privacy_page_title) . '</a>'
                );
            } elseif ($has_terms) {
                // Only terms page exists
                printf(
                    /* translators: %s: terms and conditions link */
                    esc_html__('By continuing, you acknowledge our %s.', 'workeera-remote-tech-job-board'),
                    '<a href="' . esc_url($terms_page_url) . '" target="_blank">' . esc_html($terms_page_title) . '</a>'
                );
            } elseif ($has_privacy) {
                // Only privacy policy page exists
                printf(
                    /* translators: %s: privacy policy link */
                    esc_html__('By continuing, you acknowledge our %s.', 'workeera-remote-tech-job-board'),
                    '<a href="' . esc_url($privacy_page_url) . '" target="_blank">' . esc_html($privacy_page_title) . '</a>'
                );
            }
            ?>
        </strong>
    </div>
    <?php
}

/**
 * Filter callback that redirects WP uploads into the plugin's `wp-workeera` subdirectory.
 *
 * @param  array $dir WordPress `upload_dir` array.
 * @return array      Modified upload directory descriptor.
 */
function workeera_public_upload_files_path($dir = '') {
    global $workeera_upload_files_extpath, $workeera_upload_subdir_extpath;

    $ext_subdir = $workeera_upload_subdir_extpath != '' ? '/' . $workeera_upload_subdir_extpath : '';

    $cus_dir = 'wp-workeera' . ($workeera_upload_files_extpath != '' ? '/' . $workeera_upload_files_extpath : '');

    $sub_dir = isset($dir['subdir']) && $dir['subdir'] != '' ? $dir['subdir'] : '';
    $dir_path = array(
        'path' => $dir['basedir'] . '/' . $cus_dir . $sub_dir . $ext_subdir,
        'url' => $dir['baseurl'] . '/' . $cus_dir . $sub_dir . $ext_subdir,
        'subdir' => (isset($dir['subdir']) && $dir['subdir'] != '' ? $dir['subdir'] . $ext_subdir : $cus_dir . $ext_subdir),
    );
    return $dir_path + $dir;
}

/**
 * Drop a hardening `.htaccess` + empty `index.html` into a plugin upload
 * directory so the files inside cannot be downloaded directly over HTTP
 * (CVs and application snapshots must only be served by the gated
 * download handler, which performs ownership checks).
 *
 * Safe to call repeatedly — the files are only written if missing.
 *
 * @param  string $dir Absolute directory path that has just been created
 *                     by `wp_mkdir_p` / `wp_handle_upload`.
 * @return void
 */
function workeera_protect_private_upload_dir( $dir ) {
    if ( empty( $dir ) || ! is_dir( $dir ) ) {
        return;
    }

    global $wp_filesystem;

    // Initialize WP_Filesystem
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();

    $dir = trailingslashit( $dir );

    $htaccess_path = $dir . '.htaccess';
    if ( ! file_exists( $htaccess_path ) ) {
        $rules  = "# Workeera — block direct access to private uploads.\n";
        $rules .= "Options -Indexes\n";
        $rules .= "<IfModule mod_authz_core.c>\n";
        $rules .= "    Require all denied\n";
        $rules .= "</IfModule>\n";
        $rules .= "<IfModule !mod_authz_core.c>\n";
        $rules .= "    Order deny,allow\n";
        $rules .= "    Deny from all\n";
        $rules .= "</IfModule>\n";
        $wp_filesystem->put_contents( $htaccess_path, $rules );
    }

    $index_path = $dir . 'index.html';
    if ( ! file_exists( $index_path ) ) {
        $wp_filesystem->put_contents( $index_path, '' );
    }
}

/**
 * Read a sanitized query var, falling back to `$_GET` when not registered.
 *
 * @param  string $var Query var name.
 * @return string      Sanitized value, or empty string when absent.
 */
function workeera_get_query_var($var) {
    $value = sanitize_text_field( get_query_var( $var ) );
    if (empty($value) && isset($_GET[$var])) {
        $value = sanitize_text_field( wp_unslash( $_GET[$var] ) );
    }

    return $value;
}

add_filter('query_vars', 'workeera_custom_query_vars_list');

/**
 * Register plugin-specific public query vars.
 *
 * @param  array $vars Existing query var list.
 * @return array       List with plugin vars appended.
 */
function workeera_custom_query_vars_list($vars) {
    
    $vars[] = 'workeera_tab';
    $vars[] = 'workeera_section';
    $vars[] = 'workeera_sub_menu';
    $vars[] = 'page';
    $vars[] = 'page_num';
    $vars[] = 'orderby';
    $vars[] = 'order';
    $vars[] = 's';
    $vars[] = 'id';

    return $vars;
}

/**
 * Locate and load a Workeera template part with theme override support.
 *
 * @param string $slug         Template slug (without extension).
 * @param string $name         Optional template variant name.
 * @param string $ext_template Optional sub-directory inside `workeera/`.
 */
function workeera_get_template_part($slug = '', $name = '', $ext_template = '') {
    $template = '';

    if ($ext_template != '') {
        $ext_template = trailingslashit($ext_template);
    }
    if ($name) {
        $template = locate_template(array("{$slug}-{$name}.php", "workeera-remote-tech-job-board/{$ext_template}{$slug}-{$name}.php"));
    }

    if (!$template && $name && file_exists(WORKEERA_ABSPATH . 'templates/' . "{$ext_template}/{$slug}-{$name}.php")) {
        $template = WORKEERA_ABSPATH . 'templates/' . "{$ext_template}{$slug}-{$name}.php";
    }

    if (!$template) {
        $template = locate_template(array("{$slug}.php", "workeera-remote-tech-job-board/{$ext_template}{$slug}.php"));
    }

    if ($template) {
        load_template($template, false);
    }
}

/**
 * Read a value from the custom `workeera_metadata` table.
 *
 * @param  int    $object_id   Object ID.
 * @param  string $meta_key    Meta key to fetch.
 * @param  string $object_type Object type bucket (e.g. 'post', 'user').
 * @return mixed               Unserialized meta value, or empty when missing.
 */
function workeera_get_object_meta($object_id, $meta_key, $object_type = 'post') {
    $meta_data = [
        'object_id' => $object_id,
        'object_type' => $object_type,
        'meta_key' => $meta_key,
    ];
    $meta_value = Workeera_Query_Handler::get_saved_record('metadata', $meta_data, ['meta_value'], 1, true);
    return maybe_unserialize($meta_value);
}

/**
 * Insert or update a value in the custom `workeera_metadata` table.
 *
 * @param int    $object_id   Object ID.
 * @param string $meta_key    Meta key to write.
 * @param mixed  $meta_value  Value (will be serialized when needed).
 * @param string $object_type Object type bucket (e.g. 'post', 'user').
 */
function workeera_update_object_meta($object_id, $meta_key, $meta_value, $object_type = 'post') {
    $meta_data = [
        'object_id' => $object_id,
        'object_type' => $object_type,
        'meta_key' => $meta_key,
        'meta_value' => maybe_serialize($meta_value),
    ];
    Workeera_Query_Handler::save_item_data('metadata', 'meta_id', '', $meta_data);
}

/**
 * Check if a row exists in the custom `workeera_metadata` table.
 *
 * @param  string $object_type Object type bucket.
 * @param  int    $object_id   Object ID.
 * @param  string $meta_key    Meta key to look up.
 * @return bool                True when a matching row exists.
 */
function workeera_metadata_exists($object_type, $object_id, $meta_key) {
    global $wpdb;

    $result = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_id FROM {$wpdb->prefix}workeera_metadata WHERE object_type = %s AND object_id = %d AND meta_key = %s",
        $object_type,
        $object_id,
        $meta_key
    ) );

    return !empty($result);
}

/**
 * Strip all non-digit characters from a string.
 *
 * @param  string $str Input that may contain digits and separators.
 * @return string      Digits-only string (may be empty).
 */
function workeera_absint($str) {
    $str = esc_html(sanitize_text_field(wp_unslash($str)));
    $result = preg_replace('/[^0-9]/', '', $str);

    return $result;
}

/**
 * Get the localized labels for supported salary cadence units.
 *
 * @return array Map of slug to translated label.
 */
function workeera_salary_units() {
    return [
        'hourly' => esc_html_x( 'Hour', 'Salary unit', 'workeera-remote-tech-job-board' ),
        'daily' => esc_html_x( 'Day', 'Salary unit', 'workeera-remote-tech-job-board' ),
        'weekly' => esc_html_x( 'Week', 'Salary unit', 'workeera-remote-tech-job-board' ),
        'monthly' => esc_html_x( 'Month', 'Salary unit', 'workeera-remote-tech-job-board' ),
        'annually' => esc_html_x( 'Year', 'Salary unit', 'workeera-remote-tech-job-board' ),
    ];
}

/**
 * Built-in salary range presets for each cadence unit.
 *
 * @return array Map of cadence slug to `[title, default => [min, max, interval]]`.
 */
function workeera_default_salary_ranges() {
    $salary_ranges = [
        'hourly' => [
            'title' => esc_html__('Hourly', 'workeera-remote-tech-job-board'),
            'default' => [
                'min' => 5,
                'max' => 150,
                'interval' => 5,
            ]
        ],
        'daily' => [
            'title' => esc_html__('Daily', 'workeera-remote-tech-job-board'),
            'default' => [
                'min' => 1000,
                'max' => 10000,
                'interval' => 500,
            ]
        ],
        'weekly' => [
            'title' => esc_html__('Weekly', 'workeera-remote-tech-job-board'),
            'default' => [
                'min' => 5000,
                'max' => 50000,
                'interval' => 1000,
            ]
        ],
        'monthly' => [
            'title' => esc_html__('Monthly', 'workeera-remote-tech-job-board'),
            'default' => [
                'min' => 25000,
                'max' => 250000,
                'interval' => 5000,
            ]
        ],
        'annually' => [
            'title' => esc_html__('Yearly', 'workeera-remote-tech-job-board'),
            'default' => [
                'min' => 200000,
                'max' => 2500000,
                'interval' => 50000,
            ]
        ],
    ];

    return $salary_ranges;
}

/**
 * Salary range presets merged with admin-configured overrides.
 *
 * @return array Same structure as {@see workeera_default_salary_ranges()}.
 */
function workeera_salary_ranges() {
    global $workeera_plugin_settings;

    $salary_ranges = $ret_salary_ranges = workeera_default_salary_ranges();

    foreach ($salary_ranges as $key => $range) {
        if (isset($workeera_plugin_settings['general_settings'][$key . '_salary_range']['min'])) {
            $ret_salary_ranges[$key]['default']['min'] = $workeera_plugin_settings['general_settings'][$key . '_salary_range']['min'];
        }
        if (isset($workeera_plugin_settings['general_settings'][$key . '_salary_range']['max'])) {
            $ret_salary_ranges[$key]['default']['max'] = $workeera_plugin_settings['general_settings'][$key . '_salary_range']['max'];
        }
        if (isset($workeera_plugin_settings['general_settings'][$key . '_salary_range']['interval'])) {
            $ret_salary_ranges[$key]['default']['interval'] = $workeera_plugin_settings['general_settings'][$key . '_salary_range']['interval'];
        }
    }
    return $ret_salary_ranges;
}

/**
 * Resolve the active currency settings, falling back to USD defaults.
 *
 * @return array Associative array of currency, position, and thousand_separator.
 */
function workeera_currency_settings() {
    global $workeera_plugin_settings;

    if (isset($workeera_plugin_settings['general_settings']['default_currency'])) {
        $currency_settings = isset($workeera_plugin_settings['general_settings']['default_currency']) ? $workeera_plugin_settings['general_settings']['default_currency'] : '';
    } else {
        $currency_settings = [
            'currency' => 'USD',
            'position' => 'left',
            'thousand_separator' => ',',
        ];
    }

    return $currency_settings;
}

/**
 * Format a numeric salary into HTML using the active currency settings.
 *
 * @param  int|string $salary       Raw salary amount.
 * @param  string     $salary_class Optional extra CSS class for the amount span.
 * @param  string     $salary_unit  Optional cadence label (e.g. "Month").
 * @param  string     $format_type  Display format ('' or 'k_notation').
 * @return string                   Filtered HTML, or the original amount if currency is unknown.
 */
function workeera_formatted_salary($salary, $salary_class = '', $salary_unit = '', $format_type = '') {
    global $workeera_plugin_settings, $workeera_currencies_list;

    if (empty($format_type)) {
        $format_type = isset($workeera_plugin_settings['general_settings']['salary_display_format']) ? $workeera_plugin_settings['general_settings']['salary_display_format'] : '';
    }

    $currency_settings = isset($workeera_plugin_settings['general_settings']['default_currency']) ? $workeera_plugin_settings['general_settings']['default_currency'] : '';

    $salary = workeera_absint($salary);

    $currency = isset($currency_settings['currency']) ? $currency_settings['currency'] : 'USD';
    $position = isset($currency_settings['position']) ? $currency_settings['position'] : 'left';
    $thousand_separator = isset($currency_settings['thousand_separator']) ? $currency_settings['thousand_separator'] : ',';
    $decimal_separator = '.';
    $num_decimals = 0;

    if (isset($workeera_currencies_list[$currency])) {
        $currency_symbol = '<span class="workeera-salary-currency">' . $workeera_currencies_list[$currency]['symbol'] . '</span>';

        if ($format_type === 'k_notation' && $salary >= 1000) {
            $k_value = $salary / 1000;
            // Show integer if no decimal remainder, otherwise one decimal
            if (floor($k_value) == $k_value) {
                $amount_display = number_format($k_value, 0, $decimal_separator, '');
            } else {
                $amount_display = number_format($k_value, 1, $decimal_separator, '');
            }
            $formatted_salary = '<span class="workeera-salary-amount' . (!empty($salary_class) ? ' ' . esc_attr($salary_class) : '') . '">' . $amount_display . 'k</span>';
        } else {
            // Original number_format logic
            $formatted_salary = '<span class="workeera-salary-amount' . (!empty($salary_class) ? ' ' . esc_attr($salary_class) : '') . '">' . number_format($salary, $num_decimals, $decimal_separator, $thousand_separator) . '</span>';
        }
        
        $spaced = strpos($position, '_space') !== false ? '&nbsp;' : '';
        $ret_val = '<span class="workeera-formatted-salary">';
        $ret_val .= (strpos($position, 'right') !== false ? "{$formatted_salary}{$spaced}{$currency_symbol}" : "{$currency_symbol}{$spaced}{$formatted_salary}");
        if (!empty($salary_unit)) {
            $ret_val .= '<span class="workeera-salary-unit">/' . esc_html($salary_unit) . '</span>';
        }
        $ret_val .= '</span>';
        return apply_filters('workeera_formatted_salary', $ret_val, $currency_settings);
    }

    return $salary;
}

/**
 * Get the HTML entity symbol for the configured default currency.
 *
 * @return string Currency symbol (defaults to the dollar sign).
 */
function workeera_currency_symbol() {
    global $workeera_plugin_settings, $workeera_currencies_list;

    $currency_settings = isset($workeera_plugin_settings['general_settings']['default_currency']) ? $workeera_plugin_settings['general_settings']['default_currency'] : '';

    $default_currency = isset($currency_settings['currency']) ? $currency_settings['currency'] : '';

    $symbol = '&#036;';
    if (isset($workeera_currencies_list[$default_currency]['symbol']) && !empty($workeera_currencies_list[$default_currency]['symbol'])) {
        $symbol = $workeera_currencies_list[$default_currency]['symbol'];
    }

    return $symbol;
}

add_action('wp_ajax_workeera_get_site_nonce_token', 'workeera_get_site_nonce_token');
add_action('wp_ajax_nopriv_workeera_get_site_nonce_token', 'workeera_get_site_nonce_token');

/**
 * AJAX handler that returns a fresh `workeera_nonce` form field as JSON.
 */
function workeera_get_site_nonce_token() {
    ob_start();
    wp_nonce_field('workeera_nonce', 'workeera_nonce');
    $nonce = ob_get_clean();

    wp_send_json(['nonce' => $nonce]);
}

/**
 * Resolve a stored file path and return it ONLY when it sits inside the plugin's
 * own `wp-workeera` uploads folder; otherwise return false.
 *
 * Stored CV `file` values can be attacker-influenced (they arrive as meta), so any
 * code that reads, deletes or copies one must run it through this containment check
 * first. Blocks `..` traversal and absolute paths pointing elsewhere on disk.
 *
 * @param  string $file_path Absolute path taken from a stored record.
 * @return string|false      Canonical path inside the uploads root, or false.
 */
function workeera_safe_upload_path( $file_path ) {
    if ( empty( $file_path ) || ! is_string( $file_path ) ) {
        return false;
    }

    $real           = realpath( $file_path );
    $upload_dir     = wp_get_upload_dir();
    $upload_basedir = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';
    $workeera_root  = $upload_basedir !== '' ? realpath( trailingslashit( $upload_basedir ) . 'wp-workeera' ) : false;

    if ( ! $real || ! $workeera_root || strpos( $real, $workeera_root . DIRECTORY_SEPARATOR ) !== 0 ) {
        return false;
    }

    return $real;
}

add_action('wp_ajax_workeera_add_post_nonce_token', 'workeera_add_post_nonce_token');

/**
 * AJAX handler that returns a fresh `workeera_post_save_<post_id>` form field as JSON.
 *
 * Requires an authenticated caller who can actually edit the target post — the
 * token is a save credential, so it must never be minted for arbitrary post IDs
 * or unauthenticated callers.
 */
function workeera_add_post_nonce_token() {
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if ( ! $post_id || ! is_user_logged_in() || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json( [ 'error' => 'forbidden' ], 403 );
    }
    ob_start();
    wp_nonce_field( 'workeera_post_save_' . $post_id, 'workeera_post_save_nonce' );
    $nonce = ob_get_clean();

    wp_send_json(['nonce' => $nonce]);
}

/**
 * Maximum allowed picture upload size, in megabytes.
 *
 * @return int Filterable size limit in MB.
 */
function workeera_picture_allowed_file_size() {
    return apply_filters('workeera_picture_allowed_file_size', 15); // in MBs
}

/**
 * Filterable map of allowed picture MIME types.
 *
 * @return array Map of file-extension pattern to MIME type.
 */
function workeera_picture_allowed_mime_types() {
    $mimes = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
    ];

    return apply_filters('workeera_picture_allowed_mimes_list', $mimes);
}
