<?php
if (!defined('ABSPATH')) exit;

$service  = new Asraa_Automation_Service();
$tab      = sanitize_text_field($_GET['tab'] ?? 'rules');
$rules    = $service->get_all_rules();
$logs     = ($tab === 'logs') ? $service->get_logs(100) : [];

$trigger_labels = [
    'lead_created'       => 'Lead Created',
    'lead_stage_changed' => 'Lead Stage Changed',
    'deal_created'       => 'Deal Created',
    'deal_stage_changed' => 'Deal Stage Changed',
    'deal_won'           => 'Deal Won (Closed)',
    'followup_overdue'   => 'Follow-up Overdue',
];

$action_labels = [
    'send_email'    => 'Send Email',
    'send_whatsapp' => 'Send WhatsApp',
    'assign_agent'  => 'Assign Agent',
    'change_stage'  => 'Change Stage',
    'add_followup'  => 'Add Follow-up',
];
?>

<div class="wrap">
<p><a href="<?php echo admin_url('admin.php?page=asraa-crm-automation&tab=add'); ?>" class="page-title-action">+ Add Rule</a></p>

<?php if (!empty($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Rule saved.</p></div><?php endif; ?>
<?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Rule updated.</p></div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Rule deleted.</p></div><?php endif; ?>

<nav class="nav-tab-wrapper">
<a class="nav-tab <?php echo $tab === 'rules' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=asraa-crm-automation&tab=rules'); ?>">Rules</a>
<a class="nav-tab <?php echo $tab === 'add' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=asraa-crm-automation&tab=add'); ?>">Add Rule</a>
<a class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=asraa-crm-automation&tab=logs'); ?>">Execution Logs</a>
</nav>

<?php if ($tab === 'rules'): ?>
<div class="leads-table-wrapper">
<table class="leads-table" style="margin-top:16px;">
<thead><tr>
<th>Rule Name</th><th>Trigger</th><th>Actions</th><th>Status</th><th>Created</th><th>Manage</th>
</tr></thead>
<tbody>
<?php foreach ($rules as $rule):
    $rule_actions = json_decode($rule['actions'], true) ?? [];
    $action_types = array_map(fn($a) => $action_labels[$a['type']] ?? $a['type'], $rule_actions);
?>
<tr>
<td><strong><?php echo esc_html($rule['rule_name']); ?></strong></td>
<td><?php echo esc_html($trigger_labels[$rule['trigger_event']] ?? $rule['trigger_event']); ?></td>
<td><?php echo esc_html(implode(', ', $action_types)); ?></td>
<td>
<?php if ($rule['is_active']): ?>
<span style="color:#16a34a;font-weight:600;">● Active</span>
<?php else: ?>
<span style="color:#9ca3af;">○ Inactive</span>
<?php endif; ?>
</td>
<td><?php echo date('d M Y', strtotime($rule['created_at'])); ?></td>
<td>
<span class="row-actions">
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
<?php wp_nonce_field('asraa_crm_toggle_automation'); ?>
<input type="hidden" name="action" value="asraa_crm_toggle_automation">
<input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
<button type="submit" class="button button-small"><?php echo $rule['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
</form>
<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=asraa_crm_delete_automation&rule_id='.$rule['id']), 'asraa_crm_delete_automation'); ?>" class="button button-small" onclick="return confirm('Delete this rule?')">Delete</a>
</span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php elseif ($tab === 'add'): ?>
<div style="background:#fff;padding:24px;border:1px solid #ddd;border-radius:8px;max-width:700px;margin-top:16px;">
<h2>Add Automation Rule</h2>
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
<?php wp_nonce_field('asraa_crm_automation_nonce'); ?>
<input type="hidden" name="action" value="asraa_crm_save_automation">

<table class="form-table">
<tr><th>Rule Name</th><td><input class="regular-text" name="rule_name" required></td></tr>
<tr><th>Trigger Event</th><td>
<select name="trigger_event">
<?php foreach ($trigger_labels as $key => $label): ?>
<option value="<?php echo $key; ?>"><?php echo $label; ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th>Action Type</th><td>
<select id="action-type-select" onchange="updateActionFields()">
<?php foreach ($action_labels as $key => $label): ?>
<option value="<?php echo $key; ?>"><?php echo $label; ?></option>
<?php endforeach; ?>
</select>
</td></tr>
<tr id="email-fields" style="display:none;"><th>Email Subject</th><td><input class="regular-text" id="email-subject" placeholder="Subject"></td></tr>
<tr id="email-body-row" style="display:none;"><th>Email Body</th><td><textarea id="email-body" rows="4" class="regular-text"></textarea></td></tr>
<tr id="followup-days-row" style="display:none;"><th>Days From Now</th><td><input type="number" id="followup-days" min="1" value="1" style="width:80px;"> <input class="regular-text" id="followup-note" placeholder="Follow-up note"></td></tr>
<tr><th>Active</th><td><input type="checkbox" name="is_active" value="1" checked></td></tr>
</table>

<input type="hidden" name="actions_json" id="actions_json">
<input type="hidden" name="conditions_json" value="[]">

<p>
<button type="submit" class="button button-primary">Save Rule</button>
<a href="<?php echo admin_url('admin.php?page=asraa-crm-automation'); ?>" class="button">Cancel</a>
</p>
</form>
</div>

<script>
function updateActionFields() {
    var type = document.getElementById('action-type-select').value;
    document.getElementById('email-fields').style.display = (type === 'send_email') ? '' : 'none';
    document.getElementById('email-body-row').style.display = (type === 'send_email') ? '' : 'none';
    document.getElementById('followup-days-row').style.display = (type === 'add_followup') ? '' : 'none';
}
function buildActionsJson() {
    var type = document.getElementById('action-type-select').value;
    var action = {type: type};
    if (type === 'send_email') {
        action.subject = document.getElementById('email-subject').value;
        action.body    = document.getElementById('email-body').value;
    } else if (type === 'add_followup') {
        action.days_from_now = parseInt(document.getElementById('followup-days').value, 10);
        action.note          = document.getElementById('followup-note').value;
    }
    document.getElementById('actions_json').value = JSON.stringify([action]);
    return true;
}
document.querySelector('form').addEventListener('submit', function(e) {
    var type = document.getElementById('action-type-select').value;
    if (type === 'send_email' && !document.getElementById('email-subject').value.trim()) {
        alert('Please enter an email subject.');
        e.preventDefault();
        return;
    }
    buildActionsJson();
});
updateActionFields();
</script>

<?php elseif ($tab === 'logs'): ?>
<h2 style="margin-top:16px;">Execution Logs</h2>
<div class="leads-table-wrapper">
<table class="leads-table">
<thead><tr>
<th>Rule</th><th>Trigger</th><th>Status</th><th>Notes</th><th>Executed At</th>
</tr></thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
<td><?php echo esc_html($log['rule_name'] ?? '#'.$log['rule_id']); ?></td>
<td><?php echo esc_html($trigger_labels[$log['trigger']] ?? $log['trigger']); ?></td>
<td>
<?php if ($log['status'] === 'success'): ?>
<span style="color:#16a34a;">✓ Success</span>
<?php else: ?>
<span style="color:#ef4444;">✗ Error</span>
<?php endif; ?>
</td>
<td><?php echo esc_html($log['notes'] ?? ''); ?></td>
<td><?php echo date('d M Y H:i', strtotime($log['executed_at'])); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

</div>
