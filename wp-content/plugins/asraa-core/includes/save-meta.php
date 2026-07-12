<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Save Property Meta Data
|--------------------------------------------------------------------------
*/

function asraa_save_property_meta($post_id) {

    /*
    |--------------------------------------------------------------------------
    | Security Checks
    |--------------------------------------------------------------------------
    */

    if (!isset($_POST['asraa_property_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['asraa_property_nonce'], 'asraa_property_nonce_action')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Core Media Fields
    |--------------------------------------------------------------------------
    */

    $media_fields = [
        'asraa_gallery',
        'asraa_brochure',
        'asraa_model_3d',
        'asraa_hero_video',
    ];

    foreach ($media_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta(
                $post_id,
                $field,
                sanitize_text_field($_POST[$field])
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Static Offer Fields
    |--------------------------------------------------------------------------
    */

    $offer_fields = [
        'developer_name',
        'monthly_scheme',
        'discount_offer',
        'inventory_status',
        'offer_popup_text',
    ];

    foreach ($offer_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta(
                $post_id,
                '_' . $field,
                sanitize_textarea_field($_POST[$field])
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Dynamic Field Engine Fields
    |--------------------------------------------------------------------------
    */

    $dynamic_fields = get_option('asraa_property_fields', []);

    if (!empty($dynamic_fields)) {
        foreach ($dynamic_fields as $field) {

            if (empty($field['key'])) {
                continue;
            }

            $field_key = sanitize_key($field['key']);

            if (!isset($_POST[$field_key])) {
                continue;
            }

            $field_type = $field['type'] ?? 'text';
            $value = $_POST[$field_key];

            switch ($field_type) {

                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;

                case 'url':
                    $value = esc_url_raw($value);
                    break;

                case 'number':
                    $value = sanitize_text_field($value);
                    break;

                case 'gallery':
                case 'file':
                case 'video':
                    $value = sanitize_text_field($value);
                    break;

                default:
                    $value = sanitize_text_field($value);
                    break;
            }

            update_post_meta(
                $post_id,
                $field_key,
                $value
            );
        }
    }
}

add_action('save_post_property', 'asraa_save_property_meta');