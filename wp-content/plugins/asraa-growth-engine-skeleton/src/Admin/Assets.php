<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Assets
{
    public function __construct()
    {
        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueue']
        );
    }

    public function enqueue()
    {
        wp_enqueue_style(
            'asraa-admin',
            ASRAA_GE_URL . 'assets/css/admin.css',
            [],
            ASRAA_GE_VERSION
        );

        wp_enqueue_script(
            'asraa-admin',
            ASRAA_GE_URL . 'assets/js/admin.js',
            ['jquery'],
            ASRAA_GE_VERSION,
            true
        );
    }
}