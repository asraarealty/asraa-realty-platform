<?php
/**
 * Performance Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class PerformanceScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Performance Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks WordPress and server performance configuration.';
    }

    /**
     * Scan
     */
    public function scan(): array
    {
        $issues = [];
        $details = [];

        /* PHP Version */

        $details['php_version'] = PHP_VERSION;

        if (version_compare(PHP_VERSION, '8.2', '<')) {
            $issues[] = 'PHP version is below 8.2.';
        }

        /* Memory */

        $memory = ini_get('memory_limit');

        $details['memory_limit'] = $memory;

        /* Execution Time */

        $details['max_execution_time'] = ini_get('max_execution_time');

        /* Upload Size */

        $details['upload_max_filesize'] = ini_get('upload_max_filesize');

        /* Object Cache */

        $details['object_cache'] = wp_using_ext_object_cache()
            ? 'Enabled'
            : 'Disabled';

        /* WP Debug */

        $details['wp_debug'] = (defined('WP_DEBUG') && WP_DEBUG)
            ? 'Enabled'
            : 'Disabled';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $issues[] = 'WP_DEBUG is enabled.';
        }

        /* Heartbeat */

        $details['heartbeat'] = wp_script_is('heartbeat', 'registered')
            ? 'Registered'
            : 'Not Registered';

        /* Active Theme */

        $details['theme'] = wp_get_theme()->get('Name');

        /* Active Plugins */

        $details['plugins'] = count(get_option('active_plugins', []));

        /* Site URL */

        $details['home_url'] = home_url();

        /* SSL */

        $details['ssl'] = is_ssl()
            ? 'Enabled'
            : 'Disabled';

        if (!is_ssl()) {
            $issues[] = 'Website is not using HTTPS.';
        }

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Upgrade PHP if required.',
                'Disable WP_DEBUG on production.',
                'Use HTTPS.',
                'Enable persistent object cache if available.',
            ],
            90
        );
    }
}