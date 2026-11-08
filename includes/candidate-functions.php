<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Check if a candidate post is attached to a user via the workeera_metadata table.
 *
 * @param int $user_id
 * @return int|false|Object Candidate post ID or post object if found, false otherwise.
 */
function workeera_find_candidate_post_attached( $user_id, $ret_type = '' ) {
    $where = [
        'object_type' => 'user',
        'meta_key'    => 'workeera_candidate_id',
        'object_id'  => $user_id,
    ];

    $candidate_id = Workeera_Query_Handler::get_saved_record( 'metadata', $where, [ 'meta_value' ], 1 );

    if ( ! empty( $candidate_id ) && is_numeric( $candidate_id ) ) {
        $post = get_post( (int) $candidate_id );
        if ( $post && $post->post_type === 'workeera_candidate' ) {
            if ($ret_type == 'object') {
                return $post;
            } else {
                return (int) $candidate_id;
            }
        }
    }

    return false;
}

/**
 * Ensure that a candidate user has a workeera_candidate post attached.
 *
 * If the user is of type 'candidate' and no candidate post is linked.
 *
 * @param int $user_id
 * @return int|false Candidate post ID on success, false if the user is not a candidate or creation fails.
 */
function workeera_ensure_candidate_post_for_user( $user_id ) {
    global $workeera_plugin_settings;

    $default_status = isset( $workeera_plugin_settings['candidate_settings']['default_status'] ) ? $workeera_plugin_settings['candidate_settings']['default_status'] : '';

    // Verify the user is a candidate
    $user_type = workeera_get_object_meta( $user_id, 'workeera_user_type', 'user' );
    if ( $user_type !== 'candidate' ) {
        return false;
    }

    // Already attached?
    $candidate_id = workeera_find_candidate_post_attached( $user_id );
    if ( $candidate_id ) {
        return $candidate_id;
    }

    // Look for an existing candidate post authored by the user
    $existing_posts = get_posts( [
        'post_type'      => 'workeera_candidate',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ] );

    if ( ! empty( $existing_posts ) ) {
        $candidate_post_id = $existing_posts[0]->ID;
        // Attach it by storing the custom meta
        workeera_update_object_meta( $user_id, 'workeera_candidate_id', $candidate_post_id, 'user' );
        return $candidate_post_id;
    }

    $user_data = get_userdata($user_id);

    // No candidate post exists – create a new one
    $new_post_data = [
        'post_title'   => isset($user_data->display_name) ? $user_data->display_name : esc_html__( 'Candidate Profile', 'workeera-remote-tech-job-board' ) . ' #' . $user_id,
        'post_type'    => 'workeera_candidate',
        'post_status'  => $default_status == 'pending' ? 'pending' : 'publish',
        'post_author'  => $user_id,
    ];

    $new_post_id = wp_insert_post( $new_post_data );
    if ( $new_post_id && ! is_wp_error( $new_post_id ) ) {
        workeera_update_object_meta( $user_id, 'workeera_candidate_id', $new_post_id, 'user' );
        return $new_post_id;
    }

    return false;
}

/**
 * Determine whether a user is a candidate and has a valid candidate post.
 *
 * @param int $user_id
 * @return int|false Candidate post ID if the user is a candidate and a post is attached, false otherwise.
 */
function workeera_is_user_candidate( $user_id ) {
    $user_type = workeera_get_object_meta( $user_id, 'workeera_user_type', 'user' );
    if ( $user_type !== 'candidate' ) {
        return false;
    }

    $candidate_post = workeera_find_candidate_post_attached( $user_id, 'object' );
    if ( isset($candidate_post->post_author) && $candidate_post->post_author == $user_id ) {
        return $candidate_post->ID;
    }

    return false;
}

/**
 * Resolve the profile picture URL for a user, preferring the resized thumb.
 * Returns an empty string if no picture is saved or the source file is missing.
 *
 * @param int $user_id
 * @return string
 */
function workeera_user_profile_pic_url( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id < 1 ) {
        return '';
    }

    $photo_data = workeera_get_object_meta( $user_id, 'user_profile_picture', 'user' );
    if ( empty( $photo_data ) || ! is_array( $photo_data ) || empty( $photo_data['file'] ) || ! file_exists( $photo_data['file'] ) ) {
        return '';
    }

    if ( ! empty( $photo_data['thumb_path'] ) && file_exists( $photo_data['thumb_path'] ) && ! empty( $photo_data['thumb_url'] ) ) {
        $url = $photo_data['thumb_url'];
    } else {
        $url = isset( $photo_data['url'] ) ? $photo_data['url'] : '';
    }

    if ( ! empty( $url ) && ! empty( $photo_data['version'] ) ) {
        $url = add_query_arg( [ 'ver' => absint( $photo_data['version'] ) ], $url );
    }

    return $url;
}

