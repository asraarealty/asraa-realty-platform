<?php
/**
 * Plugin Deactivator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    /**
     * Deactivate Plugin
     */
    public static function deactivate(): void
    {
        self::clearCron();

        self::clearCache();

        self::flushRewriteRules();

        /**
         * Allow other modules to perform cleanup.
         */
        do_action('asraa_growth_engine_deactivated');
    }

    /**
     * Clear Scheduled Cron Events
     */
    protected static function clearCron(): void
    {
        wp_clear_scheduled_hook('asraa_growth_engine_cron');
    }

    /**
     * Clear Plugin Cache
     */
    protected static function clearCache(): void
    {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Flush Rewrite Rules
     */
    protected static function flushRewriteRules(): void
    {
        flush_rewrite_rules();
    }
}