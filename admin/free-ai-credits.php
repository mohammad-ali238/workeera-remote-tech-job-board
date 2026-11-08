<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Hiremium API base URL for the free-credits + AI add-on calls.
if ( ! defined( 'WORKEERA_API_URL' ) ) {
	define( 'WORKEERA_API_URL', 'https://hiremium.com' );
}

/**
 * "Free AI Credits" admin page.
 *
 * Lets a site owner claim free Hiremium AI credits by verifying an email
 * address, then shows the live credit balance + usage. The whole feature is
 * gated by the "Free AI Credits" master switch in Workeera → Settings → General
 * (default ON); when OFF the submenu, dashboard badge and addon usage are all
 * suppressed.
 *
 * All state is cached in the `workeera_ai_credits` option; Hiremium remains the
 * source of truth (the page syncs on load and after each AI call).
 */
class Workeera_Free_AI_Credits {

	/**
	 * Option key holding the cached trial state.
	 */
	const OPTION = 'workeera_ai_credits';

	/**
	 * Captured hook suffix for the page (for enqueue gating).
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Wire hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_redirect' ) );

		add_action( 'wp_ajax_workeera_fac_send', array( $this, 'ajax_send_verification' ) );
		add_action( 'wp_ajax_workeera_fac_sync', array( $this, 'ajax_sync_status' ) );
	}

	/**
	 * Hiremium API base URL (filterable).
	 *
	 * @return string
	 */
	public static function api_url() {
		$url = defined( 'WORKEERA_API_URL' ) ? WORKEERA_API_URL : 'https://hiremium.com';

		return untrailingslashit( apply_filters( 'workeera_api_url', $url ) );
	}

	/**
	 * The admin page URL the verification link should return the user to. Sent
	 * as the site_url so (a) the host is used for the domain-match check and
	 * (b) the redirect lands on this admin screen where the detector runs.
	 *
	 * @return string
	 */
	public static function landing_url() {
		return admin_url( 'admin.php?page=workeera-free-ai-credits' );
	}

	/**
	 * Whether the free-credits feature is enabled (master switch in General
	 * settings, default ON — only an explicit "off" disables it).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$general = get_option( 'workeera_general_settings' );
		$value   = isset( $general['free_ai_credits'] ) ? $general['free_ai_credits'] : 'on';

		return 'off' !== $value;
	}

	/**
	 * Read the cached trial state.
	 *
	 * @return array
	 */
	public static function get_state() {
		$state = get_option( self::OPTION );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist (merge) the cached trial state.
	 *
	 * @param array $data Partial state to merge.
	 * @return array The merged state.
	 */
	private static function save_state( $data ) {
		$state = array_merge( self::get_state(), $data );
		update_option( self::OPTION, $state );

		return $state;
	}

	/**
	 * Whether a usable (verified + unexpired) trial is currently available.
	 *
	 * @return bool
	 */
	public static function is_availed() {
		$state = self::get_state();

		if ( empty( $state['api_key'] ) || empty( $state['expiry_date'] ) ) {
			return false;
		}

		return strtotime( $state['expiry_date'] ) > time();
	}

	/**
	 * Credits remaining (never negative).
	 *
	 * @return int
	 */
	public static function credits_remaining() {
		$state = self::get_state();
		$total = isset( $state['credits_total'] ) ? (int) $state['credits_total'] : 0;
		$used  = isset( $state['credits_used'] ) ? (int) $state['credits_used'] : 0;

		return max( 0, $total - $used );
	}

	/**
	 * Register the submenu (called from Workeera_Admin::admin_menu_pages, before
	 * the Settings item). Skipped entirely when the feature is disabled.
	 *
	 * @return void
	 */
	public static function register_submenu() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_submenu_page(
			'workeera-dashboard',
			esc_html_x( 'Free AI Credits', 'Admin page title', 'workeera-remote-tech-job-board' ),
			esc_html_x( 'Free AI Credits', 'Admin menu title', 'workeera-remote-tech-job-board' ),
			'manage_options',
			'workeera-free-ai-credits',
			array( 'Workeera_Free_AI_Credits', 'render_page' )
		);
	}

