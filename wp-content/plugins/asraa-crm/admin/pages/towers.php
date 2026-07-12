<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array[] $projects Injected by Asraa_CRM_Project_Controller::towers_page() */
/** @var array[] $towers   Injected by Asraa_CRM_Project_Controller::towers_page() */
$projects = $projects ?? [];
$towers   = $towers   ?? [];

$filter_project = (int) ( $_GET['project_id'] ?? 0 );
?>

<div class="wrap">
<h1 class="wp-heading-inline">🏢 Towers</h1>
<button id="asraa-add-tower-btn" class="page-title-action">+ Add Tower</button>
<hr class="wp-header-end">

<!-- Project filter -->
<form method="get" style="margin:14px 0; display:flex; gap:10px; align-items:center;">
    <input type="hidden" name="page" value="asraa-crm-towers">
    <select name="project_id" id="asraa-tower-filter-project" onchange="this.form.submit()">
        <option value="0">— All Projects —</option>
        <?php foreach ( $projects as $p ) : ?>
            <option value="<?php echo esc_attr( $p['id'] ); ?>"
                <?php selected( $filter_project, $p['id'] ); ?>>
                <?php echo esc_html( $p['name'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<table class="wp-list-table widefat fixed striped">
    <thead>
    <tr>
        <th width="60">ID</th>
        <th>Tower Name</th>
        <th>Project</th>
        <th>Total Floors</th>
        <th>Created</th>
        <th width="140">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $display = $filter_project
        ? array_filter( $towers, fn($tower) => (int)$tower['project_id'] === $filter_project )
        : $towers;
    foreach ( $display as $tower ) :
    ?>
        <tr
            data-id="<?php echo esc_attr( $tower['id'] ); ?>"
            data-name="<?php echo esc_attr( $tower['name'] ); ?>"
            data-project_id="<?php echo esc_attr( $tower['project_id'] ); ?>"
            data-total_floors="<?php echo esc_attr( $tower['total_floors'] ?? 0 ); ?>"
        >
            <td><?php echo esc_html( $tower['id'] ); ?></td>
            <td><strong><?php echo esc_html( $tower['name'] ); ?></strong></td>
            <td><?php echo esc_html( $tower['project_name'] ?? '—' ); ?></td>
            <td><?php echo esc_html( $tower['total_floors'] ?: '—' ); ?></td>
            <td><?php echo esc_html( asraa_crm_format_date( $tower['created_at'] ?? '' ) ); ?></td>
            <td>
                <button class="button button-small asraa-tower-edit">✏️ Edit</button>
                <button class="button button-small asraa-tower-delete"
                        data-id="<?php echo esc_attr( $tower['id'] ); ?>">🗑️</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ( empty( $display ) ) : ?>
        <tr><td colspan="6"><em>No towers found.</em></td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- ADD / EDIT MODAL -->
<div id="asraa-tower-modal" style="display:none;">
    <div class="asraa-modal-overlay"></div>
    <div class="asraa-modal-box" style="width:480px;">
        <h2 id="asraa-tower-modal-title">➕ Add Tower</h2>
        <form id="asraa-tower-form">
            <input type="hidden" name="action" value="asraa_save_tower">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>">
            <input type="hidden" name="id" id="tower-id" value="0">

            <table class="form-table" style="margin:0;">
                <tr>
                    <th><label>Project *</label></th>
                    <td>
                        <select name="project_id" id="tower-project-id" required style="width:100%;">
                            <option value="">— Select Project —</option>
                            <?php foreach ( $projects as $p ) : ?>
                                <option value="<?php echo esc_attr( $p['id'] ); ?>">
                                    <?php echo esc_html( $p['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Tower Name *</label></th>
                    <td><input type="text" name="name" id="tower-name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label>Total Floors</label></th>
                    <td><input type="number" name="total_floors" id="tower-floors" class="small-text" min="0"></td>
                </tr>
            </table>

            <div style="margin-top:18px; display:flex; gap:10px;">
                <button type="submit" class="button button-primary">💾 Save Tower</button>
                <button type="button" id="asraa-tower-modal-close" class="button">Cancel</button>
            </div>
            <div id="asraa-tower-msg" style="margin-top:8px;"></div>
        </form>
    </div>
</div>

<style>
.asraa-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; }
.asraa-modal-box { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:28px; border-radius:10px; z-index:9999; }
@media(max-width:640px){ .asraa-modal-box { width:95vw !important; } }
</style>

<script>
(function($){
    const nonce   = '<?php echo esc_js( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>';
    const ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    function openModal(data) {
        $('#asraa-tower-modal-title').text(data ? '✏️ Edit Tower' : '➕ Add Tower');
        $('#tower-id').val(data ? data.id : 0);
        $('#tower-name').val(data ? data.name : '');
        $('#tower-project-id').val(data ? data.project_id : '');
        $('#tower-floors').val(data ? data.total_floors : '');
        $('#asraa-tower-msg').text('');
        $('#asraa-tower-modal').fadeIn(150);
    }

    $('#asraa-add-tower-btn').on('click', function(){ openModal(null); });

    $(document).on('click', '.asraa-tower-edit', function(){
        openModal($(this).closest('tr').data());
    });

    $('#asraa-tower-modal-close, .asraa-modal-overlay').on('click', function(){
        $('#asraa-tower-modal').fadeOut(150);
    });

    $('#asraa-tower-form').on('submit', function(e){
        e.preventDefault();
        const btn = $(this).find('[type=submit]').prop('disabled', true).text('Saving…');
        $.post(ajaxurl, $(this).serialize() + '&nonce=' + nonce, function(res){
            btn.prop('disabled', false).text('💾 Save Tower');
            if (res.success) {
                $('#asraa-tower-msg').css('color','green').text(res.data.message);
                setTimeout(() => location.reload(), 700);
            } else {
                $('#asraa-tower-msg').css('color','red').text(res.data.message || 'Error.');
            }
        });
    });

    $(document).on('click', '.asraa-tower-delete', function(){
        if (!confirm('Delete this tower?')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action:'asraa_delete_tower', id, nonce }, function(res){
            if (res.success) location.reload();
            else alert(res.data.message || 'Delete failed.');
        });
    });
})(jQuery);
</script>
