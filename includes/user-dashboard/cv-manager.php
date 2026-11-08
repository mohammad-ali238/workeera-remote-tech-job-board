<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Candidate CV Manager — upload, list, download, and delete CV files.
 */
class Workeera_Candidate_CV_Manager {

    /**
     * Register asset enqueue and AJAX handlers for upload and delete.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 6);
        add_action('wp_ajax_workeera_cv_upload',   [ $this, 'upload_cv_file' ]);
        add_action('wp_ajax_workeera_cv_delete',   [ $this, 'delete_cv_file' ]);
    }

    /**
     * Enqueue CV manager assets and localized config on the dashboard's CV section.
     */
    public function enqueue_scripts() {
        global $workeera_plugin_settings;

        $dashboard_page = isset($workeera_plugin_settings['pages_settings']['user_dashboard_page']) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        if (!empty($dashboard_page) && get_post_type($dashboard_page) == 'page' && is_page($dashboard_page) && isset($_GET['section-type']) && $_GET['section-type'] == 'cv-manager') {
            wp_enqueue_style('workeera-cv-manager', Workeera_Plugin::root_url() . 'css/cv-manager.css', array('workeera-styles'), WORKEERA_VERSION);
            wp_enqueue_script('workeera-cv-manager', Workeera_Plugin::root_url() . 'js/cv-manager.js', array('workeera-scripts'), WORKEERA_VERSION, true);

            $candidate_settings = isset($workeera_plugin_settings['candidate_settings']) ? $workeera_plugin_settings['candidate_settings'] : [];
            $max_files = isset($candidate_settings['max_cv_files']) ? absint($candidate_settings['max_cv_files']) : 3;
            $file_size_bytes = isset($candidate_settings['max_cv_file_size']) ? intval($candidate_settings['max_cv_file_size']) : 5242880;
            $file_size_mb = round($file_size_bytes / (1024 * 1024), 2);
            $cv_file_types = isset($candidate_settings['cv_file_types']) ? (array) $candidate_settings['cv_file_types'] : ['pdf', 'docx'];

            $mime_map = self::get_cv_mime_map();
            $allowed_mimes = [];
            $allowed_extensions = [];
            foreach ($cv_file_types as $type) {
                if (isset($mime_map[$type])) {
                    $allowed_mimes[$type] = $mime_map[$type];
                    $allowed_extensions[] = strtoupper($type);
                }
            }

            wp_localize_script('workeera-cv-manager', 'workeera_cv_manager', [
                'ajax_url'             => admin_url('admin-ajax.php'),
                'nonce'                => wp_create_nonce('workeera_nonce'),
                'max_files'            => $max_files,
                'max_file_size'        => $file_size_mb, // in MB for UI
                'max_file_size_bytes'  => $file_size_bytes,
                'allowed_mimes'        => $allowed_mimes,
                'allowed_extensions'   => $allowed_extensions,
                'delete_confirm_title' => esc_html_x('Confirmation', 'Confirm box title', 'workeera-remote-tech-job-board'),
                'delete_confirm_msg'   => esc_html_x('Are you sure you want to delete this CV file?', 'CV manager', 'workeera-remote-tech-job-board'),
                'delete_btn_label'     => esc_html_x('Delete', 'Button', 'workeera-remote-tech-job-board'),
                'cancel_btn_label'     => esc_html_x('Cancel', 'Button', 'workeera-remote-tech-job-board'),
                'max_files_reached_msg'=> esc_html_x('You have reached the maximum number of uploaded CV files.', 'CV manager', 'workeera-remote-tech-job-board'),
                'upload_error_filetype'=> esc_html_x('File type not allowed.', 'CV manager', 'workeera-remote-tech-job-board'),
                'upload_error_filesize'=> esc_html_x('File size exceeds the allowed limit.', 'CV manager', 'workeera-remote-tech-job-board'),
                'upload_error_generic' => esc_html_x('An error occurred while uploading.', 'CV manager', 'workeera-remote-tech-job-board'),
                'upload_success'       => esc_html_x('CV file uploaded successfully.', 'CV manager', 'workeera-remote-tech-job-board'),
                'delete_success'       => esc_html_x('CV file deleted successfully.', 'CV manager', 'workeera-remote-tech-job-board'),
                'select_file_prompt'   => esc_html_x('Select a file to upload', 'CV manager', 'workeera-remote-tech-job-board'),
                'upload_btn_text'      => esc_html_x('Upload CV', 'CV manager', 'workeera-remote-tech-job-board'),
                'add_more_text'        => esc_html_x('Add More CV', 'CV manager', 'workeera-remote-tech-job-board'),
                'file_type_icon_map'   => [
                    'pdf'           => 'workeera-cvmanager-file-icon-pdf',
                    'doc'           => 'workeera-cvmanager-file-icon-doc',
                    'docx'          => 'workeera-cvmanager-file-icon-doc',
                    'txt'           => 'workeera-cvmanager-file-icon-txt',
                    'jpg|jpeg|jpe'  => 'workeera-cvmanager-file-icon-img',
                    'png'           => 'workeera-cvmanager-file-icon-img',
                ],
            ]);
        }
    }

