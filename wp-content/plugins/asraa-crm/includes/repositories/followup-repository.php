<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Followup_Repository {

    private $table;
    private $leads_table;

    public function __construct() {
        global $wpdb;
        $this->table       = $wpdb->prefix . 'asraa_crm_followups';
        $this->leads_table = $wpdb->prefix . 'asraa_crm_leads';
    }

    public function get_all( $agent_id = null ) {
        global $wpdb;
        if ($agent_id) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT f.*, l.name AS lead_name, l.email AS lead_email, l.phone AS lead_phone,
                            u.display_name AS agent_name
                     FROM {$this->table} f
                     LEFT JOIN {$this->leads_table} l ON l.id = f.lead_id
                     LEFT JOIN {$wpdb->users} u ON u.ID = f.agent_id
                     WHERE f.agent_id = %d
                     ORDER BY f.follow_date ASC",
                    $agent_id
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT f.*, l.name AS lead_name, l.email AS lead_email, l.phone AS lead_phone,
                    u.display_name AS agent_name
             FROM {$this->table} f
             LEFT JOIN {$this->leads_table} l ON l.id = f.lead_id
             LEFT JOIN {$wpdb->users} u ON u.ID = f.agent_id
             ORDER BY f.follow_date ASC",
            ARRAY_A
        );
    }

    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                (int) $id
            ),
            ARRAY_A
        );
    }

    public function get_for_lead( $lead_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, u.display_name AS agent_name
                 FROM {$this->table} f
                 LEFT JOIN {$wpdb->users} u ON u.ID = f.agent_id
                 WHERE f.lead_id = %d
                 ORDER BY f.follow_date DESC",
                (int) $lead_id
            ),
            ARRAY_A
        );
    }

    public function create( array $data ) {
        global $wpdb;
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }

    public function update( $id, array $data ) {
        global $wpdb;
        return $wpdb->update($this->table, $data, ['id' => (int) $id]);
    }

    public function delete( $id ) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => (int) $id]);
    }

    public function get_lead_health( $lead_id ) {
        global $wpdb;
        $last_activity = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT last_activity FROM {$this->leads_table}
                 WHERE id = %d
                 LIMIT 1",
                (int) $lead_id
            )
        );

        if ( empty( $last_activity ) ) {
            return 'at_risk';
        }

        $days_since = ( time() - strtotime( $last_activity ) ) / 86400;
        if ( $days_since > 5 ) {
            return 'overdue';
        }
        if ( $days_since > 2 ) {
            return 'at_risk';
        }
        return 'healthy';
    }
}
