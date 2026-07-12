<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array[]   $projects  Active projects – from Asraa_CRM_Inventory_Controller::inventory_page()
 * @var array[]   $towers    Towers for selected project (may be empty)
 * @var array     $result    { items: array[], total: int }
 * @var array     $filters   Current active filters
 */
$projects = $projects ?? [];
$towers   = $towers   ?? [];
$result   = $result   ?? [ 'items' => [], 'total' => 0 ];
$filters  = $filters  ?? [];

$items     = $result['items'];
$total     = (int) $result['total'];
$per_page  = 25;
$paged     = max( 1, (int) ( $filters['paged'] ?? 1 ) );
$num_pages = $total > 0 ? ceil( $total / $per_page ) : 1;

$statuses = Asraa_CRM_Unit_Repository::ALLOWED_STATUSES;
$status_colors = [
    'available'   => '#16a34a',
    'blocked'     => '#ca8a04',
    'negotiation' => '#2563eb',
    'token'       => '#7c3aed',
    'booked'      => '#0891b2',
    'sold'        => '#dc2626',
    'cancelled'   => '#6b7280',
];
$base_url = admin_url( 'admin.php?page=asraa-crm-inventory' );
?>

<div class="wrap">
<h1 class="wp-heading-inline">📦 Inventory</h1>
<button id="asraa-add-unit-btn" class="page-title-action">+ Add Unit</button>
<hr class="wp-header-end">

