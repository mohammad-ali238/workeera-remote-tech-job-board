<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Handles the apply-job popup form, CV upload and application submission flow.
 */
class Workeera_Job_Applications {

    /**
     * Register apply-job AJAX endpoints and front-end download handler.
     */
    public function __construct() {
        add_action( 'wp_footer', [ $this, 'popup_modal_init' ], 25 );
        // apply job popup call
        add_action('workeera_ajax_applyjob_form_call', [$this, 'apply_job_form_ajax']);

        add_action('wp_ajax_workeera_user_applyjob_call', [$this, 'user_applyjob_call_ajax']);
        add_action('wp_ajax_nopriv_workeera_user_applyjob_call', [$this, 'user_applyjob_call_ajax']);

        // CV upload inside apply-job form
        add_action('wp_ajax_workeera_applyjob_cv_upload', [$this, 'applyjob_cv_upload_ajax']);

        // Front-end CV download endpoint for application-attached CVs.
        add_action( 'wp', [ $this, 'download_cv_handle' ] );

        // Cover-letter popup content (employer + admin applications lists),
        // served through the main plugin's custom AJAX router.
        add_action( 'workeera_ajax_get_cover_letter', [ $this, 'ajax_get_cover_letter' ] );
    }

    /**
     * Render the per-application actions dropdown used at the end of each row
     * in the employer (front dashboard) and admin applications lists.
     *
     * The menu holds the built-in Download CV and View Cover Letter items, each
     * wrapped by the `workeera_app_actions_before` / `workeera_app_actions_after`
     * hooks so addons can inject their own <a>/<button> links. The whole
     * dropdown is skipped when there is nothing to show — no CV, no cover
     * letter, and no markup added by either hook.
     *
     * @param int   $application_id Application row ID.
     * @param array $args           cv_url, cv_name, cv_exists, has_cover_letter, context ('front'|'admin').
     */
    public static function render_app_actions_dropdown( $application_id, $args = [] ) {
        $application_id = absint( $application_id );

        $args = wp_parse_args(
            $args,
            [
                'cv_url'           => '',
                'cv_name'          => '',
                'cv_exists'        => false,
                'has_cover_letter' => false,
                'context'          => 'front',
                'row'              => [],
            ]
        );

        $context = ( 'admin' === $args['context'] ) ? 'admin' : 'front';
        $row     = is_array( $args['row'] ) ? $args['row'] : [];

        // Build the menu items into a buffer so addon hook output is captured
        // too — that lets us hide the whole dropdown when everything is empty.
        ob_start();

        /**
         * Fires at the START of an application's actions dropdown, before the
         * built-in items. Echo `<a>`/`<button class="workeera-appactions-item">`
         * markup to add custom links.
         *
         * @param int    $application_id Application row ID.
         * @param string $context        'front' or 'admin'.
         * @param array  $row            The application row (user_id, applicant_name, applicant_email, …).
         */
        do_action( 'workeera_app_actions_before', $application_id, $context, $row );

        if ( $args['cv_exists'] && '' !== $args['cv_url'] ) {
            ?>
            <a href="<?php echo esc_url( $args['cv_url'] ); ?>"
               class="workeera-appactions-item"
               download="<?php echo esc_attr( $args['cv_name'] ); ?>"
               rel="noopener noreferrer" target="_blank">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                <?php echo esc_html_x( 'Download CV', 'application actions', 'workeera-remote-tech-job-board' ); ?>
            </a>
            <?php
        }

        if ( $args['has_cover_letter'] ) {
            ?>
            <button type="button"
                    class="workeera-appactions-item workeera-coverletter-btn"
                    data-application-id="<?php echo absint( $application_id ); ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" /></svg>
                <?php echo esc_html_x( 'View Cover Letter', 'application actions', 'workeera-remote-tech-job-board' ); ?>
            </button>
            <?php
        }

        /**
         * Fires at the END of an application's actions dropdown, after the
         * built-in items. Same contract as `workeera_app_actions_before`.
         *
         * @param int    $application_id Application row ID.
         * @param string $context        'front' or 'admin'.
         * @param array  $row            The application row (user_id, applicant_name, applicant_email, …).
         */
        do_action( 'workeera_app_actions_after', $application_id, $context, $row );

        $menu_items = trim( ob_get_clean() );

        // Nothing to show — no CV, no cover letter, no addon items.
        if ( '' === $menu_items ) {
            return;
        }

        $wrap_class = 'workeera-appactions' . ( 'admin' === $context ? ' workeera-admin-appactions' : '' );
        ?>
        <div class="<?php echo esc_attr( $wrap_class ); ?>">
            <button type="button" class="workeera-appactions-trigger" aria-haspopup="true" aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="12" cy="19" r="2" /></svg>
                <span class="workeera-appactions-trigger-label"><?php echo esc_html_x( 'Actions', 'application actions', 'workeera-remote-tech-job-board' ); ?></span>
            </button>
            <div class="workeera-appactions-menu workeera-hide">
                <?php
                // Built above from our own escaped output plus addon hook output
                // (addons are responsible for escaping what they echo).
                echo $menu_items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Whether the current user may view/download an application's private data
     * (CV, cover letter). Allowed to: site admins, the applicant, and the
     * employer who owns the target job.
     *
     * @param  array $row Application row with user_id and job_id.
     * @return bool
     */
    public static function current_user_can_access_application( $row ) {
        if ( ! is_user_logged_in() || ! is_array( $row ) ) {
            return false;
        }

        $uid = get_current_user_id();

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( isset( $row['user_id'] ) && (int) $row['user_id'] === (int) $uid ) {
            return true; // The applicant.
        }
        $job_post = isset( $row['job_id'] ) ? get_post( absint( $row['job_id'] ) ) : null;
        if ( $job_post && (int) $job_post->post_author === (int) $uid ) {
            return true; // The employer who owns the job.
        }

        return false;
    }

    /**
     * AJAX: return one application's cover letter for the popup. Served through
     * the main plugin's custom AJAX router (`workeera_ajax_get_cover_letter`),
     * so it accepts both the front-dashboard and wp-admin nonces.
     */
    public function ajax_get_cover_letter() {
        $nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce_val, 'workeera_nonce' ) && ! wp_verify_nonce( $nonce_val, 'workeera_admin_nonce' ) ) {
            wp_send_json( [ 'error' => '1', 'reload' => '1', 'msg' => esc_html__( 'Security check failed. Please refresh the page.', 'workeera-remote-tech-job-board' ) ] );
        }