/**
 * Render a user's profile picture as a div with `background-image` (no <img> tag).
 * Falls back to a generic user-silhouette SVG icon when no picture is set.
 *
 * @param int    $user_id
 * @param string $extra_classes  Optional space-separated extra classes for the wrapper div.
 * @return string Safe HTML.
 */
function workeera_user_profile_pic_html( $user_id, $extra_classes = '' ) {
    $url     = workeera_user_profile_pic_url( $user_id );
    $classes = 'workeera-user-profile-pic';
    if ( ! empty( $extra_classes ) ) {
        $classes .= ' ' . $extra_classes;
    }

    if ( ! empty( $url ) ) {
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>" style="background-image: url('<?php echo esc_url( $url ); ?>');"></div>
        <?php
    } else {
        ?>
        <div class="<?php echo esc_attr( $classes ); ?> workeera-user-profile-pic-empty"><svg class="workeera-user-profile-pic-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></div>
        <?php
    }
}

/**
 * Compute integer age in years from a date-of-birth string stored in d/m/Y format
 * (the format produced by workeera_validate_date_input).
 *
 * @param string $dob
 * @return int 0 when the DOB is empty / unparseable.
 */
function workeera_calculate_age_from_dob( $dob ) {
    if ( empty( $dob ) || ! is_string( $dob ) ) {
        return 0;
    }

    $parts = explode( '/', $dob );
    if ( count( $parts ) !== 3 ) {
        return 0;
    }

    $day   = absint( $parts[0] );
    $month = absint( $parts[1] );
    $year  = absint( $parts[2] );

    if ( $year < 1900 || $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
        return 0;
    }

    if ( ! checkdate( $month, $day, $year ) ) {
        return 0;
    }

    $now_year  = (int) current_time( 'Y' );
    $now_month = (int) current_time( 'n' );
    $now_day   = (int) current_time( 'j' );

    $age = $now_year - $year;
    if ( $now_month < $month || ( $now_month === $month && $now_day < $day ) ) {
        $age--;
    }

    return max( 0, $age );
}

add_action('wp', 'workeera_candidate_cv_download_handle');

/**
 * Serve a candidate CV file download when the request carries a valid nonce.
 *
 * Validates nonce, ownership, and file existence before streaming the file
 * with `Content-Disposition: attachment`; aborts with `wp_die()` otherwise.
 */
function workeera_candidate_cv_download_handle() {
    if (isset($_GET['workeera_files_action']) && $_GET['workeera_files_action'] === 'download') {
        $obj_id = isset($_GET['obj_id']) ? absint($_GET['obj_id']) : 0;
        $file_id = isset($_GET['file_id']) ? sanitize_text_field( wp_unslash( $_GET['file_id'] ) ) : '';
        $nonce   = isset($_GET['nonce']) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

        if (!wp_verify_nonce($nonce, 'workeera_nonce')) {
            wp_die(esc_html__('Security check failed.', 'workeera-remote-tech-job-board'));
        }
        if (empty($file_id)) {
            wp_die(esc_html__('Invalid file ID.', 'workeera-remote-tech-job-board'));
        }

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        if (empty($user_id)) {
            wp_die(esc_html__('You are not allowed to download this file.', 'workeera-remote-tech-job-board'), 403);
        }

        $downloadable = false;
        if (!$is_admin) {
            $candidate_id = workeera_is_user_candidate($user_id);
            if ($candidate_id && $candidate_id == $obj_id) {
                $downloadable = true;
            }
        } else {
            $downloadable = true;
        }

        if (!$downloadable) {
            wp_die(esc_html__('You are not allowed to download this file.', 'workeera-remote-tech-job-board'), 403);
        }

        $files = workeera_get_object_meta($obj_id, 'candidate_cv_files');

        if (!is_array($files)) {
            wp_die(esc_html__('File not found.', 'workeera-remote-tech-job-board'), 404);
        }

        $file_row = null;
        foreach ($files as $f) {
            if (isset($f['file_id']) && $f['file_id'] === $file_id) {
                $file_row = $f;
                break;
            }
        }

        if (!$file_row) {
            wp_die(esc_html__('File not found.', 'workeera-remote-tech-job-board'), 404);
        }

        $file_path = $file_row['file'] ?? '';
        // Only serve a file that resolves inside the plugin's uploads folder —
        // the stored path comes from post meta and must not be trusted verbatim.
        $file_path = workeera_safe_upload_path( $file_path );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            wp_die(esc_html__('File not found on server.', 'workeera-remote-tech-job-board'), 404);
        }

        // Stream the file via the WP_Filesystem API.
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( empty( $wp_filesystem ) ) {
            WP_Filesystem();
        }
        if ( ! $wp_filesystem->is_readable( $file_path ) ) {
            wp_die( esc_html__( 'Could not read the file.', 'workeera-remote-tech-job-board' ) );
        }

        $file_name = $file_row['name'];
        $size = filesize($file_path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"');
        header('Content-Length: ' . $size);
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile( $file_path );
        exit;
    }
}