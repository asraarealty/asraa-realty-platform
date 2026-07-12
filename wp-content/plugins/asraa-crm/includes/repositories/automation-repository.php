<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Automation_Repository {

    private $table;
    private $logs_table;

    public function __construct() {
        global $wpdb;
        $this->table      = $wpdb->prefix . 'asraa_crm_automation_rules';
        $this->logs_table = $wpdb->prefix . 'asraa_crm_automation_logs';
    }

    public function get_all( $active_only = false ) {
        global $wpdb;
        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM {$this->table} {$where} ORDER BY id DESC",
            ARRAY_A
        );
    }

    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", (int) $id),
            ARRAY_A
        );
    }

    public function get_by_trigger( $trigger_event ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE trigger_event = %s AND is_active = 1",
                $trigger_event
            ),
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

    public function log_execution( array $data ) {
        global $wpdb;
        $data['executed_at'] = current_time('mysql');
        $wpdb->insert($this->logs_table, $data);
        return $wpdb->insert_id;
    }

    public function get_logs( $rule_id = null, $limit = 50 ) {
        global $wpdb;
        if ($rule_id) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.*, r.rule_name FROM {$this->logs_table} l
                     LEFT JOIN {$this->table} r ON r.id = l.rule_id
                     WHERE l.rule_id = %d
                     ORDER BY l.executed_at DESC LIMIT %d",
                    (int) $rule_id, (int) $limit
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, r.rule_name FROM {$this->logs_table} l
                 LEFT JOIN {$this->table} r ON r.id = l.rule_id
                 ORDER BY l.executed_at DESC LIMIT %d",
                (int) $limit
            ),
            ARRAY_A
        );
    }
}
