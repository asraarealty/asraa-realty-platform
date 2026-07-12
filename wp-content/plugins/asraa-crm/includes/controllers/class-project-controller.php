<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Project Controller
 *
 * Handles CRUD for projects and towers via AJAX.
 * Registers page callbacks for the Projects and Towers admin screens.
 */
class Asraa_CRM_Project_Controller {

	private $project_repo;
	private $tower_repo;

	public function __construct() {
		$this->project_repo = new Asraa_CRM_Project_Repository();
		$this->tower_repo   = new Asraa_CRM_Tower_Repository();
		$this->register_ajax_hooks();
	}

	// ── Hook registration ────────────────────────────────────────────────────

	private function register_ajax_hooks() {
		add_action( 'wp_ajax_asraa_save_project',         [ $this, 'ajax_save_project' ] );
		add_action( 'wp_ajax_asraa_delete_project',       [ $this, 'ajax_delete_project' ] );
		add_action( 'wp_ajax_asraa_save_tower',           [ $this, 'ajax_save_tower' ] );
		add_action( 'wp_ajax_asraa_delete_tower',         [ $this, 'ajax_delete_tower' ] );
		add_action( 'wp_ajax_asraa_get_towers_by_project',[ $this, 'ajax_get_towers_by_project' ] );
	}

	// ── Page renderers ───────────────────────────────────────────────────────

	public function projects_page() {
		$projects = $this->project_repo->get_all();
		include ASRAA_CRM_PATH . 'admin/pages/projects-v2.php';
	}

	public function towers_page() {
		$projects = $this->project_repo->get_active();
		$towers   = $this->tower_repo->get_all();
		include ASRAA_CRM_PATH . 'admin/pages/towers.php';
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	public function ajax_save_project() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = $this->sanitize_project( $_POST );

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( [ 'message' => 'Project name is required.' ] );
		}

		if ( $id ) {
			$this->project_repo->update( $id, $data );
			asraa_crm_debug_log( 'Asraa CRM: project updated id=' . $id );
			asraa_crm_fire_trigger( 'project_updated', [ 'id' => $id ] );
			wp_send_json_success( [ 'message' => 'Project updated successfully.' ] );
		} else {
			$new_id = $this->project_repo->create( $data );
			if ( ! $new_id ) {
				error_log( 'Asraa CRM: project create failed' );
				wp_send_json_error( [ 'message' => 'Failed to create project.' ] );
			}
			asraa_crm_debug_log( 'Asraa CRM: project created id=' . $new_id );
			asraa_crm_fire_trigger( 'project_created', [ 'id' => $new_id ] );
			wp_send_json_success( [ 'id' => $new_id, 'message' => 'Project created successfully.' ] );
		}
	}

	public function ajax_delete_project() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Invalid project ID.' ] );
		}

		$this->project_repo->delete( $id );
		asraa_crm_debug_log( 'Asraa CRM: project deleted id=' . $id );
		wp_send_json_success( [ 'message' => 'Project deleted.' ] );
	}

	public function ajax_save_tower() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = $this->sanitize_tower( $_POST );

		if ( empty( $data['name'] ) || empty( $data['project_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Tower name and project are required.' ] );
		}

		if ( $id ) {
			$this->tower_repo->update( $id, $data );
			asraa_crm_debug_log( 'Asraa CRM: tower updated id=' . $id );
			wp_send_json_success( [ 'message' => 'Tower updated successfully.' ] );
		} else {
			$new_id = $this->tower_repo->create( $data );
			if ( ! $new_id ) {
				error_log( 'Asraa CRM: tower create failed' );
				wp_send_json_error( [ 'message' => 'Failed to create tower.' ] );
			}
			asraa_crm_debug_log( 'Asraa CRM: tower created id=' . $new_id );
			wp_send_json_success( [ 'id' => $new_id, 'message' => 'Tower created successfully.' ] );
		}
	}

	public function ajax_delete_tower() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Invalid tower ID.' ] );
		}

		$this->tower_repo->delete( $id );
		asraa_crm_debug_log( 'Asraa CRM: tower deleted id=' . $id );
		wp_send_json_success( [ 'message' => 'Tower deleted.' ] );
	}

	public function ajax_get_towers_by_project() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$project_id = (int) ( $_POST['project_id'] ?? 0 );
		if ( ! $project_id ) {
			wp_send_json_success( [] );
		}
		wp_send_json_success( $this->tower_repo->get_by_project( $project_id ) );
	}

	// ── Sanitization ─────────────────────────────────────────────────────────

	private function sanitize_project( array $input ) {
		return [
			'name'         => sanitize_text_field( $input['name'] ?? '' ),
			'location'     => sanitize_text_field( $input['location'] ?? '' ),
			'builder'      => sanitize_text_field( $input['builder'] ?? '' ),
			'project_type' => sanitize_text_field( $input['project_type'] ?? '' ),
			'status'       => sanitize_text_field( $input['status'] ?? 'active' ),
		];
	}

	private function sanitize_tower( array $input ) {
		return [
			'project_id'   => (int) ( $input['project_id'] ?? 0 ),
			'name'         => sanitize_text_field( $input['name'] ?? '' ),
			'total_floors' => (int) ( $input['total_floors'] ?? 0 ),
		];
	}
}