<!-- FILTER BAR -->
<div class="asraa-inv-filters" style="display:flex;flex-wrap:wrap;gap:10px;margin:14px 0;align-items:flex-end;">
    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Project</label>
        <select id="asraa-filter-project" style="min-width:160px;">
            <option value="0">— All —</option>
            <?php foreach ( $projects as $p ) : ?>
                <option value="<?php echo esc_attr( $p['id'] ); ?>"
                    <?php selected( (int)($filters['project_id'] ?? 0), (int)$p['id'] ); ?>>
                    <?php echo esc_html( $p['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Tower</label>
        <select id="asraa-filter-tower" style="min-width:140px;">
            <option value="0">— All —</option>
            <?php foreach ( $towers as $t ) : ?>
                <option value="<?php echo esc_attr( $t['id'] ); ?>"
                    <?php selected( (int)($filters['tower_id'] ?? 0), (int)$t['id'] ); ?>>
                    <?php echo esc_html( $t['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Floor</label>
        <input type="number" id="asraa-filter-floor" placeholder="Any"
               value="<?php echo esc_attr( $filters['floor_no'] ?? '' ); ?>"
               style="width:80px;">
    </div>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Status</label>
        <select id="asraa-filter-status" style="min-width:130px;">
            <option value="">— All —</option>
            <?php foreach ( $statuses as $s ) : ?>
                <option value="<?php echo esc_attr( $s ); ?>"
                    <?php selected( $filters['status'] ?? '', $s ); ?>>
                    <?php echo esc_html( ucfirst( $s ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Search Unit No.</label>
        <input type="text" id="asraa-filter-search" placeholder="e.g. A-1201"
               value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>"
               style="width:140px;">
    </div>
    <div>
        <button id="asraa-apply-filters" class="button button-primary">🔍 Apply</button>
        <a href="<?php echo esc_url( $base_url ); ?>" class="button">Reset</a>
    </div>
</div>

<!-- BULK ACTION BAR -->
<div id="asraa-bulk-bar" style="display:none;background:#f0f6fc;padding:10px 14px;border:1px solid #c3d4e4;border-radius:6px;margin-bottom:12px;display:flex;gap:10px;align-items:center;">
    <span id="asraa-bulk-count" style="font-weight:600;">0 selected</span>
    <select id="asraa-bulk-status">
        <option value="">— Change status to —</option>
        <?php foreach ( $statuses as $s ) : ?>
            <option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( ucfirst( $s ) ); ?></option>
        <?php endforeach; ?>
    </select>
    <button id="asraa-bulk-apply" class="button">Apply Bulk</button>
</div>

<!-- RESULTS TABLE -->
<div id="asraa-inv-table-wrap">
<table class="wp-list-table widefat fixed striped" id="asraa-inventory-table">
    <thead>
    <tr>
        <th width="32"><input type="checkbox" id="asraa-select-all"></th>
        <th>Unit No.</th>
        <th>Project</th>
        <th>Tower</th>
        <th>Floor</th>
        <th>Config</th>
        <th>Area (sqft)</th>
        <th>Price</th>
        <th>Status</th>
        <th width="130">Actions</th>
    </tr>
    </thead>
    <tbody id="asraa-inv-tbody">
    <?php foreach ( $items as $unit ) : ?>
        <tr data-id="<?php echo esc_attr( $unit['id'] ); ?>">
            <td><input type="checkbox" class="asraa-unit-cb" value="<?php echo esc_attr( $unit['id'] ); ?>"></td>
            <td><strong><?php echo esc_html( $unit['unit_no'] ?: '—' ); ?></strong></td>
            <td><?php echo esc_html( $unit['project_name'] ?? '—' ); ?></td>
            <td><?php echo esc_html( $unit['tower_name'] ?? '—' ); ?></td>
            <td><?php echo esc_html( $unit['floor_no'] ?: '—' ); ?></td>
            <td><?php echo esc_html( $unit['configuration'] ?: '—' ); ?></td>
            <td><?php echo esc_html( $unit['area_sqft'] ? number_format( (float)$unit['area_sqft'], 0 ) : '—' ); ?></td>
            <td><?php echo esc_html( $unit['price'] ? asraa_crm_format_currency( $unit['price'] ) : '—' ); ?></td>
            <td>
                <span class="asraa-unit-status" style="
                    display:inline-block;padding:2px 10px;border-radius:12px;
                    font-size:12px;font-weight:600;color:#fff;
                    background:<?php echo esc_attr( $status_colors[ $unit['status'] ] ?? '#6b7280' ); ?>;">
                    <?php echo esc_html( ucfirst( $unit['status'] ) ); ?>
                </span>
            </td>
            <td>
                <button class="button button-small asraa-unit-edit"
                        data-unit='<?php echo esc_attr( wp_json_encode( $unit ) ); ?>'>✏️</button>
                <button class="button button-small asraa-unit-delete"
                        data-id="<?php echo esc_attr( $unit['id'] ); ?>">🗑️</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ( empty( $items ) ) : ?>
        <tr><td colspan="10"><em>No units found. Add a unit or adjust filters.</em></td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php if ( $num_pages > 1 ) : ?>
<div style="margin-top:14px;">
    <?php for ( $i = 1; $i <= $num_pages; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>"
           class="button<?php echo $i === $paged ? ' button-primary' : ''; ?>"
           style="margin-right:4px;"><?php echo esc_html( $i ); ?></a>
    <?php endfor; ?>
    <span style="margin-left:10px;color:#555;">
        Showing page <?php echo esc_html( $paged ); ?> of <?php echo esc_html( $num_pages ); ?>
        (<?php echo esc_html( $total ); ?> total)
    </span>
</div>
<?php endif; ?>
</div><!-- /#asraa-inv-table-wrap -->
</div><!-- /.wrap -->

<!-- ADD / EDIT UNIT MODAL -->
<div id="asraa-unit-modal" style="display:none;">
    <div class="asraa-modal-overlay"></div>
    <div class="asraa-modal-box" style="width:640px;">
        <h2 id="asraa-unit-modal-title">➕ Add Unit</h2>
        <form id="asraa-unit-form">
            <input type="hidden" name="action" value="asraa_save_unit">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>">
            <input type="hidden" name="id" id="unit-id" value="0">

            <div class="asraa-grid">
                <div>
                    <label>Project *</label>
                    <select name="project_id" id="unit-project-id" required style="width:100%;">
                        <option value="">— Select —</option>
                        <?php foreach ( $projects as $p ) : ?>
                            <option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Tower *</label>
                    <select name="tower_id" id="unit-tower-id" required style="width:100%;">
                        <option value="">— Select Project First —</option>
                    </select>
                </div>
                <div>
                    <label>Unit No.</label>
                    <input type="text" name="unit_no" id="unit-no" class="regular-text" placeholder="e.g. A-1201">
                </div>
                <div>
                    <label>Floor No.</label>
                    <input type="number" name="floor_no" id="unit-floor" class="small-text" min="0">
                </div>
                <div>
                    <label>Configuration</label>
                    <input type="text" name="configuration" id="unit-config" class="regular-text" placeholder="e.g. 2BHK">
                </div>
                <div>
                    <label>View Type</label>
                    <input type="text" name="view_type" id="unit-view" class="regular-text" placeholder="e.g. Sea View">
                </div>
                <div>
                    <label>Area (sqft)</label>
                    <input type="number" name="area_sqft" id="unit-area" class="regular-text" step="0.01" min="0">
                </div>
                <div>
                    <label>Price (₹)</label>
                    <input type="number" name="price" id="unit-price" class="regular-text" step="0.01" min="0">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status" id="unit-status" style="width:100%;">
                        <?php foreach ( $statuses as $s ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( ucfirst( $s ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top:18px; display:flex; gap:10px;">
                <button type="submit" class="button button-primary">💾 Save Unit</button>
                <button type="button" id="asraa-unit-modal-close" class="button">Cancel</button>
            </div>
            <div id="asraa-unit-msg" style="margin-top:8px;"></div>
        </form>
    </div>
</div>

<style>
.asraa-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; }
.asraa-modal-box { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:28px; border-radius:10px; z-index:9999; max-height:90vh; overflow-y:auto; }
.asraa-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.asraa-grid > div > label { display:block; font-weight:600; margin-bottom:4px; }
.asraa-grid input, .asraa-grid select { width:100%; }
@media(max-width:640px) {
    .asraa-inv-filters { flex-direction:column; }
    .asraa-grid { grid-template-columns:1fr; }
    .asraa-modal-box { width:95vw !important; }
}
</style>

<script>
(function($){
    const nonce   = '<?php echo esc_js( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>';
    const ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    // Cascade towers when project changes inside the modal.
    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function loadTowers(projectId, selectedId) {
        const $sel = $('#unit-tower-id').empty().append('<option value="">Loading…</option>');
        if (!projectId) { $sel.empty().append('<option value="">— Select Project First —</option>'); return; }
        $.post(ajaxurl, { action:'asraa_get_towers_by_project', project_id:projectId, nonce }, function(res){
            $sel.empty().append('<option value="">— Select Tower —</option>');
            if (res.success) {
                res.data.forEach(t => {
                    const $opt = $('<option>').val(t.id).text(t.name);
                    if (t.id == selectedId) $opt.prop('selected', true);
                    $sel.append($opt);
                });
            }
        });
    }

    $('#unit-project-id').on('change', function(){ loadTowers($(this).val(), 0); });

    // Also cascade when the filter project changes.
    $('#asraa-filter-project').on('change', function(){
        const pid = $(this).val();
        const $ts = $('#asraa-filter-tower').empty().append('<option value="0">— All —</option>');
        if (!pid) return;
        $.post(ajaxurl, { action:'asraa_get_towers_by_project', project_id:pid, nonce }, function(res){
            if (res.success) {
                res.data.forEach(t => {
                    const $opt = $('<option>').val(t.id).text(t.name);
                    $ts.append($opt);
                });
            }
        });
    });

    // Open add modal.
    $('#asraa-add-unit-btn').on('click', function(){
        $('#asraa-unit-modal-title').text('➕ Add Unit');
        $('#asraa-unit-form')[0].reset();
        $('#unit-id').val(0);
        $('#asraa-unit-msg').text('');
        $('#asraa-unit-modal').fadeIn(150);
    });

    // Open edit modal.
    $(document).on('click', '.asraa-unit-edit', function(){
        const u = $(this).data('unit');
        $('#asraa-unit-modal-title').text('✏️ Edit Unit');
        $('#unit-id').val(u.id);
        $('#unit-no').val(u.unit_no);
        $('#unit-floor').val(u.floor_no);
        $('#unit-config').val(u.configuration);
        $('#unit-view').val(u.view_type);
        $('#unit-area').val(u.area_sqft);
        $('#unit-price').val(u.price);
        $('#unit-status').val(u.status);
        $('#unit-project-id').val(u.project_id);
        loadTowers(u.project_id, u.tower_id);
        $('#asraa-unit-msg').text('');
        $('#asraa-unit-modal').fadeIn(150);
    });

    $('#asraa-unit-modal-close, .asraa-modal-overlay').on('click', function(){
        $('#asraa-unit-modal').fadeOut(150);
    });

    // Save unit.
    $('#asraa-unit-form').on('submit', function(e){
        e.preventDefault();
        const btn = $(this).find('[type=submit]').prop('disabled', true).text('Saving…');
        $.post(ajaxurl, $(this).serialize(), function(res){
            btn.prop('disabled', false).text('💾 Save Unit');
            if (res.success) {
                $('#asraa-unit-msg').css('color','green').text(res.data.message);
                setTimeout(() => location.reload(), 700);
            } else {
                $('#asraa-unit-msg').css('color','red').text(res.data.message || 'Error.');
            }
        });
    });

    // Delete unit.
    $(document).on('click', '.asraa-unit-delete', function(){
        if (!confirm('Delete this unit?')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action:'asraa_delete_unit', id, nonce }, function(res){
            if (res.success) location.reload();
            else alert(res.data.message || 'Delete failed.');
        });
    });

    // Select all checkbox.
    $('#asraa-select-all').on('change', function(){
        $('.asraa-unit-cb').prop('checked', this.checked);
        updateBulkBar();
    });
    $(document).on('change', '.asraa-unit-cb', updateBulkBar);

    function updateBulkBar() {
        const n = $('.asraa-unit-cb:checked').length;
        if (n > 0) {
            $('#asraa-bulk-bar').css('display','flex');
            $('#asraa-bulk-count').text(n + ' selected');
        } else {
            $('#asraa-bulk-bar').hide();
        }
    }

    // Apply filters via AJAX.
    $('#asraa-apply-filters').on('click', function(){
        const params = {
            action:     'asraa_inventory_search',
            nonce,
            project_id: $('#asraa-filter-project').val(),
            tower_id:   $('#asraa-filter-tower').val(),
            floor_no:   $('#asraa-filter-floor').val(),
            status:     $('#asraa-filter-status').val(),
            search:     $('#asraa-filter-search').val(),
            per_page:   25,
            paged:      1,
        };
        $(this).prop('disabled', true).text('Searching…');
        $.post(ajaxurl, params, function(res){
            $('#asraa-apply-filters').prop('disabled', false).text('🔍 Apply');
            if (!res.success) return;
            const { items } = res.data;
            let html = '';
            if (!items.length) {
                html = '<tr><td colspan="10"><em>No units found.</em></td></tr>';
            } else {
                const sc = <?php echo wp_json_encode( $status_colors ); ?>;
                items.forEach(u => {
                    const bg = sc[u.status] || '#6b7280';
                    const $tr = $('<tr>').attr('data-id', u.id);
                    $tr.append($('<td>').html('<input type="checkbox" class="asraa-unit-cb" value="' + escHtml(u.id) + '">'));
                    $tr.append($('<td>').html('<strong>' + escHtml(u.unit_no || '—') + '</strong>'));
                    $tr.append($('<td>').text(u.project_name || '—'));
                    $tr.append($('<td>').text(u.tower_name || '—'));
                    $tr.append($('<td>').text(u.floor_no || '—'));
                    $tr.append($('<td>').text(u.configuration || '—'));
                    $tr.append($('<td>').text(u.area_sqft ? Number(u.area_sqft).toLocaleString() : '—'));
                    $tr.append($('<td>').text(u.price ? '₹' + Number(u.price).toLocaleString() : '—'));
                    const $badge = $('<span>').css({display:'inline-block',padding:'2px 10px',borderRadius:'12px',fontSize:'12px',fontWeight:'600',color:'#fff',background:bg}).text(u.status.charAt(0).toUpperCase()+u.status.slice(1));
                    $tr.append($('<td>').append($badge));
                    const $editBtn = $('<button>').addClass('button button-small asraa-unit-edit').text('✏️').attr('data-unit', JSON.stringify(u));
                    const $delBtn  = $('<button>').addClass('button button-small asraa-unit-delete').text('🗑️').attr('data-id', u.id);
                    $tr.append($('<td>').append($editBtn).append(' ').append($delBtn));
                    html += $tr[0].outerHTML;
                });
            }
            $('#asraa-inv-tbody').html(html);
        });
    });

    // Bulk status update.
    $('#asraa-bulk-apply').on('click', function(){
        const ids    = $('.asraa-unit-cb:checked').map((_, el) => el.value).get();
        const status = $('#asraa-bulk-status').val();
        if (!status) { alert('Select a target status.'); return; }
        if (!confirm(`Update ${ids.length} unit(s) to "${status}"?`)) return;
        $.post(ajaxurl, { action:'asraa_bulk_update_unit_status', ids, status, nonce }, function(res){
            if (res.success) { alert(res.data.message); location.reload(); }
            else alert(res.data.message || 'Failed.');
        });
    });
})(jQuery);
</script>
