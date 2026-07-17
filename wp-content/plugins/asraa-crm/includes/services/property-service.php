<?php
if (!defined('ABSPATH')) exit;

class Asraa_Property_Service {

    private $repo;

    public function __construct() {
        $this->repo = new Asraa_Property_Repository();
    }

    public function get_all() {
        return $this->repo->get_all();
    }

    public function get_by_id( $id ) {
        return $this->repo->get_by_id($id);
    }

    public function find_by_source_post_id( $post_id ) {
        return $this->repo->get_by_source_post_id($post_id);
    }

    public function save( array $data ) {
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            unset($data['id']);
            $this->repo->update($id, $data);
            return $id;
        }
        return $this->repo->create($data);
    }

    public function delete( $id ) {
        return $this->repo->delete($id);
    }
}
