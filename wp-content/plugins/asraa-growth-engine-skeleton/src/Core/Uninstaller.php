<?php
/**
 * Plugin Uninstaller
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

// Prevent direct access.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

class Uninstaller
{
    /**
     * Uninstall Plugin
     */
    public static function uninstall(): void
    {
        global $wpdb;

        /*
        |--------------------------------------------------------------------------
        | Clear Scheduled Events
        |--------------------------------------------------------------------------
        */

        wp_clear_scheduled_hook('asraa_growth_engine_cron');

        /*
        |--------------------------------------------------------------------------
        | Delete Plugin Options
        |--------------------------------------------------------------------------
        */

        delete_option('asraa_growth_engine_version');
        delete_option('asraa_growth_engine_options');

        /*
        |--------------------------------------------------------------------------
        | Delete Site Options (Multisite)
        |--------------------------------------------------------------------------
        */

        if (is_multisite()) {
            delete_site_option('asraa_growth_engine_version');
            delete_site_option('asraa_growth_engine_options');
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Transients
        |--------------------------------------------------------------------------
        */

        delete_transient('asraa_growth_engine_cache');
        delete_transient('asraa_growth_engine_health');
        delete_transient('asraa_growth_engine_diagnostics');

        /*
        |--------------------------------------------------------------------------
        | Future Cleanup
        |--------------------------------------------------------------------------
        |
        | Uncomment these when required.
        |
        */

        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}asraa_logs");
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}asraa_growth_reports");
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}asraa_growth_cache");

        /*
        |--------------------------------------------------------------------------
        | Plugin Action
        |--------------------------------------------------------------------------
        */

        do_action('asraa_growth_engine_uninstall');
    }
}