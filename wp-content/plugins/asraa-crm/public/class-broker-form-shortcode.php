<?php
/**
 * Asraa Broker Post Form Shortcode
 *
 * Registers [asraa_broker_post_form]. Enqueues CSS/JS only when the shortcode is
 * present on the current page. Loads the form from templates/broker-form.php.
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
		 * Constructor — register shortcode and conditional asset hooks.
		 *
		 * @since 5.1.0
		 */
		public function __construct() {
			add_shortcode( 'asraa_broker_post_form', array( $this, 'render' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		}

		/**
		 * Enqueue CSS and JS only when [asraa_broker_post_form] is present on the page.
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
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'asraa_quick_post' ),
					'i18n'    => array(
						'required'     => __( 'This field is required.', 'asraa-crm' ),
						'submitting'   => __( 'Submitting\u2026', 'asraa-crm' ),
						'success'      => __( 'Your listing has been submitted and is awaiting review.', 'asraa-crm' ),
						'error'        => __( 'Submission failed. Please try again.', 'asraa-crm' ),
						'invalidPrice' => __( 'Please enter a valid price (e.g. 50L, 1.5Cr, 2500000).', 'asraa-crm' ),
						'imageTooBig'  => __( 'Image must be under 5\u00a0MB.', 'asraa-crm' ),
						'imageType'    => __( 'Please upload a JPG, PNG, or WebP image.', 'asraa-crm' ),
					),
				)
			);
		}

		/**
		 * Render the [asraa_broker_post_form] shortcode.
		 *
		 * @since  5.1.0
		 * @param  array $atts Shortcode attributes (unused).
		 * @return string      HTML output.
		 */
		public function render( $atts = array() ): string {
			if ( ! is_user_logged_in() ) {
				return '<div class="asraa-broker-form-wrapper">'
					. '<p class="asraa-login-notice">'
					. esc_html__( 'Please log in to submit a property listing.', 'asraa-crm' )
					. ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">'
					. esc_html__( 'Log in', 'asraa-crm' )
					. '</a></p></div>';
			}

			$template = ASRAA_CRM_PATH . 'templates/broker-form.php';
			if ( ! file_exists( $template ) ) {
				return '<p>' . esc_html__( 'Broker form template not found.', 'asraa-crm' ) . '</p>';
			}

			// Resolve broker data server-side; never trust browser-submitted values.
			$current_user = wp_get_current_user();
			$broker_id    = absint( $current_user->ID );
			$broker_name  = sanitize_text_field( $current_user->display_name );
			$broker_phone = sanitize_text_field(
				get_user_meta( $broker_id, 'billing_phone', true )
					?: get_user_meta( $broker_id, 'phone', true )
			);

			ob_start();
			include $template;
			return (string) ob_get_clean();
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
			if ( ! empty( $elementor_data ) && str_contains( (string) $elementor_data, $tag ) ) {
				return true;
			}
			return false;
		}
	}
}
