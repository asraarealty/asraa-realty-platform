<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Asraa CRM – Inventory Controller
 *
 * Handles CRUD for inventory units, AJAX-powered search/filter,
 * bulk status updates, lead unit shortlisting, and report tab loading.
 */
class Asraa_CRM_Inventory_Controller {

	private $unit_repo;
	private $project_repo;
	private $tower_repo;

	public function __construct() {
		$this->unit_repo    = new Asraa_CRM_Unit_Repository();
		$this->project_repo = new Asraa_CRM_Project_Repository();
		$this->tower_repo   = new Asraa_CRM_Tower_Repository();
		$this->register_ajax_hooks();
	}

	// ── Hook registration ────────────────────────────────────────────────────

	private function register_ajax_hooks() {
		add_action( 'wp_ajax_asraa_inventory_search',        [ $this, 'ajax_search' ] );
		add_action( 'wp_ajax_asraa_save_unit',               [ $this, 'ajax_save_unit' ] );
		add_action( 'wp_ajax_asraa_delete_unit',             [ $this, 'ajax_delete_unit' ] );
		add_action( 'wp_ajax_asraa_bulk_update_unit_status', [ $this, 'ajax_bulk_update_status' ] );
		add_action( 'wp_ajax_asraa_load_report_tab',         [ $this, 'ajax_load_report_tab' ] );
		add_action( 'wp_ajax_asraa_shortlist_unit',          [ $this, 'ajax_shortlist_unit' ] );
	}

	// ── Page renderers ───────────────────────────────────────────────────────

	public function inventory_page() {
		$projects = $this->project_repo->get_active();

		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$filters = [
			'project_id' => (int) ( $_GET['project_id'] ?? 0 ),
			'tower_id'   => (int) ( $_GET['tower_id'] ?? 0 ),
			'floor_no'   => isset( $_GET['floor_no'] ) ? sanitize_text_field( wp_unslash( $_GET['floor_no'] ) ) : '',
			'status'     => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
			'search'     => sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ),
			'per_page'   => 25,
			'paged'      => $paged,
		];

		$result = $this->unit_repo->get_all( $filters );
		$towers = $filters['project_id']
			? $this->tower_repo->get_by_project( $filters['project_id'] )
			: [];

