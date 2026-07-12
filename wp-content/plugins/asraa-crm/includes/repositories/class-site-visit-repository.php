<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Site Visit Repository
 *
 * CRUD + query layer for the wp_asraa_crm_site_visits table.
 */
class Asraa_CRM_Site_Visit_Repository {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_site_visits';
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return paginated site visits with optional filters.
	 *
	 * @param array $filters {
	 *   project_id  int
	 *   agent_id    int
	 *   from        string  MySQL date (Y-m-d).
	 *   to          string  MySQL date.
	 *   per_page    int     Default 25.
	 *   paged       int     Default 1.
	 * }
	 * @return array { items: array[], total: int }
	 */
	public function get_all( $filters = [] ) {
		global $wpdb;
		$leads_table    = $wpdb->prefix . 'asraa_crm_leads';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';

		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $filters['project_id'] ) ) {
			$where[]  = 'v.project_id = %d';
			$values[] = (int) $filters['project_id'];
		}
		if ( ! empty( $filters['agent_id'] ) ) {
			$where[]  = 'v.sales_agent = %d';
			$values[] = (int) $filters['agent_id'];
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[]  = 'DATE(v.visit_date) >= %s';
			$values[] = sanitize_text_field( $filters['from'] );
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[]  = 'DATE(v.visit_date) <= %s';
			$values[] = sanitize_text_field( $filters['to'] );
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) ( $filters['per_page'] ?? 25 ) );
		$paged     = max( 1, (int) ( $filters['paged'] ?? 1 ) );
		$offset    = ( $paged - 1 ) * $per_page;

		$base_sql = "FROM {$this->table} v
		             LEFT JOIN {$leads_table} l ON l.id = v.lead_id
		             LEFT JOIN {$projects_table} p ON p.id = v.project_id
		             WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) {$base_sql}", ...$values )
			);
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT v.*, l.name AS lead_name, p.name AS project_name
					 {$base_sql}
					 ORDER BY v.visit_date DESC
					 LIMIT %d OFFSET %d",
					...array_merge( $values, [ $per_page, $offset ] )
				),
				ARRAY_A
			) ?: [];
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) {$base_sql}" );
			$items = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT v.*, l.name AS lead_name, p.name AS project_name
				 {$base_sql}
				 ORDER BY v.visit_date DESC
				 LIMIT {$per_page} OFFSET {$offset}",
				ARRAY_A
			) ?: [];
		}

		return [ 'items' => $items, 'total' => $total ];
	}

	/**
	 * Return a single site visit by ID.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		$leads_table    = $wpdb->prefix . 'asraa_crm_leads';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT v.*, l.name AS lead_name, p.name AS project_name
				 FROM {$this->table} v
				 LEFT JOIN {$leads_table} l ON l.id = v.lead_id
				 LEFT JOIN {$projects_table} p ON p.id = v.project_id
				 WHERE v.id = %d",
				(int) $id
			),
			ARRAY_A
		) ?: null;
	}

	/**
	 * Return all visits for a specific lead.
	 *
	 * @param int $lead_id
	 * @return array[]
	 */
	public function get_by_lead( $lead_id ) {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.*, p.name AS project_name
				 FROM {$this->table} v
				 LEFT JOIN {$projects_table} p ON p.id = v.project_id
				 WHERE v.lead_id = %d
				 ORDER BY v.visit_date DESC",
				(int) $lead_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return all visits for a project.
	 *
	 * @param int $project_id
	 * @return array[]
	 */
	public function get_by_project( $project_id ) {
		global $wpdb;
		$leads_table = $wpdb->prefix . 'asraa_crm_leads';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.*, l.name AS lead_name
				 FROM {$this->table} v
				 LEFT JOIN {$leads_table} l ON l.id = v.lead_id
				 WHERE v.project_id = %d
				 ORDER BY v.visit_date DESC",
				(int) $project_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return the most recent visits across all leads.
	 *
	 * @param int $limit
	 * @return array[]
	 */
	public function get_recent_visits( $limit = 20 ) {
		global $wpdb;
		$leads_table    = $wpdb->prefix . 'asraa_crm_leads';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.*, l.name AS lead_name, p.name AS project_name
				 FROM {$this->table} v
				 LEFT JOIN {$leads_table} l ON l.id = v.lead_id
				 LEFT JOIN {$projects_table} p ON p.id = v.project_id
				 ORDER BY v.visit_date DESC
				 LIMIT %d",
				(int) $limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return visits scheduled for today.
	 *
	 * @return array[]
	 */
	public function get_today_visits() {
		global $wpdb;
		$leads_table    = $wpdb->prefix . 'asraa_crm_leads';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			"SELECT v.*, l.name AS lead_name, p.name AS project_name
			 FROM {$this->table} v
			 LEFT JOIN {$leads_table} l ON l.id = v.lead_id
			 LEFT JOIN {$projects_table} p ON p.id = v.project_id
			 WHERE DATE(v.visit_date) = CURDATE()
			 ORDER BY v.visit_date ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Count visits scheduled for today.
	 *
	 * @return int
	 */
	public function count_today_visits() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE DATE(visit_date) = CURDATE()"
		);
	}

	/**
	 * Return leads whose last site visit was between $min_days and $max_days ago,
	 * with no pending follow-up scheduled (revisit candidates).
	 *
	 * @param int $min_days
	 * @param int $max_days
	 * @return array[]
	 */
	public function get_revisit_candidates( $min_days = 7, $max_days = 30 ) {
		global $wpdb;
		$leads_table     = $wpdb->prefix . 'asraa_crm_leads';
		$followups_table = $wpdb->prefix . 'asraa_crm_followups';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.id AS lead_id, l.name AS lead_name, l.phone,
				        MAX(v.visit_date) AS last_visit
				 FROM {$this->table} v
				 INNER JOIN {$leads_table} l ON l.id = v.lead_id
				 LEFT JOIN {$followups_table} f ON f.lead_id = v.lead_id
				   AND f.is_done = 0 AND f.follow_date >= CURDATE()
				 WHERE l.is_deleted = 0
				   AND f.id IS NULL
				 GROUP BY v.lead_id
				 HAVING last_visit >= DATE_SUB(NOW(), INTERVAL %d DAY)
				    AND last_visit <= DATE_SUB(NOW(), INTERVAL %d DAY)
				 ORDER BY last_visit ASC
				 LIMIT 20",
				(int) $max_days,
				(int) $min_days
			),
			ARRAY_A
		) ?: [];
	}

	// ── Write ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new site visit.
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
	 * Update a site visit.
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
	 * Delete a site visit.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}
}
