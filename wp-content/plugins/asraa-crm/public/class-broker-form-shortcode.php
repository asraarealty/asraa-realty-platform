<?php
/**
 * Asraa Broker Post Form Shortcode
 *
 * Registers [asraa_broker_post_form]. Enqueues CSS/JS only when the shortcode is
 * present on the current page. When the visitor is not logged in, renders an
 * embedded Asraa Realty Login/Register screen; after successful authentication
 * the browser is redirected back to this page and the property form is shown.
 * Loads the form from templates/broker-form.php.
 *
 * @package    Asraa_CRM
 * @subpackage Public
 * @since      5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Asraa_Broker_Form_Shortcode' ) ) {

	/**
	 * Class Asraa_Broker_Form_Shortcode
	 *
	 * Manages the [asraa_broker_post_form] frontend shortcode lifecycle.
	 */
	class Asraa_Broker_Form_Shortcode {

		/**
		 * Constructor — register shortcode, conditional asset hooks, and AJAX actions.
		 *
		 * @since 5.1.0
		 */
		public function __construct() {
			add_shortcode( 'asraa_broker_post_form', array( $this, 'render' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
			// Registration AJAX endpoint — available to unauthenticated visitors.
			add_action( 'wp_ajax_nopriv_asraa_broker_register', array( $this, 'register_broker_ajax' ) );
		}

		/**
		 * Enqueue CSS and JS only when [asraa_broker_post_form] is present on the page.
		 * Assets are loaded regardless of login state so the auth panel is also styled.
		 *
		 * @since 5.1.0
		 * @return void
		 */
		public function maybe_enqueue_assets(): void {
			if ( ! $this->page_has_shortcode( 'asraa_broker_post_form' ) ) {
				return;
			}

			wp_enqueue_style(
				'asraa-broker-form',
				ASRAA_CRM_URL . 'assets/css/broker-form.css',
				array(),
				ASRAA_CRM_VERSION
			);

			wp_enqueue_script(
				'asraa-broker-form-js',
				ASRAA_CRM_URL . 'assets/js/broker-form.js',
				array( 'jquery' ),
				ASRAA_CRM_VERSION,
				true
			);

			wp_localize_script(
				'asraa-broker-form-js',
				'asraaBrokerFormConfig',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'asraa_quick_post' ),
					'regNonce' => wp_create_nonce( 'asraa_broker_register' ),
					'i18n'     => array(
						'required'      => __( 'This field is required.', 'asraa-crm' ),
						'submitting'    => __( 'Submitting\u2026', 'asraa-crm' ),
						'success'       => __( 'Your listing has been submitted and is awaiting review.', 'asraa-crm' ),
						'error'         => __( 'Submission failed. Please try again.', 'asraa-crm' ),
						'invalidPrice'  => __( 'Please enter a valid price (e.g. 50L, 1.5Cr, 2500000).', 'asraa-crm' ),
						'imageTooBig'   => __( 'Image must be under 5\u00a0MB.', 'asraa-crm' ),
						'imageType'     => __( 'Please upload a JPG, PNG, or WebP image.', 'asraa-crm' ),
						'regError'      => __( 'Registration failed. Please try again.', 'asraa-crm' ),
						'regSubmitting' => __( 'Creating account\u2026', 'asraa-crm' ),
						'createAccount' => __( 'Create Account', 'asraa-crm' ),
						'pwMismatch'    => __( 'Passwords do not match.', 'asraa-crm' ),
						'pwTooShort'    => __( 'Password must be at least 8 characters.', 'asraa-crm' ),
					),
				)
			);
		}

		/**
		 * Render the [asraa_broker_post_form] shortcode.
		 *
		 * Shows the Asraa Realty Login/Register screen when the visitor is not
		 * authenticated. Opens the property form directly when already logged in.
		 *
		 * @since  5.1.0
		 * @param  array $atts Shortcode attributes (unused).
		 * @return string      HTML output.
		 */
		public function render( $atts = array() ): string {
			if ( ! is_user_logged_in() ) {
				return $this->render_auth_panel();
			}

			$template = ASRAA_CRM_PATH . 'templates/broker-form.php';
			if ( ! file_exists( $template ) ) {
				return '<p>' . esc_html__( 'Broker form template not found.', 'asraa-crm' ) . '</p>';
			}

			// Resolve broker data server-side; never trust browser-submitted values.
			$current_user = wp_get_current_user();
			$broker_id    = absint( $current_user->ID );
			$broker_name  = sanitize_text_field( $current_user->display_name );
			$broker_email = sanitize_email( $current_user->user_email );
			$broker_phone = sanitize_text_field(
				get_user_meta( $broker_id, 'billing_phone', true )
					?: get_user_meta( $broker_id, 'phone', true )
			);

			ob_start();
			include $template;
			return (string) ob_get_clean();
		}

		/**
		 * Render the embedded login/register authentication panel.
		 *
		 * Uses WordPress's built-in wp_login_form() for the login tab.
		 * Provides a custom AJAX-based registration form for the register tab.
		 * After successful login or registration the browser is redirected back
		 * to the current page so the property form is immediately shown.
		 *
		 * @since  5.1.0
		 * @return string HTML output.
		 */
		private function render_auth_panel(): string {
			$current_permalink = get_permalink();
			$redirect_to       = $current_permalink ? esc_url_raw( $current_permalink ) : esc_url_raw( home_url( '/' ) );

			ob_start();
			?>
			<div class="asraa-broker-form-wrapper" id="asraa-broker-auth-wrapper">
				<div class="asraa-broker-form-card">

					<div class="asraa-broker-form-header">
						<h2 class="asraa-broker-form-title">
							<?php esc_html_e( 'Asraa Realty — Broker Portal', 'asraa-crm' ); ?>
						</h2>
						<p class="asraa-broker-form-subtitle">
							<?php esc_html_e( 'Sign in or create an account to submit property listings.', 'asraa-crm' ); ?>
						</p>
					</div>

					<!-- Auth error banner -->
					<div class="asraa-banner asraa-banner--error" id="asraa-auth-error" style="display:none;" role="alert" aria-live="assertive">
						<span class="asraa-banner__icon" aria-hidden="true">&#9888;</span>
						<span class="asraa-banner__message"></span>
					</div>

					<!-- Tab bar -->
					<div class="asraa-auth-tabs" role="tablist">
						<button
							class="asraa-auth-tab asraa-auth-tab--active"
							data-tab="login"
							role="tab"
							aria-selected="true"
							aria-controls="asraa-auth-panel-login"
						><?php esc_html_e( 'Log In', 'asraa-crm' ); ?></button>
						<button
							class="asraa-auth-tab"
							data-tab="register"
							role="tab"
							aria-selected="false"
							aria-controls="asraa-auth-panel-register"
						><?php esc_html_e( 'Register', 'asraa-crm' ); ?></button>
					</div>

					<!-- Login panel -->
					<div class="asraa-auth-panel" id="asraa-auth-panel-login" role="tabpanel">
						<?php
						wp_login_form(
							array(
								'redirect'       => $redirect_to,
								'form_id'        => 'asraa-login-form',
								'label_username' => __( 'Email Address or Username', 'asraa-crm' ),
								'label_password' => __( 'Password', 'asraa-crm' ),
								'label_remember' => __( 'Keep me signed in', 'asraa-crm' ),
								'label_log_in'   => __( 'Sign In', 'asraa-crm' ),
								'remember'       => true,
							)
						);
						?>
						<p class="asraa-auth-meta">
							<a href="<?php echo esc_url( wp_lostpassword_url( $redirect_to ) ); ?>">
								<?php esc_html_e( 'Forgot your password?', 'asraa-crm' ); ?>
							</a>
						</p>
					</div>

					<!-- Register panel -->
					<div class="asraa-auth-panel" id="asraa-auth-panel-register" style="display:none;" role="tabpanel">
						<form id="asraa-broker-register-form" class="asraa-auth-form" novalidate>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />

							<div class="asraa-form-grid">

								<div class="asraa-form-field">
									<label for="abr-firstname" class="asraa-form-label">
										<?php esc_html_e( 'First Name', 'asraa-crm' ); ?>
										<span class="asraa-required" aria-hidden="true">*</span>
									</label>
									<input
										type="text"
										id="abr-firstname"
										name="reg_firstname"
										class="asraa-form-input asraa-auth-required"
										autocomplete="given-name"
										maxlength="60"
									/>
									<span class="asraa-field-error" id="abr-firstname-error" role="alert"></span>
								</div>

								<div class="asraa-form-field">
									<label for="abr-lastname" class="asraa-form-label">
										<?php esc_html_e( 'Last Name', 'asraa-crm' ); ?>
										<span class="asraa-required" aria-hidden="true">*</span>
									</label>
									<input
										type="text"
										id="abr-lastname"
										name="reg_lastname"
										class="asraa-form-input asraa-auth-required"
										autocomplete="family-name"
										maxlength="60"
									/>
									<span class="asraa-field-error" id="abr-lastname-error" role="alert"></span>
								</div>

								<div class="asraa-form-field asraa-form-field--full">
									<label for="abr-email" class="asraa-form-label">
										<?php esc_html_e( 'Email Address', 'asraa-crm' ); ?>
										<span class="asraa-required" aria-hidden="true">*</span>
									</label>
									<input
										type="email"
										id="abr-email"
										name="reg_email"
										class="asraa-form-input asraa-auth-required"
										autocomplete="email"
										maxlength="100"
									/>
									<span class="asraa-field-error" id="abr-email-error" role="alert"></span>
								</div>

								<div class="asraa-form-field">
									<label for="abr-password" class="asraa-form-label">
										<?php esc_html_e( 'Password', 'asraa-crm' ); ?>
										<span class="asraa-required" aria-hidden="true">*</span>
									</label>
									<input
										type="password"
										id="abr-password"
										name="reg_password"
										class="asraa-form-input asraa-auth-required"
										autocomplete="new-password"
										minlength="8"
									/>
									<span class="asraa-field-error" id="abr-password-error" role="alert"></span>
								</div>

								<div class="asraa-form-field">
									<label for="abr-password2" class="asraa-form-label">
										<?php esc_html_e( 'Confirm Password', 'asraa-crm' ); ?>
										<span class="asraa-required" aria-hidden="true">*</span>
									</label>
									<input
										type="password"
										id="abr-password2"
										name="reg_password2"
										class="asraa-form-input asraa-auth-required"
										autocomplete="new-password"
									/>
									<span class="asraa-field-error" id="abr-password2-error" role="alert"></span>
								</div>

							</div><!-- /.asraa-form-grid -->

							<div class="asraa-auth-form-footer">
								<button type="submit" class="asraa-btn asraa-btn--primary" id="asraa-register-submit">
									<span class="asraa-btn__text"><?php esc_html_e( 'Create Account', 'asraa-crm' ); ?></span>
									<span class="asraa-btn__spinner asraa-form-spinner" aria-hidden="true"></span>
								</button>
							</div>
						</form>
					</div><!-- /#asraa-auth-panel-register -->

				</div><!-- /.asraa-broker-form-card -->
			</div><!-- /#asraa-broker-auth-wrapper -->
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * AJAX handler — register a new broker account (no-priv: unauthenticated visitors).
		 *
		 * Creates a WordPress user, auto-logs them in, and returns a JSON success
		 * payload containing the redirect URL so the JS can navigate back to the form.
		 *
		 * @since  5.1.0
		 * @return void Terminates via wp_send_json_success / wp_send_json_error.
		 */
		public function register_broker_ajax(): void {
			// 1. Nonce check.
			$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'asraa_broker_register' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload and try again.', 'asraa-crm' ) ), 403 );
			}

			// 2. Allow broker portal registration by default (overridable via filter).
			/**
			 * Filter whether broker registration is enabled from the broker portal UI.
			 *
			 * @param bool $enabled True to allow registration, false to block it.
			 */
			$registration_enabled = apply_filters( 'asraa_crm_broker_registration_enabled', true );
			if ( ! $registration_enabled ) {
				wp_send_json_error( array( 'message' => __( 'Broker registration is temporarily disabled. Please contact the site administrator.', 'asraa-crm' ) ) );
			}

			// 3. Sanitize inputs.
			$firstname = sanitize_text_field( wp_unslash( $_POST['reg_firstname'] ?? '' ) );
			$lastname  = sanitize_text_field( wp_unslash( $_POST['reg_lastname'] ?? '' ) );
			$email     = sanitize_email( wp_unslash( $_POST['reg_email'] ?? '' ) );
			$password  = wp_unslash( $_POST['reg_password'] ?? '' );

			// 4. Validate.
			if ( empty( $email ) || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'asraa-crm' ) ) );
			}
			if ( strlen( $password ) < 8 ) {
				wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'asraa-crm' ) ) );
			}
			if ( email_exists( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please sign in instead.', 'asraa-crm' ) ) );
			}

			// 5. Derive a unique username from the email local-part.
			// Email has already been validated as a valid address above, so the
			// explode() will always yield at least two parts. The count check is
			// an extra defensive guard.
			$parts = explode( '@', $email );
			if ( count( $parts ) < 2 ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'asraa-crm' ) ) );
			}
			$base_username = sanitize_user( $parts[0], true );
			if ( empty( $base_username ) ) {
				$base_username = 'broker';
			}
			$username = $base_username;
			$suffix   = 1;
			while ( username_exists( $username ) ) {
				$username = $base_username . $suffix;
				++$suffix;
			}

			// 6. Create the user.
			$user_id = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}

			if ( get_role( 'asraa_agent' ) ) {
				wp_update_user(
					array(
						'ID'   => $user_id,
						'role' => 'asraa_agent',
					)
				);
			}

			// 7. Populate display name if first/last name provided.
			$display_name = trim( $firstname . ' ' . $lastname );
			if ( $display_name ) {
				wp_update_user(
					array(
						'ID'           => $user_id,
						'first_name'   => $firstname,
						'last_name'    => $lastname,
						'display_name' => $display_name,
					)
				);
			}

			// 8. Auto-login the newly registered user.
			// false = session cookie (expires on browser close) for security on first login;
			// the user can choose a persistent session on subsequent logins via the "Keep me
			// signed in" option on the Login tab.
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, false );

			// 9. Resolve safe redirect URL (fall back to home).
			$redirect_raw = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '';
			$redirect_url = $redirect_raw ? wp_validate_redirect( esc_url_raw( $redirect_raw ), get_home_url() ) : get_home_url();

			wp_send_json_success( array( 'redirect' => esc_url_raw( $redirect_url ) ) );
		}

		/**
		 * Detect whether the current page/post uses the given shortcode,
		 * including pages built with Elementor.
		 *
		 * @since  5.1.0
		 * @param  string $tag Shortcode tag to search for.
		 * @return bool
		 */
		private function page_has_shortcode( string $tag ): bool {
			global $post;
			if ( ! ( $post instanceof WP_Post ) ) {
				return false;
			}
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
			// Elementor compatibility: inspect stored page-builder JSON meta.
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( ! empty( $elementor_data ) && false !== strpos( (string) $elementor_data, $tag ) ) {
				return true;
			}
			return false;
		}
	}
}
