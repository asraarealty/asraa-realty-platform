<?php
if (!defined('ABSPATH')) exit;

/** @var Asraa_Whatsapp_Template_Repository $repo */
$repo = new Asraa_Whatsapp_Template_Repository();

// Handle create / update
if (isset($_POST['save_template'])) {

    check_admin_referer('asraa_save_template');

    $data = [
        'title'   => sanitize_text_field($_POST['title'] ?? ''),
        'message' => wp_kses_post($_POST['message'] ?? ''),
        'status'  => intval($_POST['status'] ?? 1),
    ];

    if (!empty($_POST['id'])) {
        $repo->update((int) $_POST['id'], $data);
    } else {
        $repo->create($data);
    }

    echo '<div class="notice notice-success"><p>Template saved successfully.</p></div>';
}

// Delete
if (!empty($_GET['delete']) && check_admin_referer('delete_wa_template_' . (int) $_GET['delete'])) {
    $repo->delete((int) $_GET['delete']);
    echo '<div class="notice notice-success"><p>Template deleted.</p></div>';
}

// Edit
$edit = null;
if (!empty($_GET['edit'])) {
    $edit = $repo->get_by_id((int) $_GET['edit']);
}

$templates = $repo->get_all();
?>

<div class="wrap">
    <h1>💬 WhatsApp Templates</h1>
    <p>Create reusable WhatsApp message templates with dynamic variables for quick communication with leads.</p>

    <div class="crm-form-section">
        <h3><?php echo $edit ? 'Edit Template' : 'Create New Template'; ?></h3>

        <form method="post">
            <?php wp_nonce_field('asraa_save_template'); ?>

            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($edit['id']); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th>Template Title</th>
                    <td>
                        <input type="text" name="title" class="regular-text"
                               value="<?php echo esc_attr($edit['title'] ?? ''); ?>" required>
                    </td>
                </tr>

                <tr>
                    <th>Message</th>
                    <td>
                        <textarea name="message" rows="5" class="large-text"><?php
                            echo esc_textarea($edit['message'] ?? '');
                        ?></textarea>
                        <div style="margin-top: 10px; padding: 12px; background: #f0f0f0; border-left: 4px solid #25D366;">
                            <strong>Available Variables:</strong>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                                <code>{name}</code>
                                <code>{phone}</code>
                                <code>{email}</code>
                                <code>{budget}</code>
                                <code>{property_type}</code>
                                <code>{preferred_locations}</code>
                                <code>{timeline}</code>
                                <code>{agent_name}</code>
                            </div>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th>Status</th>
                    <td>
                        <select name="status">
                            <option value="1" <?php selected($edit['status'] ?? 1, 1); ?>>Active</option>
                            <option value="0" <?php selected($edit['status'] ?? 1, 0); ?>>Inactive</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" name="save_template" class="button button-primary">
                    <?php echo $edit ? 'Update Template' : 'Add Template'; ?>
                </button>
                <?php if ($edit): ?>
                    <a href="?page=asraa-crm-whatsapp" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <hr>

    <h2>📋 Saved Templates</h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Preview</th>
                <th>Status</th>
                <th width="180">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($templates): foreach ($templates as $t): ?>
            <tr>
                <td><?php echo esc_html($t['id']); ?></td>
                <td><strong><?php echo esc_html($t['title']); ?></strong></td>
                <td><small><?php echo esc_html(wp_trim_words($t['message'], 15)); ?></small></td>
                <td>
                    <?php if ($t['status']): ?>
                        <span style="color: green; font-weight: 600;">● Active</span>
                    <?php else: ?>
                        <span style="color: #999;">○ Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?page=asraa-crm-whatsapp&edit=<?php echo $t['id']; ?>" class="button button-small">✏️ Edit</a>
                    <a href="<?php echo wp_nonce_url('?page=asraa-crm-whatsapp&delete=' . $t['id'], 'delete_wa_template_' . $t['id']); ?>"
                       class="button button-small"
                       onclick="return confirm('Delete this template?')">🗑️ Delete</a>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5">No templates found. Create your first template above!</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
