<?php
if (!defined('ABSPATH')) exit;

$repo = new Asraa_Email_Template_Repository();

// Handle create / update
if (isset($_POST['save_template'])) {
    check_admin_referer('asraa_save_email_template');

    $data = [
        'title'    => sanitize_text_field($_POST['title'] ?? ''),
        'subject'  => sanitize_text_field($_POST['subject'] ?? ''),
        'body'     => wp_kses_post($_POST['body'] ?? ''),
        'category' => sanitize_text_field($_POST['category'] ?? ''),
        'status'   => intval($_POST['status'] ?? 1),
    ];

    if (!empty($_POST['id'])) {
        $repo->update((int) $_POST['id'], $data);
        echo '<div class="notice notice-success"><p>✅ Template updated successfully.</p></div>';
    } else {
        $repo->create($data);
        echo '<div class="notice notice-success"><p>✅ Template created successfully.</p></div>';
    }
}

// Delete
if (!empty($_GET['delete'])) {
    check_admin_referer('delete_template_' . $_GET['delete']);
    $repo->delete((int) $_GET['delete']);
    echo '<div class="notice notice-success"><p>✅ Template deleted.</p></div>';
}

// Edit
$edit = null;
if (!empty($_GET['edit'])) {
    $edit = $repo->get_by_id((int) $_GET['edit']);
}

$templates = $repo->get_all();
?>

<div class="wrap">
    <p>Create reusable email templates with dynamic variables for quick communication with leads.</p>

    <div class="crm-form-section">
        <h3><?php echo $edit ? 'Edit Template' : 'Create New Template'; ?></h3>
        
        <form method="post">
            <?php wp_nonce_field('asraa_save_email_template'); ?>

            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?php echo esc_attr($edit['id']); ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-field">
                    <label>Template Title *</label>
                    <input type="text" name="title" class="regular-text"
                           value="<?php echo esc_attr($edit['title'] ?? ''); ?>" required>
                    <p class="form-field-hint">Internal name for this template</p>
                </div>

                <div class="form-field">
                    <label>Category</label>
                    <select name="category">
                        <option value="">-- Select Category --</option>
                        <option value="welcome" <?php selected($edit['category'] ?? '', 'welcome'); ?>>Welcome</option>
                        <option value="followup" <?php selected($edit['category'] ?? '', 'followup'); ?>>Follow-up</option>
                        <option value="reminder" <?php selected($edit['category'] ?? '', 'reminder'); ?>>Reminder</option>
                        <option value="property" <?php selected($edit['category'] ?? '', 'property'); ?>>Property Info</option>
                        <option value="other" <?php selected($edit['category'] ?? '', 'other'); ?>>Other</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="1" <?php selected($edit['status'] ?? 1, 1); ?>>Active</option>
                        <option value="0" <?php selected($edit['status'] ?? 1, 0); ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-field" style="margin-top: 15px;">
                <label>Email Subject *</label>
                <input type="text" name="subject" class="large-text"
                       value="<?php echo esc_attr($edit['subject'] ?? ''); ?>" required>
            </div>

            <div class="form-field" style="margin-top: 15px;">
                <label>Email Body *</label>
                <textarea name="body" rows="12" class="large-text" required><?php
                    echo esc_textarea($edit['body'] ?? '');
                ?></textarea>
                
                <div style="margin-top: 10px; padding: 15px; background: #f0f0f0; border-left: 4px solid #2271b1;">
                    <strong>Available Variables:</strong>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px;">
                        <code>{name}</code>
                        <code>{email}</code>
                        <code>{phone}</code>
                        <code>{budget_min}</code>
                        <code>{budget_max}</code>
                        <code>{property_type}</code>
                        <code>{preferred_locations}</code>
                        <code>{timeline}</code>
                        <code>{agent_name}</code>
                        <code>{agent_email}</code>
                        <code>{agent_phone}</code>
                    </div>
                </div>
            </div>

            <p class="submit" style="margin-top: 20px;">
                <button type="submit" name="save_template" class="button button-primary">
                    <?php echo $edit ? 'Update Template' : 'Create Template'; ?>
                </button>
                <?php if ($edit): ?>
                    <a href="?page=asraa-crm-email-templates" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <hr>

    <h2>📋 Saved Templates</h2>

    <div class="leads-table-wrapper">
        <table class="leads-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th width="200">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($templates): foreach ($templates as $t): ?>
                <tr>
                    <td><?php echo esc_html($t['id']); ?></td>
                    <td><strong><?php echo esc_html($t['title']); ?></strong></td>
                    <td><?php echo esc_html($t['subject']); ?></td>
                    <td><?php echo esc_html(ucfirst($t['category'] ?? 'N/A')); ?></td>
                    <td>
                        <?php if ($t['status']): ?>
                            <span style="color: green; font-weight: 600;">● Active</span>
                        <?php else: ?>
                            <span style="color: #999;">○ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="row-actions">
                        <a href="?page=asraa-crm-email-templates&edit=<?php echo $t['id']; ?>"
                           class="button button-small">✏️ Edit</a>
                        <a href="?page=asraa-crm-email-templates&delete=<?php echo $t['id']; ?>&_wpnonce=<?php echo wp_create_nonce('delete_template_' . $t['id']); ?>"
                           class="button button-small"
                           onclick="return confirm('Are you sure you want to delete this template?')">🗑️ Delete</a>
                        </span>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center">No templates found. Create your first template above!</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
        <h3 style="margin-top: 0;">💡 Pro Tips</h3>
        <ul>
            <li><strong>Use variables:</strong> Personalize emails automatically with lead data</li>
            <li><strong>Keep it concise:</strong> Busy clients prefer short, clear emails</li>
            <li><strong>Test templates:</strong> Send test emails to yourself first</li>
            <li><strong>Track performance:</strong> Note which templates get the best responses</li>
            <li><strong>A/B test:</strong> Create variations and see what works</li>
        </ul>
    </div>
</div>
