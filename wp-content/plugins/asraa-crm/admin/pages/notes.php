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

    <?php if (!empty($notes)) : ?>

        <div class="leads-table-wrapper">
        <table class="leads-table">
            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th>Note</th>
                    <th>Lead</th>
                    <th>Added By</th>
                    <th>Date</th>
                    <th width="80">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($notes as $note) : ?>
                    <tr data-id="<?php echo esc_attr($note['id']); ?>">
                        <td><?php echo esc_html($note['id']); ?></td>

                        <td style="max-width:400px;">
                            <?php echo esc_html($note['note']); ?>
                        </td>

                        <td><?php echo esc_html($note['lead_name'] ?? '—'); ?></td>

                        <td><?php echo esc_html($note['agent_name'] ?? '—'); ?></td>

                        <td><?php echo esc_html(date('d M Y, h:i A', strtotime($note['created_at']))); ?></td>

                        <td>
                            <span class="row-actions">
                                <button type="button" class="button button-small asraa-note-delete" data-id="<?php echo esc_attr($note['id']); ?>">Delete</button>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php else : ?>
        <p>No notes found.</p>
    <?php endif; ?>
</div>

<script>
(function($){
    const nonce = '<?php echo esc_js( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>';
    // ajaxurl is already defined globally by WP core on every wp-admin page.

    $(document).on('click', '.asraa-note-delete', function(){
        if (!confirm('Delete this note?')) return;

        const id  = $(this).data('id');
        const row = $(this).closest('tr');

        $.post(ajaxurl, { action: 'asraa_delete_note', note_id: id, nonce: nonce }, function(res){
            if (res.success) {
                row.fadeOut(150, function(){ row.remove(); });
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
            }
        }).fail(function(){
            alert('AJAX request failed.');
        });
    });
})(jQuery);
</script>
