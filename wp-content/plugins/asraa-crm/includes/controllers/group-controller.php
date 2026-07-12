<?php
if (!defined('ABSPATH')) exit;

/* ============================================================
   GROUP AJAX CONTROLLER
   Registers wp_ajax_* handlers for group (segment) operations.
   Standard POST form submissions (groups-add.php, groups-edit.php)
   continue to work alongside these AJAX handlers.
============================================================ */

add_action('wp_ajax_asraa_save_group',   'asraa_ajax_save_group');
add_action('wp_ajax_asraa_delete_group', 'asraa_ajax_delete_group');
add_action('wp_ajax_asraa_get_groups',   'asraa_ajax_get_groups');

/* ------------------------------------------------------------
   SAVE (create or update) GROUP
------------------------------------------------------------ */
function asraa_ajax_save_group() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_admin_cap();

    global $wpdb;
    $table = $wpdb->prefix . 'asraa_crm_groups';

    $id          = intval($_POST['id'] ?? 0);
    $group_name  = sanitize_text_field($_POST['group_name'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $color       = asraa_crm_sanitize_color($_POST['color'] ?? '');

    if (empty($group_name)) {
        wp_send_json_error(['message' => 'Group name is required.']);
    }

    // Uniqueness check – ignore the current row on updates.
    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE group_name = %s AND id != %d",
            $group_name,
            $id
        )
    );
    if ($exists) {
        wp_send_json_error(['message' => 'A group with this name already exists.']);
    }

    $row = [
        'group_name'  => $group_name,
        'description' => $description,
        'color'       => $color,
        'updated_at'  => current_time('mysql'),
    ];

    if ($id) {
        $result = $wpdb->update($table, $row, ['id' => $id]);

        if ($result === false) {
            error_log('Asraa CRM: ajax_save_group update failed for id=' . $id . ' – ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Database error while updating group.']);
        }

        error_log('Asraa CRM: group updated id=' . $id);
        asraa_crm_fire_trigger('group_updated', ['id' => $id, 'data' => $row]);
        wp_send_json_success(['id' => $id, 'message' => 'Group updated successfully.']);
    } else {
        $row['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $row);

        if ($result === false) {
            error_log('Asraa CRM: ajax_save_group insert failed – ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Database error while creating group.']);
        }

        $new_id = (int) $wpdb->insert_id;
        error_log('Asraa CRM: group created id=' . $new_id);
        asraa_crm_fire_trigger('group_created', ['id' => $new_id, 'data' => $row]);
        wp_send_json_success(['id' => $new_id, 'message' => 'Group created successfully.']);
    }
}

/* ------------------------------------------------------------
   DELETE GROUP
------------------------------------------------------------ */
function asraa_ajax_delete_group() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_admin_cap();

    global $wpdb;
    $groups_table = $wpdb->prefix . 'asraa_crm_groups';
    $leads_table  = $wpdb->prefix . 'asraa_crm_leads';
    $id           = intval($_POST['id'] ?? 0);

    if (!$id) {
        wp_send_json_error(['message' => 'Invalid group ID.']);
    }

    $lead_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$leads_table} WHERE group_id = %d AND is_deleted = 0",
            $id
        )
    );

    if ($lead_count > 0) {
        wp_send_json_error([
            'message' => sprintf(
                'Cannot delete: %d lead(s) are assigned to this group. Reassign them first.',
                $lead_count
            ),
        ]);
    }

    $wpdb->delete($groups_table, ['id' => $id]);

    error_log('Asraa CRM: group deleted id=' . $id);
    asraa_crm_fire_trigger('group_deleted', ['id' => $id]);

    wp_send_json_success(['message' => 'Group deleted.']);
}

/* ------------------------------------------------------------
   GET GROUPS (for dropdowns / selects)
------------------------------------------------------------ */
function asraa_ajax_get_groups() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    global $wpdb;
    $table  = $wpdb->prefix . 'asraa_crm_groups';
    $groups = $wpdb->get_results(
        "SELECT id, group_name, color FROM {$table} ORDER BY group_name ASC",
        ARRAY_A
    );

    wp_send_json_success($groups ?: []);
}
