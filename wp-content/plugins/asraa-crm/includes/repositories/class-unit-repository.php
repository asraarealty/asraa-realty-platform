<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Unit Repository
 *
 * CRUD + query layer for the wp_asraa_crm_units table.
 * Optimised for 10k+ inventory records.
 */
class Asraa_CRM_Unit_Repository {

	private $table;

	/** Allowed unit status values. */
	const ALLOWED_STATUSES = [
		'available',
		'blocked',
		'negotiation',
		'token',
		'booked',
		'sold',
		'cancelled',
	];

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'asraa_crm_units';
	}

	// ── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return paginated units with optional filters.
	 *
	 * @param array $filters {
	 *   project_id int     Filter by project.
	 *   tower_id   int     Filter by tower.
	 *   floor_no   int     Filter by floor.
	 *   status     string  Filter by status.
	 *   search     string  Search unit_no (LIKE).
	 *   per_page   int     Default 25.
	 *   paged      int     Default 1.
	 * }
	 * @return array { items: array[], total: int }
	 */
	public function get_all( $filters = [] ) {
		global $wpdb;

		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';

		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $filters['project_id'] ) ) {
			$where[]  = 'u.project_id = %d';
			$values[] = (int) $filters['project_id'];
		}
		if ( ! empty( $filters['tower_id'] ) ) {
			$where[]  = 'u.tower_id = %d';
			$values[] = (int) $filters['tower_id'];
		}
		if ( isset( $filters['floor_no'] ) && $filters['floor_no'] !== '' ) {
			$where[]  = 'u.floor_no = %d';
			$values[] = (int) $filters['floor_no'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'u.status = %s';
			$values[] = sanitize_text_field( $filters['status'] );
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[]  = 'u.unit_no LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) ( $filters['per_page'] ?? 25 ) );
		$paged     = max( 1, (int) ( $filters['paged'] ?? 1 ) );
		$offset    = ( $paged - 1 ) * $per_page;

		$base_sql = "FROM {$this->table} u
		             LEFT JOIN {$towers_table} t ON t.id = u.tower_id
		             LEFT JOIN {$projects_table} p ON p.id = u.project_id
		             WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) {$base_sql}", ...$values )
			);
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.*, t.name AS tower_name, p.name AS project_name
					 {$base_sql}
					 ORDER BY u.tower_id ASC, u.floor_no ASC, u.unit_no ASC
					 LIMIT %d OFFSET %d",
					...array_merge( $values, [ $per_page, $offset ] )
				),
				ARRAY_A
			) ?: [];
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) {$base_sql}" );
			$items = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT u.*, t.name AS tower_name, p.name AS project_name
				 {$base_sql}
				 ORDER BY u.tower_id ASC, u.floor_no ASC, u.unit_no ASC
				 LIMIT {$per_page} OFFSET {$offset}",
				ARRAY_A
			) ?: [];
		}

		return [ 'items' => $items, 'total' => $total ];
	}

	/**
	 * Return a single unit with tower and project names.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT u.*, t.name AS tower_name, p.name AS project_name
				 FROM {$this->table} u
				 LEFT JOIN {$towers_table} t ON t.id = u.tower_id
				 LEFT JOIN {$projects_table} p ON p.id = u.project_id
				 WHERE u.id = %d",
				(int) $id
			),
			ARRAY_A
		) ?: null;
	}

	/**
	 * Return all units for a project.
	 *
	 * @param int $project_id
	 * @return array[]
	 */
	public function get_by_project( $project_id ) {
		global $wpdb;
		$towers_table = $wpdb->prefix . 'asraa_crm_towers';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.*, t.name AS tower_name
				 FROM {$this->table} u
				 LEFT JOIN {$towers_table} t ON t.id = u.tower_id
				 WHERE u.project_id = %d
				 ORDER BY u.tower_id ASC, u.floor_no ASC",
				(int) $project_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Return available units, optionally scoped to a project and/or configuration.
	 *
	 * @param int|null    $project_id
	 * @param string|null $config  e.g. '2BHK'
	 * @return array[]
	 */
	public function get_available_units( $project_id = null, $config = null ) {
		global $wpdb;
		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';

		$where  = [ "u.status = 'available'" ];
		$values = [];

		if ( $project_id ) {
			$where[]  = 'u.project_id = %d';
			$values[] = (int) $project_id;
		}
		if ( $config ) {
			$where[]  = 'u.configuration = %s';
			$values[] = sanitize_text_field( $config );
		}

		$where_sql = implode( ' AND ', $where );
		$base_sql  = "SELECT u.*, t.name AS tower_name, p.name AS project_name
		              FROM {$this->table} u
		              LEFT JOIN {$towers_table} t ON t.id = u.tower_id
		              LEFT JOIN {$projects_table} p ON p.id = u.project_id
		              WHERE {$where_sql}
		              ORDER BY u.price ASC";

		if ( ! empty( $values ) ) {
			return $wpdb->get_results(
				$wpdb->prepare( $base_sql, ...$values ),
				ARRAY_A
			) ?: [];
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $base_sql, ARRAY_A ) ?: [];
	}

	/**
	 * Return units filtered by status.
	 *
	 * @param string   $status
	 * @param int|null $project_id
	 * @return array[]
	 */
	public function get_units_by_status( $status, $project_id = null ) {
		global $wpdb;
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return [];
		}
		$towers_table = $wpdb->prefix . 'asraa_crm_towers';
		if ( $project_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.*, t.name AS tower_name
					 FROM {$this->table} u
					 LEFT JOIN {$towers_table} t ON t.id = u.tower_id
					 WHERE u.status = %s AND u.project_id = %d
					 ORDER BY u.tower_id ASC, u.floor_no ASC",
					$status,
					(int) $project_id
				),
				ARRAY_A
			) ?: [];
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY tower_id ASC, floor_no ASC",
				$status
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Hot inventory: units shortlisted by multiple leads within recent days.
	 *
	 * @param int $min_leads  Minimum interest count.
	 * @param int $days       Look-back window.
	 * @return array[]
	 */
	public function get_hot_inventory( $min_leads = 3, $days = 14 ) {
		global $wpdb;
		$map_table      = $wpdb->prefix . 'asraa_crm_lead_unit_map';
		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.*, t.name AS tower_name, p.name AS project_name,
				        COUNT(m.id) AS lead_count
				 FROM {$this->table} u
				 INNER JOIN {$map_table} m ON m.unit_id = u.id
				 LEFT JOIN {$towers_table} t ON t.id = u.tower_id
				 LEFT JOIN {$projects_table} p ON p.id = u.project_id
				 WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 GROUP BY u.id
				 HAVING COUNT(m.id) >= %d
				 ORDER BY lead_count DESC",
				(int) $days,
				(int) $min_leads
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Dead inventory: available units with no lead interest in $days days.
	 *
	 * @param int $days
	 * @return array[]
	 */
	public function get_dead_inventory( $days = 30 ) {
		global $wpdb;
		$map_table      = $wpdb->prefix . 'asraa_crm_lead_unit_map';
		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.*, t.name AS tower_name, p.name AS project_name
				 FROM {$this->table} u
				 LEFT JOIN {$towers_table} t ON t.id = u.tower_id
				 LEFT JOIN {$projects_table} p ON p.id = u.project_id
				 LEFT JOIN {$map_table} m ON m.unit_id = u.id
				   AND m.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 WHERE u.status = 'available'
				   AND m.id IS NULL
				 ORDER BY u.created_at ASC",
				(int) $days
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Count units grouped by status, optionally scoped to a project.
	 *
	 * @param int|null $project_id
	 * @return array[]  Each row: { status, cnt }
	 */
	public function count_by_status( $project_id = null ) {
		global $wpdb;
		if ( $project_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT status, COUNT(*) AS cnt FROM {$this->table}
					 WHERE project_id = %d GROUP BY status",
					(int) $project_id
				),
				ARRAY_A
			) ?: [];
		}
		return $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Count available units per project (for dashboard widget).
	 *
	 * @return array[]  Each row: { project_id, project_name, cnt }
	 */
	public function count_available_by_project() {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			"SELECT u.project_id, p.name AS project_name, COUNT(u.id) AS cnt
			 FROM {$this->table} u
			 LEFT JOIN {$projects_table} p ON p.id = u.project_id
			 WHERE u.status = 'available'
			 GROUP BY u.project_id
			 ORDER BY p.name ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Tower-wise inventory breakdown for heatmap rendering.
	 *
	 * @return array[]  Each row: { tower_id, tower_name, project_name, status, cnt }
	 */
	public function get_tower_heatmap() {
		global $wpdb;
		$towers_table   = $wpdb->prefix . 'asraa_crm_towers';
		$projects_table = $wpdb->prefix . 'asraa_crm_projects';
		return $wpdb->get_results(
			"SELECT u.tower_id, t.name AS tower_name, p.name AS project_name,
			        u.status, COUNT(u.id) AS cnt
			 FROM {$this->table} u
			 LEFT JOIN {$towers_table} t ON t.id = u.tower_id
			 LEFT JOIN {$projects_table} p ON p.id = u.project_id
			 GROUP BY u.tower_id, u.status
			 ORDER BY p.name ASC, t.name ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Count units marked as sold within the last 7 days.
	 *
	 * @return int
	 */
	public function count_sold_this_week() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE status = 'sold'
			   AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);
	}

	// ── Write ────────────────────────────────────────────────────────────────

	/**
	 * Insert a new unit.
	 *
	 * @param array $data
	 * @return int  Inserted ID.
	 */
	public function create( array $data ) {
		global $wpdb;
		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$wpdb->insert( $this->table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing unit.
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
	 * Delete a unit.
	 *
	 * @param int $id
	 * @return int|false
	 */
	public function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => (int) $id ] );
	}

	/**
	 * Bulk-update status for multiple units in one query.
	 *
	 * @param int[]  $ids
	 * @param string $status
	 * @return int|false  Number of rows updated.
	 */
	public function bulk_update_status( array $ids, $status ) {
		global $wpdb;
		if ( empty( $ids ) || ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return false;
		}
		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
				...array_merge( [ $status, current_time( 'mysql' ) ], $ids )
			)
		);
	}
}
