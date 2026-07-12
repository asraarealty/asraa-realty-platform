<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Lead_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_crm_leads';
    }

    public function get_all( $args = [] ) {
        global $wpdb;
        $is_deleted = isset($args['is_deleted']) ? (int) $args['is_deleted'] : 0;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE is_deleted = %d ORDER BY id DESC",
                $is_deleted
            ),
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

    public function get_by_ids( array $ids ) {
        global $wpdb;
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id IN ($placeholders) AND is_deleted = 0",
                ...$ids
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

    public function soft_delete( $id ) {
        return $this->update($id, ['is_deleted' => 1]);
    }

    public function restore( $id ) {
        return $this->update($id, ['is_deleted' => 0]);
    }
}
