<?php
/**
 * Asraa Broker Feed Management Controller
 *
 * Intercepts administrative routing paths for managing broker property records. Handles individual status
 * shifts (approve, reject, delete), batch multi-selection operations, and complete programmatic multiform 
 * item record updates including binary multi-part file side-loading processing.
 *
 * @package    Asraa_CRM
 * @subpackage Controllers
 * @category   Core
 * @version    4.1.0
 * @since      2026-07-10
 * @author     Asraa Realty Architecture Board
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Asraa_Broker_Feed_Controller' ) ) {

	/**
	 * Class Asraa_Broker_Feed_Controller
	 *
	 * Controls single actions, bulk modifications, and detailed record data updates via the administrative view dashboard.
	 */
	class Asraa_Broker_Feed_Controller {

		/**
		 * Decoupled data storage repository abstraction instance pointer.
		 *
		 * @var Asraa_Broker_Feed_Repository
		 */
		private ?Asraa_Broker_Feed_Repository $repository = null;

		/**
		 * Constructor initializes operational dependencies and binds targeted admin-post actions.
		 *
		 * @since  4.1.0
		 * @access public
		 */
		public function __construct() {
			// Initialize the standalone custom data tier layer.
			if ( class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
				$this->repository = new Asraa_Broker_Feed_Repository();
			}

			// Hook standard administrative form actions matching the view pipeline exactly.
			add_action( 'admin_post_asraa_broker_feed_single_action', array( $this, 'handle_single_action' ) );
			add_action( 'admin_post_asraa_broker_feed_bulk_action', array( $this, 'handle_bulk_action' ) );
			add_action( 'admin_post_asraa_broker_feed_update_record', array( $this, 'handle_update_record' ) );
		}

		/**
		 * Intercepts individual property actions (approve, reject, delete) via the GET query string variables.
		 *
		 * @since  4.1.0
		 * @access public
		 * @return void Dispatches redirect headers out of scope.
		 */
		public function handle_single_action(): void {
			$this->validate_security_and_privileges();
			if ( ! $this->repository ) {
				wp_die( esc_html__( 'Error: Broker feed repository is unavailable.', 'asraa-crm' ), 500 );
			}

			$feed_action = isset( $_GET['feed_action'] ) ? sanitize_key( wp_unslash( $_GET['feed_action'] ) ) : '';
			$record_id   = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

			if ( empty( $record_id ) ) {
				wp_die( esc_html__( 'Error: Invalid or missing record indicator reference.', 'asraa-crm' ), 400 );
			}

			// Verify nonce attached by view via wp_nonce_url() with action 'asraa_single_action_<id>'.
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'asraa_single_action_' . $record_id ) ) {
				wp_die( esc_html__( 'Error: Security verification failed. Nonce is invalid or expired.', 'asraa-crm' ), 403 );
			}

			$success = false;
			$message = 'error';

			switch ( $feed_action ) {
				case 'approve':
					$success = $this->repository->approve( $record_id );
					$message = $success ? 'approved' : 'error';
					break;

				case 'reject':
					$success = $this->repository->reject( $record_id );
					$message = $success ? 'rejected' : 'error';
					break;

				case 'delete':
					$success = $this->repository->delete( $record_id );
					$message = $success ? 'deleted' : 'error';
					break;

				default:
					wp_die( esc_html__( 'Error: Unrecognized operational action identifier requested.', 'asraa-crm' ), 400 );
			}

			$this->send_safe_redirect( array( 'asraa_msg' => $message ) );
		}

		/**
		 * Intercepts checked multi-selection list array operations via the POST form data payload.
		 *
		 * @since  4.1.0
		 * @access public
		 * @return void Dispatches redirect headers out of scope.
		 */
		public function handle_bulk_action(): void {
			$this->validate_security_and_privileges();
			if ( ! $this->repository ) {
				wp_die( esc_html__( 'Error: Broker feed repository is unavailable.', 'asraa-crm' ), 500 );
			}

			// Verify bulk-action nonce (view submits it as `bulk_nonce` with action `asraa_broker_feed_bulk_nonce`).
			$bulk_nonce = isset( $_POST['bulk_nonce'] ) ? sanitize_key( wp_unslash( $_POST['bulk_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $bulk_nonce, 'asraa_broker_feed_bulk_nonce' ) ) {
				wp_die( esc_html__( 'Error: Security verification failed. Bulk action nonce is invalid or expired.', 'asraa-crm' ), 403 );
			}

			$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
			$record_ids  = ( isset( $_POST['record_ids'] ) && is_array( $_POST['record_ids'] ) ) ? array_map( 'absint', wp_unslash( $_POST['record_ids'] ) ) : array();

			if ( empty( $record_ids ) ) {
				$this->send_safe_redirect( array( 'asraa_msg' => 'no_selection' ) );
				return;
			}

			$success = false;
			$message = 'bulk_error';

			switch ( $bulk_action ) {
				case 'bulk_approve':
					$success = $this->repository->bulk_update_status( $record_ids, 'approved' );
					$message = $success ? 'bulk_approved' : 'bulk_error';
					break;

				case 'bulk_reject':
					$success = $this->repository->bulk_update_status( $record_ids, 'rejected' );
					$message = $success ? 'bulk_rejected' : 'bulk_error';
					break;

				case 'bulk_delete':
					$success = $this->repository->bulk_delete( $record_ids );
					$message = $success ? 'bulk_deleted' : 'bulk_error';
					break;

				default:
					$this->send_safe_redirect( array( 'asraa_msg' => 'bulk_invalid' ) );
					return;
			}

			$this->send_safe_redirect( array( 'asraa_msg' => $message ) );
		}

		/**
		 * Processes complex property layout updates, handles nonces, sanitizes input fields, and hooks the native media library engine.
		 *
		 * @since  4.1.0
		 * @access public
		 * @return void Dispatches redirect headers out of scope.
		 */
		public function handle_update_record(): void {
			$this->validate_security_and_privileges();
			if ( ! $this->repository ) {
				wp_die( esc_html__( 'Error: Broker feed repository is unavailable.', 'asraa-crm' ), 500 );
			}

			// Enforce explicit cryptographic anti-CSRF token check.
			// View submits nonce field name `update_nonce` with action `asraa_broker_feed_update_nonce`.
			$update_nonce = isset( $_POST['update_nonce'] ) ? sanitize_key( wp_unslash( $_POST['update_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $update_nonce, 'asraa_broker_feed_update_nonce' ) ) {
				wp_die( esc_html__( 'Error: Security verification failed. Nonce has expired.', 'asraa-crm' ), 403 );
			}

			$record_id = isset( $_POST['record_id'] ) ? absint( wp_unslash( $_POST['record_id'] ) ) : ( isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0 );
			if ( empty( $record_id ) ) {
				wp_die( esc_html__( 'Error: Missing reference primary record identifier mapping index.', 'asraa-crm' ), 400 );
			}

			// Clean and organize incoming fields.
			$approval_status = isset( $_POST['approval_status'] ) ? sanitize_key( wp_unslash( $_POST['approval_status'] ) ) : 'pending';
			if ( ! in_array( $approval_status, array( 'pending', 'approved', 'rejected' ), true ) ) {
				$approval_status = 'pending';
			}
			$is_public = isset( $_POST['is_public'] ) ? absint( wp_unslash( $_POST['is_public'] ) ) : 0;
			$is_public = ( 'approved' === $approval_status ) ? ( $is_public ? 1 : 0 ) : 0;

			$payload = array(
				'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'project_name'     => isset( $_POST['project_name'] ) ? sanitize_text_field( wp_unslash( $_POST['project_name'] ) ) : '',
				'property_type'    => isset( $_POST['property_type'] ) ? sanitize_text_field( wp_unslash( $_POST['property_type'] ) ) : '',
				'transaction_type' => isset( $_POST['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_type'] ) ) : 'sale',
				'configuration'    => isset( $_POST['configuration'] ) ? sanitize_text_field( wp_unslash( $_POST['configuration'] ) ) : '',
				'location'         => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '',
				'city'             => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
				'locality'         => isset( $_POST['locality'] ) ? sanitize_text_field( wp_unslash( $_POST['locality'] ) ) : '',
				'area'             => isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '',
				'carpet_area'      => isset( $_POST['carpet_area'] ) ? sanitize_text_field( wp_unslash( $_POST['carpet_area'] ) ) : '',
				'available_units'  => isset( $_POST['available_units'] ) ? absint( wp_unslash( $_POST['available_units'] ) ) : 1,
				'price'            => isset( $_POST['price'] ) ? floatval( wp_unslash( $_POST['price'] ) ) : 0.00,
				'status'           => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'available',
				'source_group'     => isset( $_POST['source_group'] ) ? sanitize_text_field( wp_unslash( $_POST['source_group'] ) ) : '',
				'raw_message'      => isset( $_POST['raw_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['raw_message'] ) ) : '',
				'notes'            => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
				'approval_status'  => $approval_status,
				'is_public'        => $is_public,
			);

			// Check for physical image multi-part files uploaded via the file input element.
			if ( ! empty( $_FILES['property_image']['name'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';

				// Side-load attachment parsing via Core Media handlers.
				$attachment_id = media_handle_upload( 'property_image', 0 );

				if ( ! is_wp_error( $attachment_id ) ) {
					$uploaded_url = wp_get_attachment_url( $attachment_id );
					if ( $uploaded_url ) {
						$payload['image_url'] = esc_url_raw( $uploaded_url );
					}
				} else {
					error_log( '[ASRAA CRM MEDIA ERROR] Image handler side-loading failed: ' . $attachment_id->get_error_message() );
				}
			}

			// Pass sanitized modifications through the repository layer.
			$updated = $this->repository->update( $record_id, $payload );

			if ( $updated ) {
				$this->send_safe_redirect( array( 'asraa_msg' => 'updated' ) );
			} else {
				$this->send_safe_redirect( array( 'asraa_msg' => 'update_failed' ) );
			}
		}

		/**
		 * Authenticates session data and verifies minimal real estate interaction permissions.
		 *
		 * @since  4.1.0
		 * @access private
		 * @return void Interrupts operations with a 403 page on access failure.
		 */
		private function validate_security_and_privileges(): void {
			if ( ! is_user_logged_in() ) {
				wp_die( esc_html__( 'Error: Direct access denied. Please re-authenticate your account session.', 'asraa-crm' ), 403 );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Error: Insufficient administrative roles or operational capability settings.', 'asraa-crm' ), 403 );
			}
		}

		/**
		 * Sends clean header redirects back to the dashboard referer with query variables attached.
		 *
		 * @since  4.1.0
		 * @access private
		 * @param  array $query_parameters Key-value data to inject onto the redirect string.
		 * @return void Halts code lifecycle loop execution.
		 */
		private function send_safe_redirect( array $query_parameters = array() ): void {
			$destination = admin_url( 'admin.php?page=asraa-crm-broker-feed' );

			if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
				$destination = wp_validate_redirect( sanitize_url( wp_unslash( $_REQUEST['_wp_http_referer'] ) ), $destination );
			}

			if ( ! empty( $query_parameters ) ) {
				$destination = add_query_arg( $query_parameters, $destination );
			}

			wp_safe_redirect( $destination );
			exit;
		}
	}
}

// Instantiate the architecture controller at file context end.
global $asraa_broker_feed_controller;
$asraa_broker_feed_controller = new Asraa_Broker_Feed_Controller();
