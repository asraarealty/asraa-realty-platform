<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Note_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_crm_notes';
    }

    public function get_for_lead( $lead_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.*, u.display_name AS agent_name
                 FROM {$this->table} n
                 LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id
                 WHERE n.lead_id = %d
                 ORDER BY n.created_at DESC",
                (int) $lead_id
            ),
            ARRAY_A
        );
    }

    public function get_all() {
        global $wpdb;
        $leads_table = $wpdb->prefix . 'asraa_crm_leads';
        return $wpdb->get_results(
            "SELECT n.*, l.name AS lead_name, u.display_name AS agent_name
             FROM {$this->table} n
             LEFT JOIN {$leads_table} l ON l.id = n.lead_id
             LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id
             ORDER BY n.created_at DESC",
            ARRAY_A
        );
    }

    public function create( array $data ) {
        global $wpdb;
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }

    public function delete( $id ) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => (int) $id]);
    }
}
