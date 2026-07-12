<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Agent_Hierarchy_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_crm_agent_hierarchy';
    }

    /**
     * Get the full hierarchy tree as a flat list with depth metadata.
     */
    public function get_tree() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT h.*, u.display_name, u.user_email,
                    pm.meta_value AS phone,
                    pu.display_name AS manager_name
             FROM {$this->table} h
             LEFT JOIN {$wpdb->users} u ON u.ID = h.user_id
             LEFT JOIN {$wpdb->usermeta} pm ON pm.user_id = h.user_id AND pm.meta_key = 'phone'
             LEFT JOIN {$this->table} ph ON ph.user_id = h.manager_id
             LEFT JOIN {$wpdb->users} pu ON pu.ID = h.manager_id
             ORDER BY h.level ASC, h.sort_order ASC",
            ARRAY_A
        );
        return $rows;
    }

    public function get_by_user( $user_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT h.*, u.display_name FROM {$this->table} h
                 LEFT JOIN {$wpdb->users} u ON u.ID = h.user_id
                 WHERE h.user_id = %d",
                (int) $user_id
            ),
            ARRAY_A
        );
    }

    public function get_subordinates( $manager_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT h.*, u.display_name, u.user_email
                 FROM {$this->table} h
                 LEFT JOIN {$wpdb->users} u ON u.ID = h.user_id
                 WHERE h.manager_id = %d
                 ORDER BY h.sort_order ASC",
                (int) $manager_id
            ),
            ARRAY_A
        );
    }

    public function upsert( array $data ) {
        global $wpdb;
        $existing = $this->get_by_user($data['user_id']);
        if ($existing) {
            return $wpdb->update($this->table, $data, ['user_id' => (int) $data['user_id']]);
        }
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }

    public function delete( $user_id ) {
        global $wpdb;
        return $wpdb->delete($this->table, ['user_id' => (int) $user_id]);
    }
}
