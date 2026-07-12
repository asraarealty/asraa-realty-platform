<?php
/**
 * AJAX Handler
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_asraa_system_status', [$this, 'systemStatus']);
    }

    public function systemStatus(): void
    {
        check_ajax_referer('asraa_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        wp_send_json_success([
            'wordpress' => get_bloginfo('version'),
            'php'       => PHP_VERSION,
            'plugin'    => ASRAA_GE_VERSION,
            'theme'     => wp_get_theme()->get('Name'),
        ]);
    }
}