<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$lead_id = isset( $_GET['lead_id'] ) ? absint( wp_unslash( $_GET['lead_id'] ) ) : 0;
if ( ! $lead_id ) {
    echo '<div class="wrap"><h1>Lead Details</h1><div class="notice notice-warning"><p>' . esc_html__( 'No lead ID was provided. Please choose a lead from the list and try again.', 'asraa-crm' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=asraa-crm-leads' ) ) . '">Return to leads</a></p></div></div>';
    return;
}

$lead = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}asraa_crm_leads WHERE id = %d",
        $lead_id
    ),
    ARRAY_A
);

if ( ! $lead ) {
    echo '<div class="wrap"><h1>Lead Details</h1><div class="notice notice-warning"><p>' . esc_html__( 'The requested lead could not be found.', 'asraa-crm' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=asraa-crm-leads' ) ) . '">Return to leads</a></p></div></div>';
    return;
}

// Permission
if (
    ! current_user_can( 'administrator' ) &&
    (int) ( $lead['assigned_to'] ?? 0 ) !== get_current_user_id() &&
    (int) ( $lead['assigned_agent'] ?? 0 ) !== get_current_user_id()
) {
    echo '<div class="wrap"><h1>Lead Details</h1><div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this lead.', 'asraa-crm' ) . '</p></div></div>';
    return;
}

$phone_raw = trim( $lead['phone'] ?? '' );
$phone_wa  = preg_replace( '/\D/', '', $phone_raw );
$email     = trim( $lead['email'] ?? '' );
$assignable_agents = get_users([
    'capability__in' => ['asraa_manage_leads', 'manage_options'],
    'orderby'        => 'display_name',
    'order'          => 'ASC',
]);
$assignable_agents = is_array( $assignable_agents ) ? $assignable_agents : array();
$lead_stages = asraa_crm_get_lead_stages();
$lead_stages = is_array( $lead_stages ) ? $lead_stages : array();

// Fetch message history
$msg_log_repo = new Asraa_CRM_Message_Log_Repository();
$msg_history  = $msg_log_repo->get_for_lead( $lead_id );
$msg_history  = is_array( $msg_history ) ? $msg_history : array();
?>

<div class="wrap">
<h2>👤 <?php echo esc_html($lead['name']); ?></h2>

