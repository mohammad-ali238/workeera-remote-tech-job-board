<?php

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Base URL of the Workeera addons distribution server (Laravel app).
 * Replace with the live server URL before release.
 */
if ( ! defined( 'WORKEERA_ADDONS_SERVER_URL' ) ) {
    define( 'WORKEERA_ADDONS_SERVER_URL', 'https://addons.workeera.com' );
}

/**
 * Workeera Addons admin page.
 *
 * Fetches the addon catalog from the distribution server (cached in a
 * 2-hour transient), renders it as cards, and drives WP-native install /
 * activate / update flows over admin-only, nonce-protected AJAX. Zip
 * packages are never fetched from a direct URL — every server call is a
 * POST to a Laravel route carrying the site domain, the current user's IP
 * and this site's device token.
 */
class Workeera_Addons {

    /** Transient name for the cached catalog (plugin's custom transient store). */
    const TRANSIENT_NAME = 'workeera_addons_catalog';

    /** Nonce action shared by all addons AJAX endpoints. */
    const NONCE_ACTION = 'workeera_addons_nonce';

    /** Option holding this site's auto-generated device token. */
    const TOKEN_OPTION = 'workeera_addons_device_token';

    /**
     * Hook suffix of the Addons submenu page (set on admin_menu).
     *
     * @var string
     */
    private $hook_suffix = '';

