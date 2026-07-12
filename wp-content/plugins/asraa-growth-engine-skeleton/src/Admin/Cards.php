<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Cards
{
    public static function system()
    {
        Widgets::card(
            'WordPress',
            get_bloginfo('version'),
            '🟦'
        );

        Widgets::card(
            'PHP',
            PHP_VERSION,
            '🐘'
        );

        Widgets::card(
            'Plugin',
            ASRAA_GE_VERSION,
            '🚀'
        );

        Widgets::card(
            'Theme',
            wp_get_theme()->get('Name'),
            '🎨'
        );
    }
}