<?php
/**
 * Main Plugin Class
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    /**
     * Plugin Version
     */
    private string $version;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version = defined('ASRAA_GE_VERSION')
            ? ASRAA_GE_VERSION
            : '1.0.0';
    }

    /**
     * Boot Plugin
     */
    public function boot(): void
    {
        $this->loadDependencies();
        $this->registerHooks();
    }

    /**
     * Load All Plugin Components
     */
    private function loadDependencies(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Core Components
        |--------------------------------------------------------------------------
        */

        $core = [

            Database::class,
            Assets::class,

        ];

        foreach ($core as $class) {

            $this->bootClass($class);

        }

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        if (is_admin()) {

            $this->bootClass(
                \Asraa\GrowthEngine\Admin\Admin::class
            );

        }

        /*
        |--------------------------------------------------------------------------
        | SEO
        |--------------------------------------------------------------------------
        */

        $this->bootClass(
            \Asraa\GrowthEngine\SEO\Manager::class
        );

        /*
        |--------------------------------------------------------------------------
        | Analytics
        |--------------------------------------------------------------------------
        */

        $this->bootClass(
            \Asraa\GrowthEngine\Analytics\Manager::class
        );

        /*
        |--------------------------------------------------------------------------
        | AI
        |--------------------------------------------------------------------------
        */

        $this->bootClass(
            \Asraa\GrowthEngine\AI\Manager::class
        );

        /*
        |--------------------------------------------------------------------------
        | Diagnostics
        |--------------------------------------------------------------------------
        */

        $this->bootClass(
            \Asraa\GrowthEngine\Diagnostics\Manager::class
        );

        /*
        |--------------------------------------------------------------------------
        | Automation
        |--------------------------------------------------------------------------
        */

        $this->bootClass(
            \Asraa\GrowthEngine\Automation\Manager::class
        );

        /*
        |--------------------------------------------------------------------------
        | REST API
        |--------------------------------------------------------------------------
        */

        $this->bootClass(
            \Asraa\GrowthEngine\API\RestApi::class
        );

        /*
        |--------------------------------------------------------------------------
        | Logger
        |--------------------------------------------------------------------------
        */

        if (class_exists(\Asraa\GrowthEngine\Logger\Logger::class)) {

            try {

                \Asraa\GrowthEngine\Logger\Logger::info(
                    'Asraa Growth Engine booted successfully.'
                );

            } catch (\Throwable $e) {

                error_log(
                    '[ASRAA Growth Engine] Logger Error: ' .
                    $e->getMessage()
                );

            }

        }
    }

    /**
     * Boot Single Class
     */
    private function bootClass(string $class): void
    {
        if (!class_exists($class)) {
            return;
        }

        try {

            $instance = new $class();

            if (method_exists($instance, 'init')) {
                $instance->init();
            }

        } catch (\Throwable $e) {

            error_log(
                '[ASRAA Growth Engine] ' .
                $class .
                ' : ' .
                $e->getMessage()
            );

        }
    }

    /**
     * Register Hooks
     */
    private function registerHooks(): void
    {
        add_action(
            'init',
            [$this, 'init']
        );

        add_action(
            'admin_init',
            [$this, 'adminInit']
        );

        add_action(
            'rest_api_init',
            [$this, 'restInit']
        );
    }

    /**
     * Init
     */
    public function init(): void
    {
        do_action('asraa_growth_engine_init');
    }

    /**
     * Admin Init
     */
    public function adminInit(): void
    {
        do_action('asraa_growth_engine_admin_init');
    }

    /**
     * REST Init
     */
    public function restInit(): void
    {
        do_action('asraa_growth_engine_rest_init');
    }

    /**
     * Get Version
     */
    public function version(): string
    {
        return $this->version;
    }
}