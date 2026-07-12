<?php
/**
 * Security Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class SecurityScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Security Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks common WordPress security settings.';
    }

    /**
     * Scan
     */
    public function scan(): array
    {
        $issues = [];
        $details = [];

        /* SSL */

        $details['ssl'] = is_ssl();

        if (!is_ssl()) {
            $issues[] = 'Website is not using HTTPS.';
        }

        /* Debug */

        $details['wp_debug'] = defined('WP_DEBUG') && WP_DEBUG;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $issues[] = 'WP_DEBUG is enabled.';
        }

        /* Debug Log */

        $details['wp_debug_log'] = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $issues[] = 'WP_DEBUG_LOG is enabled.';
        }

        /* File Editing */

        $details['file_edit_disabled'] = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;

        if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
            $issues[] = 'Theme and plugin editor is enabled.';
        }

        /* File Modifications */

        $details['file_mods_disabled'] = defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS;

        /* XML-RPC */

        $details['xmlrpc_enabled'] = apply_filters('xmlrpc_enabled', true);

        if ($details['xmlrpc_enabled']) {
            $issues[] = 'XML-RPC is enabled.';
        }

        /* Admin Email */

        $details['admin_email'] = get_option('admin_email');

        /* WordPress Version */

        $details['wordpress_version'] = get_bloginfo('version');

        /* PHP Version */

        $details['php_version'] = PHP_VERSION;

        /* Upload Directory */

        $upload = wp_upload_dir();

        $details['uploads'] = $upload['basedir'];

        /* Health */

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Enable HTTPS.',
                'Disable WP_DEBUG on production.',
                'Disable theme/plugin editor.',
                'Review XML-RPC requirements.',
            ],
            85
        );
    }
}