        $app_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
        if ( $app_id < 1 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Invalid application.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $wpdb;
        $apps_table = $wpdb->prefix . 'workeera_applications';
        $row        = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT application_id, job_id, user_id, applicant_name, cover_letter FROM {$apps_table} WHERE application_id = %d LIMIT 1",
                $app_id
            ),
            ARRAY_A
        );

        if ( empty( $row ) || ! self::current_user_can_access_application( $row ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You are not allowed to view this cover letter.', 'workeera-remote-tech-job-board' ) ] );
        }

        $cover = trim( (string) $row['cover_letter'] );
        if ( '' === $cover ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No cover letter was provided with this application.', 'workeera-remote-tech-job-board' ) ] );
        }

        wp_send_json(
            [
                'error'        => '0',
                'applicant'    => sanitize_text_field( (string) $row['applicant_name'] ),
                'cover_letter' => sanitize_textarea_field( $cover ),
            ]
        );
    }

    /**
     * Build the front-end download URL for an application's CV.
     * The `download_cv_handle()` callback validates permissions and streams the file.
     *
     * @param int $app_id Application row ID.
     * @return string
     */
    public static function get_download_cv_url( $app_id ) {
        return add_query_arg(
            [
                'apps_files_action' => 'download_cv',
                'app_id'            => absint( $app_id ),
                'nonce'             => wp_create_nonce( 'workeera_nonce' ),
            ],
            home_url( '/' )
        );
    }

    /**
     * Stream the application's CV file when ?apps_files_action=download_cv&app_id=…&nonce=…
     * is requested. Allowed to: site administrators, the candidate who submitted
     * the application, and the employer who owns the job the application targets.
     */
    public function download_cv_handle() {
        if ( ! isset( $_GET['apps_files_action'] ) || $_GET['apps_files_action'] !== 'download_cv' ) {
            return;
        }

        $app_id = isset( $_GET['app_id'] ) ? absint( $_GET['app_id'] ) : 0;
        $nonce  = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'workeera_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'workeera-remote-tech-job-board' ) );
        }
        if ( $app_id < 1 ) {
            wp_die( esc_html__( 'Invalid application.', 'workeera-remote-tech-job-board' ), 400 );
        }
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'You are not allowed to download this file.', 'workeera-remote-tech-job-board' ), 403 );
        }

        global $wpdb;
        $apps_table = $wpdb->prefix . 'workeera_applications';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT application_id, job_id, user_id, cv_data FROM {$apps_table} WHERE application_id = %d LIMIT 1",
                $app_id
            ),
            ARRAY_A
        );

        if ( empty( $row ) ) {
            wp_die( esc_html__( 'File not found.', 'workeera-remote-tech-job-board' ), 404 );
        }

        $current_user_id = get_current_user_id();
        $is_admin        = current_user_can( 'manage_options' );
        $is_applicant    = ( (int) $row['user_id'] === (int) $current_user_id );

        $is_employer = false;
        $job_post    = get_post( absint( $row['job_id'] ) );
        if ( $job_post && (int) $job_post->post_author === (int) $current_user_id ) {
            $is_employer = true;
        }

        if ( ! $is_admin && ! $is_applicant && ! $is_employer ) {
            wp_die( esc_html__( 'You are not allowed to download this file.', 'workeera-remote-tech-job-board' ), 403 );
        }

        $cv_data = maybe_unserialize( $row['cv_data'] );
        if ( ! is_array( $cv_data ) || empty( $cv_data['file'] ) ) {
            wp_die( esc_html__( 'File not found.', 'workeera-remote-tech-job-board' ), 404 );
        }

        $file_path = $cv_data['file'];
        if ( ! file_exists( $file_path ) ) {
            wp_die( esc_html__( 'File not found on server.', 'workeera-remote-tech-job-board' ), 404 );
        }

        // Defence-in-depth: resolve the real path of the file AND of the
        // plugin's upload root, then refuse to serve anything that resolves
        // outside the plugin's upload folder. Stops `..` traversal even if a
        // malicious row reaches the table via another vector.
        $real_file_path = realpath( $file_path );
        $upload_dir     = wp_get_upload_dir();
        $upload_basedir = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';
        $workeera_root    = $upload_basedir !== '' ? realpath( trailingslashit( $upload_basedir ) . 'wp-workeera' ) : false;
        if ( ! $real_file_path || ! $workeera_root || strpos( $real_file_path, $workeera_root . DIRECTORY_SEPARATOR ) !== 0 ) {
            wp_die( esc_html__( 'You are not allowed to download this file.', 'workeera-remote-tech-job-board' ), 403 );
        }
        $file_path = $real_file_path;

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

        $file_name = ! empty( $cv_data['name'] ) ? $cv_data['name'] : wp_basename( $file_path );
        $size      = filesize( $file_path );

        nocache_headers();
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file_name ) . '"' );
        header( 'Content-Length: ' . $size );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        readfile( $file_path );
        exit;
    }

    /**
     * Return the CV mime map (mirrors Workeera_Candidate_CV_Manager).
     */
    public static function get_cv_mime_map() {
        return [
            'pdf'           => 'application/pdf',
            'doc'           => 'application/msword',
            'docx'          => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt'           => 'text/plain',
            'jpg|jpeg|jpe'  => 'image/jpeg',
            'png'           => 'image/png',
        ];
    }

    /**
     * Resolve the configured mode of an apply-form field (Job Listings →
     * Job Applications settings).
     *
     * @param  string $field Setting key: phone_number_field | upload_cv_field | cover_letter_field.
     * @return string        'on' | 'off' | 'on_required' (defaults to 'on').
     */
    public static function apply_field_mode( $field ) {
        global $workeera_plugin_settings;

        $mode = isset( $workeera_plugin_settings['job_apply_settings'][ $field ] )
            ? (string) $workeera_plugin_settings['job_apply_settings'][ $field ]
            : 'on';

        return in_array( $mode, [ 'on', 'off', 'on_required' ], true ) ? $mode : 'on';
    }

    /**
     * Get CSS icon class for a file extension.
     */
    private static function get_cv_icon_class( $extension ) {
        $map = [
            'pdf'  => 'workeera-cvmanager-file-icon-pdf',
            'doc'  => 'workeera-cvmanager-file-icon-doc',
            'docx' => 'workeera-cvmanager-file-icon-doc',
            'txt'  => 'workeera-cvmanager-file-icon-txt',
            'jpeg' => 'workeera-cvmanager-file-icon-img',
            'jpg'  => 'workeera-cvmanager-file-icon-img',
            'png'  => 'workeera-cvmanager-file-icon-img',
        ];
        return isset( $map[ $extension ] ) ? $map[ $extension ] : 'workeera-cvmanager-file-icon-other';
    }

    /**
     * Render a single selectable CV radio row (shared between PHP initial load and JS-rendered upload result).
     */
    public static function render_cv_radio_item( $file, $is_checked = false ) {
        // Keep the raw values and escape at output time with the correct
        // escaper for each context (attribute vs body) — applicant-supplied
        // filenames must never reach the DOM unescaped.
        $file_id   = isset( $file['file_id'] ) ? (string) $file['file_id'] : '';
        $file_name = isset( $file['name'] )    ? (string) $file['name']    : '';
        $file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
        $icon_cls  = self::get_cv_icon_class( $file_ext );
        $checked   = $is_checked ? 'checked' : '';

        ?>
        <label class="workeera-applyjob-cv-item<?php echo esc_attr( $is_checked ? ' workeera-applyjob-cv-item--selected' : '' ); ?>">
            <input type="radio" name="cv_file_id" value="<?php echo esc_attr( $file_id ); ?>" <?php echo esc_attr( $checked ); ?> class="workeera-applyjob-cv-radio">
            <span class="workeera-applyjob-cv-icon <?php echo esc_attr( $icon_cls ); ?>">
                <?php echo wp_kses( (string) self::cv_file_type_svg( $file_ext ), workeera_svg_allowed_tags() ); ?>
            </span>
            <span class="workeera-applyjob-cv-name" title="<?php echo esc_attr( $file_name ); ?>"><?php echo esc_html( $file_name ); ?></span>
            <span class="workeera-applyjob-cv-check-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </span>
        </label>
        <?php
    }

    /**
     * Inline SVG for file type (reuse from CV manager pattern).
     */
    public static function cv_file_type_svg( $ext ) {
        switch ( strtolower( $ext ) ) {
            case 'pdf':
                return '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#E53E3E"/><path d="M24 0 L36 12 L24 12 Z" fill="#FC8181"/><rect x="24" y="0" width="12" height="12" fill="#C53030"/><text x="18" y="32" font-size="11" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">PDF</text></svg>';
            case 'doc':
            case 'docx':
                return '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#2B6CB0"/><path d="M24 0 L36 12 L24 12 Z" fill="#63B3ED"/><rect x="24" y="0" width="12" height="12" fill="#1A4F8A"/><text x="18" y="29" font-size="8" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">WORD</text></svg>';
            case 'txt':
                return '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="36" height="44" rx="4" fill="#718096"/><path d="M24 0 L36 12 L24 12 Z" fill="#A0AEC0"/><rect x="24" y="0" width="12" height="12" fill="#4A5568"/><rect x="5" y="18" width="22" height="2.5" rx="1" fill="white" opacity="0.9"/><rect x="5" y="24" width="22" height="2.5" rx="1" fill="white" opacity="0.7"/><rect x="5" y="30" width="16" height="2.5" rx="1" fill="white" opacity="0.7"/></svg>';
            case 'jpeg':
            case 'jpg':
            case 'png':
                return '<svg width="20" height="20" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="44" height="44" rx="4" fill="#2F855A"/><polygon points="4,44 20,18 36,44" fill="#1C4532"/><circle cx="34" cy="10" r="6" fill="#F6E05E"/></svg>';
            default:
                return '<svg width="20" height="20" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="34" height="42" rx="4" fill="none" stroke="#A0AEC0" stroke-width="2"/><rect x="6" y="22" width="20" height="2" rx="1" fill="#CBD5E0"/><rect x="6" y="28" width="16" height="2" rx="1" fill="#CBD5E0"/></svg>';
        }
    }

    /**
     * AJAX: render the apply-job form HTML inside the popup.
     */
    public function apply_job_form_ajax() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( array( 'error' => 'security_failed' ) );
        }

        $random_num = wp_rand( 10000000, 99999999 );

        $job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

        $user_id = get_current_user_id();

        $candidate_id = workeera_is_user_candidate( $user_id );

        if ( ! $candidate_id ) {
            wp_send_json( array(
                'error'     => '0',
                'form_html' => '<div class="workeera-cant-perform-msgbx"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2L1 21h22L12 2zm0 6c.55 0 1 .45 1 1v5c0 .55-.45 1-1 1s-1-.45-1-1V9c0-.55.45-1 1-1zm0 10c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z" fill-rule="evenodd"/></svg><p>' . esc_html__( 'You are not a candidate. Only a candidate user can apply for a job.', 'workeera-remote-tech-job-board' ) . '</p></div>',
            ) );
        }

        $user_data = get_userdata( $user_id );

        $email_address = workeera_get_object_meta( $candidate_id, 'email_address' );
        $phone_number  = workeera_get_object_meta( $candidate_id, 'phone_number' );

        // Load uploaded CVs for this candidate.
        $uploaded_files = workeera_get_object_meta( $candidate_id, 'candidate_cv_files' );
        if ( ! is_array( $uploaded_files ) ) {
            $uploaded_files = [];
        }

        // Remove any files whose actual file no longer exists on disk.
        $uploaded_files = array_values( array_filter( $uploaded_files, function( $f ) {
            return isset( $f['file'] ) && file_exists( $f['file'] );
        } ) );

        $has_cv_files = ! empty( $uploaded_files );

        // Settings for inline CV upload.
        global $workeera_plugin_settings, $wpdb;
        $candidate_settings = isset( $workeera_plugin_settings['candidate_settings'] ) ? $workeera_plugin_settings['candidate_settings'] : [];
        $max_files          = isset( $candidate_settings['max_cv_files'] )    ? absint( $candidate_settings['max_cv_files'] )    : 3;
        $file_size_bytes    = isset( $candidate_settings['max_cv_file_size'] ) ? intval( $candidate_settings['max_cv_file_size'] ) : 5242880;
        $file_size_mb       = round( $file_size_bytes / ( 1024 * 1024 ), 2 );
        $cv_file_types      = isset( $candidate_settings['cv_file_types'] )   ? (array) $candidate_settings['cv_file_types']   : [ 'pdf', 'docx' ];

        $mime_map      = self::get_cv_mime_map();
        $allowed_mimes = [];
        $accept_parts  = [];
        $ext_labels    = [];
        foreach ( $cv_file_types as $type ) {
            if ( isset( $mime_map[ $type ] ) ) {
                $allowed_mimes[ $type ] = $mime_map[ $type ];
                $accept_parts[]         = $mime_map[ $type ];
                $ext_labels[]           = strtoupper( $type );
            }
        }
        $accept_attr  = implode( ',', $accept_parts );
        $allowed_ext_str = implode( ', ', $ext_labels );

        ob_start();
        ?>
        <div class="workeera-common-wrapper workeera-applyjob-form-maincon">
            <div class="workeera-applyjob-form-iner-con">
                <?php
                // Check duplicate application
                $apps_table = $wpdb->prefix . 'workeera_applications';
                $already_applied = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT application_id FROM {$apps_table} WHERE job_id = %d AND user_id = %d LIMIT 1",
                        $job_id,
                        $user_id
                    )
                );
                if ( $already_applied ) {
                    ?>
                    <div class="workeera-applyjob-already-applied-msg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2L1 21h22L12 2zm0 6c.55 0 1 .45 1 1v5c0 .55-.45 1-1 1s-1-.45-1-1V9c0-.55.45-1 1-1zm0 10c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z" fill-rule="evenodd"/></svg>
                        <p><?php esc_html_e( 'You have already applied for this job.', 'workeera-remote-tech-job-board' ); ?></p>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="workeera-applyjob-form-header">
                        <span class="workeera-applyjob-title"><?php esc_html_e( 'Apply Job', 'workeera-remote-tech-job-board' ); ?></span>
                    </div>
                    <form method="post" class="workeera-user-form workeera-withbtn-inline-lodr">
                        <div class="workeera-cmnfrm-wrapper">
                            <div class="workeera-cmnfrm-fields-main-wrapper">
                                <div class="workeera-cmnfrm-fields-row">
                                    <div class="workeera-cmnfrm-field-group">
                                        <label for="given-name-<?php echo absint( $random_num ); ?>" class="workeera-cmnfrm-field-label"><?php echo esc_html_x( 'Given name', 'Apply job', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'given-name-' . $random_num,
                                            'name'             => 'given_name',
                                            'classes'          => [ 'workeera-cmnfrm-field-input' ],
                                            'extra_attributes' => [
                                                'placeholder' => esc_attr_x( 'John', 'Apply job', 'workeera-remote-tech-job-board' ),
                                                'required'    => '',
                                            ],
                                            'value'            => isset( $user_data->first_name ) ? $user_data->first_name : '',
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                    <div class="workeera-cmnfrm-field-group">
                                        <label for="family-name-<?php echo absint( $random_num ); ?>" class="workeera-cmnfrm-field-label"><?php echo esc_html_x( 'Family name', 'Apply job', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'family-name-' . $random_num,
                                            'name'             => 'family_name',
                                            'classes'          => [ 'workeera-cmnfrm-field-input' ],
                                            'extra_attributes' => [
                                                'placeholder' => esc_attr_x( 'Doe', 'Apply job', 'workeera-remote-tech-job-board' ),
                                                'required'    => '',
                                            ],
                                            'value'            => isset( $user_data->last_name ) ? $user_data->last_name : '',
                                            'default'          => '',
                                        ] );
                                        ?>
                                    </div>
                                </div>
                                <?php
                                // Apply-form field modes (Job Listings → Job Applications settings).
                                $phone_mode        = self::apply_field_mode( 'phone_number_field' );
                                $cv_mode           = self::apply_field_mode( 'upload_cv_field' );
                                $cover_letter_mode = self::apply_field_mode( 'cover_letter_field' );
                                ?>
                                <div class="workeera-cmnfrm-fields-row">
                                    <div class="workeera-cmnfrm-field-group">
                                        <label for="email-address-<?php echo absint( $random_num ); ?>" class="workeera-cmnfrm-field-label"><?php echo esc_html_x( 'Email Address', 'Apply job', 'workeera-remote-tech-job-board' ); ?></label>
                                        <?php
                                        Workeera_Form_Fields::input( [
                                            'id'               => 'email-address-' . $random_num,
                                            'input_type'       => 'email',
                                            'name'             => 'email_address',
                                            'classes'          => [ 'workeera-cmnfrm-field-input' ],
                                            'extra_attributes' => [
                                                'placeholder' => esc_attr_x( 'example@abc.com', 'Apply job', 'workeera-remote-tech-job-board' ),
                                            ],
                                            'value'            => $email_address,
                                            'default'          => isset( $user_data->user_email ) ? $user_data->user_email : '',
                                        ] );
                                        ?>
                                    </div>
                                    <?php if ( 'off' !== $phone_mode ) : ?>
                                        <div class="workeera-cmnfrm-field-group">
                                            <label for="phone-number-<?php echo absint( $random_num ); ?>" class="workeera-cmnfrm-field-label"><?php echo esc_html_x( 'Phone Number', 'Apply job', 'workeera-remote-tech-job-board' ); ?></label>
                                            <?php
                                            $phone_extra = [];
                                            if ( 'on_required' === $phone_mode ) {
                                                $phone_extra['required'] = '';
                                            }
                                            Workeera_Form_Fields::input( [
                                                'id'               => 'phone-number-' . $random_num,
                                                'input_type'       => 'tel',
                                                'name'             => 'phone_number',
                                                'classes'          => [ 'workeera-cmnfrm-field-input' ],
                                                'extra_attributes' => $phone_extra,
                                                'value'            => $phone_number,
                                                'default'          => '',
                                            ] );
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ( 'off' !== $cv_mode ) : ?>
                                <div class="workeera-cmnfrm-field-group workeera-applyjob-cv-field-group" data-cv-required="<?php echo esc_attr( 'on_required' === $cv_mode ? '1' : '0' ); ?>">
                                    <label class="workeera-cmnfrm-field-label"><?php echo esc_html_x( 'CV / Resume', 'Apply job', 'workeera-remote-tech-job-board' ); ?></label>

                                    <?php if ( $has_cv_files ) : ?>
                                        <div class="workeera-applyjob-cv-list" data-max-size="<?php echo absint( $file_size_bytes ); ?>" data-accept="<?php echo esc_attr( $accept_attr ); ?>" data-max-files="<?php echo absint( $max_files ); ?>">
                                            <?php
                                            foreach ( $uploaded_files as $index => $cv_file ) {
                                                self::render_cv_radio_item( $cv_file, $index === 0 );
                                            }
                                            ?>
                                        </div>
                                        <?php if ( count( $uploaded_files ) < $max_files ) : ?>
                                            <button type="button" class="workeera-btn workeera-applyjob-cv-upload-trigger workeera-applyjob-cv-addmore-btn">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                <?php echo esc_html_x( 'Upload New CV', 'Apply job', 'workeera-remote-tech-job-board' ); ?>
                                            </button>
                                        <?php endif; ?>

                                    <?php else : ?>
                                        <div class="workeera-applyjob-cv-upload-box" data-max-size="<?php echo absint( $file_size_bytes ); ?>" data-accept="<?php echo esc_attr( $accept_attr ); ?>">
                                            <div class="workeera-applyjob-cv-upload-icon">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            </div>
                                            <p class="workeera-applyjob-cv-upload-info">
                                                <?php
                                                printf(
                                                    /* translators: 1: allowed types, 2: max size in MB */
                                                    esc_html_x( 'Allowed: %1$s &mdash; Max %2$s MB', 'Apply job CV upload', 'workeera-remote-tech-job-board' ),
                                                    '<strong>' . esc_html( $allowed_ext_str ) . '</strong>',
                                                    '<strong>' . esc_html( $file_size_mb ) . '</strong>'
                                                );
                                                ?>
                                            </p>
                                            <button type="button" class="workeera-btn workeera-applyjob-cv-upload-trigger">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                <?php echo esc_html_x( 'Upload CV', 'Apply job', 'workeera-remote-tech-job-board' ); ?>
                                            </button>
                                            <div class="workeera-applyjob-cv-list workeera-applyjob-cv-list--inline"></div>
                                        </div>

                                    <?php endif; ?>

                                    <input type="file" class="workeera-applyjob-cv-file-input workeera-hide" accept="<?php echo esc_attr( $accept_attr ); ?>">

                                    <input type="hidden" name="cv_file_id" class="workeera-applyjob-cv-hidden-id" value="<?php echo esc_attr( $has_cv_files ? $uploaded_files[0]['file_id'] : '' ); ?>">

                                </div>
                                <?php endif; ?>

                                <?php if ( 'off' !== $cover_letter_mode ) : ?>
                                <div class="workeera-cmnfrm-field-group">
                                    <label for="cover-letter-<?php echo absint( $random_num ); ?>" class="workeera-cmnfrm-field-label"><?php echo esc_html_x( 'Cover Letter', 'Apply job', 'workeera-remote-tech-job-board' ); ?></label>
                                    <?php
                                    $cover_letter_extra = [];
                                    if ( 'on_required' === $cover_letter_mode ) {
                                        $cover_letter_extra['required'] = '';
                                    }
                                    Workeera_Form_Fields::textarea( [
                                        'id'               => 'cover-letter-' . $random_num,
                                        'name'             => 'cover_letter',
                                        'classes'          => [ 'workeera-cmnfrm-field-textarea' ],
                                        'extra_attributes' => $cover_letter_extra,
                                        'value'            => '',
                                        'default'          => '',
                                    ] );
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php workeera_terms_privacy_policy() ?>

                            <div class="workeera-form-submitbtn-con">
                                <button type="submit" class="workeera-btn workeera-large-btn"><?php echo esc_html_x( 'Apply Job', 'Apply job', 'workeera-remote-tech-job-board' ); ?></button>
                                <input type="hidden" name="job_id" value="<?php echo absint( $job_id ); ?>">
                                <?php wp_nonce_field( 'workeera_nonce', 'workeera_nonce' ); ?>
                                <input type="hidden" name="action" value="workeera_user_applyjob_call">
                            </div>
                        </div>
                    </form>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
        $form_html = ob_get_clean();

        wp_send_json( array( 'error' => '0', 'form_html' => $form_html ) );
    }

    /**
     * Output the apply-job modal markup in the footer of relevant pages.
     */
    public function popup_modal_init() {
        global $workeera_plugin_settings;

        $jobs_page = isset( $workeera_plugin_settings['pages_settings']['jobs_page'] ) ? $workeera_plugin_settings['pages_settings']['jobs_page'] : '';
        if ( ( ! empty( $jobs_page ) && get_post_type( $jobs_page ) === 'page' && is_page( $jobs_page ) ) || is_singular( 'workeera_job' ) ) {
            ?>
            <div id="workeera-apply-job-popup" class="workeera-modal-popup workeera-popup-hide">
                <div class="workeera-modal-container">
                    <div class="workeera-modal-inner-area">
                        <div class="workeera-modal-content-area">
                            <a href="javascript:;" class="workeera-popup-closebtn"><span class="workeera-remove-icon"></span></a>
                            <div class="workeera-modalinit-con"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * AJAX: upload a CV file directly from the apply-job popup.
     * Saves it to candidate_cv_files meta (same structure as cv-manager.php).
     */
    public function applyjob_cv_upload_ajax() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'error' => 'security_failed' ] );
        }

        $user_id = get_current_user_id();

        $candidate_id = workeera_is_user_candidate( $user_id );
        if ( ! $candidate_id ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Candidate profile not found.', 'workeera-remote-tech-job-board' ) ] );
        }

        if ( ! isset( $_FILES['cv_file'] ) || empty( $_FILES['cv_file']['size'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'No file uploaded.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $workeera_plugin_settings;
        $candidate_settings = isset( $workeera_plugin_settings['candidate_settings'] ) ? $workeera_plugin_settings['candidate_settings'] : [];
        $max_files          = isset( $candidate_settings['max_cv_files'] )    ? absint( $candidate_settings['max_cv_files'] )    : 3;
        $max_size_bytes     = isset( $candidate_settings['max_cv_file_size'] ) ? intval( $candidate_settings['max_cv_file_size'] ) : 5242880;
        $cv_file_types      = isset( $candidate_settings['cv_file_types'] )   ? (array) $candidate_settings['cv_file_types']   : [ 'pdf', 'docx' ];

        // Check existing count.
        $existing_files = workeera_get_object_meta( $candidate_id, 'candidate_cv_files' );
        if ( ! is_array( $existing_files ) ) {
            $existing_files = [];
        }
        if ( count( $existing_files ) >= $max_files ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'You have reached the maximum number of CV files.', 'workeera-remote-tech-job-board' ) ] );
        }

        // $_FILES values are typed (size is int, error is int, name/tmp_name/type are strings).
        // Sanitize text fields with sanitize_text_field; numeric fields are validated as ints below.
        $file = array_map( 'sanitize_text_field', $_FILES['cv_file'] );

        if ( absint( $file['size'] ) > $max_size_bytes ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'File size exceeds the allowed limit.', 'workeera-remote-tech-job-board' ) ] );
        }

        $mime_map      = self::get_cv_mime_map();
        $allowed_mimes = [];
        foreach ( $cv_file_types as $type ) {
            if ( isset( $mime_map[ $type ] ) ) {
                $allowed_mimes[ $type ] = $mime_map[ $type ];
            }
        }

        $file_type_check = wp_check_filetype( $file['name'] );
        if ( ! isset( $file_type_check['type'] ) || ! in_array( $file_type_check['type'], array_values( $allowed_mimes ), true ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'File type not allowed.', 'workeera-remote-tech-job-board' ) ] );
        }

        global $workeera_upload_files_extpath;
        $workeera_upload_files_extpath = 'candidate-cvs';
        add_filter( 'upload_dir', 'workeera_public_upload_files_path' );

        $uploaded = wp_handle_upload( $file, [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ] );

        remove_filter( 'upload_dir', 'workeera_public_upload_files_path' );

        if ( isset( $uploaded['error'] ) ) {
            /* translators: %s: error message */
            wp_send_json( [ 'error' => '1', 'msg' => sprintf( esc_html__( 'Upload failed: %s', 'workeera-remote-tech-job-board' ), $uploaded['error'] ) ] );
        }

        $file_id   = uniqid( 'cv_', true );
        $file_data = [
            'file_id' => $file_id,
            'file'    => $uploaded['file'],
            'url'     => $uploaded['url'],
            'name'    => sanitize_file_name( $file['name'] ),
            'size'    => absint( $file['size'] ),
            'type'    => $file['type'],
            'date'    => current_time( 'mysql' ),
        ];

        $existing_files[] = $file_data;
        workeera_update_object_meta( $candidate_id, 'candidate_cv_files', $existing_files );

        // Return the rendered radio item HTML so JS can inject it.
        ob_start();
        self::render_cv_radio_item( $file_data, true );
        $item_html = ob_get_clean();

        wp_send_json( [
            'error'     => '0',
            'msg'       => esc_html__( 'CV uploaded successfully.', 'workeera-remote-tech-job-board' ),
            'file_id'   => $file_id,
            'item_html' => $item_html,
        ] );
    }

    /**
     * Copy a candidate-owned CV file into the application-files folder so the
     * application retains an independent copy. Returns a new $cv_data array
     * with the `file` and `url` keys repointed to the new copy. All other
     * fields (file_id, name, size, type, date) are preserved.
     *
     * Returns false on failure (missing source, target dir not writable, copy failed).
     *
     * @param  array $cv_data Original CV data array (must contain `file`).
     * @return array|false
     */
    private static function copy_cv_to_application_files( $cv_data ) {
        if ( ! is_array( $cv_data ) || empty( $cv_data['file'] ) || ! file_exists( $cv_data['file'] ) ) {
            return false;
        }

        // The copy source comes from stored meta — confirm it resolves inside the
        // plugin's uploads folder before copying it anywhere public.
        $safe_source = function_exists( 'workeera_safe_upload_path' ) ? workeera_safe_upload_path( $cv_data['file'] ) : $cv_data['file'];
        if ( ! $safe_source ) {
            return false;
        }
        $cv_data['file'] = $safe_source;

        // Resolve target dir via the plugin's own upload-path filter so the
        // year/month subdir convention matches the rest of the plugin.
        global $workeera_upload_files_extpath;
        $previous_extpath            = $workeera_upload_files_extpath;
        $workeera_upload_files_extpath = 'application-files';

        add_filter( 'upload_dir', 'workeera_public_upload_files_path' );
        // wp_get_upload_dir() bypasses wp_upload_dir()'s static cache so the
        // filter is guaranteed to apply on this call.
        $upload_dir = wp_get_upload_dir();
        remove_filter( 'upload_dir', 'workeera_public_upload_files_path' );

        $workeera_upload_files_extpath = $previous_extpath;

        $target_dir_path = isset( $upload_dir['path'] ) ? $upload_dir['path'] : '';
        $target_dir_url  = isset( $upload_dir['url'] )  ? $upload_dir['url']  : '';

        if ( empty( $target_dir_path ) || empty( $target_dir_url ) ) {
            return false;
        }

        if ( ! wp_mkdir_p( $target_dir_path ) ) {
            return false;
        }

        // Lock down the application-files folder so the raw file URL can't be
        // downloaded directly — every CV must come through the gated handler.
        if ( function_exists( 'workeera_protect_private_upload_dir' ) ) {
            workeera_protect_private_upload_dir( $target_dir_path );
        }

        $original_basename = ! empty( $cv_data['name'] )
            ? sanitize_file_name( $cv_data['name'] )
            : sanitize_file_name( wp_basename( $cv_data['file'] ) );

        // Never write a disallowed / executable extension into a public folder,
        // regardless of what the stored CV record claims the name is. The upload
        // allowlist guards the uploaded file; this guards the file that lands.
        $type_check = wp_check_filetype( $original_basename, get_allowed_mime_types() );
        if ( empty( $type_check['ext'] ) || empty( $type_check['type'] ) ) {
            return false;
        }

        // Avoid name collisions across applications.
        $unique_basename = wp_unique_filename( $target_dir_path, $original_basename );

        $target_path = trailingslashit( $target_dir_path ) . $unique_basename;
        $target_url  = trailingslashit( $target_dir_url )  . $unique_basename;

        // Copy the CV via the WP_Filesystem API rather than a direct copy() call.
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( empty( $wp_filesystem ) ) {
            WP_Filesystem();
        }
        if ( ! $wp_filesystem || ! $wp_filesystem->copy( $cv_data['file'], $target_path, true ) ) {
            return false;
        }

        $new_cv_data         = $cv_data;
        $new_cv_data['file'] = $target_path;
        $new_cv_data['url']  = $target_url;

        return $new_cv_data;
    }

    /**
     * AJAX: process the job application submission.
     */
    public function user_applyjob_call_ajax() {
        global $wpdb;

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json( array( 'error' => 'security_failed' ) );
        }

        $job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

        if ( $job_id < 1 ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Invalid job.', 'workeera-remote-tech-job-board' ) ) );
        }

        $user_id = get_current_user_id();

        // Verify candidate
        $candidate_id = workeera_is_user_candidate( $user_id );
        if ( ! $candidate_id ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ) );
        }

        // Verify the job post is published
        $job_post = get_post( $job_id );
        if ( ! $job_post || $job_post->post_type !== 'workeera_job' || $job_post->post_status !== 'publish' ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'This job is not available.', 'workeera-remote-tech-job-board' ) ) );
        }

        $candidate_post = get_post( $candidate_id );
        if ( ! $candidate_post || $candidate_post->post_status !== 'publish' ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Your profile is not approved yet.', 'workeera-remote-tech-job-board' ) ) );
        }

        // Check job_fields: expiry, deadline, is_filled
        $job_fields = Workeera_Query_Handler::get_saved_record( 'job_fields', [ 'job_id' => $job_id ], 'all', 1 );

        if ( ! empty( $job_fields ) ) {
            $current_time = current_time( 'timestamp' );

            // Expiry date.
            if ( ! empty( $job_fields['expiry_date'] ) ) {
                $expiry_ts = strtotime( $job_fields['expiry_date'] );
                if ( $expiry_ts && $current_time > $expiry_ts ) {
                    wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'This job listing has expired.', 'workeera-remote-tech-job-board' ) ) );
                }
            }

            // Application deadline.
            if ( ! empty( $job_fields['application_deadline'] ) ) {
                $deadline_ts = strtotime( $job_fields['application_deadline'] );
                if ( $deadline_ts && $current_time > $deadline_ts ) {
                    wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'The application deadline for this job has passed.', 'workeera-remote-tech-job-board' ) ) );
                }
            }

            // Is filled.
            if ( ! empty( $job_fields['is_filled'] ) && (int) $job_fields['is_filled'] === 1 ) {
                wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'This job position has already been filled.', 'workeera-remote-tech-job-board' ) ) );
            }
        }

        // Check duplicate application
        $apps_table   = $wpdb->prefix . 'workeera_applications';
        $already_applied = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT application_id FROM {$apps_table} WHERE job_id = %d AND user_id = %d LIMIT 1",
                $job_id,
                $user_id
            )
        );
        if ( $already_applied ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'You have already applied for this job.', 'workeera-remote-tech-job-board' ) ) );
        }

        // Sanitize form fields
        $given_name   = isset( $_POST['given_name'] )    ? sanitize_text_field( wp_unslash( $_POST['given_name'] ) )    : '';
        $family_name  = isset( $_POST['family_name'] )   ? sanitize_text_field( wp_unslash( $_POST['family_name'] ) )   : '';
        $email        = isset( $_POST['email_address'] ) ? sanitize_email( wp_unslash( $_POST['email_address'] ) )      : '';
        $phone        = isset( $_POST['phone_number'] )  ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) )  : '';
        $cover_letter = isset( $_POST['cover_letter'] )  ? sanitize_textarea_field( wp_unslash( $_POST['cover_letter'] ) ) : '';

        if ( mb_strlen( $given_name ) > 100 || mb_strlen( $family_name ) > 100 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Name is too long. Please make it shorter.', 'workeera-remote-tech-job-board' ) ] );
        }

        if ( mb_strlen( $cover_letter ) > 3000 ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__( 'Cover letter is too long (max 3000 characters).', 'workeera-remote-tech-job-board' ) ] );
        }

        $applicant_name = trim( $given_name . ' ' . $family_name );
        if ( empty( $applicant_name ) ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Applicant name is required.', 'workeera-remote-tech-job-board' ) ) );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'A valid email address is required.', 'workeera-remote-tech-job-board' ) ) );
        }

        // Enforce the configured apply-form field modes server-side — the
        // form hides disabled fields and marks required ones, but the
        // settings are only trustworthy when re-checked here.
        $phone_mode        = self::apply_field_mode( 'phone_number_field' );
        $cv_mode           = self::apply_field_mode( 'upload_cv_field' );
        $cover_letter_mode = self::apply_field_mode( 'cover_letter_field' );

        if ( 'off' === $phone_mode ) {
            $phone = ''; // Field disabled — never store a smuggled value.
        } elseif ( 'on_required' === $phone_mode && '' === trim( $phone ) ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Phone number is required.', 'workeera-remote-tech-job-board' ) ) );
        }
        if ( mb_strlen( $phone ) > 50 ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Phone number is too long.', 'workeera-remote-tech-job-board' ) ) );
        }

        if ( 'off' === $cover_letter_mode ) {
            $cover_letter = '';
        } elseif ( 'on_required' === $cover_letter_mode && '' === trim( $cover_letter ) ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Cover letter is required.', 'workeera-remote-tech-job-board' ) ) );
        }

        // Resolve selected CV data
        $cv_data      = null;
        $cv_file_id   = isset( $_POST['cv_file_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cv_file_id'] ) ) : '';

        if ( 'off' === $cv_mode ) {
            $cv_file_id = ''; // Field disabled — ignore any submitted selection.
        }

        if ( ! empty( $cv_file_id ) ) {
            $candidate_files = workeera_get_object_meta( $candidate_id, 'candidate_cv_files' );
            if ( is_array( $candidate_files ) ) {
                foreach ( $candidate_files as $cv_file ) {
                    if ( isset( $cv_file['file_id'] ) && $cv_file['file_id'] === $cv_file_id ) {
                        $cv_data = $cv_file;
                        break;
                    }
                }
            }
        }

        // CV required but nothing valid selected → reject before insert.
        if ( 'on_required' === $cv_mode && ! is_array( $cv_data ) ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Please select or upload a CV to apply.', 'workeera-remote-tech-job-board' ) ) );
        }

        // Make an independent copy of the selected CV in application-files so the
        // application keeps its own copy regardless of future candidate CV changes.
        if ( is_array( $cv_data ) && ! empty( $cv_data['file'] ) ) {
            $copied_cv = self::copy_cv_to_application_files( $cv_data );
            if ( is_array( $copied_cv ) ) {
                $cv_data = $copied_cv;
            } else {
                wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Failed to attach CV. Please try again.', 'workeera-remote-tech-job-board' ) ) );
            }
        }

        $ip_address = workeera_user_ip_address();

        $insert_result = $wpdb->insert(
            $apps_table,
            [
                'job_id'          => $job_id,
                'user_id'         => $user_id,
                'applicant_name'  => $applicant_name,
                'applicant_email' => $email,
                'applicant_phone' => $phone,
                'cover_letter'    => $cover_letter,
                'cv_data'         => maybe_serialize( $cv_data ),
                'status'          => 'pending',
                'ip_address'      => $ip_address,
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $insert_result === false ) {
            wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Failed to submit application. Please try again.', 'workeera-remote-tech-job-board' ) ) );
        }

        /**
         * Fires after a job application is successfully inserted.
         *
         * @param int   $application_id  Newly inserted application ID.
         * @param int   $job_id          Job post ID.
         * @param int   $user_id         Applicant user ID.
         * @param int   $candidate_id    Candidate post ID.
         */
        do_action( 'workeera_after_job_applied', $wpdb->insert_id, $job_id, $user_id, $candidate_id );

        ob_start();
        ?>
        <div class="workeera-applyjob-success-con">
            <div class="workeera-applyjob-success-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h3 class="workeera-applyjob-success-title"><?php esc_html_e( 'Application Submitted', 'workeera-remote-tech-job-board' ); ?></h3>
            <p class="workeera-applyjob-success-msg"><?php esc_html_e( 'Thank you for applying! Your application has been received.', 'workeera-remote-tech-job-board' ); ?></p>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json( array( 'error' => '0', 'msg' => esc_html__( 'Job applied successfully.', 'workeera-remote-tech-job-board' ), 'html' => $html, 'callback' => 'workeera_apply_job_callback' ) );
    }
}

new Workeera_Job_Applications;