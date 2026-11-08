<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Employer dashboard "My Companies" page.
 *
 * Lists the employer's saved companies as accordions (edit inline + save),
 * supports adding a new company via a popup, deleting with confirmation, and
 * AJAX search + pagination — mirroring the Manage Jobs UX. All company data is
 * stored via the multi-company helpers in includes/company-functions.php.
 */
class Workeera_User_My_Companies {

	/**
	 * Companies per page.
	 *
	 * @var int
	 */
	private static $per_page = 10;

	/**
	 * Register the AJAX endpoints.
	 */
	public function __construct() {
		add_action( 'wp_ajax_workeera_my_companies_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_workeera_save_company', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_workeera_delete_company', array( $this, 'ajax_delete' ) );
	}

	/**
	 * Whether the current user is an employer.
	 *
	 * @return bool
	 */
	private static function is_employer() {
		return workeera_get_object_meta( get_current_user_id(), 'workeera_user_type', 'user' ) === 'employer';
	}

	/**
	 * Verify nonce + employer gate for an AJAX request; exits on failure.
	 *
	 * @return void
	 */
	private static function ajax_guard() {
		$nonce_val = isset( $_POST['workeera_nonce'] ) ? sanitize_key( wp_unslash( $_POST['workeera_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce_val, 'workeera_nonce' ) ) {
			wp_send_json( array( 'error' => 'security_failed' ) );
		}
		if ( ! is_user_logged_in() || ! self::is_employer() ) {
			wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'You are not allowed to perform this action.', 'workeera-remote-tech-job-board' ) ) );
		}
	}