    /**
     * Map of accepted CV file-type slugs to their canonical MIME types.
     *
     * @return array
     */
    private static function get_cv_mime_map() {
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
     * Render the CV manager section — upload box, file list, and add-more controls.
     */
    public static function files() {
        global $workeera_plugin_settings;

        $candidate_settings = isset($workeera_plugin_settings['candidate_settings']) ? $workeera_plugin_settings['candidate_settings'] : [];
        $max_files = isset($candidate_settings['max_cv_files']) ? absint($candidate_settings['max_cv_files']) : 3;
        $file_size_bytes = isset($candidate_settings['max_cv_file_size']) ? intval($candidate_settings['max_cv_file_size']) : 5242880;
        $file_size_mb = round($file_size_bytes / (1024 * 1024), 2);
        $cv_file_types = isset($candidate_settings['cv_file_types']) ? (array) $candidate_settings['cv_file_types'] : ['pdf', 'docx'];

        $allowed_ext_display = array_map('strtoupper', $cv_file_types);
        $allowed_ext_str = implode(', ', $allowed_ext_display);

        $user_id = get_current_user_id();
        $candidate_id = workeera_is_user_candidate($user_id);
        $uploaded_files = [];
        if ($candidate_id) {
            $uploaded_files = workeera_get_object_meta($candidate_id, 'candidate_cv_files');
            if (!is_array($uploaded_files)) {
                $uploaded_files = [];
            }
        }

        $has_files = !empty($uploaded_files);
        $file_count = count($uploaded_files);
        $nonce = wp_create_nonce('workeera_nonce');
        ?>
        <div class="workeera-cvmanager-files-wrap workeera-usrdshb-main-wrap">
            <div class="workeera-usrdshb-container">

                <div class="workeera-cvmanager-header">
                    <strong class="workeera-cvmanager-title"><?php esc_html_e('CV manager', 'workeera-remote-tech-job-board'); ?></strong>
                    <div class="workeera-cvmanager-sub"><?php esc_html_e('Upload your CV file for better visibility', 'workeera-remote-tech-job-board'); ?></div>
                </div>
                <!-- Upload Box (hidden if files exist) -->
                <div class="workeera-cvmanager-upload-box <?php echo esc_attr($has_files ? 'workeera-hide' : ''); ?>">
                    <div class="workeera-cvmanager-upload-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="17 8 12 3 7 8" />
                            <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                    </div>
                    <div class="workeera-cvmanager-upload-info">
                        <?php
                        printf(
                            /* translators: 1: comma-separated allowed file extensions, 2: maximum size in megabytes */
                            esc_html_x('Allowed file types: %1$s. Max file size: %2$s MB.', 'CV manager upload info', 'workeera-remote-tech-job-board'),
                            '<strong>' . esc_html($allowed_ext_str) . '</strong>',
                            '<strong>' . esc_html($file_size_mb) . '</strong>'
                        );
                        ?>
                    </div>
                    <button type="button" class="workeera-btn workeera-large-btn workeera-cvmanager-upload-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <?php echo esc_html_x('Upload CV', 'CV manager', 'workeera-remote-tech-job-board'); ?>
                    </button>
                    <input type="file" class="workeera-cvmanager-file-input workeera-hide" accept="<?php echo esc_attr(self::get_accept_attribute($cv_file_types)); ?>" />
                </div>

                <!-- Files List -->
                <div class="workeera-cvmanager-files-list">
                    <?php foreach ($uploaded_files as $file) {
                        self::render_file_item($file, $candidate_id, $nonce);
                    } ?>
                </div>

                <!-- Add More Wrapper -->
                <div class="workeera-cvmanager-addmore-wrap">
                    <?php if ($has_files && $file_count < $max_files): ?>
                        <button type="button" class="workeera-btn workeera-cvmanager-addmore-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <?php echo esc_html_x('Add More CV', 'CV manager', 'workeera-remote-tech-job-board'); ?>
                        </button>
                    <?php elseif ($has_files && $file_count >= $max_files): ?>
                        <p class="workeera-cvmanager-max-msg"><?php echo esc_html_x('You have reached the maximum number of CV files.', 'CV manager', 'workeera-remote-tech-job-board'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="workeera-cvmanager-loader workeera-hide">
                    <?php self::loader_html(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single CV file item HTML (used in both initial load and AJAX response).
     */
    private static function render_file_item($file, $candidate_id, $nonce = null) {
        if ($nonce === null) {
            $nonce = wp_create_nonce('workeera_nonce');
        }
        // Keep raw values; escape at output time with the right escaper per context.
        $file_id      = isset($file['file_id']) ? (string) $file['file_id'] : '';
        $file_name    = isset($file['name'])    ? (string) $file['name']    : '';
        $file_ext     = pathinfo($file_name, PATHINFO_EXTENSION);
        $download_url = add_query_arg([
            'workeera_files_action' => 'download',
            'obj_id'              => $candidate_id,
            'file_id'             => $file_id,
            'nonce'               => $nonce,
        ], home_url());
        $icon_class = self::get_file_icon_class($file_ext);
        ?>
        <div class="workeera-cvmanager-file-item" data-file-id="<?php echo esc_attr($file_id); ?>">
            <div class="workeera-cvmanager-file-icon <?php echo esc_attr($icon_class); ?>">
                <?php self::file_type_svg($file_ext); ?>
            </div>
            <span class="workeera-cvmanager-file-name" title="<?php echo esc_attr($file_name); ?>"><?php echo esc_html($file_name); ?></span>
            <a href="<?php echo esc_url($download_url); ?>" class="workeera-cvmanager-download-btn" title="<?php echo esc_attr_x('Download', 'CV manager', 'workeera-remote-tech-job-board'); ?>" download>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
            </a>
            <?php
            /**
             * Render extra per-file actions in the CV manager list (e.g.
             * addon parse/analyze buttons) — between download and delete.
             *
             * @param array $file         CV file data (file_id, file, url, name, size, type, date).
             * @param int   $candidate_id Candidate post ID owning the file.
             */
            do_action('workeera_cv_file_actions', $file, $candidate_id);
            ?>
            <button class="workeera-cvmanager-delete-btn" data-file-id="<?php echo esc_attr($file_id); ?>" title="<?php echo esc_attr_x('Delete', 'CV manager', 'workeera-remote-tech-job-board'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                    <path d="M10 11v6" />
                    <path d="M14 11v6" />
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                </svg>
            </button>
        </div>
        <?php
    }

    /**
     * Return accept attribute string for file input.
     */
    private static function get_accept_attribute($cv_file_types) {
        $mime_map = self::get_cv_mime_map();
        $accept = [];
        foreach ($cv_file_types as $type) {
            if (isset($mime_map[$type])) {
                $accept[] = $mime_map[$type];
            }
        }
        return implode(',', $accept);
    }

    /**
     * Get CSS class for file type icon based on extension.
     */
    private static function get_file_icon_class($extension) {
        $map = [
            'pdf'  => 'workeera-cvmanager-file-icon-pdf',
            'doc'  => 'workeera-cvmanager-file-icon-doc',
            'docx' => 'workeera-cvmanager-file-icon-doc',
            'txt'  => 'workeera-cvmanager-file-icon-txt',
            'jpeg' => 'workeera-cvmanager-file-icon-img',
            'jpg'  => 'workeera-cvmanager-file-icon-img',
            'png'  => 'workeera-cvmanager-file-icon-img',
        ];
        return isset($map[$extension]) ? $map[$extension] : 'workeera-cvmanager-file-icon-other';
    }

    /**
     * Render a SVG icon for file type.
     */
    private static function file_type_svg($ext) {
        $svg = '';
        switch (strtolower($ext)) {
            case 'pdf':
                $svg = '<svg width="24" height="24" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="36" height="44" rx="4" fill="#E53E3E"/>
                    <path d="M24 0 L36 12 L24 12 Z" fill="#FC8181"/>
                    <rect x="24" y="0" width="12" height="12" fill="#C53030"/>
                    <text x="18" y="32" font-size="11" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">PDF</text>
                </svg>';
                break;
            case 'doc':
            case 'docx':
                $svg = '<svg width="24" height="24" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="36" height="44" rx="4" fill="#2B6CB0"/>
                    <path d="M24 0 L36 12 L24 12 Z" fill="#63B3ED"/>
                    <rect x="24" y="0" width="12" height="12" fill="#1A4F8A"/>
                    <text x="18" y="29" font-size="8" font-weight="700" fill="white" text-anchor="middle" font-family="sans-serif">WORD</text>
                    <rect x="5" y="33" width="22" height="2.5" rx="1" fill="white" opacity="0.7"/>
                    <rect x="5" y="38" width="16" height="2.5" rx="1" fill="white" opacity="0.5"/>
                </svg>';
                break;
            case 'txt':
                $svg = '<svg width="24" height="24" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="36" height="44" rx="4" fill="#718096"/>
                    <path d="M24 0 L36 12 L24 12 Z" fill="#A0AEC0"/>
                    <rect x="24" y="0" width="12" height="12" fill="#4A5568"/>
                    <rect x="5" y="18" width="22" height="2.5" rx="1" fill="white" opacity="0.9"/>
                    <rect x="5" y="24" width="22" height="2.5" rx="1" fill="white" opacity="0.7"/>
                    <rect x="5" y="30" width="16" height="2.5" rx="1" fill="white" opacity="0.7"/>
                    <rect x="5" y="36" width="19" height="2.5" rx="1" fill="white" opacity="0.5"/>
                </svg>';
                break;
            case 'jpeg':
            case 'jpg':
            case 'png':
                $svg = '<svg width="24" height="24" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="44" height="44" rx="4" fill="#2F855A"/>
                    <rect width="44" height="24" rx="4" fill="#276749"/>
                    <polygon points="4,44 20,18 36,44" fill="#1C4532"/>
                    <polygon points="16,44 28,26 40,44" fill="#22543D"/>
                    <circle cx="34" cy="10" r="6" fill="#F6E05E"/>
                </svg>';
                break;
            default:
                $svg = '<svg width="24" height="24" viewBox="0 0 36 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="1" width="34" height="42" rx="4" fill="none" stroke="#A0AEC0" stroke-width="2"/>
                    <path d="M24 1 L35 12" stroke="#A0AEC0" stroke-width="2"/>
                    <path d="M24 1 L24 13 L35 13" fill="white" stroke="#A0AEC0" stroke-width="2" stroke-linejoin="round"/>
                    <rect x="6" y="22" width="20" height="2" rx="1" fill="#CBD5E0"/>
                    <rect x="6" y="28" width="16" height="2" rx="1" fill="#CBD5E0"/>
                    <rect x="6" y="34" width="18" height="2" rx="1" fill="#CBD5E0"/>
                </svg>';
        }
        echo wp_kses( (string) $svg, workeera_svg_allowed_tags() );
    }

    /**
     * Inline loader HTML for AJAX.
     */
    private static function loader_html() {
        ?>
        <div class="workeera-loder-con inline-loder">
            <div class="workeera-loder-iner">
                <div class="workeera-loader"></div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX: Upload CV (now returns full item HTML)
    // -------------------------------------------------------------------------

    /**
     * AJAX handler that validates a CV upload, persists the file, and returns
     * the rendered list-item HTML alongside the updated file count.
     */
    public function upload_cv_file() {
        global $workeera_plugin_settings;

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('You must be logged in.', 'workeera-remote-tech-job-board')]);
        }

        $candidate_id = workeera_is_user_candidate($user_id);
        if (!$candidate_id) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('Candidate profile not found.', 'workeera-remote-tech-job-board')]);
        }

