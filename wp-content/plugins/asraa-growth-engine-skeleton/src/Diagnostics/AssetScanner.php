<?php
/**
 * Asset Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class AssetScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Asset Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks CSS, JavaScript and asset loading.';
    }

    /**
     * Scan Assets
     */
    public function scan(): array
    {
        global $wp_scripts;
        global $wp_styles;

        $issues = [];
        $details = [];

        $scripts = [];
        $styles = [];

        if ($wp_scripts instanceof \WP_Scripts) {

            foreach ($wp_scripts->registered as $handle => $script) {

                $scripts[] = [
                    'handle' => $handle,
                    'src'    => $script->src,
                    'deps'   => $script->deps,
                ];

            }

        }

        if ($wp_styles instanceof \WP_Styles) {

            foreach ($wp_styles->registered as $handle => $style) {

                $styles[] = [
                    'handle' => $handle,
                    'src'    => $style->src,
                    'deps'   => $style->deps,
                ];

            }

        }

        $details['registered_scripts'] = count($scripts);
        $details['registered_styles'] = count($styles);

        /* jQuery */

        if (wp_script_is('jquery', 'registered')) {

            $details['jquery'] = 'Registered';

        }

        /* Google Maps */

        foreach ($scripts as $script) {

            if (
                !empty($script['src']) &&
                stripos($script['src'], 'maps.googleapis') !== false
            ) {

                $issues[] = 'Google Maps JavaScript detected. Verify it only loads where needed.';
            }

        }

        /* Chart.js */

        foreach ($scripts as $script) {

            if (
                !empty($script['src']) &&
                stripos($script['src'], 'chart') !== false
            ) {

                $details['chartjs'] = $script['src'];

            }

        }

        /* Elementor */

        if (defined('ELEMENTOR_VERSION')) {

            $details['elementor'] = ELEMENTOR_VERSION;

        }

        /* LiteSpeed */

        if (defined('LSCWP_V')) {

            $details['litespeed_cache'] = LSCWP_V;

        }

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Load Google Maps only on pages that require maps.',
                'Unload unnecessary CSS and JavaScript.',
                'Review duplicate asset loading.',
            ],
            90
        );
    }
}