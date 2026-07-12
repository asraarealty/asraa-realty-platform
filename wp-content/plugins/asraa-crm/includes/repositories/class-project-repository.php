<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Project Repository
 *
 * CRUD layer for the wp_asraa_crm_projects table.
 */
class Asraa_CRM_Project_Repository {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_projects';
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return all projects, optionally filtered by status.
	 *
	 * @param string|null $status  e.g. 'active', 'inactive', or null for all.
	 * @return array[]
	 */
	public function get_all( $status = null ) {
		global $wpdb;
		if ( $status !== null ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE status = %s ORDER BY name ASC",
					$status
				),
				ARRAY_A
			) ?: [];
		}
		return $wpdb->get_results(
			"SELECT * FROM {$this->table} ORDER BY name ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return only active projects (shorthand for get_all('active')).
	 *
	 * @return array[]
	 */
	public function get_active() {
		return $this->get_all( 'active' );
	}

	/**
	 * Return a single project by ID.
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

	// ── Write ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new project.
	 *
	 * @param array $data
	 * @return int  Inserted ID.
	 */
	public function create( array $data ) {
		global $wpdb;
		$now            = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$wpdb->insert( $this->table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing project.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return int|false
	 */
	public function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( $this->table, $data, [ 'id' => (int) $id ] );
	}

	/**
	 * Delete a project.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}
}
