<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Stage_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_crm_stages';
    }

    public function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY sort_order ASC",
            OBJECT
        );
    }

    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                (int) $id
            ),
            OBJECT
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
}
