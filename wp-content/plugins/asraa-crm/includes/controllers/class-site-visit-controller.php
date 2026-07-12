<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Site Visit Controller
 *
 * Handles CRUD for site visits via AJAX and renders the admin page.
 */
class Asraa_CRM_Site_Visit_Controller {

	private $visit_repo;
	private $project_repo;

	public function __construct() {
		$this->visit_repo   = new Asraa_CRM_Site_Visit_Repository();
		$this->project_repo = new Asraa_CRM_Project_Repository();
		$this->register_ajax_hooks();
	}

	// ── Hook registration ────────────────────────────────────────────────────

	private function register_ajax_hooks() {
		add_action( 'wp_ajax_asraa_save_site_visit',   [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_asraa_delete_site_visit', [ $this, 'ajax_delete' ] );
	}

	// ── Page renderer ────────────────────────────────────────────────────────

	public function site_visits_page() {
		$projects = $this->project_repo->get_active();

		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$filters = [
			'project_id' => (int) ( $_GET['project_id'] ?? 0 ),
			'agent_id'   => (int) ( $_GET['agent_id'] ?? 0 ),
			'from'       => sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ),
			'to'         => sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ),
			'per_page'   => 25,
			'paged'      => $paged,
		];

		$result = $this->visit_repo->get_all( $filters );
		include ASRAA_CRM_PATH . 'admin/pages/site-visits.php';
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	public function ajax_save() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = $this->sanitize_visit( $_POST );

		if ( empty( $data['lead_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Lead is required.' ] );
		}

		if ( $id ) {
			$this->visit_repo->update( $id, $data );
			asraa_crm_debug_log( 'Asraa CRM: site visit updated id=' . $id );

			if ( ( $data['visit_outcome'] ?? '' ) === 'completed' ) {
				$this->fire_visit_completed_events( $id, $data );
			}

			wp_send_json_success( [ 'message' => 'Site visit updated.' ] );
		} else {
			$new_id = $this->visit_repo->create( $data );
			if ( ! $new_id ) {
				error_log( 'Asraa CRM: site visit create failed' );
				wp_send_json_error( [ 'message' => 'Failed to save site visit.' ] );
			}
			asraa_crm_debug_log( 'Asraa CRM: site visit created id=' . $new_id );

			// Log activity on the lead timeline.
			$activity_repo = new Asraa_CRM_Lead_Activity_Repository();
			$activity_repo->log_activity(
				$data['lead_id'],
				'visit_scheduled',
				sprintf( 'Site visit scheduled on %s', asraa_crm_format_date( $data['visit_date'] ) )
			);

			asraa_crm_fire_trigger( 'site_visit_scheduled', [
				'visit_id' => $new_id,
				'data'     => $data,
			] );

			if ( ( $data['visit_outcome'] ?? '' ) === 'completed' ) {
				$this->fire_visit_completed_events( $new_id, $data );
			}

			wp_send_json_success( [ 'id' => $new_id, 'message' => 'Site visit saved.' ] );
		}
	}

	public function ajax_delete() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Invalid visit ID.' ] );
		}

		$this->visit_repo->delete( $id );
		asraa_crm_debug_log( 'Asraa CRM: site visit deleted id=' . $id );
		wp_send_json_success( [ 'message' => 'Site visit deleted.' ] );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Fire visit-completed triggers and log lead activity.
	 *
	 * @param int   $visit_id
	 * @param array $data  Sanitized visit data.
	 */
	private function fire_visit_completed_events( $visit_id, array $data ) {
		$activity_repo = new Asraa_CRM_Lead_Activity_Repository();
		$activity_repo->log_activity(
			$data['lead_id'],
			'visit_completed',
			sprintf( 'Visit completed — Outcome: %s', $data['visit_outcome'] ?? '' )
		);

		asraa_crm_fire_trigger( 'site_visit_completed', [
			'visit_id' => $visit_id,
			'data'     => $data,
		] );
	}

	// ── Sanitization ─────────────────────────────────────────────────────────

	private function sanitize_visit( array $input ) {
		return [
			'lead_id'       => (int) ( $input['lead_id'] ?? 0 ),
			'project_id'    => (int) ( $input['project_id'] ?? 0 ),
			'unit_id'       => (int) ( $input['unit_id'] ?? 0 ) ?: null,
			'visit_date'    => sanitize_text_field( $input['visit_date'] ?? '' ),
			'sales_agent'   => (int) ( $input['sales_agent'] ?? get_current_user_id() ),
			'feedback'      => sanitize_textarea_field( $input['feedback'] ?? '' ),
			'visit_outcome' => sanitize_text_field( $input['visit_outcome'] ?? '' ),
		];
	}
}
