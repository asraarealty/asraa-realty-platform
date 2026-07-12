<?php
/**
 * Plugin Activator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    /**
     * Activate Plugin
     */
    public static function activate(): void
    {
        self::storeVersion();

        self::createDatabase();

        self::createDefaultOptions();

        self::createDirectories();

        self::scheduleCron();

        self::registerCapabilities();

        flush_rewrite_rules();
    }

    /**
     * Store Plugin Version
     */
    protected static function storeVersion(): void
    {
        update_option(
            'asraa_growth_engine_version',
            ASRAA_GE_VERSION
        );

        update_option(
            'asraa_growth_engine_installed',
            current_time('mysql')
        );
    }

    /**
     * Create Database
     */
    protected static function createDatabase(): void
    {
        if (class_exists(Database::class)) {

            $database = new Database();

            $database->init();

        }
    }

    /**
     * Default Options
     */
    protected static function createDefaultOptions(): void
    {
        add_option(
            'asraa_growth_engine_options',
            []
        );
    }

    /**
     * Create Required Directories
     */
    protected static function createDirectories(): void
    {
        $directories = [

            ASRAA_GE_PATH . 'logs',
            ASRAA_GE_PATH . 'cache',
            ASRAA_GE_PATH . 'exports',

        ];

        foreach ($directories as $directory) {

            if (!file_exists($directory)) {

                wp_mkdir_p($directory);

            }

        }
    }

    /**
     * Schedule Cron
     */
    protected static function scheduleCron(): void
    {
        if (!wp_next_scheduled('asraa_growth_engine_cron')) {

            wp_schedule_event(
                time(),
                'hourly',
                'asraa_growth_engine_cron'
            );

        }
    }

    /**
     * Register Capabilities
     */
    protected static function registerCapabilities(): void
    {
        $role = get_role('administrator');

        if (!$role) {
            return;
        }

        $capabilities = [

            'manage_asraa_growth_engine',
            'manage_asraa_growth_engine_seo',
            'manage_asraa_growth_engine_ai',
            'manage_asraa_growth_engine_analytics',
            'manage_asraa_growth_engine_reports',

        ];

        foreach ($capabilities as $capability) {

            $role->add_cap($capability);

        }
    }
}