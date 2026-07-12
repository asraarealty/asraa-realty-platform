<?php
/**
 * Asraa Agent Quick Post Controller
 *
 * Captures administrative form submissions, sanitizes input payloads, standardizes human-readable
 * financial strings into absolute database integers, handles safe multi-file attachment streams via the
 * WordPress Core Media Engine, and handles data writing using decoupled Repositories.
 *
 * @package    Asraa_CRM
 * @subpackage Controllers
 * @category   Core
 * @version    3.0.0
 * @since      2026-07-10
 * @author     Asraa Realty Architecture Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly to prevent direct execution scanning.
}

if ( ! class_exists( 'Asraa_Agent_Quick_Post_Controller' ) ) {

	/**
	 * Class Asraa_Agent_Quick_Post_Controller
	 *
	 * Manages the routing lifecycle for processing quick property listing posts.
	 * Enforces strict compliance with WordPress Coding Standards, handles secure nonce verification,
	 * verifies role capabilities, filters external inputs, and acts as a controller mediator.
	 */
	class Asraa_Agent_Quick_Post_Controller {

		/**
		 * Constructor initializes internal hooks and routing listeners.
		 * Registers the operational controller actions inside the WordPress admin post pipeline.
		 *
		 * @since  3.0.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'admin_post_asraa_quick_post', array( $this, 'save_property' ) );
			// AJAX endpoint for the frontend broker form shortcode.
			add_action( 'wp_ajax_asraa_quick_post', array( $this, 'save_property_ajax' ) );
		}

		/**
		 * Captures administrative form actions, executes authorization handshakes, normalizes data layout
		 * vectors, and saves values through the repository abstraction layer.
		 *
		 * @since  3.0.0
		 * @access public
		 * @return void Finishes execution by redirecting headers out of scope.
		 */
		public function save_property(): void {
			// Temporary Debug Logs at Start
			error_log('===== ASRAA QUICK POST START =====');
			error_log(print_r($_POST, true));

			// 1. Enforce strict user session authentication verification.
			if ( ! is_user_logged_in() ) {
				error_log( '[ASRAA CRM CONTROLLER SECURITY] Unauthenticated form post attempt intercepted.' );
				wp_die( esc_html__( 'Error: You must be logged in to access this resource.', 'asraa-crm' ), 403 );
			}

			// 2. Enforce strict capability checks tailored for authenticated real estate roles.
			if ( ! current_user_can( 'read' ) ) {
				error_log( '[ASRAA CRM CONTROLLER SECURITY] Insufficient capabilities detected for user ID: ' . get_current_user_id() );
				wp_die( esc_html__( 'Error: You do not possess sufficient permissions to record information.', 'asraa-crm' ), 403 );
			}

			// 3. Perform a rigorous anti-CSRF token verification handshake.
			if ( ! isset( $_POST['asraa_quick_post_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['asraa_quick_post_nonce'] ), 'asraa_quick_post' ) ) {
				error_log( '[ASRAA CRM CONTROLLER SECURITY] Nonce verification handshake failure intercepted.' );
				wp_die( esc_html__( 'Error: Security verification failed. Please reload the interface and retry.', 'asraa-crm' ), 403 );
			}

			// 4. Verify that required system layout modules exist.
			if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				error_log( '[ASRAA CRM CONTROLLER FAILURE] Structural component Asraa_Broker_Feed_Repository is missing from runtime.' );
				wp_die( esc_html__( 'Critical System Error: Operational data storage layers are inaccessible.', 'asraa-crm' ), 500 );
			}

			// 5. Initialize the decoupled repository layer to isolate data writing tasks.
			$repository = new Asraa_Broker_Feed_Repository();
			error_log('Repository Loaded');

			// 6. Automatically look up authenticated session parameters to prevent profile spoofing.
			$current_user       = wp_get_current_user();
			$source_agent_id    = absint( $current_user->ID );
			$source_agent_name  = sanitize_text_field( $current_user->display_name );
			$source_agent_phone = sanitize_text_field( get_user_meta( $source_agent_id, 'billing_phone', true ) ?: get_user_meta( $source_agent_id, 'phone', true ) );

			// 7. Sanitize incoming form parameters.
			$title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
			$project_name     = isset( $_POST['project_name'] ) ? sanitize_text_field( wp_unslash( $_POST['project_name'] ) ) : '';
			$transaction_type = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : 'sale';
			$property_type    = isset( $_POST['property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['property_type'] ) ) : '';
			$configuration    = isset( $_POST['configuration'] ) ? sanitize_text_field( wp_unslash( $_POST['configuration'] ) ) : '';
			$city             = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
			$locality         = isset( $_POST['locality'] ) ? sanitize_text_field( wp_unslash( $_POST['locality'] ) ) : '';
			$location         = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
			$carpet_area      = isset( $_POST['carpet_area'] ) ? sanitize_text_field( wp_unslash( $_POST['carpet_area'] ) ) : '';
			$available_units  = isset( $_POST['available_units'] ) ? absint( wp_unslash( $_POST['available_units'] ) ) : 1;
			$price_raw        = isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '0';
			$notes            = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
			$price_parsed     = $this->parse_shorthand_price_to_number( $price_raw );

			// Compile data vector structure matrix array payload context matching DB structures
			$payload = array(
				'title'              => $title,
				'project_name'       => $project_name,
				'transaction_type'   => $transaction_type,
				'property_type'      => $property_type,
				'configuration'      => $configuration,
				'city'               => $city,
				'locality'           => $locality,
				'location'           => $location,
				'carpet_area'        => $carpet_area,
				'available_units'    => $available_units,
				'price'              => $price_parsed,
				'notes'              => $notes,
				'source_agent_id'    => $source_agent_id,
				'source_agent_name'  => $source_agent_name,
				'source_agent_phone' => $source_agent_phone,
				'approval_status'    => 'pending',
				'is_public'          => 0,
			);

			$insertion_id = $repository->create( $payload );

			global $wpdb;
			error_log('Insert Result: ' . print_r($insertion_id, true));
			error_log('DB Error: ' . $wpdb->last_error);

			// Redirect cleanly back to prevent the blank admin-post rendering execution profile loop
			$fallback_redirect = admin_url( 'admin.php?page=asraa-broker-feed' );
			if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
				$fallback_redirect = wp_validate_redirect( sanitize_url( wp_unslash( $_REQUEST['_wp_http_referer'] ) ), $fallback_redirect );
			}

			if ( $insertion_id ) {
				wp_safe_redirect( add_query_arg( 'asraa_quick_post_success', '1', $fallback_redirect ) );
			} else {
				wp_safe_redirect( add_query_arg( 'asraa_quick_post_error', 'db_failure', $fallback_redirect ) );
			}
			exit;
		}

		/**
		 * AJAX handler for the frontend [asraa_broker_post_form] shortcode submission.
		 *
		 * Fires on: wp_ajax_asraa_quick_post (logged-in users only).
		 * Reuses the same nonce action, repository, and price parser as save_property().
		 * Handles optional image upload via the WordPress Media Library engine.
		 * Returns JSON — never redirects.
		 *
		 * @since  5.1.0
		 * @access public
		 * @return void Terminates via wp_send_json_success / wp_send_json_error.
		 */
		public function save_property_ajax(): void {
			// 1. Session authentication.
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'You must be logged in to submit a listing.', 'asraa-crm' ) ), 403 );
			}

			// 2. Capability check.
			if ( ! current_user_can( 'read' ) ) {
				wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'asraa-crm' ) ), 403 );
			}

			// 3. Nonce verification.
			$nonce = isset( $_POST['asraa_quick_post_nonce'] ) ? sanitize_key( wp_unslash( $_POST['asraa_quick_post_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'asraa_quick_post' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'asraa-crm' ) ), 403 );
			}

			// 4. Repository availability.
			if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				wp_send_json_error( array( 'message' => __( 'System error. Please contact support.', 'asraa-crm' ) ), 500 );
			}

			$repository = new Asraa_Broker_Feed_Repository();

			// 5. Resolve broker identity from the server session — never from POST.
			$current_user       = wp_get_current_user();
			$source_agent_id    = absint( $current_user->ID );
			$source_agent_name  = sanitize_text_field( $current_user->display_name );
			$source_agent_phone = sanitize_text_field(
				get_user_meta( $source_agent_id, 'billing_phone', true )
					?: get_user_meta( $source_agent_id, 'phone', true )
			);

			// 6. Sanitize form fields.
			$title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
			$project_name     = isset( $_POST['project_name'] ) ? sanitize_text_field( wp_unslash( $_POST['project_name'] ) ) : '';
			$transaction_type = isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : 'sale';
			$property_type    = isset( $_POST['property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['property_type'] ) ) : '';
			$configuration    = isset( $_POST['configuration'] ) ? sanitize_text_field( wp_unslash( $_POST['configuration'] ) ) : '';
			$city             = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
			$locality         = isset( $_POST['locality'] ) ? sanitize_text_field( wp_unslash( $_POST['locality'] ) ) : '';
			$location         = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
			$carpet_area      = isset( $_POST['carpet_area'] ) ? sanitize_text_field( wp_unslash( $_POST['carpet_area'] ) ) : '';
			$available_units  = isset( $_POST['available_units'] ) ? absint( wp_unslash( $_POST['available_units'] ) ) : 1;
			$price_raw        = isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '0';
			$notes            = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
			$price_parsed     = $this->parse_shorthand_price_to_number( $price_raw );

			// 7. Handle optional property image upload via WordPress Media Library.
			$image_url = '';
			if ( ! empty( $_FILES['property_image']['name'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';

				$attachment_id = media_handle_upload( 'property_image', 0 );
				if ( ! is_wp_error( $attachment_id ) ) {
					$uploaded_url = wp_get_attachment_url( $attachment_id );
					if ( $uploaded_url ) {
						$image_url = esc_url_raw( $uploaded_url );
					}
				} else {
					error_log( '[ASRAA CRM MEDIA] AJAX image upload failed: ' . $attachment_id->get_error_message() );
				}
			}

			// 8. Compose and persist the payload.
			$payload = array(
				'title'              => $title,
				'project_name'       => $project_name,
				'transaction_type'   => $transaction_type,
				'property_type'      => $property_type,
				'configuration'      => $configuration,
				'city'               => $city,
				'locality'           => $locality,
				'location'           => $location,
				'carpet_area'        => $carpet_area,
				'available_units'    => $available_units,
				'price'              => $price_parsed,
				'notes'              => $notes,
				'image_url'          => $image_url,
				'source_agent_id'    => $source_agent_id,
				'source_agent_name'  => $source_agent_name,
				'source_agent_phone' => $source_agent_phone,
				'approval_status'    => 'pending',
				'is_public'          => 0,
			);

			$insertion_id = $repository->create( $payload );

			if ( $insertion_id ) {
				wp_send_json_success(
					array(
						'message' => __( 'Your listing has been submitted and is awaiting review.', 'asraa-crm' ),
						'id'      => $insertion_id,
					)
				);
			} else {
				wp_send_json_error(
					array( 'message' => __( 'Failed to save your listing. Please try again.', 'asraa-crm' ) ),
					500
				);
			}
		}

		/**
		 * Parses human-entered financial shortcodes into standard values.
		 */
		private function parse_shorthand_price_to_number( string $price_string ): float {
			$clean_string = trim( str_replace( array( ',', ' ' ), '', strtolower( $price_string ) ) );
			if ( empty( $clean_string ) ) {
				return 0.00;
			}
			$multiplied_valuation = 1.00;
			if ( str_contains( $clean_string, 'l' ) ) {
				$multiplied_valuation = 100000.00;
				$clean_string         = str_replace( array( 'lakhs', 'lakh', 'lac', 'l' ), '', $clean_string );
			} elseif ( str_contains( $clean_string, 'cr' ) || str_contains( $clean_string, 'crore' ) ) {
				$multiplied_valuation = 10000000.00;
				$clean_string         = str_replace( array( 'crores', 'crore', 'cr' ), '', $clean_string );
			}
			return floatval( $clean_string ) * $multiplied_valuation;
		}
	}
}
