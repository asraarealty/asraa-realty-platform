<?php
/**
 * Asraa Broker Feed Shortcode
 *
 * Registers [asraa_broker_feed]. Displays approved, public property listings
 * in a responsive card grid. Enqueues CSS only when the shortcode is present.
 * Loads the card layout from templates/broker-feed.php.
 *
 * @package    Asraa_CRM
 * @subpackage Public
 * @since      5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Asraa_Broker_Feed_Shortcode' ) ) {

	/**
	 * Class Asraa_Broker_Feed_Shortcode
	 *
	 * Manages the [asraa_broker_feed] frontend shortcode lifecycle.
	 */
	class Asraa_Broker_Feed_Shortcode {

		/**
		 * Constructor — register shortcode and conditional asset hooks.
		 *
		 * @since 5.1.0
		 */
		public function __construct() {
			add_shortcode( 'asraa_broker_feed', array( $this, 'render' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		}

		/**
		 * Enqueue CSS only when [asraa_broker_feed] is present on the page.
		 *
		 * @since 5.1.0
		 * @return void
		 */
		public function maybe_enqueue_assets(): void {
			if ( ! $this->page_has_shortcode( 'asraa_broker_feed' ) ) {
				return;
			}
			wp_enqueue_style(
				'asraa-broker-feed',
				ASRAA_CRM_URL . 'assets/css/broker-form.css',
				array(),
				ASRAA_CRM_VERSION
			);
		}

		/**
		 * Render the [asraa_broker_feed] shortcode.
		 *
		 * Fetches only approved + public listings via Asraa_Broker_Feed_Repository.
		 *
		 * @since  5.1.0
		 * @param  array $atts Shortcode attributes (unused).
		 * @return string      HTML output.
		 */
		public function render( $atts = array() ): string {
			if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				return '<p>' . esc_html__( 'Broker feed is currently unavailable.', 'asraa-crm' ) . '</p>';
			}

			$template = ASRAA_CRM_PATH . 'templates/broker-feed.php';
			if ( ! file_exists( $template ) ) {
				return '<p>' . esc_html__( 'Broker feed template not found.', 'asraa-crm' ) . '</p>';
			}

			$repository = new Asraa_Broker_Feed_Repository();
			$listings   = $repository->get_public_feed( ARRAY_A, 60 );

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
			if ( ! empty( $elementor_data ) && false !== strpos( $elementor_data, $tag ) ) {
				return true;
			}
			return false;
		}
	}
}