    /**
     * Register menu, assets, and AJAX endpoints.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_submenu' ], 12 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        add_action( 'wp_ajax_workeera_addons_refresh', [ $this, 'ajax_refresh' ] );
        add_action( 'wp_ajax_workeera_addons_install', [ $this, 'ajax_install' ] );
        add_action( 'wp_ajax_workeera_addons_activate', [ $this, 'ajax_activate' ] );
    }

    /**
     * Add the "Addons" submenu under the Workeera admin menu.
     */
    public function register_submenu() {
        $this->hook_suffix = add_submenu_page(
            'workeera-dashboard',
            esc_html_x( 'Workeera Addons', 'Admin page title', 'workeera-remote-tech-job-board' ),
            esc_html_x( 'Addons', 'Admin menu title', 'workeera-remote-tech-job-board' ),
            'manage_options',
            'workeera-addons',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue the page's dedicated CSS/JS — only on the Addons screen.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( empty( $this->hook_suffix ) || $hook !== $this->hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'workeera-addons',
            Workeera_Plugin::root_url() . 'css/addons.css',
            [ 'workeera-styles' ],
            WORKEERA_VERSION
        );

        wp_enqueue_script(
            'workeera-addons',
            Workeera_Plugin::root_url() . 'js/admin/addons.js',
            [ 'jquery' ],
            WORKEERA_VERSION,
            true
        );

        wp_localize_script( 'workeera-addons', 'workeera_addons_str', [
            'ajax_url'    => esc_url( admin_url( 'admin-ajax.php' ) ),
            'nonce'       => esc_attr( wp_create_nonce( self::NONCE_ACTION ) ),
            'installing'  => esc_html_x( 'Installing…', 'Addons page', 'workeera-remote-tech-job-board' ),
            'updating'    => esc_html_x( 'Updating…', 'Addons page', 'workeera-remote-tech-job-board' ),
            'activating'  => esc_html_x( 'Activating…', 'Addons page', 'workeera-remote-tech-job-board' ),
            'refreshing'  => esc_html_x( 'Checking…', 'Addons page', 'workeera-remote-tech-job-board' ),
            'refresh'     => esc_html_x( 'Check for updates', 'Addons page', 'workeera-remote-tech-job-board' ),
            'error'       => esc_html_x( 'Something went wrong. Please try again.', 'Addons page', 'workeera-remote-tech-job-board' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Server communication
    // -------------------------------------------------------------------------

    /**
     * Return this site's device token, generating it on first use.
     *
     * @return string 40-char alphanumeric token.
     */
    private function device_token() {
        $token = get_option( self::TOKEN_OPTION, '' );
        if ( empty( $token ) || ! is_string( $token ) ) {
            $token = wp_generate_password( 40, false, false );
            update_option( self::TOKEN_OPTION, $token, false );
        }

        return $token;
    }

    /**
     * POST a request to the addons server with the site's identity payload.
     *
     * Every call carries the site domain, the current user's IP address and
     * the device token so the server can rate-limit and block abuse.
     *
     * @param  string $endpoint Server route, e.g. '/api/v1/addons'.
     * @param  array  $extra    Additional body fields (e.g. slug).
     * @param  int    $timeout  Request timeout in seconds.
     * @return array{code:int,body:string,json:?array,error:string}
     */
    private function api_request( $endpoint, $extra = [], $timeout = 30 ) {
        $body = array_merge(
            [
                'domain'       => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
                'ip'           => workeera_user_ip_address(),
                'device_token' => $this->device_token(),
            ],
            $extra
        );

        $response = wp_remote_post(
            untrailingslashit( WORKEERA_ADDONS_SERVER_URL ) . $endpoint,
            [
                'timeout' => absint( $timeout ),
                'body'    => $body,
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'code'  => 0,
                'body'  => '',
                'json'  => null,
                'error' => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw  = (string) wp_remote_retrieve_body( $response );
        $json = json_decode( $raw, true );

        $error = '';
        if ( $code >= 400 ) {
            $error = ( is_array( $json ) && ! empty( $json['message'] ) )
                ? sanitize_text_field( $json['message'] )
                : sprintf(
                    /* translators: %d: HTTP status code */
                    esc_html_x( 'Addons server returned an error (HTTP %d).', 'Addons page', 'workeera-remote-tech-job-board' ),
                    $code
                );
            if ( 429 === $code ) {
                $error = esc_html_x( 'Too many requests. Please wait a minute and try again.', 'Addons page', 'workeera-remote-tech-job-board' );
            }
        }

        return [
            'code'  => $code,
            'body'  => $raw,
            'json'  => is_array( $json ) ? $json : null,
            'error' => $error,
        ];
    }

    // -------------------------------------------------------------------------
    // Catalog (2-hour transient cache)
    // -------------------------------------------------------------------------

    /**
     * Get the addon catalog — cached copy first, server fetch otherwise.
     *
     * @param  bool $force Skip the cache and re-fetch from the server.
     * @return array{addons:array,error:string}
     */
    private function get_catalog( $force = false ) {
        if ( ! $force ) {
            $cached = Workeera_Transients::get( self::TRANSIENT_NAME );
            if ( ! empty( $cached ) && is_array( $cached ) ) {
                return [ 'addons' => $cached, 'error' => '' ];
            }
        }

        $response = $this->api_request( '/api/v1/addons' );

        if ( ! empty( $response['error'] ) || empty( $response['json']['addons'] ) || ! is_array( $response['json']['addons'] ) ) {
            $error = ! empty( $response['error'] )
                ? $response['error']
                : esc_html_x( 'Could not load addons from the server.', 'Addons page', 'workeera-remote-tech-job-board' );

            return [ 'addons' => [], 'error' => $error ];
        }

        $addons = [];
        foreach ( $response['json']['addons'] as $addon ) {
            if ( ! is_array( $addon ) || empty( $addon['slug'] ) ) {
                continue;
            }
            $slug = sanitize_key( $addon['slug'] );
            if ( $slug === '' ) {
                continue;
            }

            // Only accept an inline image data URI for the thumbnail — anything
            // else is dropped so no unexpected remote URL reaches the page.
            $thumbnail = isset( $addon['thumbnail'] ) ? (string) $addon['thumbnail'] : '';
            if ( ! preg_match( '#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$#', $thumbnail ) ) {
                $thumbnail = '';
            }

            $addons[ $slug ] = [
                'slug'              => $slug,
                'name'              => isset( $addon['name'] ) ? sanitize_text_field( $addon['name'] ) : $slug,
                'small_description' => isset( $addon['small_description'] ) ? sanitize_text_field( $addon['small_description'] ) : '',
                'version'           => isset( $addon['version'] ) ? sanitize_text_field( $addon['version'] ) : '1.0.0',
                'thumbnail'         => $thumbnail,
            ];
        }

        Workeera_Transients::set( [
            'name'    => self::TRANSIENT_NAME,
            'value'   => $addons,
            'timeout' => 2 * HOUR_IN_SECONDS,
        ] );

        return [ 'addons' => $addons, 'error' => '' ];
    }

    // -------------------------------------------------------------------------
    // Install-state resolution (mirrors the native plugins screen)
    // -------------------------------------------------------------------------

    /**
     * Resolve the install/activation state of an addon by its directory slug.
     *
     * @param  string $slug           Addon slug (plugin directory name).
     * @param  string $remote_version Latest version on the server.
     * @return array{status:string,file:string,installed_version:string}
     */
    private function addon_state( $slug, $remote_version ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( get_plugins() as $plugin_file => $plugin_data ) {
            if ( dirname( $plugin_file ) !== $slug ) {
                continue;
            }

            $installed_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';

            if ( $installed_version !== '' && version_compare( $installed_version, $remote_version, '<' ) ) {
                $status = 'update';
            } elseif ( is_plugin_active( $plugin_file ) ) {
                $status = 'active';
            } else {
                $status = 'inactive';
            }

            return [
                'status'            => $status,
                'file'              => $plugin_file,
                'installed_version' => $installed_version,
            ];
        }

        return [ 'status' => 'not_installed', 'file' => '', 'installed_version' => '' ];
    }

    /**
     * Merge install state + rendered action button into each catalog entry.
     *
     * @param  array $addons Catalog entries keyed by slug.
     * @return array         Entries with status/installed_version/button_html.
     */
    private function addons_with_state( $addons ) {
        $result = [];
        foreach ( $addons as $slug => $addon ) {
            $state = $this->addon_state( $slug, $addon['version'] );

            $addon['status']            = $state['status'];
            $addon['installed_version'] = $state['installed_version'];
            $addon['button_html']       = $this->render_action_button( $slug, $state['status'] );

            $result[ $slug ] = $addon;
        }

        return $result;
    }

    /**
     * Render the state-appropriate action button for an addon card.
     *
     * @param  string $slug   Addon slug.
     * @param  string $status not_installed|update|inactive|active.
     * @return string         Escaped button HTML.
     */
    private function render_action_button( $slug, $status ) {
        $slug = sanitize_key( $slug );

        switch ( $status ) {
            case 'not_installed':
                return '<button type="button" class="button button-primary workeera-addon-action" data-action="install" data-slug="' . esc_attr( $slug ) . '">'
                    . esc_html_x( 'Install', 'Addons page', 'workeera-remote-tech-job-board' ) . '</button>';

            case 'update':
                return '<button type="button" class="button button-primary workeera-addon-action workeera-addon-update-btn" data-action="install" data-slug="' . esc_attr( $slug ) . '">'
                    . esc_html_x( 'Update', 'Addons page', 'workeera-remote-tech-job-board' ) . '</button>';

            case 'inactive':
                return '<button type="button" class="button workeera-addon-action" data-action="activate" data-slug="' . esc_attr( $slug ) . '">'
                    . esc_html_x( 'Activate', 'Addons page', 'workeera-remote-tech-job-board' ) . '</button>';

            case 'active':
            default:
                return '<button type="button" class="button workeera-addon-active-btn" disabled>'
                    . esc_html_x( 'Active ✓', 'Addons page', 'workeera-remote-tech-job-board' ) . '</button>';
        }
    }

    // -------------------------------------------------------------------------
    // Page rendering
    // -------------------------------------------------------------------------

    /**
     * Render the Addons admin page (cards grid, 3 per row).
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $catalog = $this->get_catalog();
        $addons  = $this->addons_with_state( $catalog['addons'] );
        ?>
        <div class="wrap workeera-addons-wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html_x( 'Workeera Addons', 'Addons page', 'workeera-remote-tech-job-board' ); ?></h1>
            <button type="button" class="page-title-action workeera-addons-refresh"><?php echo esc_html_x( 'Check for updates', 'Addons page', 'workeera-remote-tech-job-board' ); ?></button>
            <hr class="wp-header-end">

            <div class="workeera-addons-infobox">
                <span class="workeera-addons-infobox-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </span>
                <p class="workeera-addons-infobox-text">
                    <?php
                    printf(
                        wp_kses(
                            /* translators: %s: addons.workeera.com link */
                            __( 'Before you install: adding an add-on shares only your site&#8217;s URL and IP address with %s so we can verify the request and block spam. Nothing else is sent &mdash; no personal data is collected, stored, or shared. It is completely safe.', 'workeera-remote-tech-job-board' ),
                            [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                        ),
                        '<a href="https://addons.workeera.com" target="_blank" rel="noopener noreferrer">addons.workeera.com</a>'
                    );
                    ?>
                </p>
            </div>

            <div class="notice workeera-addons-notice" hidden><p></p></div>

            <?php if ( ! empty( $catalog['error'] ) ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $catalog['error'] ); ?></p></div>
            <?php endif; ?>

            <?php if ( empty( $addons ) && empty( $catalog['error'] ) ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html_x( 'No addons are available yet.', 'Addons page', 'workeera-remote-tech-job-board' ); ?></p></div>
            <?php endif; ?>

            <div class="workeera-addons-grid">
                <?php foreach ( $addons as $addon ) { $this->render_card( $addon ); } ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render one addon card.
     *
     * @param array $addon Catalog entry with state + button_html merged in.
     */
    private function render_card( $addon ) {
        ?>
        <div class="workeera-addon-card" data-slug="<?php echo esc_attr( $addon['slug'] ); ?>">
            <div class="workeera-addon-thumb">
                <?php if ( ! empty( $addon['thumbnail'] ) ) : ?>
                    <img src="<?php echo esc_attr( $addon['thumbnail'] ); ?>" alt="<?php echo esc_attr( $addon['name'] ); ?>">
                <?php else : ?>
                    <span class="workeera-addon-thumb-placeholder" aria-hidden="true">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.28V16.7a1 1 0 0 1-.5.87l-7.5 4.33a1 1 0 0 1-1 0L4 17.57a1 1 0 0 1-.5-.87V7.28a1 1 0 0 1 .5-.86L11.5 2.1a1 1 0 0 1 1 0L20 6.42a1 1 0 0 1 .5.86z"/><polyline points="3.77 6.5 12 11.25 20.23 6.5"/><line x1="12" y1="22.08" x2="12" y2="11.25"/></svg>
                    </span>
                <?php endif; ?>
            </div>
            <div class="workeera-addon-body">
                <h2 class="workeera-addon-name"><?php echo esc_html( $addon['name'] ); ?></h2>
                <p class="workeera-addon-desc"><?php echo esc_html( $addon['small_description'] ); ?></p>
            </div>
            <div class="workeera-addon-footer">
                <span class="workeera-addon-version">
                    <?php
                    /* translators: %s: addon version number */
                    printf( esc_html_x( 'Version %s', 'Addons page', 'workeera-remote-tech-job-board' ), esc_html( $addon['version'] ) );
                    if ( 'update' === $addon['status'] && $addon['installed_version'] !== '' ) {
                        echo ' <em class="workeera-addon-installed-ver">';
                        /* translators: %s: currently installed addon version */
                        printf( esc_html_x( '(installed: %s)', 'Addons page', 'workeera-remote-tech-job-board' ), esc_html( $addon['installed_version'] ) );
                        echo '</em>';
                    }
                    ?>
                </span>
                <span class="workeera-addon-action-area">
                    <?php echo $this->render_action_button( $addon['slug'], $addon['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- button markup built from literals, all values escaped in render_action_button() ?>
                </span>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX endpoints (admin only, nonce protected)
    // -------------------------------------------------------------------------

    /**
     * Verify the AJAX nonce and required capabilities (fail-early).
     *
     * @param string $capability Extra capability required beyond manage_options.
     */
    private function verify_ajax( $capability ) {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html_x( 'Security check failed.', 'Addons page', 'workeera-remote-tech-job-board' ) ] );
        }
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( $capability ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html_x( 'You are not allowed to manage addons.', 'Addons page', 'workeera-remote-tech-job-board' ) ] );
        }
    }

    /**
     * Read and whitelist the requested addon slug against the cached catalog.
     *
     * @param  array $addons Catalog entries keyed by slug.
     * @return string        Valid slug, or ends the request with an error.
     */
    private function requested_slug( $addons ) {
        $slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
        if ( $slug === '' || ! isset( $addons[ $slug ] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html_x( 'Unknown addon.', 'Addons page', 'workeera-remote-tech-job-board' ) ] );
        }

        return $slug;
    }

    /**
     * AJAX: clear the cache and re-fetch the catalog from the server.
     */
    public function ajax_refresh() {
        $this->verify_ajax( 'install_plugins' );

        Workeera_Transients::delete( self::TRANSIENT_NAME );

        $catalog = $this->get_catalog( true );
        if ( ! empty( $catalog['error'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => $catalog['error'] ] );
        }

        wp_send_json( [
            'error'  => '0',
            'msg'    => esc_html_x( 'Addons list refreshed.', 'Addons page', 'workeera-remote-tech-job-board' ),
            'addons' => $this->addons_with_state( $catalog['addons'] ),
        ] );
    }

    /**
     * AJAX: install (or update — same flow with overwrite) an addon.
     */
    public function ajax_install() {
        $this->verify_ajax( 'install_plugins' );

        $catalog = $this->get_catalog();
        $slug    = $this->requested_slug( $catalog['addons'] );

        $result = $this->download_and_install( $slug );
        if ( is_wp_error( $result ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html( $result->get_error_message() ) ] );
        }

        wp_send_json( [
            'error'  => '0',
            'msg'    => esc_html_x( 'Addon installed successfully.', 'Addons page', 'workeera-remote-tech-job-board' ),
            'addons' => $this->addons_with_state( $catalog['addons'] ),
        ] );
    }

    /**
     * AJAX: activate an installed addon.
     */
    public function ajax_activate() {
        $this->verify_ajax( 'activate_plugins' );

        $catalog = $this->get_catalog();
        $slug    = $this->requested_slug( $catalog['addons'] );

        $state = $this->addon_state( $slug, $catalog['addons'][ $slug ]['version'] );
        if ( empty( $state['file'] ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html_x( 'Addon is not installed.', 'Addons page', 'workeera-remote-tech-job-board' ) ] );
        }

        $activated = activate_plugin( $state['file'] );
        if ( is_wp_error( $activated ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html( $activated->get_error_message() ) ] );
        }

        wp_send_json( [
            'error'  => '0',
            'msg'    => esc_html_x( 'Addon activated.', 'Addons page', 'workeera-remote-tech-job-board' ),
            'addons' => $this->addons_with_state( $catalog['addons'] ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Download + install pipeline
    // -------------------------------------------------------------------------

    /**
     * Download an addon zip from the server (POST route — no direct file URL)
     * into a temp file, then run it through WP's Plugin_Upgrader.
     *
     * @param  string $slug Whitelisted addon slug.
     * @return true|WP_Error
     */
    private function download_and_install( $slug ) {
        $response = $this->api_request( '/api/v1/addons/download', [ 'slug' => $slug ], 120 );

        if ( ! empty( $response['error'] ) || 200 !== $response['code'] || '' === $response['body'] ) {
            $msg = ! empty( $response['error'] )
                ? $response['error']
                : esc_html_x( 'Could not download the addon package.', 'Addons page', 'workeera-remote-tech-job-board' );

            return new WP_Error( 'workeera_addon_download_failed', $msg );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            WP_Filesystem();
        }
        if ( ! $wp_filesystem ) {
            return new WP_Error( 'workeera_addon_fs_failed', esc_html_x( 'Could not access the filesystem.', 'Addons page', 'workeera-remote-tech-job-board' ) );
        }

        // Write the zip to a temp file — Plugin_Upgrader accepts a readable
        // local package path, so no zip URL is ever used or exposed.
        $tmp_file = wp_tempnam( $slug . '.zip' );
        if ( ! $tmp_file || ! $wp_filesystem->put_contents( $tmp_file, $response['body'] ) ) {
            return new WP_Error( 'workeera_addon_tmp_failed', esc_html_x( 'Could not save the addon package.', 'Addons page', 'workeera-remote-tech-job-board' ) );
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $tmp_file, [ 'overwrite_package' => true ] );

        wp_delete_file( $tmp_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $skin_errors = $skin->get_errors();
        if ( is_wp_error( $skin_errors ) && $skin_errors->has_errors() ) {
            return $skin_errors;
        }

        if ( true !== $result ) {
            return new WP_Error( 'workeera_addon_install_failed', esc_html_x( 'The addon package could not be installed.', 'Addons page', 'workeera-remote-tech-job-board' ) );
        }

        return true;
    }
}

new Workeera_Addons();
