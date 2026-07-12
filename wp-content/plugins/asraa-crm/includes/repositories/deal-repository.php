<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Deal_Repository {

    /**
     * Ordered list of deal pipeline stages used for sorting and display.
     * Add or reorder here and the change propagates to all query ORDER BY clauses.
     */
    const STAGE_ORDER = ['prospect', 'negotiation', 'contract', 'closed_won', 'closed_lost'];

    private $table;
    private $activities_table;

    public function __construct() {
        global $wpdb;
        $this->table            = $wpdb->prefix . 'asraa_crm_deals';
        $this->activities_table = $wpdb->prefix . 'asraa_crm_deal_activities';
    }

    public function get_all( $args = [] ) {
        global $wpdb;
        $where = '1=1';
        $params = [];

        if (!empty($args['agent_id'])) {
            $where   .= ' AND d.agent_id = %d';
            $params[] = (int) $args['agent_id'];
        }
        if (!empty($args['stage'])) {
            $where   .= ' AND d.stage = %s';
            $params[] = $args['stage'];
        }
        if (!empty($args['lead_id'])) {
            $where   .= ' AND d.lead_id = %d';
            $params[] = (int) $args['lead_id'];
        }

        $sql = "SELECT d.*, l.name AS lead_name, u.display_name AS agent_name
                FROM {$this->table} d
                LEFT JOIN {$wpdb->prefix}asraa_crm_leads l ON l.id = d.lead_id
                LEFT JOIN {$wpdb->users} u ON u.ID = d.agent_id
                WHERE {$where}
                ORDER BY d.created_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT d.*, l.name AS lead_name, u.display_name AS agent_name
                 FROM {$this->table} d
                 LEFT JOIN {$wpdb->prefix}asraa_crm_leads l ON l.id = d.lead_id
                 LEFT JOIN {$wpdb->users} u ON u.ID = d.agent_id
                 WHERE d.id = %d",
                (int) $id
            ),
            ARRAY_A
        );
    }

    public function create( array $data ) {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }

    public function update( $id, array $data ) {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($this->table, $data, ['id' => (int) $id]);
    }

    public function delete( $id ) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => (int) $id]);
    }

    public function add_activity( array $data ) {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($this->activities_table, $data);
        return $wpdb->insert_id;
    }

    public function get_activities( $deal_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name AS user_name
                 FROM {$this->activities_table} a
                 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                 WHERE a.deal_id = %d
                 ORDER BY a.created_at DESC",
                (int) $deal_id
            ),
            ARRAY_A
        );
    }

    public function get_pipeline_summary() {
        global $wpdb;
        $stages_csv = implode("','", array_map('esc_sql', self::STAGE_ORDER));
        return $wpdb->get_results(
            "SELECT stage, COUNT(*) AS count, SUM(deal_value) AS total_value
             FROM {$this->table}
             GROUP BY stage
             ORDER BY FIELD(stage,'{$stages_csv}')",
            ARRAY_A
        );
    }
}
