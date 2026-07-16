<?php
if (!defined('ABSPATH')) exit;

$service  = new Asraa_Deal_Service();
$tab      = sanitize_text_field($_GET['tab'] ?? 'pipeline');
$deal_id  = isset($_GET['deal_id']) ? (int) $_GET['deal_id'] : 0;

// Handle single deal view
$editing = null;
if ($deal_id) {
    $editing = $service->get_by_id($deal_id);
}

$deals   = $service->get_all();
$summary = $service->get_pipeline_summary();

$stages = [
    'prospect'    => ['label' => '🔍 Prospect',    'color' => '#6b7280'],
    'negotiation' => ['label' => '🤝 Negotiation', 'color' => '#f59e0b'],
    'contract'    => ['label' => '📄 Contract',    'color' => '#3b82f6'],
    'closed_won'  => ['label' => '✅ Closed Won',  'color' => '#10b981'],
    'closed_lost' => ['label' => '❌ Closed Lost', 'color' => '#ef4444'],
];

$comm_repo = new Asraa_CRM_Commission_Repository();
$plans     = $comm_repo->get_all_plans();

$leads_table = $GLOBALS['wpdb']->prefix . 'asraa_crm_leads';
$all_leads   = $GLOBALS['wpdb']->get_results("SELECT id, name FROM $leads_table WHERE is_deleted=0 ORDER BY name ASC", ARRAY_A);
$agents      = get_users(['role__in' => ['administrator', 'editor', 'agent']]);
?>

<div class="wrap">

<p><a href="<?php echo admin_url('admin.php?page=asraa-crm-deals&deal_id=new'); ?>" class="page-title-action">+ Add Deal</a></p>

<?php if (!empty($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Deal saved.</p></div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Deal deleted.</p></div><?php endif; ?>

<?php if ($deal_id): ?>
<!-- ADD / EDIT FORM -->
<div style="background:#fff;padding:24px;border-radius:8px;max-width:700px;border:1px solid #ddd;margin-bottom:20px;">
<h2><?php echo $editing ? 'Edit Deal' : 'New Deal'; ?></h2>
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
<?php wp_nonce_field('asraa_crm_deal_nonce'); ?>
<input type="hidden" name="action" value="asraa_crm_save_deal">
<?php if ($editing): ?><input type="hidden" name="deal_id" value="<?php echo $editing['id']; ?>"><?php endif; ?>

<table class="form-table">
<tr><th>Title</th><td><input class="regular-text" name="title" value="<?php echo esc_attr($editing['title'] ?? ''); ?>" required></td></tr>
<tr><th>Lead</th><td>
<select name="lead_id">
<option value="">-- Select Lead --</option>
<?php foreach ($all_leads as $l): ?>
<option value="<?php echo $l['id']; ?>" <?php selected($editing['lead_id'] ?? '', $l['id']); ?>><?php echo esc_html($l['name']); ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th>Agent</th><td>
<select name="agent_id">
<?php foreach ($agents as $u): ?>
<option value="<?php echo $u->ID; ?>" <?php selected($editing['agent_id'] ?? get_current_user_id(), $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th>Deal Value (₹)</th><td><input type="number" step="0.01" name="deal_value" value="<?php echo esc_attr($editing['deal_value'] ?? ''); ?>"></td></tr>
<tr><th>Stage</th><td>
<select name="stage">
<?php foreach ($stages as $key => $s): ?>
<option value="<?php echo $key; ?>" <?php selected($editing['stage'] ?? 'prospect', $key); ?>><?php echo $s['label']; ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th>Expected Close Date</th><td><input type="date" name="expected_close_date" value="<?php echo esc_attr($editing['expected_close_date'] ?? ''); ?>"></td></tr>
<tr><th>Commission Plan</th><td>
<select name="commission_plan_id">
<option value="">-- None --</option>
<?php foreach ($plans as $p): ?>
<option value="<?php echo $p['id']; ?>" <?php selected($editing['commission_plan_id'] ?? '', $p['id']); ?>><?php echo esc_html($p['plan_name']); ?> (<?php echo $p['type'] === 'flat' ? '₹' . number_format($p['rate']) : $p['rate'] . '%'; ?>)</option>
<?php endforeach; ?>
</select></td></tr>
<tr><th>Notes</th><td><textarea name="notes" rows="3" class="regular-text"><?php echo esc_textarea($editing['notes'] ?? ''); ?></textarea></td></tr>
</table>

<p><button type="submit" class="button button-primary">Save Deal</button>
<a href="<?php echo admin_url('admin.php?page=asraa-crm-deals'); ?>" class="button">Cancel</a></p>
</form>
</div>
<?php else: ?>

<!-- PIPELINE SUMMARY -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
<?php
$summary_map = [];
foreach ($summary as $s) $summary_map[$s['stage']] = $s;
foreach ($stages as $key => $st):
    $cnt = $summary_map[$key]['count'] ?? 0;
    $val = $summary_map[$key]['total_value'] ?? 0;
?>
<div style="background:#fff;border-radius:8px;padding:16px 20px;border-left:5px solid <?php echo $st['color']; ?>;min-width:160px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
<strong><?php echo $st['label']; ?></strong><br>
<span style="font-size:22px;font-weight:700;"><?php echo $cnt; ?></span> deals<br>
<small>₹<?php echo number_format($val); ?></small>
</div>
<?php endforeach; ?>
</div>

<!-- DEALS TABLE -->
<div class="leads-table-wrapper">
<table class="leads-table">
<thead><tr>
<th>Title</th><th>Lead</th><th>Agent</th><th>Value</th><th>Stage</th><th>Close Date</th><th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($deals as $deal): ?>
<tr>
<td><?php echo esc_html($deal['title']); ?></td>
<td><?php echo esc_html($deal['lead_name'] ?? '-'); ?></td>
<td><?php echo esc_html($deal['agent_name'] ?? '-'); ?></td>
<td>₹<?php echo number_format($deal['deal_value']); ?></td>
<td><span style="background:<?php echo $stages[$deal['stage']]['color'] ?? '#ddd'; ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;"><?php echo esc_html($stages[$deal['stage']]['label'] ?? $deal['stage']); ?></span></td>
<td><?php echo $deal['expected_close_date'] ? date('d M Y', strtotime($deal['expected_close_date'])) : '-'; ?></td>
<td>
<span class="row-actions">
<a class="button button-small" href="<?php echo admin_url('admin.php?page=asraa-crm-deals&deal_id='.$deal['id']); ?>">Edit</a>
<a class="button button-small" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=asraa_crm_delete_deal&deal_id='.$deal['id']), 'asraa_crm_delete_deal'); ?>" onclick="return confirm('Delete this deal?')">Delete</a>
</span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>
</div>
