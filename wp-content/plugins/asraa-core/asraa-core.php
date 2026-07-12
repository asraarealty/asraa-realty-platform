<?php
/**
 * Plugin Name: Asraa Core
 * Plugin URI: https://asraarealty.com
 * Description: Core engine for Asraa Realty (Property, Media, SEO, GraphQL, Leads, Floorplans, CRM)
 * Version: 1.0.0
 * Author: Asraa Realty
 * Author URI: https://asraarealty.com
 * License: GPL2
 * Text Domain: asraa-core
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/

define('ASRAA_CORE_VERSION', '1.0.0');
define('ASRAA_CORE_PATH', plugin_dir_path(__FILE__));
define('ASRAA_CORE_URL', plugin_dir_url(__FILE__));

/*
|--------------------------------------------------------------------------
| Load Core Files
|--------------------------------------------------------------------------
*/

$asraa_files = [

    /*
    |--------------------------------------------------------------------------
    | Core Dashboard Engine
    |--------------------------------------------------------------------------
    */
    'includes/settings.php',

    /*
    |--------------------------------------------------------------------------
    | Dynamic Field Builder Engine
    |--------------------------------------------------------------------------
    */
    'includes/field-engine.php',

    /*
    |--------------------------------------------------------------------------
    | Property Engine
    |--------------------------------------------------------------------------
    */
    'includes/dynamic-metabox.php',
    'includes/save-meta.php',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Engine
    |--------------------------------------------------------------------------
    */
    'graphql/property-fields.php',

    /*
    |--------------------------------------------------------------------------
    | Admin Assets
    |--------------------------------------------------------------------------
    */
    'includes/admin-assets.php',

    /*
    |--------------------------------------------------------------------------
    | SEO Engine
    |--------------------------------------------------------------------------
    */
    'includes/seo.php',
    'includes/schema.php',
    'includes/sitemap.php',
    'includes/breadcrumbs.php',

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    'includes/helpers.php',
];

/*
|--------------------------------------------------------------------------
| Require Files
|--------------------------------------------------------------------------
*/

foreach ($asraa_files as $file) {
    $path = ASRAA_CORE_PATH . $file;

    if (file_exists($path)) {
        require_once $path;
    }
}

/*
|--------------------------------------------------------------------------
| Activation Hook
|--------------------------------------------------------------------------
*/

function asraa_core_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'asraa_core_activate');

/*
|--------------------------------------------------------------------------
| Deactivation Hook
|--------------------------------------------------------------------------
*/

function asraa_core_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'asraa_core_deactivate');