<form id="asraa-lead-edit-form">
    <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead_id); ?>">
    <input type="hidden" name="action" value="asraa_update_lead">
    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce('asraa_crm_nonce') ); ?>">

    <table class="form-table">
        <tr>
            <th>Name</th>
            <td>
                <input type="text" name="name" class="regular-text"
                       value="<?php echo esc_attr($lead['name']); ?>" required>
            </td>
        </tr>
        <tr>
            <th>Email</th>
            <td>
                <input type="email" name="email" class="regular-text"
                       value="<?php echo esc_attr($email); ?>">
            </td>
        </tr>
        <tr>
            <th>Phone</th>
            <td>
                <input type="text" name="phone" class="regular-text"
                       value="<?php echo esc_attr($phone_raw); ?>">
            </td>
        </tr>
        <tr>
            <th>Intent</th>
            <td>
                <select name="intent">
                    <option value="">Select intent</option>
                    <option value="buy" <?php selected($lead['intent'] ?? '', 'buy'); ?>>Buy</option>
                    <option value="sell" <?php selected($lead['intent'] ?? '', 'sell'); ?>>Sell</option>
                    <option value="invest" <?php selected($lead['intent'] ?? '', 'invest'); ?>>Invest</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>Location</th>
            <td>
                <input type="text" name="location" class="regular-text"
                       value="<?php echo esc_attr($lead['location'] ?? ''); ?>"
                       placeholder="e.g. Mira Road, Thane">
            </td>
        </tr>
        <tr>
            <th>Budget</th>
            <td>
                <input type="text" name="budget" class="regular-text"
                       value="<?php echo esc_attr($lead['budget'] ?? ''); ?>"
                       placeholder="e.g. 15000000">
            </td>
        </tr>
        <tr>
            <th>Property Type</th>
            <td>
                <input type="text" name="property_type" class="regular-text"
                       value="<?php echo esc_attr($lead['property_type'] ?? ''); ?>"
                       placeholder="e.g. Apartment">
            </td>
        </tr>
        <tr>
            <th>Assign Agent</th>
            <td>
                <select name="assigned_agent">
                    <option value="0">Unassigned</option>
                    <?php foreach ($assignable_agents as $agent): ?>
                        <option value="<?php echo esc_attr($agent->ID); ?>" <?php selected((int)($lead['assigned_agent'] ?? 0), (int)$agent->ID); ?>>
                            <?php echo esc_html($agent->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th>Stage</th>
            <td>
                <select name="lead_stage">
                    <?php foreach ($lead_stages as $stage): ?>
                        <option value="<?php echo esc_attr($stage); ?>" <?php selected($lead['lead_stage'] ?? 'new', $stage); ?>>
                            <?php echo esc_html(ucwords(str_replace('_', ' ', $stage))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <p>
        <button type="submit" class="button button-primary">💾 Save Changes</button>
        <span id="asraa-lead-save-msg" style="margin-left:10px;"></span>
    </p>
</form>

<hr>

<h2>📲 Quick Actions</h2>

<div style="display:flex; gap:10px; flex-wrap:wrap;">

    <?php if ($phone_wa): ?>
    <button class="button button-primary" id="asraa-lv-wa-btn"
            style="background:#25D366; border-color:#1a9e4f;"
            data-lead-id="<?php echo esc_attr($lead_id); ?>"
            data-lead-name="<?php echo esc_attr($lead['name']); ?>">
        💬 WhatsApp Message
    </button>
    <?php else: ?>
    <button class="button" disabled title="No phone number">💬 WhatsApp (no phone)</button>
    <?php endif; ?>

    <?php if ($email): ?>
    <button class="button button-primary" id="asraa-lv-email-btn"
            data-lead-id="<?php echo esc_attr($lead_id); ?>"
            data-lead-name="<?php echo esc_attr($lead['name']); ?>"
            data-lead-email="<?php echo esc_attr($email); ?>">
        ✉️ Send Email
    </button>
    <?php else: ?>
    <button class="button" disabled title="No email address">✉️ Email (no email)</button>
    <?php endif; ?>

</div>

<hr>

<h2>📨 Message History</h2>

<?php if ($msg_history): ?>
<table class="widefat striped">
    <thead>
        <tr>
            <th>Type</th>
            <th>Recipient</th>
            <th>Subject / Preview</th>
            <th>Sent By</th>
            <th>Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($msg_history as $msg): ?>
        <tr>
            <td>
                <?php if ($msg['message_type'] === 'whatsapp'): ?>
                    <span style="color:#25D366; font-weight:600;">💬 WhatsApp</span>
                <?php else: ?>
                    <span style="color:#2271b1; font-weight:600;">✉️ Email</span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($msg['recipient'] ?: '—'); ?></td>
            <td>
                <?php if ($msg['subject']): ?>
                    <strong><?php echo esc_html($msg['subject']); ?></strong><br>
                <?php endif; ?>
                <small><?php echo esc_html(wp_trim_words($msg['content'], 12)); ?></small>
            </td>
            <td><?php echo esc_html($msg['sent_by_name'] ?? '—'); ?></td>
            <td><?php echo esc_html(date('d M Y, h:i A', strtotime($msg['sent_at']))); ?></td>
            <td>
                <?php echo $msg['status'] === 'sent'
                    ? '<span style="color:green;">✓ Sent</span>'
                    : '<span style="color:red;">✗ Failed</span>'; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p><em>No messages sent yet.</em></p>
<?php endif; ?>

</div>

<!-- WHATSAPP COMPOSE DIALOG -->
<div id="asraa-lv-wa-dialog" style="display:none;" class="asraa-dialog-overlay">
    <div class="asraa-dialog-box">
        <h2>💬 WhatsApp – <?php echo esc_html($lead['name']); ?></h2>
        <p style="color:#555;">Phone: <strong><?php echo esc_html($phone_raw ?: 'N/A'); ?></strong></p>

        <label><strong>Template</strong></label>
        <select id="asraa-lv-wa-template" style="width:100%; margin-bottom:10px;">
            <option value="">-- Custom Message --</option>
        </select>

        <label><strong>Image URL</strong> <span style="font-weight:400; color:#888;">(optional – share a property image)</span></label>
        <input type="url" id="asraa-lv-wa-image" placeholder="https://example.com/image.jpg"
               style="width:100%; margin-bottom:10px; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">

        <label><strong>Message</strong></label>
        <textarea id="asraa-lv-wa-message" rows="6" style="width:100%;"></textarea>
        <p style="color:#888; font-size:12px;">
            Variables: {name} {phone} {budget} {property_type} {preferred_locations} {agent_name}
        </p>

        <div style="margin-top:12px; display:flex; gap:8px;">
            <button id="asraa-lv-wa-send-btn" class="button button-primary"
                    style="background:#25D366; border-color:#1a9e4f;">
                Open WhatsApp
            </button>
            <button id="asraa-lv-wa-cancel-btn" class="button">Cancel</button>
        </div>
        <div id="asraa-lv-wa-msg" style="margin-top:8px;"></div>
    </div>
</div>

<!-- EMAIL COMPOSE DIALOG -->
<div id="asraa-lv-email-dialog" style="display:none;" class="asraa-dialog-overlay">
    <div class="asraa-dialog-box">
        <h2>✉️ Email – <?php echo esc_html($lead['name']); ?></h2>
        <p style="color:#555;">To: <strong><?php echo esc_html($email ?: 'N/A'); ?></strong></p>

        <label><strong>Template</strong></label>
        <select id="asraa-lv-email-template" style="width:100%; margin-bottom:10px;">
            <option value="">-- Custom Message --</option>
        </select>

        <label><strong>Subject</strong></label>
        <input type="text" id="asraa-lv-email-subject" style="width:100%; margin-bottom:10px;">

        <label><strong>Message</strong></label>
        <textarea id="asraa-lv-email-body" rows="6" style="width:100%;"></textarea>
        <p style="color:#888; font-size:12px;">
            Variables: {name} {email} {phone} {budget} {property_type} {preferred_locations} {agent_name}
        </p>

        <div style="margin-top:12px; display:flex; gap:8px;">
            <button id="asraa-lv-email-send-btn" class="button button-primary">Send Email</button>
            <button id="asraa-lv-email-cancel-btn" class="button">Cancel</button>
        </div>
        <div id="asraa-lv-email-msg" style="margin-top:8px;"></div>
    </div>
</div>

<script>
/* Pass the current lead ID to the shared JS module */
window.asraaCRM = window.asraaCRM || {};
asraaCRM.leadViewId = <?php echo (int) $lead_id; ?>;
</script>
