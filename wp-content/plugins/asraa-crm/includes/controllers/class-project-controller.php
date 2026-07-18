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
		add_action( 'wp_ajax_asraa_import_project_listing',[ $this, 'ajax_import_from_listing' ] );
	}

	// ── Page renderers ───────────────────────────────────────────────────────

	public function projects_page() {
		$projects = $this->project_repo->get_all();

		// Site listings available to import from — `property` posts tagged
		// "under-construction" (property_status) or "new-launch" (property_label),
		// the two taxonomy terms confirmed live as representing projects rather
		// than ready/individual listings.
		$site_listings = get_posts( [
			'post_type'      => 'property',
			'post_status'    => 'publish',
			'numberposts'    => 300,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => [
				'relation' => 'OR',
				[
					'taxonomy' => 'property_status',
					'field'    => 'slug',
					'terms'    => 'under-construction',
				],
				[
					'taxonomy' => 'property_label',
					'field'    => 'slug',
					'terms'    => 'new-launch',
				],
			],
		] );

		include ASRAA_CRM_PATH . 'admin/pages/projects-v2.php';
	}

	public function towers_page() {
		$projects = $this->project_repo->get_active();
		$towers   = $this->tower_repo->get_all();
		include ASRAA_CRM_PATH . 'admin/pages/towers.php';
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	/**
	 * Pre-fill data for the "Import from existing listing" dropdown.
	 * Only returns fields that map cleanly onto the CRM project form —
	 * doesn't auto-save anything, the admin still reviews and clicks Save.
	 */
	public function ajax_import_from_listing() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || $post->post_type !== 'property' || $post->post_status !== 'publish' ) {
			wp_send_json_error( [ 'message' => 'Listing not found.' ] );
		}

		$location = get_post_meta( $post_id, '_property_map_location_address', true );

		$type_terms   = get_the_terms( $post_id, 'property_type' );
		$project_type = ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) ? $type_terms[0]->name : '';

		$existing = $this->project_repo->get_by_source_post_id( $post_id );

		wp_send_json_success( [
			'post_id'          => $post_id,
			'name'             => $post->post_title,
			'location'         => $location ?: '',
			'project_type'     => $project_type,
			'already_imported' => $existing ? (int) $existing['id'] : 0,
		] );
	}

	public function ajax_save_project() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = $this->sanitize_project( $_POST );

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( [ 'message' => 'Project name is required.' ] );
		}

		if ( ! empty( $data['source_post_id'] ) ) {
			$existing = $this->project_repo->get_by_source_post_id( $data['source_post_id'] );
			if ( $existing && (int) $existing['id'] !== $id ) {
				wp_send_json_error( [ 'message' => 'This listing was already imported as Project #' . $existing['id'] . '.' ] );
			}
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
			'name'            => sanitize_text_field( $input['name'] ?? '' ),
			'location'        => sanitize_text_field( $input['location'] ?? '' ),
			'builder'         => sanitize_text_field( $input['builder'] ?? '' ),
			'project_type'    => sanitize_text_field( $input['project_type'] ?? '' ),
			'status'          => sanitize_text_field( $input['status'] ?? 'active' ),
			'source_post_id'  => intval( $input['source_post_id'] ?? 0 ) ?: null,
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
