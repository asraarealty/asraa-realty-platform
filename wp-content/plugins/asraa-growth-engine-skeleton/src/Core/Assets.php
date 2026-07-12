<?php
/**
 * Assets Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Assets
{
    /**
     * Initialize
     */
    public function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'frontendAssets']);
    }

    /**
     * Admin Assets
     */
    public function adminAssets(string $hook): void
    {
        // Load only on Asraa Growth Engine admin pages
        if (strpos($hook, 'asraa-growth-engine') === false) {
            return;
        }

        $css = ASRAA_GE_PATH . 'assets/css/admin.css';
        $js  = ASRAA_GE_PATH . 'assets/js/admin.js';

        if (file_exists($css)) {
            wp_enqueue_style(
                'asraa-growth-engine-admin',
                ASRAA_GE_URL . 'assets/css/admin.css',
                [],
                (string) filemtime($css)
            );
        }

        if (file_exists($js)) {
            wp_enqueue_script(
                'asraa-growth-engine-admin',
                ASRAA_GE_URL . 'assets/js/admin.js',
                ['jquery'],
                (string) filemtime($js),
                true
            );

            wp_localize_script(
                'asraa-growth-engine-admin',
                'AsraaGrowthEngine',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('asraa_growth_engine'),
                    'version' => ASRAA_GE_VERSION,
                ]
            );
        }
    }

    /**
     * Frontend Assets
     */
    public function frontendAssets(): void
    {
        // Reserved for future frontend functionality
    }
}