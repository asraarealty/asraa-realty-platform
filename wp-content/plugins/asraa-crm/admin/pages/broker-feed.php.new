<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Asraa_Broker_Feed_Repository' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Broker Feed repository is unavailable.', 'asraa-crm' ) . '</p></div>';
	return;
}

$repository = new Asraa_Broker_Feed_Repository();
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$approval_status = isset( $_GET['approval_status'] ) ? sanitize_key( wp_unslash( $_GET['approval_status'] ) ) : '';
$is_public = isset( $_GET['is_public'] ) ? sanitize_key( wp_unslash( $_GET['is_public'] ) ) : '';
$property_type = isset( $_GET['property_type'] ) ? sanitize_text_field( wp_unslash( $_GET['property_type'] ) ) : '';
$transaction_type = isset( $_GET['transaction_type'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_type'] ) ) : '';
$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
$per_page = 20;
$offset = ( $paged - 1 ) * $per_page;
$records = $repository->get_filtered( array(
	'search' => $search,
	'approval_status' => $approval_status,
	'is_public' => $is_public,
	'property_type' => $property_type,
	'transaction_type' => $transaction_type,
	'per_page' => $per_page,
	'offset' => $offset,
), ARRAY_A );
$total_items = $repository->count_filtered( array(
	'search' => $search,
	'approval_status' => $approval_status,
	'is_public' => $is_public,
	'property_type' => $property_type,
	'transaction_type' => $transaction_type,
) );
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
$base_url = admin_url( 'admin.php?page=asraa-crm-broker-feed' );
$messages = array(
	'approved' => __( 'Record approved.', 'asraa-crm' ),
	'rejected' => __( 'Record rejected.', 'asraa-crm' ),
	'deleted' => __( 'Record deleted.', 'asraa-crm' ),
	'updated' => __( 'Record updated.', 'asraa-crm' ),
	'update_failed' => __( 'Update failed.', 'asraa-crm' ),
	'bulk_approved' => __( 'Selected records approved.', 'asraa-crm' ),
	'bulk_rejected' => __( 'Selected records rejected.', 'asraa-crm' ),
	'bulk_deleted' => __( 'Selected records deleted.', 'asraa-crm' ),
	'no_selection' => __( 'Select at least one record.', 'asraa-crm' ),
);
if ( isset( $_GET['asraa_msg'] ) ) {
	$message_key = sanitize_key( wp_unslash( $_GET['asraa_msg'] ) );
	if ( isset( $messages[ $message_key ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $message_key ] ) . '</p></div>';
	}
}
?>
<div class="wrap asraa-feed-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Broker Feed', 'asraa-crm' ); ?></h1>
	<hr class="wp-header-end">
	<style>
		.asraa-feed-wrap .asraa-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin:16px 0 12px}.asraa-feed-wrap .asraa-toolbar .field{display:flex;flex-direction:column;gap:4px}.asraa-feed-wrap .asraa-toolbar .field label{font-size:12px;font-weight:600}.asraa-feed-wrap .asraa-responsive-table{overflow-x:auto}.asraa-feed-wrap table{min-width:980px}.asraa-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}.asraa-badge-pending{background:#fef3c7;color:#92400e}.asraa-badge-approved{background:#dcfce7;color:#166534}.asraa-badge-rejected{background:#fee2e2;color:#991b1b}.asraa-thumb{width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #d0d7de;background:#f6f7f7}.asraa-actions-flex{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.asraa-actions-flex .button{padding:0 8px;min-height:28px;line-height:26px}.asraa-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;display:none;align-items:center;justify-content:center;padding:20px}.asraa-modal-content{background:#fff;border-radius:8px;max-width:760px;width:100%;max-height:90vh;overflow:auto}.asraa-modal-header,.asraa-modal-footer{padding:16px 20px;background:#f6f7f7}.asraa-modal-body{padding:20px}.asraa-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px}.asraa-modal-footer{display:flex;justify-content:flex-end;gap:10px}@media (max-width:782px){.asraa-grid-2{grid-template-columns:1fr}}
	</style>
	<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="asraa-toolbar">
		<input type="hidden" name="page" value="asraa-crm-broker-feed">
		<div class="field"><label for="s">Search</label><input type="search" id="s" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Title, city, broker..."></div>
		<div class="field"><label for="approval_status">Approval</label><select id="approval_status" name="approval_status"><option value="">All</option><option value="pending" <?php selected( $approval_status, 'pending' ); ?>>Pending</option><option value="approved" <?php selected( $approval_status, 'approved' ); ?>>Approved</option><option value="rejected" <?php selected( $approval_status, 'rejected' ); ?>>Rejected</option></select></div>
		<div class="field"><label for="is_public">Public</label><select id="is_public" name="is_public"><option value="">All</option><option value="1" <?php selected( $is_public, '1' ); ?>>Yes</option><option value="0" <?php selected( $is_public, '0' ); ?>>No</option></select></div>
		<div class="field"><label for="property_type">Type</label><input type="text" id="property_type" name="property_type" value="<?php echo esc_attr( $property_type ); ?>" placeholder="Apartment"></div>
		<div class="field"><label for="transaction_type">Intent</label><input type="text" id="transaction_type" name="transaction_type" value="<?php echo esc_attr( $transaction_type ); ?>" placeholder="sale"></div>
		<div class="field"><button type="submit" class="button button-primary">Filter</button></div>
	</form>
	<form id="asraaBulkActionForm" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="asraa_broker_feed_bulk_action">
		<?php wp_nonce_field( 'asraa_broker_feed_bulk_nonce', 'bulk_nonce' ); ?>
		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<select name="bulk_action" id="bulk-action-selector-top"><option value="-1">Bulk Actions</option><option value="bulk_approve">Approve Selected</option><option value="bulk_reject">Reject Selected</option><option value="bulk_delete">Delete Selected</option></select>
				<button type="submit" class="button action">Apply</button>
			</div>
			<div class="tablenav-pages" style="float:right;"><span class="displaying-num"><?php echo esc_html( $total_items ); ?> items</span></div>
		</div>
		<div class="asraa-responsive-table">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr><td class="check-column"><input type="checkbox" id="cb-select-all-1"></td><th>Cover</th><th>Property</th><th>Project</th><th>Type</th><th>Intent</th><th>City</th><th>Broker</th><th>Status</th><th>Actions</th></tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $records ) ) : ?>
						<?php foreach ( $records as $record ) : ?>
							<?php $row_id = (int) $record['id']; $image = ! empty( $record['image_url'] ) ? esc_url( $record['image_url'] ) : ''; $status = ! empty( $record['approval_status'] ) ? sanitize_key( $record['approval_status'] ) : 'pending'; $badge = 'asraa-badge-pending'; if ( 'approved' === $status ) { $badge = 'asraa-badge-approved'; } elseif ( 'rejected' === $status ) { $badge = 'asraa-badge-rejected'; } ?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="record_ids[]" value="<?php echo esc_attr( $row_id ); ?>"></th>
								<td><?php if ( $image ) : ?><img src="<?php echo $image; ?>" class="asraa-thumb" alt=""><?php else : ?><div class="asraa-thumb" style="display:flex;align-items:center;justify-content:center;"><span class="dashicons dashicons-format-image"></span></div><?php endif; ?></td>
								<td><strong><?php echo esc_html( $record['title'] ?? '' ); ?></strong><div class="description"><?php echo esc_html( $record['city'] ?? '' ); ?> / <?php echo esc_html( $record['locality'] ?? '' ); ?></div></td>
								<td><?php echo esc_html( $record['project_name'] ?? '' ); ?></td>
								<td><?php echo esc_html( $record['property_type'] ?? '' ); ?></td>
								<td><?php echo esc_html( ucfirst( $record['transaction_type'] ?? 'sale' ) ); ?></td>
								<td><?php echo esc_html( $record['city'] ?? '' ); ?></td>
								<td><?php echo esc_html( $record['source_agent_name'] ?? '' ); ?></td>
								<td><span class="asraa-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $status ); ?></span></td>
								<td>
									<div class="asraa-actions-flex">
										<button type="button" class="button button-secondary asraa-edit-trigger-btn" data-record-payload="<?php echo esc_attr( wp_json_encode( $record ) ); ?>"><span class="dashicons dashicons-edit"></span></button>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=asraa_broker_feed_single_action&feed_action=approve&id=' . $row_id ), 'asraa_single_action_' . $row_id ) ); ?>" class="button button-secondary">Approve</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=asraa_broker_feed_single_action&feed_action=reject&id=' . $row_id ), 'asraa_single_action_' . $row_id ) ); ?>" class="button button-secondary">Reject</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=asraa_broker_feed_single_action&feed_action=delete&id=' . $row_id ), 'asraa_single_action_' . $row_id ) ); ?>" class="button button-link-delete" onclick="return confirm('Delete this record?');">Delete</a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="10" style="text-align:center;padding:24px;">No broker feed entries found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</form>
	<div class="tablenav bottom">
		<?php echo paginate_links( array( 'base' => add_query_arg( array( 'page' => 'asraa-crm-broker-feed', 'paged' => '%#%' ), $base_url ), 'format' => '', 'current' => $paged, 'total' => $total_pages, 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ); ?>
	</div>
</div>
<div class="asraa-modal-backdrop" id="asraaEditModalContainer">
	<div class="asraa-modal-content">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin:0;">
			<input type="hidden" name="action" value="asraa_broker_feed_update_record">
			<input type="hidden" name="record_id" id="modal_record_id" value="">
			<?php wp_nonce_field( 'asraa_broker_feed_update_nonce', 'update_nonce' ); ?>
			<div class="asraa-modal-header"><h2>Edit Broker Listing</h2><button type="button" class="button asraa-modal-close" id="asraaCloseModalCrossBtn">×</button></div>
			<div class="asraa-modal-body">
				<div class="asraa-grid-2"><div><label>Title</label><input type="text" name="title" id="modal_title" class="widefat" required></div><div><label>Project Name</label><input type="text" name="project_name" id="modal_project_name" class="widefat"></div></div>
				<div class="asraa-grid-2"><div><label>Property Type</label><select name="property_type" id="modal_property_type" class="widefat"><option value="Apartment">Apartment</option><option value="Villa">Villa</option><option value="Penthouse">Penthouse</option><option value="Studio">Studio</option><option value="Plot">Plot</option><option value="Commercial Office">Commercial Office</option></select></div><div><label>Transaction Type</label><select name="transaction_type" id="modal_transaction_type" class="widefat"><option value="sale">Sale</option><option value="rent">Rent</option><option value="lease">Lease</option><option value="resale">Resale</option><option value="new_launch">New Launch</option></select></div></div>
				<div class="asraa-grid-2"><div><label>Configuration</label><input type="text" name="configuration" id="modal_configuration" class="widefat"></div><div><label>City</label><input type="text" name="city" id="modal_city" class="widefat" required></div></div>
				<div class="asraa-grid-2"><div><label>Locality</label><input type="text" name="locality" id="modal_locality" class="widefat"></div><div><label>Carpet Area</label><input type="text" name="carpet_area" id="modal_carpet_area" class="widefat"></div></div>
				<div class="asraa-grid-2"><div><label>Available Units</label><input type="number" name="available_units" id="modal_available_units" class="widefat" min="1"></div><div><label>Price</label><input type="text" name="price" id="modal_price" class="widefat"></div></div>
				<div class="asraa-grid-2"><div><label>Approval Status</label><select name="approval_status" id="modal_approval_status" class="widefat"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div><div><label>Publicly Visible</label><select name="is_public" id="modal_is_public" class="widefat"><option value="0">No</option><option value="1">Yes</option></select></div></div>
				<div class="asraa-grid-2"><div><label>Image</label><input type="file" name="property_image" id="modal_property_image" accept="image/*" class="widefat"></div><div><label>Preview</label><img id="modal_preview_render_src" src="#" style="max-width:120px;max-height:120px;display:none;border-radius:6px"></div></div>
				<div><label>Notes</label><textarea name="notes" id="modal_notes" class="widefat" rows="4"></textarea></div>
			</div>
			<div class="asraa-modal-footer"><button type="button" class="button button-secondary" id="asraaCloseModalFooterBtn">Cancel</button><button type="submit" class="button button-primary">Save Changes</button></div>
		</form>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	const modal = document.getElementById('asraaEditModalContainer');
	const closeButtons = [document.getElementById('asraaCloseModalCrossBtn'), document.getElementById('asraaCloseModalFooterBtn')];
	const triggers = document.querySelectorAll('.asraa-edit-trigger-btn');
	const fields = {
		record_id: document.getElementById('modal_record_id'),
		title: document.getElementById('modal_title'),
		project_name: document.getElementById('modal_project_name'),
		property_type: document.getElementById('modal_property_type'),
		transaction_type: document.getElementById('modal_transaction_type'),
		configuration: document.getElementById('modal_configuration'),
		city: document.getElementById('modal_city'),
		locality: document.getElementById('modal_locality'),
		carpet_area: document.getElementById('modal_carpet_area'),
		available_units: document.getElementById('modal_available_units'),
		price: document.getElementById('modal_price'),
		approval_status: document.getElementById('modal_approval_status'),
		is_public: document.getElementById('modal_is_public'),
		notes: document.getElementById('modal_notes'),
		preview: document.getElementById('modal_preview_render_src')
	};
	triggers.forEach(function (btn) {
		btn.addEventListener('click', function () {
			const payload = JSON.parse(this.getAttribute('data-record-payload'));
			fields.record_id.value = payload.id || '';
			fields.title.value = payload.title || '';
			fields.project_name.value = payload.project_name || '';
			fields.property_type.value = payload.property_type || 'Apartment';
			fields.transaction_type.value = payload.transaction_type || 'sale';
			fields.configuration.value = payload.configuration || '';
			fields.city.value = payload.city || '';
			fields.locality.value = payload.locality || '';
			fields.carpet_area.value = payload.carpet_area || '';
			fields.available_units.value = payload.available_units || 1;
			fields.price.value = payload.price || 0;
			fields.approval_status.value = payload.approval_status || 'pending';
			fields.is_public.value = payload.is_public || 0;
			fields.notes.value = payload.notes || '';
			if (payload.image_url) { fields.preview.src = payload.image_url; fields.preview.style.display = 'block'; } else { fields.preview.removeAttribute('src'); fields.preview.style.display = 'none'; }
			modal.style.display = 'flex';
		});
	});
	closeButtons.forEach(function (btn) { if (btn) { btn.addEventListener('click', function () { modal.style.display = 'none'; }); } });
	if (modal) { modal.addEventListener('click', function (e) { if (e.target === modal) { modal.style.display = 'none'; } }); }
});
</script>
