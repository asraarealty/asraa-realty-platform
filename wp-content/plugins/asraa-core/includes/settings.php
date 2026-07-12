<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Admin Menu
|--------------------------------------------------------------------------
*/

function asraa_core_admin_menu() {

    add_menu_page(
        'Asraa Core',
        'Asraa Core',
        'manage_options',
        'asraa-core-settings',
        'asraa_core_dashboard_page',
        'dashicons-admin-generic',
        25
    );

    add_submenu_page(
        'asraa-core-settings',
        'Property Fields',
        'Property Fields',
        'manage_options',
        'asraa-core-fields',
        'asraa_core_fields_page'
    );

    add_submenu_page(
        'asraa-core-settings',
        'Meta Mapping',
        'Meta Mapping',
        'manage_options',
        'asraa-core-mapping',
        'asraa_core_mapping_page'
    );

    add_submenu_page(
        'asraa-core-settings',
        'Offer Engine',
        'Offer Engine',
        'manage_options',
        'asraa-core-offers',
        'asraa_core_offers_page'
    );

    add_submenu_page(
        'asraa-core-settings',
        'Lead Routing',
        'Lead Routing',
        'manage_options',
        'asraa-core-leads',
        'asraa_core_leads_page'
    );

    add_submenu_page(
        'asraa-core-settings',
        'Debug Inspector',
        'Debug Inspector',
        'manage_options',
        'asraa-core-debug',
        'asraa_core_debug_page'
    );
}

add_action('admin_menu', 'asraa_core_admin_menu');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

function asraa_core_dashboard_page() {
?>
<div class="wrap">
    <h1>Asraa Core Dashboard</h1>

    <div class="notice notice-success">
        <p>Asraa Core Framework Active</p>
    </div>

    <table class="widefat striped">
        <tbody>
            <tr>
                <td><strong>Plugin Version</strong></td>
                <td><?php echo ASRAA_CORE_VERSION; ?></td>
            </tr>
            <tr>
                <td><strong>GraphQL Status</strong></td>
                <td>Enabled</td>
            </tr>
            <tr>
                <td><strong>Property Engine</strong></td>
                <td>Running</td>
            </tr>
            <tr>
                <td><strong>Lead Engine</strong></td>
                <td>Ready</td>
            </tr>
        </tbody>
    </table>
</div>
<?php
}

/*
|--------------------------------------------------------------------------
| Meta Mapping
|--------------------------------------------------------------------------
*/

function asraa_core_mapping_page() {
?>
<div class="wrap">
    <h1>Meta Mapping Engine</h1>

    <form method="post" action="options.php">
        <?php settings_fields('asraa_core_mapping_group'); ?>

        <table class="form-table">

            <tr>
                <th>Gallery Meta Key</th>
                <td>
                    <input type="text"
                           name="asraa_gallery_key"
                           value="<?php echo esc_attr(
                               get_option('asraa_gallery_key', '_property_gallery')
                           ); ?>">
                </td>
            </tr>

            <tr>
                <th>Price Meta Key</th>
                <td>
                    <input type="text"
                           name="asraa_price_key"
                           value="<?php echo esc_attr(
                               get_option('asraa_price_key', '_property_price')
                           ); ?>">
                </td>
            </tr>

            <tr>
                <th>Beds Meta Key</th>
                <td>
                    <input type="text"
                           name="asraa_beds_key"
                           value="<?php echo esc_attr(
                               get_option('asraa_beds_key', '_property_beds')
                           ); ?>">
                </td>
            </tr>

        </table>

        <?php submit_button(); ?>
    </form>
</div>
<?php
}

/*
|--------------------------------------------------------------------------
| Offer Engine
|--------------------------------------------------------------------------
*/

function asraa_core_offers_page() {
?>
<div class="wrap">
    <h1>Offer Engine</h1>
    <p>Manage schemes, discounts and inventory logic.</p>
</div>
<?php
}

/*
|--------------------------------------------------------------------------
| Lead Routing
|--------------------------------------------------------------------------
*/

function asraa_core_leads_page() {
?>
<div class="wrap">
    <h1>Lead Routing</h1>
    <p>Connect CRM, Email, WhatsApp, Webhooks.</p>
</div>
<?php
}

/*
|--------------------------------------------------------------------------
| Debug Inspector
|--------------------------------------------------------------------------
*/

function asraa_core_debug_page() {
?>
<div class="wrap">
    <h1>Debug Inspector</h1>
    <p>Property meta debugger will be moved here.</p>
</div>
<?php
}

/*
|--------------------------------------------------------------------------
| Register Options
|--------------------------------------------------------------------------
*/

function asraa_core_register_settings() {
    register_setting('asraa_core_mapping_group', 'asraa_gallery_key');
    register_setting('asraa_core_mapping_group', 'asraa_price_key');
    register_setting('asraa_core_mapping_group', 'asraa_beds_key');
}

add_action('admin_init', 'asraa_core_register_settings');