<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Tower Repository
 *
 * CRUD layer for the wp_asraa_crm_towers table.
 */
class Asraa_CRM_Tower_Repository {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_towers';
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return all towers with their project name.
	 *
	 * @return array[]
	 */
	public function get_all() {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			"SELECT t.*, p.name AS project_name
			 FROM {$this->table} t
			 LEFT JOIN {$projects_table} p ON p.id = t.project_id
			 ORDER BY p.name ASC, t.name ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return a single tower by ID.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				(int) $id
			),
			ARRAY_A
		) ?: null;
	}

	/**
	 * Return towers belonging to a project.
	 *
	 * @param int $project_id
	 * @return array[]
	 */
	public function get_by_project( $project_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE project_id = %d ORDER BY name ASC",
				(int) $project_id
			),
			ARRAY_A
		) ?: [];
	}

	// ── Write ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new tower.
	 *
	 * @param array $data
	 * @return int  Inserted ID.
	 */
	public function create( array $data ) {
		global $wpdb;
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $this->table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing tower.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return int|false
	 */
	public function update( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( $this->table, $data, [ 'id' => (int) $id ] );
	}

	/**
	 * Delete a tower.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}
}
