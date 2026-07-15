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
			if ( ! isset( $_POST['asraa_quick_post_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['asraa_quick_post_nonce'] ) ), 'asraa_quick_post' ) ) {
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

			// 6. Automatically look up authenticated session parameters to prevent profile spoofing.
			$broker_profile     = $this->get_broker_profile();
			$source_agent_id    = $broker_profile['id'];
			$source_agent_name  = $broker_profile['name'];

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
			$broker_phone_raw = isset( $_POST['broker_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['broker_phone'] ) ) : '';
			$broker_phone     = $this->normalize_indian_mobile( $broker_phone_raw );
			$price_parsed     = $this->parse_shorthand_price_to_number( $price_raw );
			$validation_error = $this->validate_listing_input( $title, $project_name, $transaction_type, $property_type, $city, $price_parsed, $broker_phone );
			if ( $validation_error ) {
				wp_die( esc_html( $validation_error ), 400 );
			}

			// 7a. Backfill the broker's profile if it has no phone number on file yet.
			$this->maybe_backfill_broker_phone( $source_agent_id, $broker_phone );

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
				'source_agent_phone' => $broker_phone,
				'approval_status'    => 'pending',
				'is_public'          => 0,
			);

			$existing = $repository->find_duplicate_submission( $payload );
			if ( ! empty( $existing ) ) {
				wp_die( esc_html__( 'A similar listing already exists. Please update the existing entry instead.', 'asraa-crm' ), 409 );
			}

			$insertion_id = $repository->create( $payload );

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
			$broker_profile     = $this->get_broker_profile();
			$source_agent_id    = $broker_profile['id'];
			$source_agent_name  = $broker_profile['name'];

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
			$broker_phone_raw = isset( $_POST['broker_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['broker_phone'] ) ) : '';
			$broker_phone     = $this->normalize_indian_mobile( $broker_phone_raw );
			$price_parsed     = $this->parse_shorthand_price_to_number( $price_raw );
			$validation_error = $this->validate_listing_input( $title, $project_name, $transaction_type, $property_type, $city, $price_parsed, $broker_phone );
			if ( $validation_error ) {
				wp_send_json_error( array( 'message' => $validation_error ), 400 );
			}

			// 6a. Backfill the broker's profile if it has no phone number on file yet.
			$this->maybe_backfill_broker_phone( $source_agent_id, $broker_phone );

			// 7. Handle optional property image upload via WordPress Media Library.
			$image_url       = '';
			$image_upload_msg = '';
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
					// Non-blocking: listing is saved without an image; inform the user.
					$image_upload_msg = __( 'Note: your listing was saved but the image could not be uploaded.', 'asraa-crm' );
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
				'source_agent_phone' => $broker_phone,
				'approval_status'    => 'pending',
				'is_public'          => 0,
			);

			$existing = $repository->find_duplicate_submission( $payload );
			if ( ! empty( $existing ) ) {
				wp_send_json_error(
					array( 'message' => __( 'A similar listing already exists. Please contact support to update it.', 'asraa-crm' ) ),
					409
				);
			}

			$insertion_id = $repository->create( $payload );

			if ( $insertion_id ) {
				$success_message = __( 'Your listing has been submitted and is awaiting review.', 'asraa-crm' );
				if ( ! empty( $image_upload_msg ) ) {
					$success_message .= ' ' . $image_upload_msg;
				}
				wp_send_json_success(
					array(
						'message' => $success_message,
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
			$crore_removed = (string) preg_replace( '/(?:crores?|cr)\b/', '', $clean_string );
			$lakh_removed  = (string) preg_replace( '/(?:lakhs?|lacs?)\b/', '', $clean_string );
			$l_suffix_removed = (string) preg_replace( '/l\b/', '', $clean_string );

			if ( $crore_removed !== $clean_string ) {
				$multiplied_valuation = 10000000.00;
				$clean_string         = $crore_removed;
			} elseif ( $lakh_removed !== $clean_string ) {
				$multiplied_valuation = 100000.00;
				$clean_string         = $lakh_removed;
			} elseif ( preg_match( '/\d+l\b/', $clean_string ) && $l_suffix_removed !== $clean_string ) {
				$multiplied_valuation = 100000.00;
				$clean_string         = $l_suffix_removed;
			}
			return floatval( $clean_string ) * $multiplied_valuation;
		}

		/**
		 * Normalize a user-entered Indian mobile number to a plain 10-digit string.
		 *
		 * Strips whitespace/hyphens/parentheses and an optional leading country
		 * code (+91 / 91) or trunk prefix (0), leaving a canonical 10-digit form
		 * for storage. Does not validate — call is_valid_indian_mobile() after.
		 *
		 * @since 5.2.0
		 * @param string $raw Raw user input.
		 * @return string Normalized digits (may still be invalid length/prefix).
		 */
		private function normalize_indian_mobile( string $raw ): string {
			$digits = preg_replace( '/\D/', '', $raw );
			$digits = is_string( $digits ) ? $digits : '';

			if ( '' === $digits ) {
				return '';
			}

			if ( 12 === strlen( $digits ) && '91' === substr( $digits, 0, 2 ) ) {
				$digits = substr( $digits, 2 );
			} elseif ( 11 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
				$digits = substr( $digits, 1 );
			}

			return $digits;
		}

		/**
		 * Validate a normalized Indian mobile number: exactly 10 digits, starting 6-9.
		 *
		 * @since 5.2.0
		 * @param string $normalized Output of normalize_indian_mobile().
		 * @return bool
		 */
		private function is_valid_indian_mobile( string $normalized ): bool {
			return 1 === preg_match( '/^[6-9]\d{9}$/', $normalized );
		}

		/**
		 * Backfill the broker's user profile with their mobile number when the
		 * profile does not already have one on file. Never overwrites an
		 * existing phone number — this is additive/backfill only.
		 *
		 * @since 5.2.0
		 * @param int    $user_id           Broker user ID.
		 * @param string $normalized_phone  Validated 10-digit mobile number.
		 * @return void
		 */
		private function maybe_backfill_broker_phone( int $user_id, string $normalized_phone ): void {
			if ( $user_id <= 0 || '' === $normalized_phone ) {
				return;
			}

			$existing = (string) ( get_user_meta( $user_id, 'billing_phone', true ) ?: get_user_meta( $user_id, 'phone', true ) );
			if ( '' !== trim( $existing ) ) {
				return; // Profile already has a number on file — do not overwrite.
			}

			update_user_meta( $user_id, 'phone', $normalized_phone );
		}

		/**
		 * Resolve broker identity from the authenticated WordPress session.
		 *
		 * @return array{id:int,name:string,phone:string}
		 */
		private function get_broker_profile(): array {
			$current_user    = wp_get_current_user();
			$source_agent_id = absint( $current_user->ID );
			$display_name    = sanitize_text_field( $current_user->display_name );
			$first_name      = sanitize_text_field( (string) get_user_meta( $source_agent_id, 'first_name', true ) );
			$last_name       = sanitize_text_field( (string) get_user_meta( $source_agent_id, 'last_name', true ) );
			$fallback_name   = trim( implode( ' ', array_filter( array( trim( $first_name ), trim( $last_name ) ) ) ) );
			$source_name     = $display_name ? $display_name : ( $fallback_name ? $fallback_name : sanitize_text_field( $current_user->user_login ) );
			$source_phone    = sanitize_text_field(
				(string) ( get_user_meta( $source_agent_id, 'billing_phone', true ) ?: get_user_meta( $source_agent_id, 'phone', true ) )
			);

			return array(
				'id'    => $source_agent_id,
				'name'  => $source_name,
				'phone' => $source_phone,
			);
		}

		/**
		 * Validate core listing fields.
		 *
		 * @param string $title Listing title.
		 * @param string $project_name Project name.
		 * @param string $transaction_type Transaction type.
		 * @param string $property_type Property type.
		 * @param string $city City.
		 * @param float  $price Price.
		 * @param string $normalized_phone Normalized 10-digit broker mobile number.
		 * @return string Empty string when valid, translated message when invalid.
		 */
		private function validate_listing_input(
			string $title,
			string $project_name,
			string $transaction_type,
			string $property_type,
			string $city,
			float $price,
			string $normalized_phone
		): string {
			if ( '' === $title || '' === $project_name || '' === $property_type || '' === $city ) {
				return __( 'Please complete all required fields.', 'asraa-crm' );
			}

			if ( '' === $normalized_phone ) {
				return __( 'Please enter your mobile number.', 'asraa-crm' );
			}

			if ( ! $this->is_valid_indian_mobile( $normalized_phone ) ) {
				return __( 'Please enter a valid 10-digit Indian mobile number.', 'asraa-crm' );
			}

			if ( $price <= 0 ) {
				return __( 'Please enter a valid price.', 'asraa-crm' );
			}

			$allowed_transaction_types = array( 'sale', 'rent', 'resale' );
			if ( ! in_array( $transaction_type, $allowed_transaction_types, true ) ) {
				return __( 'Invalid transaction type selected.', 'asraa-crm' );
			}

			return '';
		}
	}
}
