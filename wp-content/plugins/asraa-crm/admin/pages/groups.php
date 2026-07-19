<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to manage groups.', 'asraa-crm'));
}

global $wpdb;

$groups_table = $wpdb->prefix . 'asraa_crm_groups';
$leads_table  = $wpdb->prefix . 'asraa_crm_leads';

/* ============================================================
   HANDLE DELETE
============================================================ */
if (
    !empty($_GET['delete_group']) &&
    wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_group_' . (int) $_GET['delete_group'])
) {
    $group_id = (int) $_GET['delete_group'];

    $lead_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$leads_table} WHERE group_id = %d",
            $group_id
        )
    );

    if ($lead_count > 0) {
        $delete_error = sprintf(
            /* translators: %d: number of leads in this group */
            esc_html__('Cannot delete: %d lead(s) are assigned to this group. Reassign them first.', 'asraa-crm'),
            $lead_count
        );
    } else {
        $wpdb->delete($groups_table, ['id' => $group_id]);
        wp_redirect(admin_url('admin.php?page=asraa-crm-groups&deleted=1'));
        exit;
    }
}

/* ============================================================
   FETCH GROUPS WITH LEAD COUNT
============================================================ */
$groups = $wpdb->get_results(
    "SELECT g.*, COUNT(l.id) AS lead_count
     FROM {$groups_table} g
     LEFT JOIN {$leads_table} l ON l.group_id = g.id AND l.is_deleted = 0
     GROUP BY g.id
     ORDER BY g.id ASC",
    ARRAY_A
);
?>

<div class="wrap">
<p><a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-groups-add')); ?>" class="page-title-action">Add New Group</a></p>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group deleted.', 'asraa-crm'); ?></p></div>
<?php endif; ?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Group saved.', 'asraa-crm'); ?></p></div>
<?php endif; ?>

<?php if (!empty($delete_error)): ?>
    <div class="notice notice-error"><p><?php echo esc_html($delete_error); ?></p></div>
<?php endif; ?>

<?php if (!empty($groups)): ?>
<div class="asraa-groups-grid">
    <?php foreach ($groups as $group): ?>
    <?php
        $color      = !empty($group['color']) ? $group['color'] : '#6b7280';
        $lead_count = (int) $group['lead_count'];
    ?>
    <div class="asraa-group-card">
        <span class="asraa-group-card-color" style="background:<?php echo esc_attr($color); ?>;"></span>
        <h3><?php echo esc_html($group['group_name']); ?></h3>
        <?php if (!empty($group['description'])): ?>
            <p class="asraa-group-desc"><?php echo esc_html($group['description']); ?></p>
        <?php else: ?>
            <p class="asraa-group-desc">&nbsp;</p>
        <?php endif; ?>
        <span class="asraa-group-count"><?php echo esc_html($lead_count); ?> contact(s)</span>
        <div class="asraa-group-card-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-groups-edit&group_id=' . $group['id'])); ?>"
               class="button button-small">Edit</a>
            <a href="<?php echo esc_url(
                wp_nonce_url(
                    admin_url('admin-post.php?action=asraa_crm_export_group_leads&group_id=' . $group['id']),
                    'asraa_crm_export_group_' . $group['id']
                )
            ); ?>"
               class="button button-small"
               title="<?php esc_attr_e('Export this group\'s leads to CSV', 'asraa-crm'); ?>">
                Export
            </a>
            <?php if ($lead_count === 0): ?>
                <a href="<?php echo esc_url(
                    wp_nonce_url(
                        admin_url('admin.php?page=asraa-crm-groups&delete_group=' . $group['id']),
                        'delete_group_' . $group['id']
                    )
                ); ?>"
                   class="button button-small"
                   onclick="return confirm('<?php esc_attr_e('Delete this group?', 'asraa-crm'); ?>');">
                    Delete
                </a>
            <?php else: ?>
                <?php
                $delete_blocked_msg = sprintf(
                    /* translators: %d: number of contacts currently in this group */
                    __("Can't delete \u{2014} reassign or remove this group's %d contact(s) first.", 'asraa-crm'),
                    $lead_count
                );
                ?>
                <span class="button button-small"
                      title="<?php echo esc_attr($delete_blocked_msg); ?>"
                      style="opacity:0.5; cursor:not-allowed;"
                      onclick="alert('<?php echo esc_js($delete_blocked_msg); ?>'); return false;">Delete</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="asraa-groups-empty">
    <span class="dashicons dashicons-groups"></span>
    <p><?php esc_html_e('No groups found. Create your first group.', 'asraa-crm'); ?></p>
    <a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-groups-add')); ?>" class="button button-primary">
        <?php esc_html_e('Add Group', 'asraa-crm'); ?>
    </a>
</div>
<?php endif; ?>
</div>
