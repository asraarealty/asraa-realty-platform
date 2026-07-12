<?php
if (!defined('ABSPATH')) exit;

/**
 * Safe defaults — this view is included directly by the admin menu,
 * so no controller has necessarily populated $notes yet.
 */
if ( ! isset( $notes ) || ! is_array( $notes ) ) {
    global $wpdb;
    $notes_table = $wpdb->prefix . 'asraa_crm_notes';
    $leads_table = $wpdb->prefix . 'asraa_crm_leads';
    $users_table = $wpdb->users;

    // Only query if the notes table actually exists; otherwise fall back to empty.
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $notes_table ) );
    if ( $exists ) {
        $notes = $wpdb->get_results(
            "SELECT n.*, l.name AS lead_name, u.display_name AS agent_name
             FROM {$notes_table} n
             LEFT JOIN {$leads_table} l ON l.id = n.lead_id
             LEFT JOIN {$users_table} u ON u.ID = n.user_id
             ORDER BY n.id DESC
             LIMIT 200",
            ARRAY_A
        );
    }
    if ( ! is_array( $notes ) ) {
        $notes = [];
    }
}
?>

<div class="wrap">
    <h1>Notes</h1>

    <?php if (!empty($notes)) : ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th>Note</th>
                    <th>Lead</th>
                    <th>Added By</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($notes as $note) : ?>
                    <tr>
                        <td><?php echo esc_html($note['id']); ?></td>

                        <td style="max-width:400px;">
                            <?php echo esc_html($note['note']); ?>
                        </td>

                        <td><?php echo esc_html($note['lead_name'] ?? '—'); ?></td>

                        <td><?php echo esc_html($note['agent_name'] ?? '—'); ?></td>

                        <td><?php echo esc_html(date('d M Y, h:i A', strtotime($note['created_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else : ?>
        <p>No notes found.</p>
    <?php endif; ?>
</div>
