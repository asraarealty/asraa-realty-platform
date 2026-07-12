<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Lead Activity Repository
 *
 * CRUD layer for the wp_asraa_crm_lead_activity table.
 * Stores inventory-specific lead timeline events such as
 * unit_shortlisted, visit_scheduled, booking_confirmed, etc.
 */
class Asraa_CRM_Lead_Activity_Repository {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_lead_activity';
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return activities for a lead, newest first.
	 *
	 * @param int $lead_id
	 * @param int $limit   0 = no limit.
	 * @return array[]
	 */
	public function get_by_lead( $lead_id, $limit = 50 ) {
		global $wpdb;
		$lead_id = (int) $lead_id;
		$limit   = (int) $limit;
		if ( $limit > 0 ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.*, u.display_name AS created_by_name
					 FROM {$this->table} a
					 LEFT JOIN {$wpdb->users} u ON u.ID = a.created_by
					 WHERE a.lead_id = %d
					 ORDER BY a.created_at DESC
					 LIMIT %d",
					$lead_id,
					$limit
				),
				ARRAY_A
			) ?: [];
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name AS created_by_name
				 FROM {$this->table} a
				 LEFT JOIN {$wpdb->users} u ON u.ID = a.created_by
				 WHERE a.lead_id = %d
				 ORDER BY a.created_at DESC",
				$lead_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return the full lead timeline (alias for get_by_lead with high limit).
	 *
	 * @param int $lead_id
	 * @return array[]
	 */
	public function get_lead_timeline( $lead_id ) {
		return $this->get_by_lead( $lead_id, 100 );
	}

	/**
	 * Return a single activity by ID.
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
	 * Log an activity entry for a lead.
	 *
	 * @param int         $lead_id
	 * @param string      $activity_type  e.g. 'unit_shortlisted', 'visit_completed'
	 * @param string      $description    Human-readable detail.
	 * @param int|null    $created_by     WP user ID; defaults to current user.
	 * @return int|false  Inserted ID or false on failure.
	 */
	public function log_activity( $lead_id, $activity_type, $description = '', $created_by = null ) {
		global $wpdb;
		if ( $created_by === null ) {
			$created_by = get_current_user_id();
		}
		$inserted = $wpdb->insert( $this->table, [
			'lead_id'       => (int) $lead_id,
			'activity_type' => sanitize_text_field( $activity_type ),
			'description'   => sanitize_textarea_field( $description ),
			'created_by'    => (int) $created_by,
			'created_at'    => current_time( 'mysql' ),
		] );
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Alias for log_activity to match the generic repository interface.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public function create( array $data ) {
		return $this->log_activity(
			$data['lead_id']       ?? 0,
			$data['activity_type'] ?? 'system',
			$data['description']   ?? '',
			$data['created_by']    ?? null
		);
	}

	/**
	 * Delete an activity entry.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}
}
