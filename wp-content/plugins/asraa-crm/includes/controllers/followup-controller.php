<?php
if (!defined('ABSPATH')) exit;

function asraa_crm_followups() {
    global $wpdb;

    $repo     = new Asraa_CRM_Followup_Repository();
    $user_id  = get_current_user_id();
    $is_admin = current_user_can('administrator');

    /* =========================
       BULK ACTIONS
    ========================= */
    if (
        !empty($_POST['bulk_action']) &&
        !empty($_POST['followup_ids']) &&
        is_array($_POST['followup_ids'])
    ) {
        check_admin_referer('asraa_bulk_followup_delete');

        $bulk_action = sanitize_text_field($_POST['bulk_action']);

        foreach ($_POST['followup_ids'] as $id) {
            $id = (int) $id;

            if ($bulk_action === 'delete') {
                $repo->delete($id);
            }

            if ($bulk_action === 'mark_done') {
                $repo->update($id, [
                    'is_done' => 1
                ]);
            }
        }

        wp_redirect(admin_url('admin.php?page=asraa-crm-followups'));
        exit;
    }

    /* =========================
       DELETE SINGLE
    ========================= */
    if (
        !empty($_GET['delete']) &&
        wp_verify_nonce(
            $_GET['_wpnonce'] ?? '',
            'delete_followup_' . (int) $_GET['delete']
        )
    ) {
        $repo->delete((int) $_GET['delete']);

        wp_redirect(admin_url('admin.php?page=asraa-crm-followups'));
        exit;
    }

    /* =========================
       MARK DONE / UNDO
    ========================= */
    if (
        !empty($_GET['toggle_done']) &&
        wp_verify_nonce(
            $_GET['_wpnonce'] ?? '',
            'toggle_done_' . (int) $_GET['toggle_done']
        )
    ) {
        $f = $repo->get_by_id((int) $_GET['toggle_done']);

        if ($f) {
            $repo->update((int) $_GET['toggle_done'], [
                'is_done' => $f['is_done'] ? 0 : 1
            ]);
        }

        wp_redirect(
            wp_get_referer() ?: admin_url('admin.php?page=asraa-crm-followups')
        );
        exit;
    }

    /* =========================
       ADD FOLLOWUP
    ========================= */
    if (isset($_POST['add_followup'])) {
        check_admin_referer('asraa_add_followup');

        $repo->create([
            'lead_id'     => (int) $_POST['lead_id'],
            'agent_id'    => (int) ($_POST['agent_id'] ?? $user_id),
            'follow_date' => sanitize_text_field($_POST['follow_date'] ?? ''),
            'note'        => sanitize_textarea_field($_POST['note'] ?? ''),
            'is_done'     => 0,
        ]);

        wp_redirect(admin_url('admin.php?page=asraa-crm-followups'));
        exit;
    }

    /* =========================
       UPDATE FOLLOWUP
    ========================= */
    if (isset($_POST['update_followup'])) {
        check_admin_referer('asraa_update_followup');

        $repo->update((int) $_POST['id'], [
            'lead_id'     => (int) $_POST['lead_id'],
            'agent_id'    => (int) ($_POST['agent_id'] ?? $user_id),
            'follow_date' => sanitize_text_field($_POST['follow_date'] ?? ''),
            'note'        => sanitize_textarea_field($_POST['note'] ?? ''),
            'is_done'     => (int) ($_POST['is_done'] ?? 0),
        ]);

        wp_redirect(admin_url('admin.php?page=asraa-crm-followups'));
        exit;
    }

    /* =========================
       LOAD DATA
    ========================= */
    $edit = !empty($_GET['edit'])
        ? $repo->get_by_id((int) $_GET['edit'])
        : null;

    $followups = $is_admin
        ? $repo->get_all()
        : $repo->get_all($user_id);

    $leads_table = $wpdb->prefix . 'asraa_crm_leads';

    $leads = $wpdb->get_results(
        "SELECT id, name
         FROM {$leads_table}
         WHERE is_deleted = 0
         ORDER BY name ASC",
        ARRAY_A
    );

    $users = get_users([
        'role__in' => ['administrator', 'editor', 'author']
    ]);

    include ASRAA_CRM_PATH . 'admin/pages/followups.php';
}