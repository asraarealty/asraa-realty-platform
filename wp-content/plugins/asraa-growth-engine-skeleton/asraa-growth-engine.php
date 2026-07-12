<?php
/**
 * Plugin Name: Asraa Growth Engine
 * Plugin URI: https://asraarealty.com
 * Description: Enterprise SEO, AI, Analytics & Growth Platform for Asraa Realty.
 * Version: 0.1.0
 * Author: Asraa Realty
 * Author URI: https://asraarealty.com
 * License: Proprietary
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Text Domain: asraa-growth-engine
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Compatibility Checks
|--------------------------------------------------------------------------
*/

global $wp_version;

if (version_compare(PHP_VERSION, '8.2', '<')) {

    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Asraa Growth Engine requires PHP 8.2 or higher.', 'asraa-growth-engine');
        echo '</p></div>';
    });

    return;
}

if (version_compare($wp_version, '6.5', '<')) {

    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Asraa Growth Engine requires WordPress 6.5 or higher.', 'asraa-growth-engine');
        echo '</p></div>';
    });

    return;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/

define('ASRAA_GE_VERSION', '0.1.0');
define('ASRAA_GE_FILE', __FILE__);
define('ASRAA_GE_PATH', plugin_dir_path(__FILE__));
define('ASRAA_GE_URL', plugin_dir_url(__FILE__));
define('ASRAA_GE_BASENAME', plugin_basename(__FILE__));

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
*/

$autoload = ASRAA_GE_PATH . 'vendor/autoload.php';

if (file_exists($autoload)) {

    require_once $autoload;

} else {

    spl_autoload_register(static function (string $class): void {

        $prefix = 'Asraa\\GrowthEngine\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));

        $file = ASRAA_GE_PATH .
            'src/' .
            str_replace('\\', DIRECTORY_SEPARATOR, $relative) .
            '.php';

        if (file_exists($file)) {
            require_once $file;
        }

    });

}

/*
|--------------------------------------------------------------------------
| Activation
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    static function (): void {

        if (class_exists(\Asraa\GrowthEngine\Core\Activator::class)) {
            \Asraa\GrowthEngine\Core\Activator::activate();
        }

    }
);

/*
|--------------------------------------------------------------------------
| Deactivation
|--------------------------------------------------------------------------
*/

register_deactivation_hook(
    __FILE__,
    static function (): void {

        if (class_exists(\Asraa\GrowthEngine\Core\Deactivator::class)) {
            \Asraa\GrowthEngine\Core\Deactivator::deactivate();
        }

    }
);

/*
|--------------------------------------------------------------------------
| Uninstall (Optional)
|--------------------------------------------------------------------------
|
| Create src/Core/Uninstaller.php before enabling this.
|
*/

/*
register_uninstall_hook(
    __FILE__,
    [\Asraa\GrowthEngine\Core\Uninstaller::class, 'uninstall']
);
*/

/*
|--------------------------------------------------------------------------
| Boot Plugin
|--------------------------------------------------------------------------
*/

add_action(
    'plugins_loaded',
    static function (): void {

        if (!class_exists(\Asraa\GrowthEngine\Core\Plugin::class)) {
            return;
        }

        try {

            (new \Asraa\GrowthEngine\Core\Plugin())->boot();

        } catch (\Throwable $e) {

            error_log(
                '[ASRAA Growth Engine] Boot Error: ' .
                $e->getMessage()
            );

            add_action('admin_notices', static function () use ($e) {

                echo '<div class="notice notice-error"><p>';
                echo esc_html__('Asraa Growth Engine failed to boot.', 'asraa-growth-engine');
                echo '<br><small>' . esc_html($e->getMessage()) . '</small>';
                echo '</p></div>';

            });

        }

    }
);