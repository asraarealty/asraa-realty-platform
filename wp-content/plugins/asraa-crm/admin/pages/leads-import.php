<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to import leads.', 'asraa-crm'));
}

global $wpdb;
$table        = $wpdb->prefix . 'asraa_crm_leads';
$groups_table = $wpdb->prefix . 'asraa_crm_groups';

$imported = 0;
$skipped  = 0;

/**
 * Look up or create a group by name.
 * Falls back to the "Client" group id (or the first group) when $name is empty.
 *
 * @param string $name Group name from CSV (may be empty).
 * @return int|null group id, or null if no groups exist at all.
 */
if ( ! function_exists( 'asraa_crm_get_or_create_group' ) ) {
function asraa_crm_get_or_create_group( $name ) {
    global $wpdb;
    $groups_table = $wpdb->prefix . 'asraa_crm_groups';

    // Fallback: use "Client" when no group name provided.
    if ('' === $name) {
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$groups_table} WHERE group_name = %s LIMIT 1",
                'Client'
            )
        );
        if ($id) {
            return (int) $id;
        }
        // If Client doesn't exist, use the first available group.
        $id = $wpdb->get_var("SELECT id FROM {$groups_table} ORDER BY id ASC LIMIT 1");
        return $id ? (int) $id : null;
    }

    // Look for an exact (case-insensitive) match.
    $id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$groups_table} WHERE LOWER(group_name) = LOWER(%s) LIMIT 1",
            $name
        )
    );

    if ($id) {
        return (int) $id;
    }

    // Auto-create the group.
    $wpdb->insert(
        $groups_table,
        [
            'group_name'  => $name,
            'description' => '',
            'color'       => '#6b7280',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ]
    );

    return (int) $wpdb->insert_id;
}
} // end function_exists guard

if (isset($_POST['import_csv'])) {

    check_admin_referer('asraa_import_leads');

    if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {

        $file = fopen($_FILES['csv']['tmp_name'], 'r'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

        // Read and normalise header row.
        $raw_header    = fgetcsv($file);
        $header        = array_map('strtolower', array_map('trim', $raw_header));
        $required_cols = ['name', 'email', 'phone'];

        // Validate that required columns are present (group is optional).
        $missing = array_diff($required_cols, $header);
        if (!empty($missing)) {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('Invalid CSV format. Required columns: name, email, phone. Optional: group', 'asraa-crm')
                . '</p></div>';
        } else {

            // Build column-index map for flexible column ordering.
            $col = array_flip($header);

            while (($row = fgetcsv($file)) !== false) {

                $name  = sanitize_text_field($row[ $col['name']  ] ?? '');
                $email = sanitize_email($row[ $col['email'] ] ?? '');
                $phone = sanitize_text_field($row[ $col['phone'] ] ?? '');

                // Group column is optional.
                $group_name = '';
                if (isset($col['group'], $row[ $col['group'] ])) {
                    $group_name = sanitize_text_field(trim($row[ $col['group'] ]));
                }

                // Skip empty rows.
                if ('' === $name && '' === $email) {
                    $skipped++;
                    continue;
                }

                // Prevent duplicate by email (only when email provided).
                if ('' !== $email) {
                    $exists = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table} WHERE email = %s",
                            $email
                        )
                    );

                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                $group_id = asraa_crm_get_or_create_group($group_name);

                $insert_data = [
                    'name'        => $name,
                    'email'       => $email,
                    'phone'       => $phone,
                    'status'      => 'new',
                    'assigned_to' => get_current_user_id(),
                    'created_at'  => current_time('mysql'),
                ];

                if (null !== $group_id) {
                    $insert_data['group_id'] = $group_id;
                }

                $wpdb->insert($table, $insert_data);

                $imported++;
            }

            fclose($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

            echo '<div class="notice notice-success"><p>';
            /* translators: 1: imported count, 2: skipped count */
            printf(
                esc_html__('Imported: %1$d leads | Skipped: %2$d', 'asraa-crm'),
                $imported,
                $skipped
            );
            echo '</p></div>';
        }
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Import Leads (CSV)', 'asraa-crm'); ?></h1>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('asraa_import_leads'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('CSV File', 'asraa-crm'); ?></th>
                <td>
                    <input type="file" name="csv" accept=".csv" required>
                </td>
            </tr>
        </table>

        <p>
            <button class="button button-primary" name="import_csv">
                <?php esc_html_e('Import Leads', 'asraa-crm'); ?>
            </button>
        </p>
    </form>

    <h3><?php esc_html_e('CSV Format', 'asraa-crm'); ?></h3>
    <p>
        <?php esc_html_e('Required columns:', 'asraa-crm'); ?>
        <code>name, email, phone</code><br>
        <?php esc_html_e('Optional column:', 'asraa-crm'); ?>
        <code>group</code>
        &mdash; <?php esc_html_e('Assign the lead to a group. Auto-creates the group if it does not exist. Defaults to "Client" if omitted.', 'asraa-crm'); ?>
    </p>
    <p><strong><?php esc_html_e('Example:', 'asraa-crm'); ?></strong></p>
    <pre>name,email,phone,group
Rahul Sharma,rahul@email.com,9876543210,Client
Ajay Mehta,ajay@email.com,9876540000,Agent
XYZ Developers,contact@xyz.com,9898989898,Developer</pre>
</div>
