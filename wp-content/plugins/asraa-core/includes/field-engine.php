<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Register Property Fields Option
|--------------------------------------------------------------------------
*/

function asraa_register_field_engine_settings() {
    register_setting(
        'asraa_core_fields_group',
        'asraa_property_fields'
    );
}
add_action('admin_init', 'asraa_register_field_engine_settings');

/*
|--------------------------------------------------------------------------
| Property Fields Builder Page
|--------------------------------------------------------------------------
*/

function asraa_core_fields_page() {

    $fields = get_option('asraa_property_fields', []);

    ?>
    <div class="wrap">
        <h1>Property Fields Builder</h1>

        <form method="post" action="options.php">

            <?php settings_fields('asraa_core_fields_group'); ?>

            <table class="widefat striped" id="asraa-fields-table">
                <thead>
                    <tr>
                        <th>Field Label</th>
                        <th>Field Key</th>
                        <th>Field Type</th>
                        <th>GraphQL</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($fields)) : ?>
                        <?php foreach ($fields as $index => $field) : ?>
                            <tr>
                                <td>
                                    <input type="text"
                                           name="asraa_property_fields[<?php echo $index; ?>][label]"
                                           value="<?php echo esc_attr($field['label']); ?>"
                                           class="widefat">
                                </td>

                                <td>
                                    <input type="text"
                                           name="asraa_property_fields[<?php echo $index; ?>][key]"
                                           value="<?php echo esc_attr($field['key']); ?>"
                                           class="widefat">
                                </td>

                                <td>
                                    <select name="asraa_property_fields[<?php echo $index; ?>][type]">
                                        <option value="text" <?php selected($field['type'], 'text'); ?>>Text</option>
                                        <option value="number" <?php selected($field['type'], 'number'); ?>>Number</option>
                                        <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Textarea</option>
                                        <option value="gallery" <?php selected($field['type'], 'gallery'); ?>>Gallery</option>
                                        <option value="file" <?php selected($field['type'], 'file'); ?>>File</option>
                                        <option value="url" <?php selected($field['type'], 'url'); ?>>URL</option>
                                        <option value="video" <?php selected($field['type'], 'video'); ?>>Video</option>
                                        <option value="select" <?php selected($field['type'], 'select'); ?>>Select</option>
                                    </select>
                                </td>

                                <td>
                                    <input type="checkbox"
                                           name="asraa_property_fields[<?php echo $index; ?>][graphql]"
                                           value="1"
                                           <?php checked(isset($field['graphql']) ? $field['graphql'] : '', 1); ?>>
                                </td>

                                <td>
                                    <button type="button" class="button asraa-remove-row">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>
            </table>

            <p>
                <button
                    type="button"
                    id="asraa-add-field"
                    class="button button-primary">
                    Add New Field
                </button>
            </p>

            <?php submit_button('Save Fields'); ?>

        </form>
    </div>

    <script>
    jQuery(document).ready(function ($) {

        let fieldIndex = <?php echo count($fields); ?>;

        $("#asraa-add-field").on("click", function () {

            let row = `
                <tr>
                    <td>
                        <input type="text"
                               name="asraa_property_fields[${fieldIndex}][label]"
                               class="widefat"
                               placeholder="Field Label">
                    </td>

                    <td>
                        <input type="text"
                               name="asraa_property_fields[${fieldIndex}][key]"
                               class="widefat"
                               placeholder="field_key">
                    </td>

                    <td>
                        <select name="asraa_property_fields[${fieldIndex}][type]">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="textarea">Textarea</option>
                            <option value="gallery">Gallery</option>
                            <option value="file">File</option>
                            <option value="url">URL</option>
                            <option value="video">Video</option>
                            <option value="select">Select</option>
                        </select>
                    </td>

                    <td>
                        <input type="checkbox"
                               name="asraa_property_fields[${fieldIndex}][graphql]"
                               value="1">
                    </td>

                    <td>
                        <button type="button" class="button asraa-remove-row">
                            Remove
                        </button>
                    </td>
                </tr>
            `;

            $("#asraa-fields-table tbody").append(row);

            fieldIndex++;
        });

        $(document).on("click", ".asraa-remove-row", function () {
            $(this).closest("tr").remove();
        });

    });
    </script>

    <?php
}