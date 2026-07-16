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
			// Priority 20: runs after Growth Engine's Title.php (priority 10, default),
			// which otherwise hardcodes the front-page title before this could apply.
			add_filter( 'pre_get_document_title', array( $this, 'maybe_override_title' ), 20 );
			add_action( 'save_post', array( $this, 'maybe_populate_meta_description' ) );
		}

		/**
		 * Override the page title with something on-topic when the current
		 * singular page hosts [asraa_broker_feed] and no manual title override
		 * (_asraa_meta_title) has already been set by an admin.
		 *
		 * Safe no-op if Growth Engine isn't active — this filter just never
		 * gets a chance to matter, since nothing reads pre_get_document_title
		 * output differently either way.
		 *
		 * @since 5.3.0
		 * @param string $title The title WordPress core / other filters have produced so far.
		 * @return string
		 */
		public function maybe_override_title( $title ) {
			if ( ! $this->page_has_shortcode( 'asraa_broker_feed' ) ) {
				return $title;
			}
			global $post;
			if ( $post instanceof WP_Post && get_post_meta( $post->ID, '_asraa_meta_title', true ) ) {
				return $title; // Respect a manually-set title.
			}
			/**
			 * Filter the default title used for the page hosting [asraa_broker_feed].
			 *
			 * @since 5.3.0
			 * @param string $default_title
			 */
			$default_title = apply_filters( 'asraa_feed_page_title', __( 'Property Listings', 'asraa-crm' ) . ' | ' . get_bloginfo( 'name' ) );
			return $default_title;
		}

		/**
		 * On save, populate a sensible default meta description for the page
		 * hosting [asraa_broker_feed], if one hasn't already been set manually.
		 * Growth Engine's Meta.php already reads _asraa_meta_description on any
		 * singular page — this just gives it something to read. Safe no-op if
		 * Growth Engine isn't active.
		 *
		 * @since 5.3.0
		 * @param int $post_id
		 */
		public function maybe_populate_meta_description( $post_id ) {
			$post = get_post( $post_id );
			if ( ! ( $post instanceof WP_Post ) || ! has_shortcode( $post->post_content, 'asraa_broker_feed' ) ) {
				return;
			}
			if ( get_post_meta( $post_id, '_asraa_meta_description', true ) ) {
				return; // Don't clobber a manually-set description.
			}
			update_post_meta(
				$post_id,
				'_asraa_meta_description',
				__( "Browse the latest verified property listings from Asraa Realty's broker network.", 'asraa-crm' )
			);
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
			wp_enqueue_script(
				'asraa-broker-feed-carousel',
				ASRAA_CRM_URL . 'assets/js/broker-feed-carousel.js',
				array(),
				ASRAA_CRM_VERSION,
				true
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
