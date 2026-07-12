<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array[]  $projects  Active projects
 * @var array    $result    { items: array[], total: int }
 * @var array    $filters   Current active filters
 */
$projects = $projects ?? [];
$result   = $result   ?? [ 'items' => [], 'total' => 0 ];
$filters  = $filters  ?? [];

$items    = $result['items'];
$total    = (int) $result['total'];
$per_page = 25;
$paged    = max( 1, (int) ( $filters['paged'] ?? 1 ) );
$num_pages = $total > 0 ? ceil( $total / $per_page ) : 1;

$agents = get_users( [ 'role__in' => [ 'asraa_agent', 'asraa_admin', 'administrator' ], 'fields' => [ 'ID', 'display_name' ] ] );
$base_url = admin_url( 'admin.php?page=asraa-crm-site-visits' );
?>

<div class="wrap">
<h1 class="wp-heading-inline">🚗 Site Visits</h1>
<button id="asraa-add-visit-btn" class="page-title-action">+ Schedule Visit</button>
<hr class="wp-header-end">

<!-- FILTERS -->
<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;margin:14px 0;align-items:flex-end;">
    <input type="hidden" name="page" value="asraa-crm-site-visits">

    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Project</label>
        <select name="project_id" style="min-width:150px;">
            <option value="0">— All —</option>
            <?php foreach ( $projects as $p ) : ?>
                <option value="<?php echo esc_attr( $p['id'] ); ?>"
                    <?php selected( (int)($filters['project_id']??0), (int)$p['id'] ); ?>>
                    <?php echo esc_html( $p['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">Agent</label>
        <select name="agent_id" style="min-width:150px;">
            <option value="0">— All —</option>
            <?php foreach ( $agents as $a ) : ?>
                <option value="<?php echo esc_attr( $a->ID ); ?>"
                    <?php selected( (int)($filters['agent_id']??0), (int)$a->ID ); ?>>
                    <?php echo esc_html( $a->display_name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">From</label>
        <input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ?? '' ); ?>">
    </div>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:3px;">To</label>
        <input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ?? '' ); ?>">
    </div>

    <div>
        <button type="submit" class="button button-primary">🔍 Filter</button>
        <a href="<?php echo esc_url( $base_url ); ?>" class="button">Reset</a>
    </div>
</form>

<!-- TABLE -->
<table class="wp-list-table widefat fixed striped">
    <thead>
    <tr>
        <th>Lead</th>
        <th>Project</th>
        <th>Visit Date</th>
        <th>Sales Agent</th>
        <th>Outcome</th>
        <th>Feedback</th>
        <th width="120">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ( $items as $v ) :
        $agent_name = $v['sales_agent'] ? get_user_by( 'ID', (int) $v['sales_agent'] )?->display_name ?? '—' : '—';
    ?>
        <tr data-id="<?php echo esc_attr( $v['id'] ); ?>">
            <td>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=asraa-crm-leads&lead_id=' . $v['lead_id'] ) ); ?>">
                    <?php echo esc_html( $v['lead_name'] ?? 'Lead #' . $v['lead_id'] ); ?>
                </a>
            </td>
            <td><?php echo esc_html( $v['project_name'] ?? '—' ); ?></td>
            <td><?php echo esc_html( asraa_crm_format_date( $v['visit_date'] ) ); ?></td>
            <td><?php echo esc_html( $agent_name ); ?></td>
            <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $v['visit_outcome'] ?? '' ) ) ?: '—' ); ?></td>
            <td><?php echo esc_html( wp_trim_words( $v['feedback'] ?? '', 10 ) ?: '—' ); ?></td>
            <td>
                <button class="button button-small asraa-visit-edit"
                        data-visit='<?php echo esc_attr( wp_json_encode( $v ) ); ?>'>✏️</button>
                <button class="button button-small asraa-visit-delete"
                        data-id="<?php echo esc_attr( $v['id'] ); ?>">🗑️</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ( empty( $items ) ) : ?>
        <tr><td colspan="7"><em>No site visits found.</em></td></tr>
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
        Page <?php echo esc_html( $paged ); ?>/<?php echo esc_html( $num_pages ); ?>
        (<?php echo esc_html( $total ); ?> total)
    </span>
</div>
<?php endif; ?>
</div>

