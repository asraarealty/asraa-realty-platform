<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Commission_Repository {

    private $table;
    private $plans_table;

    public function __construct() {
        global $wpdb;
        $this->table       = $wpdb->prefix . 'asraa_crm_commissions';
        $this->plans_table = $wpdb->prefix . 'asraa_crm_commission_plans';
    }

    /* ---------- Plans ---------- */

    public function get_all_plans() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->plans_table} ORDER BY id DESC", ARRAY_A);
    }

    public function get_plan( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->plans_table} WHERE id = %d", (int) $id),
            ARRAY_A
        );
    }

    public function create_plan( array $data ) {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($this->plans_table, $data);
        return $wpdb->insert_id;
    }

    public function update_plan( $id, array $data ) {
        global $wpdb;
        return $wpdb->update($this->plans_table, $data, ['id' => (int) $id]);
    }

    public function delete_plan( $id ) {
        global $wpdb;
        return $wpdb->delete($this->plans_table, ['id' => (int) $id]);
    }

    /* ---------- Commissions ---------- */

    public function get_all( $args = [] ) {
        global $wpdb;
        $where  = '1=1';
        $params = [];

        if (!empty($args['agent_id'])) {
            $where   .= ' AND c.agent_id = %d';
            $params[] = (int) $args['agent_id'];
        }
        if (!empty($args['status'])) {
            $where   .= ' AND c.status = %s';
            $params[] = $args['status'];
        }

        $sql = "SELECT c.*, d.title AS deal_title, u.display_name AS agent_name,
                       p.plan_name
                FROM {$this->table} c
                LEFT JOIN {$wpdb->prefix}asraa_crm_deals d ON d.id = c.deal_id
                LEFT JOIN {$wpdb->users} u ON u.ID = c.agent_id
                LEFT JOIN {$this->plans_table} p ON p.id = c.plan_id
                WHERE {$where}
                ORDER BY c.created_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", (int) $id),
            ARRAY_A
        );
    }

    public function create( array $data ) {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
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

    public function get_agent_summary( $agent_id = null ) {
        global $wpdb;
        if ($agent_id) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT c.agent_id, u.display_name AS agent_name,
                            COUNT(*) AS deal_count,
                            SUM(c.commission_amount) AS total_earned,
                            SUM(CASE WHEN c.status='paid' THEN c.commission_amount ELSE 0 END) AS paid,
                            SUM(CASE WHEN c.status='pending' THEN c.commission_amount ELSE 0 END) AS pending
                     FROM {$this->table} c
                     LEFT JOIN {$wpdb->users} u ON u.ID = c.agent_id
                     WHERE c.agent_id = %d
                     GROUP BY c.agent_id",
                    (int) $agent_id
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT c.agent_id, u.display_name AS agent_name,
                    COUNT(*) AS deal_count,
                    SUM(c.commission_amount) AS total_earned,
                    SUM(CASE WHEN c.status='paid' THEN c.commission_amount ELSE 0 END) AS paid,
                    SUM(CASE WHEN c.status='pending' THEN c.commission_amount ELSE 0 END) AS pending
             FROM {$this->table} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.agent_id
             GROUP BY c.agent_id
             ORDER BY total_earned DESC",
            ARRAY_A
        );
    }
}