	/**
	 * Enqueue page assets only on the Free AI Credits screen.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$is_page = isset( $_GET['page'] ) && 'workeera-free-ai-credits' === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen check only.
		if ( ! $is_page ) {
			return;
		}

		wp_enqueue_style(
			'workeera-free-ai-credits',
			Workeera_Plugin::root_url() . 'css/admin/free-ai-credits.css',
			array( 'workeera-admin-styles' ),
			WORKEERA_VERSION
		);
		wp_enqueue_script(
			'workeera-free-ai-credits',
			Workeera_Plugin::root_url() . 'js/admin/free-ai-credits.js',
			array( 'jquery', 'workeera-scripts' ),
			WORKEERA_VERSION,
			true
		);

		$state = self::get_state();
		wp_localize_script(
			'workeera-free-ai-credits',
			'workeera_fac_str',
			array(
				'ajax_url'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'         => wp_create_nonce( 'workeera_fac_nonce' ),
				'uuid'          => isset( $state['uuid'] ) ? $state['uuid'] : '',
				'poll_interval' => 10000,
				'i18n'          => array(
					'sending'    => esc_html__( 'Sending…', 'workeera-remote-tech-job-board' ),
					'checking'   => esc_html__( 'Checking…', 'workeera-remote-tech-job-board' ),
					'not_yet'    => esc_html__( 'Not verified yet. Please click the link in your email, then try again.', 'workeera-remote-tech-job-board' ),
					'generic'    => esc_html__( 'Something went wrong. Please try again.', 'workeera-remote-tech-job-board' ),
				),
			)
		);
	}

	/**
	 * Detect the verification redirect (?workeera_trial=…), sync the status, and
	 * clean the URL.
	 *
	 * @return void
	 */
	public function maybe_handle_redirect() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['workeera_trial'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- external redirect from signed verify link.
			return;
		}

