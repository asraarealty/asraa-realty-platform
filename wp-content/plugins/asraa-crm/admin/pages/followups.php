<?php
if (!defined('ABSPATH')) exit;

/**
 * ================================================================
 *  FOLLOW-UPS ADMIN PAGE — self-contained controller + view
 * ================================================================
 *  This file is included directly by Asraa_CRM_Admin_Menu::render_page(),
 *  so the required view variables ($leads, $users, $followups, $edit)
 *  must be initialised here BEFORE any markup.
 *
 *  Also handles POST / GET actions (add, update, delete, bulk delete,
 *  mark done / undo) inline so the page works without needing the
 *  admin-menu to route through the controller function.
 *
 *  Fixes v5.0.1:
 *    - Undefined variable $leads / $users / $followups / $edit
 *    - foreach() argument must be of type array|object, null given
 *    - Single + bulk delete not working (handlers never fired)
 */

global $wpdb;

// Guard: initialise all view variables to safe defaults.
$leads     = [];
$users     = [];
$followups = [];
$edit      = null;

// Bail out gracefully if the followups repository class is missing.
if ( ! class_exists( 'Asraa_CRM_Followup_Repository' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Follow-ups module could not load — repository class missing.', 'asraa-crm' ) . '</p></div>';
    return;
}

$repo     = new Asraa_CRM_Followup_Repository();
$user_id  = get_current_user_id();
$is_admin = current_user_can( 'administrator' );

/* =========================
   BULK ACTIONS
========================= */
if (
    ! empty( $_POST['bulk_action'] ) &&
    ! empty( $_POST['followup_ids'] ) &&
    is_array( $_POST['followup_ids'] ) &&
    current_user_can( 'manage_options' )
) {
    check_admin_referer( 'asraa_bulk_followup_delete' );

    $bulk_action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) );
    $ids         = array_map( 'intval', (array) $_POST['followup_ids'] );

    foreach ( $ids as $fid ) {
        if ( $fid <= 0 ) {
            continue;
        }
        if ( 'delete' === $bulk_action ) {
            $repo->delete( $fid );
        } elseif ( 'mark_done' === $bulk_action ) {
            $repo->update( $fid, [ 'is_done' => 1 ] );
        }
    }

    wp_safe_redirect( admin_url( 'admin.php?page=asraa-crm-followups&bulk=1' ) );
    exit;
}

/* =========================
   DELETE SINGLE
========================= */
if (
    ! empty( $_GET['delete'] ) &&
    isset( $_GET['_wpnonce'] ) &&
    wp_verify_nonce(
        sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ),
        'delete_followup_' . (int) $_GET['delete']
    ) &&
    current_user_can( 'manage_options' )
) {
    $repo->delete( (int) $_GET['delete'] );
    wp_safe_redirect( admin_url( 'admin.php?page=asraa-crm-followups&deleted=1' ) );
    exit;
}

/* =========================
   MARK DONE / UNDO
========================= */
if (
    ! empty( $_GET['toggle_done'] ) &&
    isset( $_GET['_wpnonce'] ) &&
    wp_verify_nonce(
        sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ),
        'toggle_done_' . (int) $_GET['toggle_done']
    )
) {
    $f = $repo->get_by_id( (int) $_GET['toggle_done'] );
    if ( $f && is_array( $f ) ) {
        $repo->update( (int) $_GET['toggle_done'], [
            'is_done' => empty( $f['is_done'] ) ? 1 : 0,
        ] );
    }
    wp_safe_redirect(
        wp_get_referer() ?: admin_url( 'admin.php?page=asraa-crm-followups' )
    );
    exit;
}

/* =========================
   ADD FOLLOWUP
========================= */
if ( isset( $_POST['add_followup'] ) ) {
    check_admin_referer( 'asraa_add_followup' );

    $repo->create( [
        'lead_id'     => (int) ( $_POST['lead_id'] ?? 0 ),
        'agent_id'    => (int) ( $_POST['agent_id'] ?? $user_id ),
        'follow_date' => sanitize_text_field( wp_unslash( $_POST['follow_date'] ?? '' ) ),
        'note'        => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
        'is_done'     => 0,
        'created_at'  => current_time( 'mysql' ),
    ] );

    wp_safe_redirect( admin_url( 'admin.php?page=asraa-crm-followups&added=1' ) );
    exit;
}

/* =========================
   UPDATE FOLLOWUP
========================= */
if ( isset( $_POST['update_followup'] ) ) {
    check_admin_referer( 'asraa_update_followup' );

    $repo->update( (int) ( $_POST['id'] ?? 0 ), [
        'lead_id'     => (int) ( $_POST['lead_id'] ?? 0 ),
        'agent_id'    => (int) ( $_POST['agent_id'] ?? $user_id ),
        'follow_date' => sanitize_text_field( wp_unslash( $_POST['follow_date'] ?? '' ) ),
        'note'        => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
        'is_done'     => (int) ( $_POST['is_done'] ?? 0 ),
    ] );

    wp_safe_redirect( admin_url( 'admin.php?page=asraa-crm-followups&updated=1' ) );
    exit;
}

/* =========================
   LOAD DATA (safe fallbacks)
========================= */
$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
if ( $edit_id > 0 ) {
    $edit = $repo->get_by_id( $edit_id );
    if ( ! is_array( $edit ) ) {
        $edit = null;
    }
}

$fetched_followups = $is_admin ? $repo->get_all() : $repo->get_all( $user_id );
$followups         = is_array( $fetched_followups ) ? $fetched_followups : [];

