<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Activity Repository
 *
 * CRUD layer for the wp_asraa_crm_activities table.
 * Activities represent the client / lead timeline:
 * calls, meetings, notes, WhatsApp messages, emails, stage changes, etc.
 */
class Asraa_CRM_Activity_Repository {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_activities';
	}

	// ── read ─────────────────────────────────────────────────────────────────

	/**
	 * Return all activities for a lead, most recent first.
	 *
	 * @param int   $lead_id
	 * @param int   $limit   0 = no limit.
	 * @return array[]
	 */
	public function get_for_lead( $lead_id, $limit = 50 ) {
		global $wpdb;
		$lead_id = (int) $lead_id;
		$limit   = (int) $limit;

		if ( $limit > 0 ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE lead_id = %d ORDER BY created_at DESC LIMIT %d",
					$lead_id,
					$limit
				),
				ARRAY_A
			) ?: [];
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE lead_id = %d ORDER BY created_at DESC",
				$lead_id
			),
			ARRAY_A
		) ?: [];
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

	/**
	 * Return recent activities across ALL leads (admin timeline feed).
	 *
	 * @param int $limit
	 * @return array[]
	 */
	public function get_recent( $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, l.name AS lead_name
				 FROM {$this->table} a
				 LEFT JOIN {$wpdb->prefix}asraa_crm_leads l ON l.id = a.lead_id
				 ORDER BY a.created_at DESC
				 LIMIT %d",
				(int) $limit
			),
			ARRAY_A
		) ?: [];
	}

	// ── write ─────────────────────────────────────────────────────────────────

	/**
	 * Insert a new activity and return its ID.
	 *
	 * @param array $data {
	 *   lead_id    int     Required.
	 *   type       string  'call'|'meeting'|'note'|'email'|'whatsapp'|'stage_change'|'system'
	 *   subject    string  Short label.
	 *   body       string  Detail / transcript.
	 *   user_id    int     WP user who logged the activity.
	 *   created_at string  MySQL datetime; defaults to now.
	 * }
	 * @return int|false  Inserted ID, or false on failure.
	 */
	public function create( array $data ) {
		global $wpdb;

		$data = wp_parse_args( $data, [
			'lead_id'    => 0,
			'type'       => 'note',
			'subject'    => '',
			'body'       => '',
			'user_id'    => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		] );

		$inserted = $wpdb->insert( $this->table, [
			'lead_id'    => (int) $data['lead_id'],
			'type'       => sanitize_text_field( $data['type'] ),
			'subject'    => sanitize_text_field( $data['subject'] ),
			'body'       => sanitize_textarea_field( $data['body'] ),
			'user_id'    => (int) $data['user_id'],
			'created_at' => $data['created_at'],
		] );

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing activity.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return int|false
	 */
	public function update( $id, array $data ) {
		global $wpdb;
		$allowed = [ 'type', 'subject', 'body' ];
		$update  = [];
		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}
		if ( empty( $update ) ) return false;
		return $wpdb->update( $this->table, $update, [ 'id' => (int) $id ] );
	}

	/**
	 * Delete an activity.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}
}