	/**
	 * Render the page shell (list is loaded via AJAX).
	 *
	 * @return void
	 */
	public static function listings() {
		if ( ! self::is_employer() ) {
			echo '<div class="workeera-usrdshb-main-wrap"><p>' . esc_html__( 'This section is only available to employers.', 'workeera-remote-tech-job-board' ) . '</p></div>';
			return;
		}

		wp_enqueue_style( 'workeera-mycompanies-styles' );
		wp_enqueue_script( 'workeera-mycompanies-scripts' );
		?>
		<div class="workeera-usrcmp-wrap workeera-usrjobs-wrap workeera-usrdshb-main-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'workeera_nonce' ) ); ?>">

			<div class="workeera-usrdshb-container">
				<div class="workeera-usrjobs-header">
					<strong class="workeera-usrjobs-title"><?php echo esc_html_x( 'My Companies', 'My companies', 'workeera-remote-tech-job-board' ); ?></strong>
					<?php if ( workeera_user_can_add_company( get_current_user_id() ) ) : ?>
					<button type="button" class="workeera-usrjobs-postnew-btn workeera-usrcmp-addnew-btn">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
						<?php echo esc_html_x( 'Add New', 'My companies', 'workeera-remote-tech-job-board' ); ?>
					</button>
					<?php else : ?>
					<span class="workeera-usrcmp-limit-note">
						<?php
						/* translators: %d: maximum companies per user */
						echo esc_html( sprintf( __( 'Company limit reached (%d).', 'workeera-remote-tech-job-board' ), workeera_company_max_per_user() ) );
						?>
					</span>
					<?php endif; ?>
				</div>

				<div class="workeera-usrjobs-filterbar">
					<div class="workeera-usrjobs-search-wrap">
						<span class="workeera-usrjobs-search-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
						</span>
						<input type="text" class="workeera-usrjobs-field-input workeera-usrcmp-search-input" placeholder="<?php echo esc_attr_x( 'Search companies…', 'My companies', 'workeera-remote-tech-job-board' ); ?>" />
					</div>
				</div>

				<div class="workeera-usrcmp-list-holder">
					<div class="workeera-usrcmp-loder-con"><?php self::loader(); ?></div>
					<div class="workeera-usrcmp-list"></div>
					<div class="workeera-usrcmp-pagination"></div>

					<div class="workeera-usrcmp-empty workeera-hide">
						<div class="workeera-usrcmp-empty-icon">
							<svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18" /><path d="M5 21V7l8-4v18" /><path d="M19 21V11l-6-4" /><line x1="9" y1="9" x2="9" y2="9.01" /><line x1="9" y1="12" x2="9" y2="12.01" /><line x1="9" y1="15" x2="9" y2="15.01" /></svg>
						</div>
						<strong class="workeera-usrcmp-empty-title"><?php echo esc_html_x( 'No companies yet', 'My companies', 'workeera-remote-tech-job-board' ); ?></strong>
						<p class="workeera-usrcmp-empty-desc"><?php echo esc_html_x( 'Add your first company to reuse it when posting jobs.', 'My companies', 'workeera-remote-tech-job-board' ); ?></p>
						<button type="button" class="workeera-usrjobs-empty-cta workeera-usrcmp-empty-cta workeera-usrcmp-addnew-btn"><?php echo esc_html_x( 'Add a company', 'My companies', 'workeera-remote-tech-job-board' ); ?></button>
					</div>

					<div class="workeera-init-loder-holdr workeera-hide"><?php self::loader(); ?></div>
				</div>

				<?php self::render_modal(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Skeleton loader markup (cloned by JS while loading).
	 *
	 * @return void
	 */
	private static function loader() {
		?>
		<div class="workeera-listin-loadr workeera-usrcmp-loadr">
			<?php for ( $i = 0; $i < 4; $i++ ) : ?>
				<div class="workeera-usrcmp-loderitm">
					<div class="workeera-cmploder-logo"></div>
					<div class="workeera-cmploder-info">
						<span class="workeera-cmploder-name"></span>
						<span class="workeera-cmploder-meta"></span>
					</div>
					<div class="workeera-cmploder-chev"></div>
				</div>
			<?php endfor; ?>
		</div>
		<?php
	}

	/**
	 * The add-new company modal (reuses the shared popup scaffold).
	 *
	 * @return void
	 */
	private static function render_modal() {
		?>
		<div id="workeera-company-modal" class="workeera-modal-popup workeera-popup-hide">
			<div class="workeera-modal-container">
				<div class="workeera-modal-inner-area">
					<div class="workeera-modal-content-area">
						<a href="javascript:;" class="workeera-popup-closebtn"><span class="workeera-remove-icon"></span></a>
						<div class="workeera-modalinit-con">
							<div class="workeera-usrcmp-modal-form">
								<strong class="workeera-usrcmp-modal-title"><?php echo esc_html_x( 'Add a company', 'My companies', 'workeera-remote-tech-job-board' ); ?></strong>
								<?php self::render_company_form( array(), 'modal' ); ?>
								<div class="workeera-usrcmp-modal-actions">
									<button type="button" class="workeera-usrjobs-postnew-btn workeera-usrcmp-create-btn"><?php echo esc_html_x( 'Create company', 'My companies', 'workeera-remote-tech-job-board' ); ?></button>
								</div>
								<div class="workeera-usrcmp-form-notice workeera-hide"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the shared company fields (used inline in accordions + the modal).
	 * Field elements carry stable classes the JS reads to build the request.
	 *
	 * @param array  $company Company data (empty for a new company).
	 * @param string $context 'modal' | 'inline'.
	 * @return void
	 */
	public static function render_company_form( $company = array(), $context = 'inline' ) {
		$rand     = wp_rand( 10000000, 99999999 );
		$name     = isset( $company['name'] ) ? $company['name'] : '';
		$website  = isset( $company['website'] ) ? $company['website'] : '';
		$tagline  = isset( $company['tagline'] ) ? $company['tagline'] : '';
		$linkedin = isset( $company['linkedin'] ) ? $company['linkedin'] : '';
		$emp      = isset( $company['employee_count'] ) ? $company['employee_count'] : '';
		$logo_url = isset( $company['logo'] ) ? workeera_company_logo_url( $company['logo'] ) : '';
		?>
		<div class="workeera-usrcmp-form workeera-usrform-wrapper">
			<div class="workeera-usrform-photo-row">
				<?php if ( workeera_company_field_enabled( 'logo' ) ) : ?>
				<div class="workeera-usrform-photo-upload<?php echo esc_attr( $logo_url ? ' workeera-with-filled-photo' : '' ); ?> workeera-usrcmp-logo-box">
					<input type="file" class="workeera-hide workeera-cmp-logo" accept="image/*" id="workeera-cmp-logo-<?php echo absint( $rand ); ?>">
						<input type="hidden" class="workeera-cmp-remove-logo" value="0">
					<div class="workeera-user-photo-imgholdr<?php echo esc_attr( $logo_url ? '' : ' workeera-hide' ); ?>">
						<div class="workeera-user-photo-imgbox workeera-cmp-logo-preview" style="background-image: url('<?php echo esc_url( $logo_url ); ?>');"></div>
							<div class="workeera-user-photo-remve" title="<?php echo esc_attr_x( 'Remove logo', 'My companies', 'workeera-remote-tech-job-board' ); ?>"><div class="workeera-remove-icon"></div></div>
					</div>
					<div class="workeera-photo-svgicnlbl-con<?php echo esc_attr( $logo_url ? ' workeera-hide' : '' ); ?>" title="<?php echo esc_attr_x( 'Upload logo', 'My companies', 'workeera-remote-tech-job-board' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" /><circle cx="12" cy="13" r="4" /></svg>
						<span class="workeera-user-photo-title"><?php echo esc_html_x( 'Logo', 'My companies', 'workeera-remote-tech-job-board' ); ?></span>
					</div>
				</div>
				<?php endif; ?>
				<div class="workeera-usrform-photo-fields">
					<div class="workeera-usrform-field-group">
						<label class="workeera-usrform-field-label"><?php echo esc_html_x( 'Company name', 'My companies', 'workeera-remote-tech-job-board' ); ?></label>
						<input type="text" class="workeera-usrform-field-input workeera-cmp-name" maxlength="100" placeholder="<?php echo esc_attr_x( 'e.g. Digi Soft', 'My companies', 'workeera-remote-tech-job-board' ); ?>" value="<?php echo esc_attr( $name ); ?>">
					</div>
					<?php if ( workeera_company_field_enabled( 'website' ) ) : ?>
					<div class="workeera-usrform-field-group">
						<label class="workeera-usrform-field-label"><?php echo esc_html_x( 'Website', 'My companies', 'workeera-remote-tech-job-board' ); ?></label>
						<input type="url" class="workeera-usrform-field-input workeera-cmp-website" maxlength="200" placeholder="https://exampleurl.com" value="<?php echo esc_attr( $website ); ?>">
					</div>
					<?php else : ?>
						<input type="hidden" class="workeera-cmp-website" value="<?php echo esc_attr( $website ); ?>">
					<?php endif; ?>
				</div>
			</div>
			<div class="workeera-grid-two-one">
				<?php if ( workeera_company_field_enabled( 'tagline' ) ) : ?>
				<div class="workeera-usrform-field-group">
					<label class="workeera-usrform-field-label"><?php echo esc_html_x( 'Tagline', 'My companies', 'workeera-remote-tech-job-board' ); ?></label>
					<input type="text" class="workeera-usrform-field-input workeera-cmp-tagline" maxlength="160" placeholder="<?php echo esc_attr_x( 'Brief description about the company', 'My companies', 'workeera-remote-tech-job-board' ); ?>" value="<?php echo esc_attr( $tagline ); ?>">
				</div>
				<?php else : ?>
					<input type="hidden" class="workeera-cmp-tagline" value="<?php echo esc_attr( $tagline ); ?>">
				<?php endif; ?>
				<?php if ( workeera_company_field_enabled( 'employee_count' ) ) : ?>
				<div class="workeera-usrform-field-group">
					<label class="workeera-usrform-field-label"><?php echo esc_html_x( 'Number of employees', 'My companies', 'workeera-remote-tech-job-board' ); ?></label>
					<select class="workeera-usrform-field-select workeera-cmp-employee">
						<option value=""><?php echo esc_html_x( 'Select', 'My companies', 'workeera-remote-tech-job-board' ); ?></option>
						<?php foreach ( workeera_company_employee_count_fields() as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $emp, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php else : ?>
					<input type="hidden" class="workeera-cmp-employee" value="<?php echo esc_attr( $emp ); ?>">
				<?php endif; ?>
			</div>
			<?php if ( workeera_company_field_enabled( 'linkedin' ) ) : ?>
			<div class="workeera-usrform-field-group">
				<label class="workeera-usrform-field-label"><?php echo esc_html_x( 'LinkedIn', 'My companies', 'workeera-remote-tech-job-board' ); ?></label>
				<input type="url" class="workeera-usrform-field-input workeera-cmp-linkedin" maxlength="200" placeholder="https://linkedin.com/company/example" value="<?php echo esc_attr( $linkedin ); ?>">
			</div>
			<?php else : ?>
				<input type="hidden" class="workeera-cmp-linkedin" value="<?php echo esc_attr( $linkedin ); ?>">
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one company accordion item.
	 *
	 * @param array $company Company data.
	 * @return string HTML.
	 */
	public static function render_company_item( $company ) {
		$id       = isset( $company['id'] ) ? $company['id'] : '';
		$name     = isset( $company['name'] ) ? $company['name'] : '';
		$website  = isset( $company['website'] ) ? $company['website'] : '';
		$emp      = isset( $company['employee_count'] ) ? $company['employee_count'] : '';
		$logo_url = isset( $company['logo'] ) ? workeera_company_logo_url( $company['logo'] ) : '';
		$host     = $website ? preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) : '';
		$jobs_cnt = $id ? workeera_company_jobs_count( $id ) : 0;

		ob_start();
		?>
		<div class="workeera-usrcmp-item" data-company-id="<?php echo esc_attr( $id ); ?>">
			<div class="workeera-usrcmp-head">
				<div class="workeera-usrcmp-head-main">
					<div class="workeera-usrcmp-logo">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
						<?php else : ?>
							<span class="workeera-usrcmp-logo-ph"><?php echo esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="workeera-usrcmp-head-info">
						<strong class="workeera-usrcmp-name"><?php echo esc_html( $name ); ?></strong>
						<div class="workeera-usrcmp-meta">
							<?php if ( $host ) : ?>
								<span class="workeera-usrcmp-metaitem workeera-usrcmp-url">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="2" y1="12" x2="22" y2="12" /><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" /></svg>
									<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $host ); ?></a>
								</span>
							<?php endif; ?>
							<?php if ( $emp ) : ?>
								<span class="workeera-usrcmp-metaitem workeera-usrcmp-emp">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
									<?php
									/* translators: %s: employee count range */
									echo esc_html( sprintf( _x( '%s employees', 'My companies', 'workeera-remote-tech-job-board' ), $emp ) );
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<span class="workeera-usrcmp-jobs-pill">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
					<?php
					/* translators: %s: number of jobs posted for this company */
					echo esc_html( sprintf( _n( '%s job', '%s jobs', $jobs_cnt, 'workeera-remote-tech-job-board' ), number_format_i18n( $jobs_cnt ) ) );
					?>
				</span>
				<span class="workeera-usrcmp-chevron">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9" /></svg>
				</span>
			</div>
			<div class="workeera-usrcmp-body">
				<?php self::render_company_form( $company, 'inline' ); ?>
				<div class="workeera-usrcmp-item-actions">
					<button type="button" class="workeera-usrjobs-postnew-btn workeera-usrcmp-save-btn"><?php echo esc_html_x( 'Save changes', 'My companies', 'workeera-remote-tech-job-board' ); ?></button>
					<button type="button" class="workeera-usrcmp-delete-btn" data-company-id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html_x( 'Delete', 'My companies', 'workeera-remote-tech-job-board' ); ?></button>
					<span class="workeera-usrcmp-form-notice workeera-hide"></span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: list + search + paginate the user's companies.
	 *
	 * @return void
	 */
	public function ajax_list() {
		self::ajax_guard();

		$user_id = get_current_user_id();
		$page    = isset( $_POST['page_num'] ) ? max( 1, absint( $_POST['page_num'] ) ) : 1;
		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$companies = workeera_get_user_companies( $user_id, true );

		// Newest first (most recently added company at the top).
		usort(
			$companies,
			function ( $a, $b ) {
				$ta = isset( $a['created_at'] ) ? strtotime( $a['created_at'] ) : 0;
				$tb = isset( $b['created_at'] ) ? strtotime( $b['created_at'] ) : 0;
				if ( $ta === $tb ) {
					return 0;
				}
				return ( $tb < $ta ) ? -1 : 1;
			}
		);

		if ( '' !== $search ) {
			$needle    = mb_strtolower( $search );
			$companies = array_values( array_filter(
				$companies,
				function ( $c ) use ( $needle ) {
					return false !== mb_strpos( mb_strtolower( $c['name'] . ' ' . $c['website'] ), $needle );
				}
			) );
		}

		$total  = count( $companies );
		$offset = ( $page - 1 ) * self::$per_page;
		$slice  = array_slice( $companies, $offset, self::$per_page );

		$list_html = '<div class="workeera-usrcmp-list">';
		foreach ( $slice as $company ) {
			$list_html .= self::render_company_item( $company );
		}
		$list_html .= '</div>';

		/* translators: %d: total companies */
		$info_label = sprintf( _x( '%d companies', 'My companies', 'workeera-remote-tech-job-board' ), $total );
		$pagination = Workeera_Dashboard_Common::render_pagination( $page, $total, self::$per_page, $info_label );

		wp_send_json( array(
			'error'           => '0',
			'list_html'       => $list_html,
			'pagination_html' => $pagination,
			'total'           => $total,
			'is_empty'        => empty( $slice ) ? '1' : '0',
			'empty_cta_label' => ( '' !== $search ) ? esc_html__( 'Clear search', 'workeera-remote-tech-job-board' ) : esc_html__( 'Add a company', 'workeera-remote-tech-job-board' ),
		) );
	}

	/**
	 * AJAX: create or update a company (multipart — optional logo file).
	 *
	 * @return void
	 */
	public function ajax_save() {
		self::ajax_guard();

		$user_id    = get_current_user_id();
		$company_id = isset( $_POST['company_id'] ) ? sanitize_text_field( wp_unslash( $_POST['company_id'] ) ) : '';

		$input = array(
			'name'           => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'website'        => isset( $_POST['website'] ) ? wp_unslash( $_POST['website'] ) : '',
			'tagline'        => isset( $_POST['tagline'] ) ? wp_unslash( $_POST['tagline'] ) : '',
			'employee_count' => isset( $_POST['employee_count'] ) ? wp_unslash( $_POST['employee_count'] ) : '',
			'linkedin'       => isset( $_POST['linkedin'] ) ? wp_unslash( $_POST['linkedin'] ) : '',
		);

		// 1) Save fields (create or update) with validation + dedupe.
		$result = workeera_save_user_company( $user_id, $input, $company_id, null );
		if ( '' !== $result['error'] ) {
			wp_send_json( array( 'error' => '1', 'msg' => $result['error'] ) );
		}
		$saved = $result['company'];

		// 2) Process an uploaded logo (needs the saved id for its filename).
		if ( isset( $_FILES['company_logo'] ) && ! empty( $_FILES['company_logo']['size'] ) ) {
			$logo_res = workeera_process_company_logo( $_FILES['company_logo'], $user_id, $saved['id'] );
			if ( '0' !== $logo_res['error'] ) {
				wp_send_json( array( 'error' => '1', 'msg' => $logo_res['msg'] ) );
			}
			$result = workeera_save_user_company( $user_id, $input, $saved['id'], $logo_res['logo'] );
			if ( '' !== $result['error'] ) {
				wp_send_json( array( 'error' => '1', 'msg' => $result['error'] ) );
			}
		} elseif ( isset( $_POST['remove_logo'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remove_logo'] ) ) ) {
			// No new file, but the user cleared the logo → remove it (empty array).
			$result = workeera_save_user_company( $user_id, $input, $saved['id'], array() );
			if ( '' !== $result['error'] ) {
				wp_send_json( array( 'error' => '1', 'msg' => $result['error'] ) );
			}
		}

		wp_send_json( array(
			'error' => '0',
			'msg'   => ( '' !== $company_id ) ? esc_html__( 'Company updated.', 'workeera-remote-tech-job-board' ) : esc_html__( 'Company created.', 'workeera-remote-tech-job-board' ),
		) );
	}

	/**
	 * AJAX: delete a company.
	 *
	 * @return void
	 */
	public function ajax_delete() {
		self::ajax_guard();

		$user_id    = get_current_user_id();
		$company_id = isset( $_POST['company_id'] ) ? sanitize_text_field( wp_unslash( $_POST['company_id'] ) ) : '';

		if ( '' === $company_id || ! workeera_delete_user_company( $user_id, $company_id ) ) {
			wp_send_json( array( 'error' => '1', 'msg' => esc_html__( 'That company could not be deleted.', 'workeera-remote-tech-job-board' ) ) );
		}

		wp_send_json( array( 'error' => '0', 'msg' => esc_html__( 'Company deleted.', 'workeera-remote-tech-job-board' ) ) );
	}
}

new Workeera_User_My_Companies;