		include ASRAA_CRM_PATH . 'admin/pages/inventory.php';
	}

	public function reports_page() {
		$projects = $this->project_repo->get_active();
		include ASRAA_CRM_PATH . 'admin/pages/inventory-reports.php';
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	public function ajax_search() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$filters = [
			'project_id' => (int) ( $_POST['project_id'] ?? 0 ),
			'tower_id'   => (int) ( $_POST['tower_id'] ?? 0 ),
			'floor_no'   => sanitize_text_field( wp_unslash( $_POST['floor_no'] ?? '' ) ),
			'status'     => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
			'search'     => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
			'per_page'   => (int) ( $_POST['per_page'] ?? 25 ),
			'paged'      => (int) ( $_POST['paged'] ?? 1 ),
		];

		wp_send_json_success( $this->unit_repo->get_all( $filters ) );
	}

	public function ajax_save_unit() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = $this->sanitize_unit( $_POST );

		if ( empty( $data['project_id'] ) || empty( $data['tower_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Project and Tower are required.' ] );
		}

		if ( $id ) {
			$this->unit_repo->update( $id, $data );
			asraa_crm_debug_log( 'Asraa CRM: unit updated id=' . $id );
			asraa_crm_fire_trigger( 'unit_updated', [ 'id' => $id, 'data' => $data ] );
			wp_send_json_success( [ 'message' => 'Unit updated successfully.' ] );
		} else {
			$new_id = $this->unit_repo->create( $data );
			if ( ! $new_id ) {
				error_log( 'Asraa CRM: unit create failed' );
				wp_send_json_error( [ 'message' => 'Failed to create unit.' ] );
			}
			asraa_crm_debug_log( 'Asraa CRM: unit created id=' . $new_id );
			asraa_crm_fire_trigger( 'unit_created', [ 'id' => $new_id, 'data' => $data ] );
			wp_send_json_success( [ 'id' => $new_id, 'message' => 'Unit created successfully.' ] );
		}
	}

	public function ajax_delete_unit() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Invalid unit ID.' ] );
		}

		$this->unit_repo->delete( $id );
		asraa_crm_debug_log( 'Asraa CRM: unit deleted id=' . $id );
		wp_send_json_success( [ 'message' => 'Unit deleted.' ] );
	}

	public function ajax_bulk_update_status() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$ids    = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		if ( empty( $ids ) || ! in_array( $status, Asraa_CRM_Unit_Repository::ALLOWED_STATUSES, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid request. Check IDs and status value.' ] );
		}

		$updated = $this->unit_repo->bulk_update_status( $ids, $status );
		asraa_crm_debug_log( 'Asraa CRM: bulk unit status update ' . count( $ids ) . ' units → ' . $status );
		wp_send_json_success( [ 'updated' => $updated, 'message' => $updated . ' unit(s) updated.' ] );
	}

	/**
	 * Lazy-load a single report tab fragment.
	 * Tab keys: by_project | heatmap | sold_week | dead_inventory
	 */
	public function ajax_load_report_tab() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$tab = sanitize_text_field( wp_unslash( $_POST['tab'] ?? '' ) );

		ob_start();

		switch ( $tab ) {
			case 'by_project':
				$rows = $this->unit_repo->count_available_by_project();
				?>
				<table class="widefat striped">
					<thead><tr><th>Project</th><th>Available Units</th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['project_name'] ); ?></td>
							<td><strong><?php echo esc_html( $row['cnt'] ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="2"><em>No data.</em></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
				<?php
				break;

			case 'heatmap':
				$rows = $this->unit_repo->get_tower_heatmap();
				$towers = [];
				foreach ( $rows as $row ) {
					$key = $row['project_name'] . ' › ' . $row['tower_name'];
					$towers[ $key ][ $row['status'] ] = (int) $row['cnt'];
				}
				$statuses = Asraa_CRM_Unit_Repository::ALLOWED_STATUSES;
				$colors   = [
					'available'   => '#16a34a',
					'blocked'     => '#ca8a04',
					'negotiation' => '#2563eb',
					'token'       => '#7c3aed',
					'booked'      => '#0891b2',
					'sold'        => '#dc2626',
					'cancelled'   => '#6b7280',
				];
				?>
				<div style="overflow-x:auto;">
				<table class="widefat striped asraa-heatmap-table">
					<thead>
					<tr>
						<th>Tower</th>
						<?php foreach ( $statuses as $s ) : ?>
							<th style="text-align:center;background:<?php echo esc_attr( $colors[ $s ] ?? '#ddd' ); ?>;color:#fff;">
								<?php echo esc_html( ucfirst( $s ) ); ?>
							</th>
						<?php endforeach; ?>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $towers as $tower_label => $status_counts ) : ?>
						<tr>
							<td><?php echo esc_html( $tower_label ); ?></td>
							<?php foreach ( $statuses as $s ) : ?>
								<td style="text-align:center;font-weight:600;">
									<?php echo esc_html( $status_counts[ $s ] ?? 0 ); ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $towers ) ) : ?>
						<tr><td colspan="<?php echo esc_attr( count( $statuses ) + 1 ); ?>"><em>No data.</em></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
				</div>
				<?php
				break;

			case 'sold_week':
				$rows = $this->unit_repo->get_units_by_status( 'sold' );
				?>
				<table class="widefat striped">
					<thead><tr><th>Unit</th><th>Project</th><th>Tower</th><th>Config</th><th>Price</th><th>Sold At</th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['unit_no'] ); ?></td>
							<td><?php echo esc_html( $r['project_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $r['tower_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $r['configuration'] ); ?></td>
							<td><?php echo esc_html( asraa_crm_format_currency( $r['price'] ) ); ?></td>
							<td><?php echo esc_html( asraa_crm_format_date( $r['updated_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><em>No units sold yet.</em></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
				<?php
				break;

			case 'dead_inventory':
				$rows = $this->unit_repo->get_dead_inventory( 30 );
				?>
				<table class="widefat striped">
					<thead><tr><th>Unit</th><th>Project</th><th>Tower</th><th>Config</th><th>Price</th><th>Listed Since</th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['unit_no'] ); ?></td>
							<td><?php echo esc_html( $r['project_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $r['tower_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $r['configuration'] ); ?></td>
							<td><?php echo esc_html( asraa_crm_format_currency( $r['price'] ) ); ?></td>
							<td><?php echo esc_html( asraa_crm_format_date( $r['created_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><em>No dead inventory.</em></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
				<?php
				break;

			default:
				echo '<p>' . esc_html__( 'Unknown report tab.', 'asraa-crm' ) . '</p>';
		}

		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html ] );
	}

	public function ajax_shortlist_unit() {
		asraa_crm_verify_ajax_nonce();
		asraa_crm_require_ajax_cap();

		$lead_id = (int) ( $_POST['lead_id'] ?? 0 );
		$unit_id = (int) ( $_POST['unit_id'] ?? 0 );

		if ( ! $lead_id || ! $unit_id ) {
			wp_send_json_error( [ 'message' => 'Invalid lead or unit ID.' ] );
		}

		$unit = $this->unit_repo->get_by_id( $unit_id );
		if ( ! $unit ) {
			wp_send_json_error( [ 'message' => 'Unit not found.' ] );
		}

		$map_repo = new Asraa_CRM_Lead_Unit_Map_Repository();
		$map_id   = $map_repo->shortlist(
			$lead_id,
			$unit_id,
			(int) $unit['project_id'],
			(int) $unit['tower_id']
		);

		$activity_repo = new Asraa_CRM_Lead_Activity_Repository();
		$activity_repo->log_activity(
			$lead_id,
			'unit_shortlisted',
			sprintf( 'Unit %s shortlisted in %s', $unit['unit_no'] ?? '', $unit['project_name'] ?? '' )
		);

		asraa_crm_fire_trigger( 'unit_shortlisted', [
			'lead_id' => $lead_id,
			'unit_id' => $unit_id,
			'unit'    => $unit,
		] );

		wp_send_json_success( [ 'map_id' => $map_id, 'message' => 'Unit shortlisted.' ] );
	}

	// ── Sanitization ─────────────────────────────────────────────────────────

	private function sanitize_unit( array $input ) {
		$status = sanitize_text_field( $input['status'] ?? 'available' );
		if ( ! in_array( $status, Asraa_CRM_Unit_Repository::ALLOWED_STATUSES, true ) ) {
			$status = 'available';
		}
		return [
			'project_id'    => (int) ( $input['project_id'] ?? 0 ),
			'tower_id'      => (int) ( $input['tower_id'] ?? 0 ),
			'unit_no'       => sanitize_text_field( $input['unit_no'] ?? '' ),
			'floor_no'      => (int) ( $input['floor_no'] ?? 0 ),
			'configuration' => sanitize_text_field( $input['configuration'] ?? '' ),
			'area_sqft'     => (float) ( $input['area_sqft'] ?? 0 ),
			'price'         => (float) ( $input['price'] ?? 0 ),
			'status'        => $status,
			'view_type'     => sanitize_text_field( $input['view_type'] ?? '' ),
		];
	}
}
