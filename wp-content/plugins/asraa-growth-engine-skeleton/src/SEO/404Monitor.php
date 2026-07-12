<?php
/**
 * 404 Monitor
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Monitor404
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('template_redirect', [$this, 'track404']);
    }

    /**
     * Track 404 pages.
     */
    public function track404(): void
    {
        if (!is_404()) {
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'asraa_404_logs';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }

        $url = esc_url_raw(home_url(add_query_arg([], $GLOBALS['wp']->request)));

        $wpdb->insert(
            $table,
            [
                'url'         => $url,
                'referrer'    => isset($_SERVER['HTTP_REFERER'])
                    ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']))
                    : '',
                'ip_address'  => isset($_SERVER['REMOTE_ADDR'])
                    ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
                    : '',
                'user_agent'  => isset($_SERVER['HTTP_USER_AGENT'])
                    ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
                    : '',
                'created_at'  => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Get latest logs.
     */
    public static function latest(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}asraa_404_logs
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Delete all logs.
     */
    public static function clear(): void
    {
        global $wpdb;

        $wpdb->query(
            "TRUNCATE TABLE {$wpdb->prefix}asraa_404_logs"
        );
    }
}