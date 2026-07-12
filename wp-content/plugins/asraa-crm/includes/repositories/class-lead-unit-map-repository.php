<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Lead Unit Map Repository
 *
 * CRUD layer for the wp_asraa_crm_lead_unit_map table.
 * Tracks which leads are interested in which units/projects,
 * and records interest level, visit status, and booking stage.
 */
class Asraa_CRM_Lead_Unit_Map_Repository {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_lead_unit_map';
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return a single map entry by ID.
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
	 * Return all units shortlisted for a lead, with unit/tower/project details.
	 *
	 * @param int $lead_id
	 * @return array[]
	 */
	public function get_by_lead( $lead_id ) {
		global $wpdb;
		$units_table    = $wpdb->prefix . 'asraa_crm_units';
		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*,
				        u.unit_no, u.floor_no, u.configuration, u.area_sqft,
				        u.price, u.status AS unit_status, u.view_type,
				        t.name AS tower_name,
				        p.name AS project_name
				 FROM {$this->table} m
				 LEFT JOIN {$units_table} u ON u.id = m.unit_id
				 LEFT JOIN {$towers_table} t ON t.id = m.tower_id
				 LEFT JOIN {$projects_table} p ON p.id = m.project_id
				 WHERE m.lead_id = %d
				 ORDER BY m.created_at DESC",
				(int) $lead_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return all leads interested in a specific unit.
	 *
	 * @param int $unit_id
	 * @return array[]
	 */
	public function get_by_unit( $unit_id ) {
		global $wpdb;
		$leads_table = $wpdb->prefix . 'asraa_crm_leads';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, l.name AS lead_name, l.phone, l.email
				 FROM {$this->table} m
				 LEFT JOIN {$leads_table} l ON l.id = m.lead_id
				 WHERE m.unit_id = %d
				 ORDER BY m.created_at DESC",
				(int) $unit_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Find the most recent map entry for a lead+unit pair.
	 *
	 * @param int $lead_id
	 * @param int $unit_id
	 * @return array|null
	 */
	public function get_by_lead_and_unit( $lead_id, $unit_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE lead_id = %d AND unit_id = %d
				 ORDER BY id DESC LIMIT 1",
				(int) $lead_id,
				(int) $unit_id
			),
			ARRAY_A
		) ?: null;
	}

	/**
	 * Return all leads that showed interest in units of the same configuration.
	 * Used for reopening leads after a cancellation.
	 *
	 * @param string $configuration  e.g. '2BHK'
	 * @param int    $exclude_lead   Lead to exclude (the cancelling lead).
	 * @return array[]
	 */
	public function get_leads_by_configuration( $configuration, $exclude_lead = 0 ) {
		global $wpdb;
		$units_table = $wpdb->prefix . 'asraa_crm_units';
		$leads_table = $wpdb->prefix . 'asraa_crm_leads';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT m.lead_id, l.name AS lead_name, l.phone, l.email
				 FROM {$this->table} m
				 INNER JOIN {$units_table} u ON u.id = m.unit_id
				 INNER JOIN {$leads_table} l ON l.id = m.lead_id
				 WHERE u.configuration = %s
				   AND m.lead_id != %d
				   AND l.is_deleted = 0
				 ORDER BY m.created_at DESC
				 LIMIT 50",
				sanitize_text_field( $configuration ),
				(int) $exclude_lead
			),
			ARRAY_A
		) ?: [];
	}

	// ── Write ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new map entry.
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
	 * Update a map entry.
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
	 * Delete a map entry.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}

	/**
	 * Shortlist a unit for a lead (upsert: create if not already mapped).
	 *
	 * @param int    $lead_id
	 * @param int    $unit_id
	 * @param int    $project_id
	 * @param int    $tower_id
	 * @param string $interest_level
	 * @return int  Map entry ID.
	 */
	public function shortlist( $lead_id, $unit_id, $project_id = 0, $tower_id = 0, $interest_level = 'interested' ) {
		$existing = $this->get_by_lead_and_unit( $lead_id, $unit_id );
		if ( $existing ) {
			return (int) $existing['id'];
		}
		return $this->create( [
			'lead_id'        => (int) $lead_id,
			'unit_id'        => (int) $unit_id,
			'project_id'     => (int) $project_id,
			'tower_id'       => (int) $tower_id,
			'interest_level' => sanitize_text_field( $interest_level ),
			'visit_status'   => 'pending',
		] );
	}

	/**
	 * Update the booking stage for a lead+unit pair.
	 *
	 * @param int    $lead_id
	 * @param int    $unit_id
	 * @param string $interest_level
	 * @param string $visit_status
	 * @return bool
	 */
	public function update_booking_stage( $lead_id, $unit_id, $interest_level, $visit_status = '' ) {
		global $wpdb;
		$existing = $this->get_by_lead_and_unit( $lead_id, $unit_id );
		if ( ! $existing ) {
			return false;
		}
		$data = [ 'interest_level' => sanitize_text_field( $interest_level ) ];
		if ( $visit_status ) {
			$data['visit_status'] = sanitize_text_field( $visit_status );
		}
		return $wpdb->update( $this->table, $data, [ 'id' => (int) $existing['id'] ] ) !== false;
	}
}
