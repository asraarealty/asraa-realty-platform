<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$table        = $wpdb->prefix . 'asraa_crm_leads';
$groups_table = $wpdb->prefix . 'asraa_crm_groups';

$all_groups = $wpdb->get_results(
    "SELECT id, group_name FROM {$groups_table} ORDER BY group_name ASC",
    ARRAY_A
);

$lead_stages = function_exists('asraa_crm_get_lead_stages')
    ? asraa_crm_get_lead_stages()
    : ['new', 'contacted', 'interested', 'visit_scheduled', 'closed'];

$assignable_agents = get_users([
    'capability__in' => ['asraa_manage_leads', 'manage_options'],
    'orderby'        => 'display_name',
    'order'          => 'ASC',
]);

/* ===============================
   HANDLE FORM SUBMIT
================================ */
if (isset($_POST['add_lead'])) {

    check_admin_referer('asraa_add_lead');

    $name          = trim(sanitize_text_field($_POST['name'] ?? ''));
    $email         = sanitize_email($_POST['email'] ?? '');
    $phone         = trim(sanitize_text_field($_POST['phone'] ?? ''));
    $intent        = sanitize_key($_POST['intent'] ?? '');
    $location      = trim(sanitize_text_field($_POST['location'] ?? ''));
    $budget        = asraa_crm_parse_budget_value($_POST['budget'] ?? '');
    $property_type = sanitize_text_field($_POST['property_type'] ?? '');
    $source        = sanitize_text_field($_POST['source'] ?? 'Manual Admin');

    $lead_stage    = asraa_crm_sanitize_lead_stage($_POST['lead_stage'] ?? 'new');
    $agent_id      = !empty($_POST['assigned_agent']) ? intval($_POST['assigned_agent']) : 0;
    $group_id      = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;

    if (empty($name)) {
        wp_die('Lead name is required.');
    }

    $now = current_time('mysql');

    $score_data = [
        'budget'        => $budget,
        'property_type' => $property_type,
        'location'      => $location,
        'phone'         => $phone,
        'email'         => $email,
    ];

    $data = [
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'intent'         => $intent,
        'location'       => $location,
        'budget'         => $budget > 0 ? $budget : null,
        'property_type'  => $property_type,
        'source'         => $source ?: 'Manual Admin',
        'status'         => asraa_crm_get_status_for_stage($lead_stage),
        'lead_stage'     => $lead_stage,
        'group_id'       => $group_id,
        'assigned_to'    => $agent_id ?: null,
        'assigned_agent' => $agent_id ?: null,
        'lead_score'     => asraa_crm_calculate_ai_lead_score($score_data),
        'last_activity'  => $now,
        'created_at'     => $now,
    ];

    $data['whatsapp_link'] = asraa_crm_build_agent_whatsapp_link($data, $agent_id);

    $inserted = $wpdb->insert($table, $data);

    if ($inserted) {
        $lead_id = $wpdb->insert_id;

        do_action('asraa_crm_lead_created', $lead_id, $data);

        wp_safe_redirect(
            admin_url('admin.php?page=asraa-crm-leads&added=1')
        );
        exit;
    }
}

/* prevent null GET warnings */
$added = $_GET['added'] ?? '';
?>

<div class="wrap">

<?php if ($added): ?>
<div class="notice notice-success is-dismissible">
    <p>Lead added successfully.</p>
</div>
<?php endif; ?>

<form method="post">

<?php wp_nonce_field('asraa_add_lead'); ?>

<table class="form-table">

<tr>
<th>Name</th>
<td>
<input type="text" name="name" class="regular-text" required>
</td>
</tr>

<tr>
<th>Email</th>
<td>
<input type="email" name="email" class="regular-text">
</td>
</tr>

<tr>
<th>Phone</th>
<td>
<input type="text" name="phone" class="regular-text">
</td>
</tr>

<tr>
<th>Group</th>
<td>
<select name="group_id" id="lead-group-select">
<option value="0">— No group —</option>
<?php foreach ($all_groups as $g): ?>
<option value="<?php echo esc_attr($g['id']); ?>" data-name="<?php echo esc_attr($g['group_name']); ?>">
<?php echo esc_html($g['group_name']); ?>
</option>
<?php endforeach; ?>
</select>
</td>
</tr>

<tr class="asraa-property-req-row">
<th>Intent</th>
<td>
<select name="intent">
    <option value="">Select intent</option>
    <option value="buy">Buy</option>
    <option value="sell">Sell</option>
    <option value="invest">Invest</option>
</select>
</td>
</tr>

<tr class="asraa-property-req-row">
<th>Location</th>
<td>
<input type="text" name="location" class="regular-text">
</td>
</tr>

<tr class="asraa-property-req-row">
<th>Budget</th>
<td>
<input type="text" name="budget" class="regular-text" placeholder="e.g. 15000000 or 1.5 Cr">
</td>
</tr>

<tr class="asraa-property-req-row">
<th>Property Type</th>
<td>
<input type="text" name="property_type" class="regular-text">
</td>
</tr>

<tr>
<th>Source</th>
<td>
<select name="source">
<option value="Manual Admin" selected>Manual Admin</option>
<option value="AI Chatbot">AI Chatbot</option>
<option value="Website Form">Website Form</option>
<option value="Referral">Referral</option>
<option value="WhatsApp">WhatsApp</option>
</select>
</td>
</tr>

<tr>
<th>Lead Stage</th>
<td>
<select name="lead_stage" required>
<?php foreach ($lead_stages as $stage): ?>
<option value="<?php echo esc_attr($stage); ?>">
<?php echo esc_html(ucwords(str_replace('_', ' ', $stage))); ?>
</option>
<?php endforeach; ?>
</select>
</td>
</tr>

<tr>
<th>Assign Agent</th>
<td>
<select name="assigned_agent">
<option value="0">Unassigned</option>

<?php foreach ($assignable_agents as $agent): ?>
<option value="<?php echo esc_attr($agent->ID); ?>">
<?php echo esc_html($agent->display_name); ?>
</option>
<?php endforeach; ?>

</select>
</td>
</tr>

</table>

<p>
<button type="submit" name="add_lead" class="button button-primary">
Save Lead
</button>
</p>

</form>

<script>
(function () {
    var HIDDEN_FOR_GROUPS = ['agent', 'developer', 'mall brands'];
    var select = document.getElementById('lead-group-select');
    var rows = document.querySelectorAll('.asraa-property-req-row');
    if (!select || !rows.length) return;

    function toggle() {
        var opt = select.options[select.selectedIndex];
        var name = (opt && opt.getAttribute('data-name') || '').toLowerCase();
        var hide = HIDDEN_FOR_GROUPS.indexOf(name) !== -1;
        rows.forEach(function (row) {
            row.style.display = hide ? 'none' : '';
        });
    }

    select.addEventListener('change', toggle);
    toggle();
})();
</script>

</div>