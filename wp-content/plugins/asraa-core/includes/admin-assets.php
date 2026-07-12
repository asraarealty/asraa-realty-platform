<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Admin Assets
|--------------------------------------------------------------------------
*/

function asraa_admin_assets($hook) {

    global $post;

    /*
    |--------------------------------------------------------------------------
    | Load only on Property Edit Screen + Asraa Core Pages
    |--------------------------------------------------------------------------
    */

    $is_property_screen = (
        ($hook === 'post.php' || $hook === 'post-new.php') &&
        isset($post) &&
        $post->post_type === 'property'
    );

    $is_asraa_screen = (
        strpos($hook, 'asraa-core') !== false
    );

    if (!$is_property_screen && !$is_asraa_screen) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Native WP Media
    |--------------------------------------------------------------------------
    */

    wp_enqueue_media();

    /*
    |--------------------------------------------------------------------------
    | Admin Styles
    |--------------------------------------------------------------------------
    */

    wp_enqueue_style(
        'asraa-admin-style',
        ASRAA_CORE_URL . 'assets/css/admin.css',
        [],
        ASRAA_CORE_VERSION
    );

    /*
    |--------------------------------------------------------------------------
    | Uploader Engine
    |--------------------------------------------------------------------------
    */

    wp_enqueue_script(
        'asraa-admin-uploader',
        ASRAA_CORE_URL . 'assets/js/uploader.js',
        ['jquery'],
        ASRAA_CORE_VERSION,
        true
    );

    /*
    |--------------------------------------------------------------------------
    | Dynamic Field Engine
    |--------------------------------------------------------------------------
    */

    wp_enqueue_script(
        'asraa-field-engine',
        ASRAA_CORE_URL . 'assets/js/field-engine.js',
        ['jquery'],
        ASRAA_CORE_VERSION,
        true
    );

    /*
    |--------------------------------------------------------------------------
    | Localize
    |--------------------------------------------------------------------------
    */

    wp_localize_script(
        'asraa-field-engine',
        'asraaCore',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('asraa_core_nonce'),
        ]
    );
}

add_action('admin_enqueue_scripts', 'asraa_admin_assets');