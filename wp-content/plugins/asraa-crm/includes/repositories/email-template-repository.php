<?php
if (!defined('ABSPATH')) exit;

class Asraa_Email_Template_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_crm_email_templates';
    }

    public function get_all( $active_only = false ) {
        global $wpdb;
        if ($active_only) {
            return $wpdb->get_results(
                "SELECT * FROM {$this->table} WHERE status = 1 ORDER BY id DESC",
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY id DESC",
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
}
