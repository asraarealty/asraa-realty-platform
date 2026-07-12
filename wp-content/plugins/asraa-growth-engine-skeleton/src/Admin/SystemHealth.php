<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SystemHealth
{
    public static function data(): array
    {
        return [

            'wordpress' => get_bloginfo('version'),

            'php' => PHP_VERSION,

            'theme' => wp_get_theme()->get('Name'),

            'memory' => ini_get('memory_limit'),

            'debug' => defined('WP_DEBUG') && WP_DEBUG,

            'multisite' => is_multisite(),

            'ssl' => is_ssl(),

            'cron' => wp_next_scheduled('asraa_growth_engine_cron'),

        ];
    }
}