		$uuid    = sanitize_text_field( wp_unslash( $_GET['workeera_trial'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = isset( $_GET['workeera_trial_token'] ) ? sanitize_text_field( wp_unslash( $_GET['workeera_trial_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = isset( $_GET['workeera_trial_status'] ) ? sanitize_key( wp_unslash( $_GET['workeera_trial_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$clean_redirect = remove_query_arg( array( 'workeera_trial', 'workeera_trial_token', 'workeera_trial_status' ) );

		// Only honor a redirect for the claim THIS site initiated. Without this
		// check, a crafted link could inject an arbitrary trial's credentials
		// into the site's options (feature hijack / DoS).
		$state    = self::get_state();
		$expected = isset( $state['uuid'] ) ? (string) $state['uuid'] : '';
		if ( '' === $expected || ! hash_equals( $expected, $uuid ) ) {
			wp_safe_redirect( $clean_redirect );
			exit;
		}

		// Expired / invalid link — the backend already told us.
		if ( 'expired' === $status ) {
			wp_safe_redirect( add_query_arg( 'wfac_status', 'expired', $clean_redirect ) );
			exit;
		}

		$verified = $this->sync_from_remote( $uuid, $token );

		wp_safe_redirect( add_query_arg( 'wfac_status', $verified ? 'success' : 'failed', $clean_redirect ) );
		exit;
	}

	/**
	 * AJAX: send a verification email for the entered address.
	 *
	 * @return void
	 */
	public function ajax_send_verification() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'workeera_fac_nonce' ) ) {
			wp_send_json( array( 'error' => 'security_failed' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( array( 'error' => 'forbidden', 'msg' => esc_html__( 'You are not allowed to do this.', 'workeera-remote-tech-job-board' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Please enter a valid email address.', 'workeera-remote-tech-job-board' ) ) );
		}

		$response = wp_remote_post(
			self::api_url() . '/api/v1/trial/request',
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'email'    => $email,
					'site_url' => self::landing_url(),
					'ip'       => self::admin_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'Could not reach the credits service. Please try again.', 'workeera-remote-tech-job-board' ) ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 || ! is_array( $body ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : esc_html__( 'The request was rejected. Please try again later.', 'workeera-remote-tech-job-board' );
			wp_send_json( array( 'error' => '1', 'msg' => $msg ) );
		}

		// Cache the uuid + email so the page can poll for verification.
		$status = isset( $body['status'] ) ? sanitize_key( $body['status'] ) : 'sent';
		if ( ! empty( $body['uuid'] ) && 'exists' !== $status ) {
			self::save_state(
				array(
					'uuid'   => sanitize_text_field( $body['uuid'] ),
					'email'  => $email,
					'status' => 'pending',
				)
			);
		}

		wp_send_json(
			array(
				'error'  => '0',
				'status' => $status,
				'uuid'   => isset( $body['uuid'] ) ? sanitize_text_field( $body['uuid'] ) : '',
				'msg'    => isset( $body['message'] ) ? $body['message'] : '',
			)
		);
	}

	/**
	 * AJAX: sync the trial status from Hiremium (used by the poll + Confirm button).
	 *
	 * @return void
	 */
	public function ajax_sync_status() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'workeera_fac_nonce' ) ) {
			wp_send_json( array( 'error' => 'security_failed' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( array( 'error' => 'forbidden' ) );
		}

		$state = self::get_state();
		$uuid  = isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : ( isset( $state['uuid'] ) ? $state['uuid'] : '' );
		if ( '' === $uuid ) {
			wp_send_json( array( 'error' => '1', 'verified' => false ) );
		}

		$verified = $this->sync_from_remote( $uuid, '' );

		wp_send_json(
			array(
				'error'    => '0',
				'verified' => (bool) $verified,
			)
		);
	}

	/**
	 * Call Hiremium /trial/status and cache any verified result locally.
	 *
	 * @param string $uuid  Trial uuid.
	 * @param string $token Optional one-time redirect token.
	 * @return bool Whether the trial is now verified/availed.
	 */
	private function sync_from_remote( $uuid, $token = '' ) {
		$response = wp_remote_post(
			self::api_url() . '/api/v1/trial/status',
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'uuid'           => $uuid,
					'site_url'       => self::landing_url(),
					'redirect_token' => $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['verified'] ) ) {
			return false;
		}

		$save = array(
			'uuid'          => $uuid,
			'status'        => 'verified',
			'credits_total' => isset( $body['credits_total'] ) ? (int) $body['credits_total'] : 0,
			'credits_used'  => isset( $body['credits_used'] ) ? (int) $body['credits_used'] : 0,
			'expiry_date'   => isset( $body['expires_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $body['expires_at'] ) ) : '',
			'verified_date' => isset( self::get_state()['verified_date'] ) && self::get_state()['verified_date'] ? self::get_state()['verified_date'] : gmdate( 'Y-m-d H:i:s' ),
		);
		if ( ! empty( $body['api_key'] ) ) {
			$save['api_key'] = sanitize_text_field( $body['api_key'] );
		}
		if ( isset( $body['recent_usage'] ) && is_array( $body['recent_usage'] ) ) {
			$save['recent_usage'] = array_slice( $body['recent_usage'], 0, 20 );
		}

		self::save_state( $save );

		return true;
	}

	/**
	 * The trial api_key to use for AI calls, or '' when free credits are not
	 * currently usable. Add-ons call this to decide whether to bill the trial.
	 *
	 * @return string
	 */
	public static function active_trial_key() {
		if ( ! self::is_enabled() || ! self::is_availed() || self::credits_remaining() <= 0 ) {
			return '';
		}

		$state = self::get_state();

		return isset( $state['api_key'] ) ? (string) $state['api_key'] : '';
	}

	/**
	 * Record a trial AI call locally after a successful response, keeping the
	 * cached balance + usage list fresh. (Hiremium remains authoritative; the
	 * Free AI Credits page re-syncs the full picture on load.)
	 *
	 * @param string $endpoint          Short operation label.
	 * @param int    $credits_remaining Remaining credits returned by the API.
	 * @return void
	 */
	public static function record_call( $endpoint, $credits_remaining ) {
		$state = self::get_state();
		if ( empty( $state['api_key'] ) ) {
			return;
		}

		$total    = isset( $state['credits_total'] ) ? (int) $state['credits_total'] : 0;
		$old_used = isset( $state['credits_used'] ) ? (int) $state['credits_used'] : 0;
		$new_used = ( null === $credits_remaining ) ? $old_used : max( 0, $total - (int) $credits_remaining );
		$consumed = max( 1, $new_used - $old_used );

		$usage = isset( $state['recent_usage'] ) && is_array( $state['recent_usage'] ) ? $state['recent_usage'] : array();
		array_unshift(
			$usage,
			array(
				'endpoint' => sanitize_text_field( $endpoint ),
				'credits'  => $consumed,
				'status'   => 'success',
				'at'       => gmdate( 'c' ),
			)
		);
		$usage = array_slice( $usage, 0, 20 );

		self::save_state(
			array(
				'credits_used' => $new_used,
				'recent_usage' => $usage,
			)
		);
	}

	/**
	 * Best-effort admin IP.
	 *
	 * @return string
	 */
	private static function admin_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Compact credits badge for the dashboard welcome banner (right side).
	 * Returns '' when the feature is off or no trial is active.
	 *
	 * @return string HTML.
	 */
	public static function dashboard_badge_html() {
		if ( ! self::is_enabled() || ! self::is_availed() ) {
			return '';
		}

		$state     = self::get_state();
		$remaining = self::credits_remaining();
		$total     = isset( $state['credits_total'] ) ? (int) $state['credits_total'] : 0;
		$expiry    = isset( $state['expiry_date'] ) ? $state['expiry_date'] : '';

		ob_start();
		?>
		<div class="workeera-dshb-faccredits">
			<span class="workeera-facbadge-icon" aria-hidden="true">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L15 8l6 .9-4.5 4.3L18 21l-6-3.2L6 21l1.5-7.8L3 8.9 9 8z"/></svg>
			</span>
			<span class="workeera-facbadge-body">
				<strong><?php echo esc_html( number_format_i18n( $remaining ) ); ?></strong>
				<span class="workeera-facbadge-sub">
					<?php
					/* translators: %d: total credits */
					echo esc_html( sprintf( __( 'free AI credits left of %d', 'workeera-remote-tech-job-board' ), $total ) );
					?>
				</span>
				<?php if ( $expiry ) : ?>
					<span class="workeera-facbadge-exp">
						<?php
						/* translators: %s: expiry date */
						echo esc_html( sprintf( __( 'Expires %s', 'workeera-remote-tech-job-board' ), date_i18n( get_option( 'date_format' ), strtotime( $expiry ) ) ) );
						?>
					</span>
				<?php endif; ?>
			</span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Print the outcome banner after a verification redirect (success / expired
	 * / failed). The flag is set by maybe_handle_redirect() on our own redirect,
	 * so it is a read-only display value.
	 *
	 * @return void
	 */
	private static function render_flash_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag set by our own redirect.
		$flash = isset( $_GET['wfac_status'] ) ? sanitize_key( wp_unslash( $_GET['wfac_status'] ) ) : '';
		if ( '' === $flash ) {
			return;
		}

		if ( 'success' === $flash ) {
			$class = 'notice-success';
			$msg   = esc_html__( 'Success! Your email is verified and your free AI credits are now active.', 'workeera-remote-tech-job-board' );
		} elseif ( 'expired' === $flash ) {
			$class = 'notice-warning';
			$msg   = esc_html__( 'That verification link has expired or is invalid. Please request a new one below.', 'workeera-remote-tech-job-board' );
		} else {
			$class = 'notice-error';
			$msg   = esc_html__( 'We could not confirm your verification. Please try again, or request a new link below.', 'workeera-remote-tech-job-board' );
		}

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible workeera-fac-flash"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Render the Free AI Credits page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'workeera-remote-tech-job-board' ) );
		}

		$availed = self::is_availed();
		$state   = self::get_state();
		$pending = ! $availed && ! empty( $state['uuid'] );
		?>
		<div class="wrap workeera-page workeera-fac-wrap">
			<?php self::render_flash_notice(); ?>
			<div class="workeera-fac-hero">
				<div class="workeera-fac-hero-icon" aria-hidden="true">
					<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/><circle cx="12" cy="12" r="4"/></svg>
				</div>
				<div>
					<h1><?php echo esc_html_x( 'Free AI Credits', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></h1>
					<p><?php echo esc_html_x( 'Try Workeera’s AI features — resume parsing, applicant match scoring and the AI resume builder — on the house. Verify your email to unlock free credits, powered by Hiremium.', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></p>
				</div>
			</div>

			<?php if ( $availed ) : ?>
				<?php self::render_availed( $state ); ?>
			<?php else : ?>
				<?php self::render_claim( $state, $pending ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the claim (not-yet-availed) state.
	 *
	 * @param array $state   Cached state.
	 * @param bool  $pending Whether a verification is already in flight.
	 * @return void
	 */
	private static function render_claim( $state, $pending ) {
		$email = isset( $state['email'] ) ? $state['email'] : '';
		?>
		<div class="workeera-fac-card workeera-fac-claim" data-pending="<?php echo esc_attr( $pending ? '1' : '0' ); ?>">
			<h2><?php echo esc_html_x( 'Get your free credits', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></h2>
			<p class="workeera-fac-muted"><?php echo esc_html_x( 'Enter your email and we’ll send a verification link. Confirm it and your credits activate instantly.', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></p>

			<div class="workeera-fac-form">
				<input type="email" class="workeera-fac-email" placeholder="<?php echo esc_attr__( 'you@company.com', 'workeera-remote-tech-job-board' ); ?>" value="<?php echo esc_attr( $email ); ?>" <?php echo $pending ? 'disabled' : ''; ?> />
				<button type="button" class="button button-primary workeera-fac-send-btn" <?php echo $pending ? 'style="display:none"' : ''; ?>>
					<?php echo esc_html_x( 'Send verification link', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?>
				</button>
			</div>

			<div class="workeera-fac-pending" style="<?php echo $pending ? '' : 'display:none'; ?>">
				<div class="workeera-fac-pending-note">
					<span class="workeera-fac-spinner" aria-hidden="true"></span>
					<?php echo esc_html_x( 'We’ve emailed you a verification link. Waiting for confirmation… this page updates automatically once you click it.', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?>
				</div>
				<button type="button" class="button workeera-fac-confirm-btn">
					<?php echo esc_html_x( 'I’ve verified — check now', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?>
				</button>
			</div>

			<div class="workeera-fac-notice" style="display:none"></div>
		</div>
		<?php
	}

	/**
	 * Render the availed (active credits) state.
	 *
	 * @param array $state Cached state.
	 * @return void
	 */
	private static function render_availed( $state ) {
		$total     = isset( $state['credits_total'] ) ? (int) $state['credits_total'] : 0;
		$used      = isset( $state['credits_used'] ) ? (int) $state['credits_used'] : 0;
		$remaining = self::credits_remaining();
		$pct       = $total > 0 ? min( 100, round( ( $remaining / $total ) * 100 ) ) : 0;
		$expiry    = isset( $state['expiry_date'] ) ? $state['expiry_date'] : '';
		$usage     = isset( $state['recent_usage'] ) && is_array( $state['recent_usage'] ) ? $state['recent_usage'] : array();
		?>
		<div class="workeera-fac-card workeera-fac-active">
			<div class="workeera-fac-active-top">
				<div class="workeera-fac-meter">
					<div class="workeera-fac-meter-num"><strong><?php echo esc_html( number_format_i18n( $remaining ) ); ?></strong><span><?php echo esc_html_x( 'credits left', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></span></div>
					<div class="workeera-fac-bar"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></div>
					<div class="workeera-fac-meter-foot">
						<?php
						/* translators: 1: used credits, 2: total credits */
						echo esc_html( sprintf( __( '%1$d of %2$d used', 'workeera-remote-tech-job-board' ), $used, $total ) );
						?>
					</div>
				</div>
				<ul class="workeera-fac-facts">
					<li><span><?php echo esc_html_x( 'Status', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></span><strong class="workeera-fac-ok"><?php echo esc_html_x( 'Active', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></strong></li>
					<?php if ( $expiry ) : ?>
						<li><span><?php echo esc_html_x( 'Expires', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></span><strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expiry ) ) ); ?></strong></li>
					<?php endif; ?>
					<li><span><?php echo esc_html_x( 'Email', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></span><strong><?php echo esc_html( isset( $state['email'] ) ? $state['email'] : '—' ); ?></strong></li>
				</ul>
			</div>
			<p class="workeera-fac-muted workeera-fac-hint"><?php echo esc_html_x( 'These credits are used automatically by the Workeera AI add-ons while free credits are enabled. Manage the master switch in Settings → General.', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></p>
		</div>

		<div class="workeera-fac-card workeera-fac-usage">
			<h2><?php echo esc_html_x( 'Usage detail', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></h2>
			<?php if ( empty( $usage ) ) : ?>
				<p class="workeera-fac-muted"><?php echo esc_html_x( 'No AI operations yet. Usage will appear here as your add-ons make AI calls.', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html_x( 'When', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></th>
							<th><?php echo esc_html_x( 'Operation', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></th>
							<th><?php echo esc_html_x( 'Credits', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></th>
							<th><?php echo esc_html_x( 'Status', 'Free AI Credits page', 'workeera-remote-tech-job-board' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $usage as $row ) : ?>
							<tr>
								<td><?php echo esc_html( ! empty( $row['at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['at'] ) ) : '—' ); ?></td>
								<td><?php echo esc_html( isset( $row['endpoint'] ) ? $row['endpoint'] : '—' ); ?></td>
								<td><?php echo esc_html( isset( $row['credits'] ) ? (int) $row['credits'] : 0 ); ?></td>
								<td><span class="workeera-fac-tag workeera-fac-tag-<?php echo esc_attr( isset( $row['status'] ) ? sanitize_html_class( $row['status'] ) : 'success' ); ?>"><?php echo esc_html( isset( $row['status'] ) ? ucfirst( $row['status'] ) : 'success' ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

new Workeera_Free_AI_Credits();
