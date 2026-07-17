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
        add_action('wp_ajax_asraa_import_site_listing', [$this, 'ajax_import_from_listing']);
    }

    public function properties_page() {
        $properties = $this->service->get_all();

        // Site listings available to import from — the real `property` post
        // type from the wp-realestate plugin, not this CRM's own table.
        $site_listings = get_posts([
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'numberposts'    => 300,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        include ASRAA_CRM_PATH . 'admin/pages/properties.php';
    }

    /**
     * Pre-fill data for the "Import from existing listing" dropdown.
     * Only returns fields that map cleanly onto the CRM property form —
     * doesn't auto-save anything, the admin still reviews and clicks Save.
     */
    public function ajax_import_from_listing() {
        asraa_crm_verify_ajax_nonce();
        asraa_crm_require_ajax_cap();

        $post_id = intval($_POST['post_id'] ?? 0);
        $post = $post_id ? get_post($post_id) : null;

        if (!$post || $post->post_type !== 'property' || $post->post_status !== 'publish') {
            wp_send_json_error(['message' => 'Listing not found.']);
        }

        $price    = get_post_meta($post_id, '_property_price', true);
        $location = get_post_meta($post_id, '_property_map_location_address', true);

        $type_terms    = get_the_terms($post_id, 'property_type');
        $property_type = (!is_wp_error($type_terms) && !empty($type_terms)) ? $type_terms[0]->name : '';

        // property_status terms are admin-defined on the live site (no fixed
        // slugs in code) — match Rent vs Sale by keyword rather than assuming
        // an exact slug, so this doesn't break if the terms are renamed.
        $status_terms     = get_the_terms($post_id, 'property_status');
        $transaction_type = '';
        if (!is_wp_error($status_terms) && !empty($status_terms)) {
            foreach ($status_terms as $term) {
                $needle = strtolower($term->slug . ' ' . $term->name);
                if (strpos($needle, 'rent') !== false) {
                    $transaction_type = 'rent';
                    break;
                }
                if (strpos($needle, 'sale') !== false || strpos($needle, 'sell') !== false || strpos($needle, 'buy') !== false) {
                    $transaction_type = 'sale';
                    break;
                }
            }
        }

        $existing = $this->service->find_by_source_post_id($post_id);

        wp_send_json_success([
            'post_id'          => $post_id,
            'title'            => $post->post_title,
            'price'            => $price ?: '',
            'location'         => $location ?: '',
            'property_type'    => $property_type,
            'transaction_type' => $transaction_type,
            'already_imported' => $existing ? (int) $existing['id'] : 0,
        ]);
    }

    public function ajax_save() {
        asraa_crm_verify_ajax_nonce();
        asraa_crm_require_ajax_cap();

        $data = $this->sanitize_property_data($_POST);

        if (empty($data['title'])) {
            error_log('Asraa CRM: ajax_save_property – missing title');
            wp_send_json_error(['message' => 'Property title is required.']);
        }

        if (!empty($data['source_post_id'])) {
            $existing = $this->service->find_by_source_post_id($data['source_post_id']);
            if ($existing) {
                wp_send_json_error(['message' => 'This listing was already imported as Property #' . $existing['id'] . '.']);
            }
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

        $data = $this->sanitize_property_data($_POST);

        if (!empty($data['source_post_id'])) {
            $existing = $this->service->find_by_source_post_id($data['source_post_id']);
            if ($existing && (int) $existing['id'] !== $id) {
                wp_send_json_error(['message' => 'This listing was already imported as Property #' . $existing['id'] . '.']);
            }
        }

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
            'location'         => sanitize_text_field($input['location'] ?? ''),
            'price'            => floatval($input['price'] ?? 0),
            'status'           => sanitize_text_field($input['status'] ?? 'available'),
            'image_url'        => esc_url_raw($input['image_url'] ?? ''),
            'source_post_id'   => intval($input['source_post_id'] ?? 0) ?: null,
        ];
    }
}
