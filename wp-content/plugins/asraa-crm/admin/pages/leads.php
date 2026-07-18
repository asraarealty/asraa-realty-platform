<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$user_id  = get_current_user_id();
$is_admin = current_user_can('administrator');

$table        = $wpdb->prefix . 'asraa_crm_leads';
$notes        = $wpdb->prefix . 'asraa_crm_notes';
$groups_table = $wpdb->prefix . 'asraa_crm_groups';
$users_table  = $wpdb->users;

$view = sanitize_text_field($_GET['view'] ?? '');
$is_trash = ($view === 'trash');

/* =========================================================
   INLINE MANUAL UPDATES
========================================================= */
if (!empty($_POST['inline_update']) && !$is_trash) {

    check_admin_referer('asraa_inline_lead_update');

    $lead_id = intval($_POST['lead_id'] ?? 0);

    if ($lead_id > 0) {

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $lead_id),
            ARRAY_A
        );

        if ($existing) {

            $assigned_agent = intval($_POST['assigned_agent'] ?? ($existing['assigned_agent'] ?? 0));
            $lead_stage     = asraa_crm_sanitize_lead_stage(
                $_POST['lead_stage'] ?? ($existing['lead_stage'] ?? 'new')
            );

            $update_data = [
                'assigned_agent' => $assigned_agent ?: null,
                'assigned_to'    => $assigned_agent ?: null,
                'lead_stage'     => $lead_stage,
                'status'         => asraa_crm_get_status_for_stage($lead_stage),
                'last_activity'  => current_time('mysql'),
            ];

            $wa_lead = [
                'property_type' => $existing['property_type'] ?? '',
                'location'      => $existing['location'] ?? '',
                'budget'        => $existing['budget'] ?? '',
            ];

            $update_data['whatsapp_link'] =
                asraa_crm_build_agent_whatsapp_link($wa_lead, $assigned_agent);

            $wpdb->update($table, $update_data, ['id' => $lead_id]);

            $note_parts = ['[SYSTEM] Lead updated'];

            if ((int)($existing['assigned_agent'] ?? 0) !== $assigned_agent) {
                $note_parts[] = 'Agent changed';
            }

            if (($existing['lead_stage'] ?? 'new') !== $lead_stage) {
                $note_parts[] = 'Stage changed to ' . $lead_stage;
            }

            $wpdb->insert($notes, [
                'lead_id'    => $lead_id,
                'note'       => implode(' | ', $note_parts),
                'user_id'    => $user_id,
                'created_at' => current_time('mysql')
            ]);

            asraa_crm_debug_log("Lead updated ID={$lead_id}");
        }
    }

    wp_safe_redirect(
        wp_validate_redirect(
            wp_get_referer(),
            admin_url('admin.php?page=asraa-crm-leads')
        )
    );
    exit;
}

/* =========================================================
   BULK ACTIONS
========================================================= */
if (
    !empty($_POST['bulk_action']) &&
    !empty($_POST['lead_ids']) &&
    is_array($_POST['lead_ids'])
) {
    check_admin_referer('bulk_leads_action');

    $bulk_action = sanitize_text_field($_POST['bulk_action']);

    foreach ($_POST['lead_ids'] as $lead_id) {

        $lead_id = intval($lead_id);

        if ($bulk_action === 'trash') {
            $wpdb->update($table, ['is_deleted' => 1], ['id' => $lead_id]);
        }

        if ($bulk_action === 'restore') {
            $wpdb->update($table, ['is_deleted' => 0], ['id' => $lead_id]);
        }

        if ($bulk_action === 'delete_forever' && $is_admin) {
            $wpdb->delete($table, ['id' => $lead_id]);
        }

        if (!$is_trash && strpos($bulk_action, 'set_group_') === 0) {
            $group_id = intval(str_replace('set_group_', '', $bulk_action));
            if ($group_id > 0) {
                $wpdb->update($table, ['group_id' => $group_id], ['id' => $lead_id]);
            }
        }
    }

    wp_safe_redirect(wp_get_referer());
    exit;
}

/* =========================================================
   FILTERS
========================================================= */
$filter_group_id = intval($_GET['group_id'] ?? 0);

$all_groups = $wpdb->get_results(
    "SELECT id, group_name, color FROM {$groups_table} ORDER BY group_name ASC",
    ARRAY_A
);

$assignable_agents = get_users([
    'capability__in' => ['asraa_manage_leads', 'manage_options'],
    'orderby'        => 'display_name',
    'order'          => 'ASC',
]);