        if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['size'] <= 0) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('No file uploaded.', 'workeera-remote-tech-job-board')]);
        }

        // $_FILES values are typed (size is int, error is int, name/tmp_name/type are strings).
        // Sanitize text fields with sanitize_text_field; numeric fields are validated as ints below.
        $file = array_map( 'sanitize_text_field', $_FILES['cv_file'] );
        $candidate_settings = isset($workeera_plugin_settings['candidate_settings']) ? $workeera_plugin_settings['candidate_settings'] : [];
        $max_size_bytes = isset($candidate_settings['max_cv_file_size']) ? intval($candidate_settings['max_cv_file_size']) : 5242880;
        if (absint($file['size']) > $max_size_bytes) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('File size exceeds the allowed limit.', 'workeera-remote-tech-job-board')]);
        }

        $allowed_types = isset($candidate_settings['cv_file_types']) ? (array) $candidate_settings['cv_file_types'] : ['pdf', 'docx'];
        $mime_map = self::get_cv_mime_map();
        
        // Build an associative array [ 'pdf' => 'application/pdf', … ]
        $allowed_mimes_assoc = [];
        foreach ($allowed_types as $type) {
            if (isset($mime_map[$type])) {
                $allowed_mimes_assoc[$type] = $mime_map[$type];
            }
        }

        // Validate file type using wp_check_filetype (still works)
        $file_type_check = wp_check_filetype($file['name']);
        if (!isset($file_type_check['type']) || !in_array($file_type_check['type'], array_values($allowed_mimes_assoc))) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('File type not allowed.', 'workeera-remote-tech-job-board')]);
        }

        // Set custom upload dir and handle the upload
        global $workeera_upload_files_extpath;
        $workeera_upload_files_extpath = 'candidate-cvs';
        add_filter('upload_dir', 'workeera_public_upload_files_path');

        // Now pass the associative array to wp_handle_upload
        $uploaded = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => $allowed_mimes_assoc,
        ]);

        remove_filter('upload_dir', 'workeera_public_upload_files_path');

        if (isset($uploaded['error'])) {
            /* translators: %s: error message */
            wp_send_json(['error' => '1', 'msg' => sprintf(esc_html__('Upload failed: %s', 'workeera-remote-tech-job-board'), $uploaded['error'])]);
        }

        // Lock down the new CV folder so the raw file URL can't be downloaded
        // directly — every CV must come through the gated download handler.
        workeera_protect_private_upload_dir( dirname( $uploaded['file'] ) );

        $file_id = uniqid('cv_', true);
        $file_data = [
            'file_id' => $file_id,
            'file'    => $uploaded['file'],
            'url'     => $uploaded['url'],
            'name'    => sanitize_file_name($file['name']),
            'size'    => absint($file['size']),
            'type'    => $file['type'],
            'date'    => current_time('mysql'),
        ];

        $existing_files = workeera_get_object_meta($candidate_id, 'candidate_cv_files');
        if (!is_array($existing_files)) {
            $existing_files = [];
        }
        $existing_files[] = $file_data;
        workeera_update_object_meta($candidate_id, 'candidate_cv_files', $existing_files);

        $nonce = wp_create_nonce('workeera_nonce');
        ob_start();
        self::render_file_item($file_data, $candidate_id, $nonce);
        $item_html = ob_get_clean();

        wp_send_json([
            'error'     => '0',
            'msg'       => esc_html__('CV file uploaded successfully.', 'workeera-remote-tech-job-board'),
            'item_html' => $item_html,
            'file_count'=> count($existing_files),
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX: Delete CV file
    // -------------------------------------------------------------------------

    /**
     * AJAX handler that removes a candidate's CV file by file_id and updates meta.
     */
    public function delete_cv_file() {
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('You must be logged in.', 'workeera-remote-tech-job-board')]);
        }

        // Verify ownership of the file before deleting.
        $candidate_id = workeera_is_user_candidate($user_id);
        if (!$candidate_id) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('Candidate profile not found.', 'workeera-remote-tech-job-board')]);
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        if (empty($file_id)) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('Invalid file ID.', 'workeera-remote-tech-job-board')]);
        }

        $existing_files = workeera_get_object_meta($candidate_id, 'candidate_cv_files');
        if (!is_array($existing_files)) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('No files found.', 'workeera-remote-tech-job-board')]);
        }

        $found = false;
        $new_files = [];
        foreach ($existing_files as $file) {
            if (isset($file['file_id']) && $file['file_id'] === $file_id) {
                // Only delete a path that resolves inside the plugin's uploads
                // folder — the stored `file` value must not be trusted verbatim.
                if ( isset( $file['file'] ) && ( $safe_path = workeera_safe_upload_path( $file['file'] ) ) ) {
                    wp_delete_file( $safe_path );
                }
                $found = true;
                continue;
            }
            $new_files[] = $file;
        }

        if (!$found) {
            wp_send_json(['error' => '1', 'msg' => esc_html__('File not found.', 'workeera-remote-tech-job-board')]);
        }

        workeera_update_object_meta($candidate_id, 'candidate_cv_files', $new_files);

        wp_send_json(['error' => '0', 'msg' => esc_html__('CV file deleted successfully.', 'workeera-remote-tech-job-board'), 'file_count' => count($new_files)]);
    }
}

new Workeera_Candidate_CV_Manager;