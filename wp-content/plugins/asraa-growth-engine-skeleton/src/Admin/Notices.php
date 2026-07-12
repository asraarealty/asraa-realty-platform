<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Notices
{
    public static function success(string $message): void
    {
        echo '<div class="notice notice-success"><p>' .
            esc_html($message) .
            '</p></div>';
    }

    public static function warning(string $message): void
    {
        echo '<div class="notice notice-warning"><p>' .
            esc_html($message) .
            '</p></div>';
    }

    public static function error(string $message): void
    {
        echo '<div class="notice notice-error"><p>' .
            esc_html($message) .
            '</p></div>';
    }
}