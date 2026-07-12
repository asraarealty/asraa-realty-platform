<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to manage groups.', 'asraa-crm'));
}

global $wpdb;

$groups_table = $wpdb->prefix . 'asraa_crm_groups';
$group_id     = (int) ($_GET['group_id'] ?? 0);
$error        = '';

if (!$group_id) {
    wp_redirect(admin_url('admin.php?page=asraa-crm-groups'));
    exit;
}

$group = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$groups_table} WHERE id = %d",
        $group_id
    ),
    ARRAY_A
);

if (!$group) {
    wp_die(esc_html__('Group not found.', 'asraa-crm'));
}

/* ============================================================
   HANDLE FORM SUBMIT
============================================================ */
if (isset($_POST['save_group'])) {

    check_admin_referer('asraa_edit_group_' . $group_id);

    $group_name  = sanitize_text_field($_POST['group_name'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $color       = sanitize_hex_color($_POST['color'] ?? '#6b7280');

    if (empty($group_name)) {
        $error = esc_html__('Group name is required.', 'asraa-crm');
    } else {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$groups_table} WHERE group_name = %s AND id != %d",
                $group_name,
                $group_id
            )
        );

        if ($existing) {
            $error = esc_html__('A group with this name already exists.', 'asraa-crm');
        } else {
            $wpdb->update(
                $groups_table,
                [
                    'group_name'  => $group_name,
                    'description' => $description,
                    'color'       => $color ?: '#6b7280',
                    'updated_at'  => current_time('mysql'),
                ],
                ['id' => $group_id]
            );
            asraa_crm_fire_trigger('group_updated', [
                'id'         => $group_id,
                'group_name' => $group_name,
                'color'      => $color ?: '#6b7280',
            ]);
            wp_redirect(admin_url('admin.php?page=asraa-crm-groups&saved=1'));
            exit;
        }
    }
}

// Use submitted values on error, otherwise use DB values.
$form = [
    'group_name'  => isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : $group['group_name'],
    'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : $group['description'],
    'color'       => isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : $group['color'],
];
?>

<div class="wrap">
<h1><?php esc_html_e('Edit Group', 'asraa-crm'); ?></h1>

<?php if ($error): ?>
    <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
<?php endif; ?>

<form method="post">
    <?php wp_nonce_field('asraa_edit_group_' . $group_id); ?>

    <table class="form-table asraa-group-form-table">
        <tr>
            <th scope="row"><label for="group_name"><?php esc_html_e('Group Name', 'asraa-crm'); ?></label></th>
            <td>
                <input type="text" id="group_name" name="group_name" class="regular-text" required
                       value="<?php echo esc_attr($form['group_name']); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="description"><?php esc_html_e('Description', 'asraa-crm'); ?></label></th>
            <td>
                <textarea id="description" name="description" class="large-text" rows="3"><?php
                    echo esc_textarea($form['description']);
                ?></textarea>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="color"><?php esc_html_e('Color', 'asraa-crm'); ?></label></th>
            <td>
                <input type="color" id="color" name="color"
                       value="<?php echo esc_attr($form['color'] ?: '#6b7280'); ?>">
                <span class="description"><?php esc_html_e('Choose a color for this group.', 'asraa-crm'); ?></span>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit" name="save_group" class="button button-primary">
            <?php esc_html_e('Update Group', 'asraa-crm'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-groups')); ?>" class="button">
            <?php esc_html_e('Cancel', 'asraa-crm'); ?>
        </a>
    </p>
</form>
</div>
