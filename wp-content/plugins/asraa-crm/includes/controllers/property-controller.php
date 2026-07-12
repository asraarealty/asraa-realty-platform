<?php
if (!defined('ABSPATH')) exit;

class Asraa_Property_Controller {

    private $service;

    public function __construct() {
        $this->service = new Asraa_Property_Service();
        $this->register_ajax_hooks();
    }

    private function register_ajax_hooks() {
        add_action('wp_ajax_asraa_save_property',   [$this, 'ajax_save']);
        add_action('wp_ajax_asraa_update_property', [$this, 'ajax_update']);
        add_action('wp_ajax_asraa_delete_property', [$this, 'ajax_delete']);
    }

    public function properties_page() {
        $properties = $this->service->get_all();
        include ASRAA_CRM_PATH . 'admin/pages/properties.php';
    }

    public function ajax_save() {
        asraa_crm_verify_ajax_nonce();
        asraa_crm_require_ajax_cap();

        $data = $this->sanitize_property_data($_POST);

        if (empty($data['title'])) {
            error_log('Asraa CRM: ajax_save_property – missing title');
            wp_send_json_error(['message' => 'Property title is required.']);
        }

        $id = $this->service->save($data);

        if (!$id) {
            error_log('Asraa CRM: ajax_save_property – DB insert failed');
            wp_send_json_error(['message' => 'Failed to save property. Please try again.']);
        }

        asraa_crm_debug_log('Asraa CRM: property created id=' . $id);
        asraa_crm_fire_trigger('property_created', ['id' => $id, 'data' => $data]);

        wp_send_json_success(['id' => $id, 'message' => 'Property saved successfully.']);
    }

    public function ajax_update() {
        asraa_crm_verify_ajax_nonce();
        asraa_crm_require_ajax_cap();

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            error_log('Asraa CRM: ajax_update_property – missing property ID');
            wp_send_json_error(['message' => 'Invalid property ID.']);
        }

        $data   = $this->sanitize_property_data($_POST);
        $result = $this->service->save(array_merge($data, ['id' => $id]));

        if (!$result) {
            error_log('Asraa CRM: ajax_update_property – save failed for id=' . $id);
            wp_send_json_error(['message' => 'Failed to update property. Please try again.']);
        }

        asraa_crm_debug_log('Asraa CRM: property updated id=' . $id);
        asraa_crm_fire_trigger('property_updated', ['id' => $id, 'data' => $data]);

        wp_send_json_success(['message' => 'Property updated successfully.']);
    }

    public function ajax_delete() {
        asraa_crm_verify_ajax_nonce();
        asraa_crm_require_ajax_cap();

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            error_log('Asraa CRM: ajax_delete_property – missing property ID');
            wp_send_json_error(['message' => 'Invalid property ID.']);
        }

        $this->service->delete($id);

        asraa_crm_debug_log('Asraa CRM: property deleted id=' . $id);
        asraa_crm_fire_trigger('property_deleted', ['id' => $id]);

        wp_send_json_success(['message' => 'Property deleted successfully.']);
    }

    private function sanitize_property_data( array $input ) {
        return [
            'title'            => sanitize_text_field($input['title'] ?? ''),
            'transaction_type' => sanitize_text_field($input['transaction_type'] ?? ''),
            'property_type'    => sanitize_text_field($input['property_type'] ?? ''),
            'builder_name'     => sanitize_text_field($input['builder_name'] ?? ''),
            'city'             => sanitize_text_field($input['city'] ?? ''),
            'price'            => floatval($input['price'] ?? 0),
            'status'           => sanitize_text_field($input['status'] ?? 'available'),
            'image_url'        => esc_url_raw($input['image_url'] ?? ''),
        ];
    }
}
