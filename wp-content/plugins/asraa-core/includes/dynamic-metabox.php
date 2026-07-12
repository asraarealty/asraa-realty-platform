<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Dynamic Property Fields Metabox
|--------------------------------------------------------------------------
*/

function asraa_register_dynamic_property_metabox() {
    add_meta_box(
        'asraa_dynamic_property_fields',
        'Dynamic Property Fields',
        'asraa_dynamic_property_metabox_callback',
        'property',
        'normal',
        'default'
    );
}

add_action('add_meta_boxes', 'asraa_register_dynamic_property_metabox');

/*
|--------------------------------------------------------------------------
| Render Dynamic Fields
|--------------------------------------------------------------------------
*/

function asraa_dynamic_property_metabox_callback($post) {

    $fields = get_option('asraa_property_fields', []);

    if (empty($fields)) {
        echo '<p>No custom fields created yet.</p>';
        return;
    }

    wp_nonce_field(
        'asraa_dynamic_fields_nonce_action',
        'asraa_dynamic_fields_nonce'
    );

    echo '<table class="form-table">';

    foreach ($fields as $field) {

        if (empty($field['key'])) {
            continue;
        }

        $field_key   = $field['key'];
        $field_label = $field['label'];
        $field_type  = $field['type'];

        $value = get_post_meta(
            $post->ID,
            $field_key,
            true
        );

        echo '<tr>';
        echo '<th><label>' . esc_html($field_label) . '</label></th>';
        echo '<td>';

        switch ($field_type) {

            case 'textarea':
                echo '<textarea 
                    name="' . esc_attr($field_key) . '" 
                    rows="4" 
                    class="widefat">'
                    . esc_textarea($value) .
                '</textarea>';
                break;

            case 'number':
                echo '<input 
                    type="number" 
                    name="' . esc_attr($field_key) . '" 
                    value="' . esc_attr($value) . '" 
                    class="widefat">';
                break;

            case 'url':
                echo '<input 
                    type="url" 
                    name="' . esc_attr($field_key) . '" 
                    value="' . esc_attr($value) . '" 
                    class="widefat">';
                break;

            case 'file':
            case 'video':
            case 'gallery':
                echo '<input 
                    type="text" 
                    name="' . esc_attr($field_key) . '" 
                    value="' . esc_attr($value) . '" 
                    class="widefat">';
                echo '<p><small>Use Media IDs or URLs</small></p>';
                break;

            default:
                echo '<input 
                    type="text" 
                    name="' . esc_attr($field_key) . '" 
                    value="' . esc_attr($value) . '" 
                    class="widefat">';
                break;
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
}