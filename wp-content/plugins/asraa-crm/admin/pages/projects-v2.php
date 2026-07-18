<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array[] $projects  Injected by Asraa_CRM_Project_Controller::projects_page() */
$projects = $projects ?? [];

/** @var WP_Post[] $site_listings  Injected by Asraa_CRM_Project_Controller::projects_page() */
$site_listings = isset( $site_listings ) && is_array( $site_listings ) ? $site_listings : [];
?>

<div class="wrap">
<p><button id="asraa-add-project-btn" class="page-title-action">+ Add Project</button></p>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Project saved.</p></div>
<?php endif; ?>

<div class="leads-table-wrapper">
<table id="asraa-projects-table" class="leads-table">
	<thead>
	<tr>
		<th width="60">ID</th>
		<th>Name</th>
		<th>Location</th>
		<th>Builder</th>
		<th>Type</th>
		<th>Status</th>
		<th width="140">Actions</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $projects as $proj ) : ?>
		<tr
			data-id="<?php echo esc_attr( $proj['id'] ); ?>"
			data-name="<?php echo esc_attr( $proj['name'] ); ?>"
			data-location="<?php echo esc_attr( $proj['location'] ?? '' ); ?>"
			data-builder="<?php echo esc_attr( $proj['builder'] ?? '' ); ?>"
			data-project_type="<?php echo esc_attr( $proj['project_type'] ?? '' ); ?>"
			data-status="<?php echo esc_attr( $proj['status'] ?? 'active' ); ?>"
			data-source_post_id="<?php echo esc_attr( $proj['source_post_id'] ?? '' ); ?>"
		>
			<td><?php echo esc_html( $proj['id'] ); ?></td>
			<td><strong><?php echo esc_html( $proj['name'] ); ?></strong></td>
			<td><?php echo esc_html( $proj['location'] ?? '—' ); ?></td>
			<td><?php echo esc_html( $proj['builder'] ?? '—' ); ?></td>
			<td><?php echo esc_html( $proj['project_type'] ?? '—' ); ?></td>
			<td>
				<span class="asraa-status-badge asraa-status-badge--<?php echo esc_attr( $proj['status'] ?? 'active' ); ?>">
					<?php echo esc_html( ucfirst( $proj['status'] ?? 'active' ) ); ?>
				</span>
			</td>
			<td>
				<span class="row-actions">
				<button class="button button-small asraa-proj-edit">✏️ Edit</button>
				<button class="button button-small asraa-proj-delete"
				        data-id="<?php echo esc_attr( $proj['id'] ); ?>">🗑️</button>
				</span>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if ( empty( $projects ) ) : ?>
		<tr><td colspan="7"><em>No projects yet. Add your first project above.</em></td></tr>
	<?php endif; ?>
	</tbody>
</table>
</div>
</div>