$lead_stages = function_exists('asraa_crm_get_lead_stages')
    ? asraa_crm_get_lead_stages()
    : ['new', 'contacted', 'interested', 'visit_scheduled', 'closed'];

/* =========================================================
   FETCH LEADS
========================================================= */
$where = "WHERE l.is_deleted = " . ($is_trash ? 1 : 0);

if (!$is_trash && $filter_group_id > 0) {
    $where .= $wpdb->prepare(" AND l.group_id = %d", $filter_group_id);
}

$leads = $wpdb->get_results(
    "SELECT l.*, g.group_name, g.color AS group_color, ua.display_name AS assigned_agent_name
     FROM {$table} l
     LEFT JOIN {$groups_table} g ON g.id = l.group_id
     LEFT JOIN {$users_table} ua ON ua.ID = l.assigned_agent
     {$where}
     ORDER BY l.id DESC",
    ARRAY_A
);

$followupRepo = new Asraa_CRM_Followup_Repository();
?>

<div class="wrap">

<a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-leads')); ?>" class="button<?php echo !$is_trash ? ' button-primary' : ''; ?>">Active</a>
<a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-leads&view=trash')); ?>" class="button<?php echo $is_trash ? ' button-primary' : ''; ?>">Trash</a>

<?php if (!$is_trash && !empty($all_groups)): ?>
<form method="get" style="display:inline-block; margin-left:10px;">
    <input type="hidden" name="page" value="asraa-crm-leads">
    <select name="group_id" onchange="this.form.submit()">
        <option value="0">— Filter by group —</option>
        <?php foreach ($all_groups as $g): ?>
            <option value="<?php echo esc_attr($g['id']); ?>" <?php selected($filter_group_id, (int) $g['id']); ?>>
                <?php echo esc_html($g['group_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<hr>

<form method="post">
<?php wp_nonce_field('bulk_leads_action'); ?>

<div id="asraa-bulk-toolbar" style="display:none;">
    <span id="asraa-selected-count"></span>
    <select name="bulk_action">
        <option value="">Bulk Actions</option>
        <?php if (!$is_trash): ?>
            <option value="trash">Move to Trash</option>
            <?php foreach ($all_groups as $g): ?>
                <option value="set_group_<?php echo esc_attr($g['id']); ?>">Assign to: <?php echo esc_html($g['group_name']); ?></option>
            <?php endforeach; ?>
        <?php else: ?>
            <option value="restore">Restore</option>
            <?php if ($is_admin): ?>
                <option value="delete_forever">Delete Forever</option>
            <?php endif; ?>
        <?php endif; ?>
    </select>
    <button class="button">Apply</button>
    <button type="button" id="asraa-deselect-all-btn" class="button">Clear selection</button>
</div>

<div class="leads-table-wrapper">
<table class="leads-table">
<thead>
<tr>
    <th style="width:24px;"><input type="checkbox" id="asraa-select-all" data-testid="leads-select-all"></th>
    <th>Name</th>
    <th>Phone</th>
    <th>Intent</th>
    <th>Location</th>
    <th>Budget</th>
    <th>Property</th>
    <th>Score</th>
    <th>Agent</th>
    <th>Stage</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php if ($leads): ?>
<?php foreach ($leads as $lead): ?>

<tr>
<td>
<input type="checkbox" name="lead_ids[]" class="asraa-row-cb" value="<?php echo esc_attr($lead['id']); ?>">
</td>

<td><?php echo esc_html($lead['name'] ?? ''); ?></td>
<td><?php echo esc_html($lead['phone'] ?? ''); ?></td>
<td><?php echo esc_html(ucfirst($lead['intent'] ?? '')); ?></td>
<td><?php echo esc_html($lead['location'] ?? ''); ?></td>
<td><?php echo !empty($lead['budget']) ? esc_html(asraa_crm_format_currency($lead['budget'])) : '—'; ?></td>
<td><?php echo esc_html($lead['property_type'] ?? ''); ?></td>
<td><?php echo esc_html($lead['lead_score'] ?? 0); ?></td>
<td><?php echo esc_html($lead['assigned_agent_name'] ?? 'Unassigned'); ?></td>
<td><?php echo esc_html($lead['lead_stage'] ?? 'new'); ?></td>

<td>
<span class="row-actions">
<a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-lead-view&lead_id=' . $lead['id'])); ?>" class="button button-small">Open</a>
</span>
</td>

</tr>

<?php endforeach; ?>
<?php else: ?>

<tr>
<td colspan="11">No leads found</td>
</tr>

<?php endif; ?>

</tbody>
</table>
</div>
</form>
</div>