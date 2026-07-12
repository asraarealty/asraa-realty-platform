<?php
if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'Asraa_Property_Publisher_Service' ) ) :

class Asraa_Property_Publisher_Service {

    /**
     * Process an approved broker feed entry and sync it to the production CRM table.
     *
     * @param int $feed_item_id ID of the approved broker feed record.
     * @return bool True on success, false on failure.
     */
    public function publish_from_feed($feed_item_id) {
        $feed_item_id = absint($feed_item_id);
        if ($feed_item_id === 0) {
            error_log('[ASRAA CRM PUBLISHER ERROR] Invalid Feed ID provided.');
            return false;
        }

        // Initialize required architectural layer repositories
        if (!class_exists('Asraa_Broker_Feed_Repository')) {
            error_log('[ASRAA CRM PUBLISHER ERROR] Asraa_Broker_Feed_Repository missing.');
            return false;
        }
        if (!class_exists('Asraa_Property_Repository')) {
            error_log('[ASRAA CRM PUBLISHER ERROR] Asraa_Property_Repository missing.');
            return false;
        }

        $feed_repo     = new Asraa_Broker_Feed_Repository();
        $property_repo = new Asraa_Property_Repository();

        // Retrieve raw approved tracking entry record
        $feed_item = $feed_repo->get_by_id($feed_item_id);
        if (!$feed_item) {
            error_log("[ASRAA CRM PUBLISHER ERROR] Feed record item not found for ID: {$feed_item_id}");
            return false;
        }

        // Validate approval status state criteria before processing
        if (isset($feed_item->approval_status) && $feed_item->approval_status !== 'approved') {
            error_log("[ASRAA CRM PUBLISHER ERROR] Feed item ID {$feed_item_id} cannot be published. Status is not approved.");
            return false;
        }

        // Dynamically fetch public corporate contact markers from WordPress configuration options
        $brand_name  = get_option('asraa_crm_brand_name', 'Asraa Realty');
        $brand_phone = get_option('asraa_crm_brand_phone', '');
        $brand_email = get_option('asraa_crm_brand_email', '');

        // Standardize location strings for backward compatibility with older tables
        $legacy_location = !empty($feed_item->location) ? $feed_item->location : trim(($feed_item->locality ?? '') . ', ' . ($feed_item->city ?? ''));
        $legacy_area     = !empty($feed_item->area) ? $feed_item->area : ($feed_item->carpet_area ?? '');

        // Sanitize and construct payload array
        $property_payload = [
            'title'                  => sanitize_text_field($feed_item->title ?? ''),
            'project_name'           => sanitize_text_field($feed_item->project_name ?? ''),
            'configuration'          => sanitize_text_field($feed_item->configuration ?? ''),
            'property_type'          => sanitize_text_field($feed_item->property_type ?? ''),
            'transaction_type'       => sanitize_text_field($feed_item->transaction_type ?? 'sale'),
            'location'               => sanitize_text_field($legacy_location), // Retained for older database schemas
            'city'                   => sanitize_text_field($feed_item->city ?? ''),
            'locality'               => sanitize_text_field($feed_item->locality ?? ''),
            'area'                   => sanitize_text_field($legacy_area),     // Retained for older database schemas
            'carpet_area'            => sanitize_text_field($feed_item->carpet_area ?? ''),
            'available_units'        => absint($feed_item->available_units ?? 1),
            'price'                  => floatval($feed_item->price ?? 0),
            'image_url'              => esc_url_raw($feed_item->image_url ?? ''),
            'status'                 => sanitize_text_field($feed_item->status ?? 'available'),
            'source_agent_id'        => absint($feed_item->source_agent_id ?? 0),
            
            // Masking: Enforce corporate branding layout data paths to keep backend data protected
            'public_contact_name'    => sanitize_text_field($brand_name),
            'public_contact_phone'   => sanitize_text_field($brand_phone),
            'public_contact_email'   => sanitize_text_field($brand_email),
            'updated_at'             => current_time('mysql')
        ];

        // Search for existing duplicates via newly added repository query utility
        $existing_property_id = $property_repo->find_duplicate_property_by_meta([
            'project_name'    => $property_payload['project_name'],
            'configuration'   => $property_payload['configuration'],
            'carpet_area'     => $property_payload['carpet_area'],
            'price'           => $property_payload['price'],
            'source_agent_id' => $property_payload['source_agent_id']
        ]);

        if ($existing_property_id) {
            error_log("[ASRAA CRM PUBLISHER] Duplicate property detected with ID: {$existing_property_id}. Syncing CRM database entry.");
            $execution_state = $property_repo->update($existing_property_id, $property_payload);
        } else {
            error_log('[ASRAA CRM PUBLISHER] No matching property found. Creating new CRM entry.');
            $execution_state = $property_repo->create($property_payload);
        }

        if (false === $execution_state) {
            error_log("[ASRAA CRM PUBLISHER ERROR] Database action failed for feed ID: {$feed_item_id}");
            return false;
        }

        return true;
    }
}

endif;