<!-- ADD / EDIT MODAL -->
<div id="asraa-visit-modal" style="display:none;">
    <div class="asraa-modal-overlay"></div>
    <div class="asraa-modal-box" style="width:540px;">
        <h2 id="asraa-visit-modal-title">➕ Schedule Site Visit</h2>
        <form id="asraa-visit-form">
            <input type="hidden" name="action" value="asraa_save_site_visit">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>">
            <input type="hidden" name="id" id="visit-id" value="0">

            <table class="form-table" style="margin:0;">
                <tr>
                    <th><label>Lead ID *</label></th>
                    <td><input type="number" name="lead_id" id="visit-lead-id" class="small-text" min="1" required></td>
                </tr>
                <tr>
                    <th><label>Project</label></th>
                    <td>
                        <select name="project_id" id="visit-project-id" style="width:100%;">
                            <option value="0">— Select —</option>
                            <?php foreach ( $projects as $p ) : ?>
                                <option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Visit Date</label></th>
                    <td><input type="datetime-local" name="visit_date" id="visit-date" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Sales Agent</label></th>
                    <td>
                        <select name="sales_agent" id="visit-agent" style="width:100%;">
                            <option value="">— Assign Agent —</option>
                            <?php foreach ( $agents as $a ) : ?>
                                <option value="<?php echo esc_attr( $a->ID ); ?>"><?php echo esc_html( $a->display_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Outcome</label></th>
                    <td>
                        <select name="visit_outcome" id="visit-outcome" style="width:100%;">
                            <option value="">— Pending —</option>
                            <option value="completed">Completed</option>
                            <option value="token_expected">Token Expected</option>
                            <option value="interested">Interested</option>
                            <option value="not_interested">Not Interested</option>
                            <option value="rescheduled">Rescheduled</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Feedback</label></th>
                    <td><textarea name="feedback" id="visit-feedback" rows="4" style="width:100%;"></textarea></td>
                </tr>
            </table>

            <div style="margin-top:18px; display:flex; gap:10px;">
                <button type="submit" class="button button-primary">💾 Save Visit</button>
                <button type="button" id="asraa-visit-modal-close" class="button">Cancel</button>
            </div>
            <div id="asraa-visit-msg" style="margin-top:8px;"></div>
        </form>
    </div>
</div>

<style>
.asraa-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9998; }
.asraa-modal-box { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:28px; border-radius:10px; z-index:9999; max-height:90vh; overflow-y:auto; }
@media(max-width:640px){ .asraa-modal-box { width:95vw !important; } }
</style>

<script>
(function($){
    const nonce   = '<?php echo esc_js( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>';
    const ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    $('#asraa-add-visit-btn').on('click', function(){
        $('#asraa-visit-modal-title').text('➕ Schedule Site Visit');
        $('#asraa-visit-form')[0].reset();
        $('#visit-id').val(0);
        $('#asraa-visit-msg').text('');
        $('#asraa-visit-modal').fadeIn(150);
    });

    $(document).on('click', '.asraa-visit-edit', function(){
        const v = $(this).data('visit');
        $('#asraa-visit-modal-title').text('✏️ Edit Visit');
        $('#visit-id').val(v.id);
        $('#visit-lead-id').val(v.lead_id);
        $('#visit-project-id').val(v.project_id);
        const visitDateTime = v.visit_date ? v.visit_date.replace(' ','T').substring(0,16) : '';
        $('#visit-date').val(visitDateTime);
        $('#visit-agent').val(v.sales_agent);
        $('#visit-outcome').val(v.visit_outcome);
        $('#visit-feedback').val(v.feedback);
        $('#asraa-visit-msg').text('');
        $('#asraa-visit-modal').fadeIn(150);
    });

    $('#asraa-visit-modal-close, .asraa-modal-overlay').on('click', function(){
        $('#asraa-visit-modal').fadeOut(150);
    });

    $('#asraa-visit-form').on('submit', function(e){
        e.preventDefault();
        const btn = $(this).find('[type=submit]').prop('disabled', true).text('Saving…');
        $.post(ajaxurl, $(this).serialize(), function(res){
            btn.prop('disabled', false).text('💾 Save Visit');
            if (res.success) {
                $('#asraa-visit-msg').css('color','green').text(res.data.message);
                setTimeout(() => location.reload(), 700);
            } else {
                $('#asraa-visit-msg').css('color','red').text(res.data.message || 'Error.');
            }
        });
    });

    $(document).on('click', '.asraa-visit-delete', function(){
        if (!confirm('Delete this site visit?')) return;
        $.post(ajaxurl, { action:'asraa_delete_site_visit', id:$(this).data('id'), nonce }, function(res){
            if (res.success) location.reload();
            else alert(res.data.message || 'Delete failed.');
        });
    });
})(jQuery);
</script>
