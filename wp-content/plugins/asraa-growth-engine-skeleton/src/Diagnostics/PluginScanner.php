<?php
/**
 * Plugin Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class PluginScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Plugin Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks active plugins for conflicts and duplicate functionality.';
    }

    /**
     * Scan Plugins
     */
    public function scan(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active = (array) get_option('active_plugins', []);
        $plugins = get_plugins();

        $issues = [];
        $details = [];

        $groups = [

            'SEO' => [
                'wordpress-seo/wp-seo.php',
                'seo-by-rank-math/rank-math.php',
                'all-in-one-seo-pack/all_in_one_seo_pack.php',
                'autodescription/autodescription.php',
            ],

            'Caching' => [
                'litespeed-cache/litespeed-cache.php',
                'wp-super-cache/wp-cache.php',
                'w3-total-cache/w3-total-cache.php',
                'wp-rocket/wp-rocket.php',
            ],

            'Security' => [
                'wordfence/wordfence.php',
                'sucuri-scanner/sucuri.php',
                'better-wp-security/better-wp-security.php',
            ],

            'Forms' => [
                'contact-form-7/wp-contact-form-7.php',
                'gravityforms/gravityforms.php',
                'wpforms-lite/wpforms.php',
            ],

        ];

        foreach ($groups as $group => $list) {

            $enabled = [];

            foreach ($list as $plugin) {

                if (in_array($plugin, $active, true)) {
                    $enabled[] = $plugin;
                }

            }

            if (count($enabled) > 1) {

                $issues[] = sprintf(
                    'Multiple %s plugins are active.',
                    $group
                );

                $details[] = [
                    'group' => $group,
                    'plugins' => $enabled,
                ];

            }

        }

        foreach ($active as $plugin) {

            if (isset($plugins[$plugin])) {

                $details[] = [
                    'plugin' => $plugins[$plugin]['Name'],
                    'version' => $plugins[$plugin]['Version'],
                ];

            }

        }

        if (empty($issues)) {

            return $this->success([
                'active_plugins' => count($active),
                'plugins' => $details,
            ]);

        }

        return $this->warning(
            $issues,
            [
                'Disable duplicate plugins with overlapping functionality.',
                'Keep only one SEO plugin.',
                'Keep only one caching plugin.',
            ],
            80
        );
    }
}