<!-- ADD / EDIT MODAL -->
<div id="asraa-project-modal" style="display:none;">
	<div class="asraa-modal-overlay"></div>
	<div class="asraa-modal-box">
		<h2 id="asraa-proj-modal-title">➕ Add Project</h2>

		<form id="asraa-project-form">
			<input type="hidden" name="action" value="asraa_save_project">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>">
			<input type="hidden" name="id" id="proj-id" value="0">
			<input type="hidden" name="source_post_id" id="proj-source-post-id" value="">

			<?php if ( ! empty( $site_listings ) ) : ?>
			<div style="margin-bottom:14px;">
				<label style="display:block;font-weight:600;margin-bottom:3px;">Import from existing listing (optional)</label>
				<select id="asraa-import-project-listing" style="width:100%;">
					<option value="">— Select a site listing to pre-fill —</option>
					<?php foreach ( $site_listings as $listing ) : ?>
						<option value="<?php echo esc_attr( $listing->ID ); ?>"><?php echo esc_html( $listing->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<div id="asraa-import-project-msg" style="margin-top:6px;"></div>
			</div>
			<?php endif; ?>

			<div class="asraa-grid">
				<div>
					<label>Project Name *</label>
					<input type="text" name="name" id="proj-name" class="regular-text" required placeholder="e.g. Skyline Residences">
				</div>
				<div>
					<label>Location</label>
					<input type="text" name="location" id="proj-location" class="regular-text" placeholder="e.g. Mira Road, Mumbai">
				</div>
				<div>
					<label>Builder</label>
					<input type="text" name="builder" id="proj-builder" class="regular-text" placeholder="e.g. Lodha Group">
				</div>
				<div>
					<label>Project Type</label>
					<input type="text" name="project_type" id="proj-type" class="regular-text" placeholder="e.g. Residential, Commercial">
				</div>
				<div>
					<label>Status</label>
					<select name="status" id="proj-status">
						<option value="active">Active</option>
						<option value="inactive">Inactive</option>
						<option value="completed">Completed</option>
					</select>
				</div>
			</div>

			<div style="margin-top:18px; display:flex; gap:10px;">
				<button type="submit" class="button button-primary">💾 Save Project</button>
				<button type="button" id="asraa-proj-modal-close" class="button">Cancel</button>
			</div>
			<div id="asraa-proj-msg" style="margin-top:8px;"></div>
		</form>
	</div>
</div>

<style>
.asraa-status-badge { display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600; }
.asraa-status-badge--active    { background:#dcfce7; color:#166534; }
.asraa-status-badge--inactive  { background:#f3f4f6; color:#4b5563; }
.asraa-status-badge--completed { background:#dbeafe; color:#1e40af; }
.asraa-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; }
.asraa-modal-box { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:30px; width:600px; max-width:95vw; border-radius:10px; z-index:9999; }
.asraa-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.asraa-grid > div > label { display:block; font-weight:600; margin-bottom:4px; }
.asraa-grid input, .asraa-grid select { width:100%; }
@media(max-width:640px){ .asraa-grid { grid-template-columns:1fr; } .asraa-modal-box { width:95vw; } }
</style>

<script>
(function($){
    const nonce = '<?php echo esc_js( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>';
    // ajaxurl is already defined globally by WP core on every wp-admin page.

    function openModal(data) {
        $('#asraa-proj-modal-title').text(data ? '✏️ Edit Project' : '➕ Add Project');
        $('#proj-id').val(data ? data.id : 0);
        ['name','location','builder'].forEach(f => $('#proj-' + f).val(data ? data[f] : ''));
        $('#proj-type').val(data ? data.project_type : '');
        $('#proj-status').val(data ? data.status : 'active');
        $('#proj-source-post-id').val(data && data.source_post_id ? data.source_post_id : '');
        $('#asraa-import-project-listing').val('');
        $('#asraa-import-project-msg').html('');
        $('#asraa-proj-msg').text('');
        $('#asraa-project-modal').fadeIn(150);
    }

    $('#asraa-add-project-btn').on('click', function(){ openModal(null); });

    $(document).on('click', '.asraa-proj-edit', function(){
        const tr = $(this).closest('tr');
        openModal(tr.data());
    });

    /* ---- Import from existing site listing ---- */
    $(document).on('change', '#asraa-import-project-listing', function(){
        const postId = $(this).val();
        const $msg = $('#asraa-import-project-msg');
        $msg.html('');

        if (!postId) return;

        $.post(ajaxurl, { action: 'asraa_import_project_listing', nonce: nonce, post_id: postId }, function(resp){
            if (!resp.success) {
                $msg.html('<span style="color:red;">✗ ' + (resp.data && resp.data.message ? resp.data.message : 'Could not load listing') + '</span>');
                return;
            }

            const d = resp.data;
            $('#proj-name').val(d.name);
            if (d.location) $('#proj-location').val(d.location);
            if (d.project_type) $('#proj-type').val(d.project_type);
            $('#proj-source-post-id').val(d.post_id);

            if (d.already_imported) {
                $msg.html('<span style="color:#b45309;">⚠ Already imported as Project #' + d.already_imported + '. Saving will be blocked to avoid a duplicate.</span>');
            } else {
                $msg.html('<span style="color:green;">✓ Pre-filled — review the fields below, then Save.</span>');
            }
        }).fail(function(){
            $msg.html('<span style="color:red;">✗ AJAX request failed.</span>');
        });
    });

    $('#asraa-proj-modal-close, .asraa-modal-overlay').on('click', function(){
        $('#asraa-project-modal').fadeOut(150);
    });

    $('#asraa-project-form').on('submit', function(e){
        e.preventDefault();
        const btn = $(this).find('[type=submit]').prop('disabled', true).text('Saving…');
        $.post(ajaxurl, $(this).serialize() + '&nonce=' + nonce, function(res){
            btn.prop('disabled', false).text('💾 Save Project');
            if (res.success) {
                $('#asraa-proj-msg').css('color','green').text(res.data.message);
                setTimeout(() => location.reload(), 800);
            } else {
                $('#asraa-proj-msg').css('color','red').text(res.data.message || 'Error.');
            }
        });
    });

    $(document).on('click', '.asraa-proj-delete', function(){
        if (!confirm('Delete this project? This cannot be undone.')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action:'asraa_delete_project', id, nonce }, function(res){
            if (res.success) location.reload();
            else alert(res.data.message || 'Delete failed.');
        });
    });
})(jQuery);
</script>