$leads_table   = $wpdb->prefix . 'asraa_crm_leads';
$fetched_leads = $wpdb->get_results(
    "SELECT id, name FROM {$leads_table} WHERE is_deleted = 0 ORDER BY name ASC",
    ARRAY_A
);
$leads = is_array( $fetched_leads ) ? $fetched_leads : [];

$fetched_users = get_users( [
    'role__in' => [ 'administrator', 'editor', 'author' ],
] );
$users = is_array( $fetched_users ) ? $fetched_users : [];
?>

<div class="wrap">
<h1>Follow-ups</h1>

<!-- ADD / EDIT FORM -->
<form method="post">

<?php if (!empty($edit)): ?>
    <?php wp_nonce_field('asraa_update_followup'); ?>
    <input type="hidden" name="update_followup" value="1">
    <input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>">
<?php else: ?>
    <?php wp_nonce_field('asraa_add_followup'); ?>
    <input type="hidden" name="add_followup" value="1">
<?php endif; ?>

<table class="form-table">

<tr>
    <th>Lead</th>
    <td>
        <select name="lead_id" required>
            <?php foreach ($leads as $l): ?>
                <option value="<?php echo (int) $l['id']; ?>"
                    <?php selected($edit['lead_id'] ?? '', $l['id']); ?>>
                    <?php echo esc_html($l['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>

<tr>
    <th>Agent</th>
    <td>
        <select name="agent_id">
            <?php foreach ($users as $u): ?>
                <option value="<?php echo (int) $u->ID; ?>"
                    <?php selected($edit['agent_id'] ?? '', $u->ID); ?>>
                    <?php echo esc_html($u->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>

<tr>
    <th>Date</th>
    <td>
        <input type="date" name="follow_date"
               value="<?php echo esc_attr($edit['follow_date'] ?? ''); ?>" required>
    </td>
</tr>

<tr>
    <th>Note</th>
    <td>
        <textarea name="note" rows="3"><?php echo esc_textarea($edit['note'] ?? ''); ?></textarea>
    </td>
</tr>

<tr>
    <th>Status</th>
    <td>
        <select name="is_done">
            <option value="0" <?php selected($edit['is_done'] ?? 0, 0); ?>>Pending</option>
            <option value="1" <?php selected($edit['is_done'] ?? 0, 1); ?>>Done</option>
        </select>
    </td>
</tr>

</table>

<button class="button button-primary">
    <?php echo !empty($edit) ? 'Update Follow-up' : 'Add Follow-up'; ?>
</button>

</form>

<hr>

<h2>All Follow-ups</h2>

<!-- BULK ACTION FORM -->
<form method="post">
<?php wp_nonce_field('asraa_bulk_followup_delete'); ?>

<div style="margin-bottom:15px;">
    <select name="bulk_action">
        <option value="">Bulk Actions</option>
        <option value="delete">Delete Selected</option>
        <option value="mark_done">Mark Completed</option>
    </select>

    <button type="submit" class="button">Apply</button>
</div>

<table class="widefat striped">
<thead>
<tr>
    <th>
        <input type="checkbox" id="select-all-followups">
    </th>
    <th>ID</th>
    <th>Lead</th>
    <th>Contact</th>
    <th>Date</th>
    <th>Agent</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>

<?php if (!empty($followups)): foreach ($followups as $f): ?>

<?php
$phone = preg_replace('/\D/', '', $f['lead_phone'] ?? '');
$email = $f['lead_email'] ?? '';
?>

<tr>
    <td>
        <input type="checkbox"
               name="followup_ids[]"
               value="<?php echo (int) $f['id']; ?>">
    </td>

    <td><?php echo (int) $f['id']; ?></td>

    <td><?php echo esc_html($f['lead_name']); ?></td>

    <td>
        <?php if ($email): ?>
            <a class="button button-small"
               target="_blank"
               href="https://mail.hostinger.com/?to=<?php echo urlencode($email); ?>">
               📧 Email
            </a>
        <?php endif; ?>

        <?php if ($phone): ?>
            <a class="button button-small"
               target="_blank"
               href="https://wa.me/<?php echo esc_attr($phone); ?>">
               💬 WhatsApp
            </a>

            <a class="button button-small"
               href="tel:<?php echo esc_attr($phone); ?>">
               📞 Call
            </a>
        <?php endif; ?>
    </td>

    <td><?php echo esc_html($f['follow_date']); ?></td>

    <td><?php echo esc_html($f['agent_name']); ?></td>

    <td>
        <?php echo !empty($f['is_done'])
            ? '<span style="color:green;font-weight:bold;">Done</span>'
            : '<span style="color:orange;font-weight:bold;">Pending</span>'; ?>
    </td>

    <td>
        <a class="button button-small"
           href="<?php echo admin_url('admin.php?page=asraa-crm-followups&edit=' . $f['id']); ?>">
           Edit
        </a>

        <a class="button button-small"
           onclick="return confirm('Delete this follow-up?');"
           href="<?php echo esc_url(wp_nonce_url(
               admin_url('admin.php?page=asraa-crm-followups&delete=' . $f['id']),
               'delete_followup_' . $f['id']
           )); ?>">
           Delete
        </a>
    </td>
</tr>

<?php endforeach; else: ?>

<tr>
    <td colspan="8">No follow-ups found.</td>
</tr>

<?php endif; ?>

</tbody>
</table>
</form>
</div>

<script>
document.getElementById('select-all-followups').addEventListener('change', function () {
    document.querySelectorAll('input[name="followup_ids[]"]').forEach(function (checkbox) {
        checkbox.checked = document.getElementById('select-all-followups').checked;
    });
});
</script>