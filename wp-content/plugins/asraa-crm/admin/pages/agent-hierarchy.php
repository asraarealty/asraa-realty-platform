<?php
if (!defined('ABSPATH')) exit;

$repo   = new Asraa_CRM_Agent_Hierarchy_Repository();
$tree   = $repo->get_tree();
$agents = get_users(['role__in' => ['administrator', 'editor', 'agent', 'subscriber']]);

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asraa_hierarchy_nonce'])) {
    check_admin_referer('asraa_hierarchy_save', 'asraa_hierarchy_nonce');
    if (current_user_can('manage_options')) {
        $user_id    = (int) $_POST['user_id'];
        $manager_id = !empty($_POST['manager_id']) ? (int) $_POST['manager_id'] : null;
        $role       = sanitize_text_field($_POST['agent_role'] ?? 'agent');
        $level      = (int) sanitize_text_field($_POST['level'] ?? 1);

        $repo->upsert([
            'user_id'    => $user_id,
            'manager_id' => $manager_id,
            'role'       => $role,
            'level'      => $level,
            'sort_order' => 0,
        ]);
        echo '<div class="notice notice-success is-dismissible"><p>Hierarchy updated.</p></div>';
        $tree = $repo->get_tree();
    }
}

// Handle delete
if (isset($_GET['remove_user']) && check_admin_referer('asraa_remove_hierarchy')) {
    if (current_user_can('manage_options')) {
        $repo->delete((int) $_GET['remove_user']);
        echo '<div class="notice notice-success is-dismissible"><p>Agent removed from hierarchy.</p></div>';
        $tree = $repo->get_tree();
    }
}

$role_labels = [
    'ceo'          => '👑 CEO / Owner',
    'director'     => '🏢 Director',
    'team_leader'  => '👥 Team Leader',
    'senior_agent' => '⭐ Senior Agent',
    'agent'        => '🧑‍💼 Agent',
    'junior_agent' => '🔰 Junior Agent',
];

// Build hierarchy tree indexed by manager
$by_manager = [];
foreach ($tree as $node) {
    $mid = $node['manager_id'] ?? 0;
    $by_manager[$mid][] = $node;
}

if ( ! function_exists( 'asraa_render_hierarchy_node' ) ) {
function asraa_render_hierarchy_node($nodes, $by_manager, $role_labels, $depth = 0) {
    if ($depth > 20) return; // guard against circular relationships
    foreach ($nodes as $node) {
        $pad    = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        $arrow  = $depth > 0 ? '└─ ' : '';
        $role   = $role_labels[$node['role']] ?? $node['role'];
        $remove_url = wp_nonce_url(
            admin_url('admin.php?page=asraa-crm-hierarchy&remove_user=' . $node['user_id']),
            'asraa_remove_hierarchy'
        );
        echo "<tr>
            <td>{$pad}{$arrow}<strong>" . esc_html($node['display_name']) . "</strong></td>
            <td>" . esc_html($role) . "</td>
            <td>" . esc_html($node['manager_name'] ?? '—') . "</td>
            <td>Level " . (int)$node['level'] . "</td>
            <td>" . esc_html($node['user_email']) . "</td>
            <td><a class='button button-small' href='" . esc_url($remove_url) . "' onclick='return confirm(\"Remove?\")'>Remove</a></td>
        </tr>";
        if (!empty($by_manager[$node['user_id']])) {
            asraa_render_hierarchy_node($by_manager[$node['user_id']], $by_manager, $role_labels, $depth + 1);
        }
    }
}
}
?>

<div class="wrap">
<h1>Agent Hierarchy</h1>
<p style="color:#555;">Define the multi-level organizational structure of your agents and teams.</p>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start;">

<!-- ADD / EDIT FORM -->
<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;">
<h3>Add / Update Agent</h3>
<form method="POST">
<?php wp_nonce_field('asraa_hierarchy_save', 'asraa_hierarchy_nonce'); ?>
<table class="form-table" style="margin:0;">
<tr><th style="padding:8px 4px;">Agent</th><td>
<select name="user_id" required style="width:100%;">
<?php foreach ($agents as $u): ?>
<option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th style="padding:8px 4px;">Reports To</th><td>
<select name="manager_id" style="width:100%;">
<option value="">-- Top Level --</option>
<?php foreach ($agents as $u): ?>
<option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th style="padding:8px 4px;">Role</th><td>
<select name="agent_role" style="width:100%;">
<?php foreach ($role_labels as $key => $label): ?>
<option value="<?php echo $key; ?>"><?php echo $label; ?></option>
<?php endforeach; ?>
</select></td></tr>
<tr><th style="padding:8px 4px;">Level</th><td>
<input type="number" name="level" min="1" max="10" value="1" style="width:80px;"></td></tr>
</table>
<p><button type="submit" class="button button-primary">Save</button></p>
</form>
</div>

<!-- HIERARCHY TREE -->
<div>
<h3>Organization Chart</h3>
<table class="wp-list-table widefat fixed striped">
<thead><tr>
<th>Agent</th><th>Role</th><th>Reports To</th><th>Level</th><th>Email</th><th>Actions</th>
</tr></thead>
<tbody>
<?php
$top_level = $by_manager[0] ?? ($by_manager[''] ?? []);
if (!empty($top_level)) {
    asraa_render_hierarchy_node($top_level, $by_manager, $role_labels, 0);
} else {
    echo '<tr><td colspan="6" style="text-align:center;color:#999;">No agents added yet. Use the form to add agents.</td></tr>';
}
?>
</tbody>
</table>
</div>

</div>
</div>
