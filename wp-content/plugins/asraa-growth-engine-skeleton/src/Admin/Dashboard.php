<?php
/**
 * Dashboard Controller
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'init']);
    }

    /**
     * Initialize Dashboard
     */
    public function init(): void
    {
        // Reserved for future dashboard hooks.
    }

    /**
     * Dashboard Statistics
     */
    public static function get_stats(): array
    {
        return [

            'properties' => post_type_exists('property')
                ? (int) (wp_count_posts('property')->publish ?? 0)
                : 0,

            'posts' => (int) (wp_count_posts('post')->publish ?? 0),

            'pages' => (int) (wp_count_posts('page')->publish ?? 0),

            'users' => (int) (count_users()['total_users'] ?? 0),

            'media' => (int) wp_count_posts('attachment')->inherit,

            'plugins' => count(get_plugins()),

            'theme' => wp_get_theme()->get('Name'),

            'php' => PHP_VERSION,

            'wordpress' => get_bloginfo('version'),

            'memory_limit' => ini_get('memory_limit'),

            'debug' => defined('WP_DEBUG') && WP_DEBUG,

        ];
    }

    /**
     * Recent Activity
     */
    public static function recent_activity(int $limit = 10): array
    {
        return get_posts([

            'post_type' => 'any',

            'post_status' => 'publish',

            'posts_per_page' => $limit,

            'orderby' => 'modified',

            'order' => 'DESC',

        ]);
    }

    /**
     * System Status
     */
    public static function system_status(): array
    {
        return [

            'php' => PHP_VERSION,

            'wordpress' => get_bloginfo('version'),

            'mysql' => $GLOBALS['wpdb']->db_version(),

            'memory' => ini_get('memory_limit'),

            'upload_max' => ini_get('upload_max_filesize'),

            'execution_time' => ini_get('max_execution_time'),

            'debug' => defined('WP_DEBUG') && WP_DEBUG,

        ];
    }

    /**
     * Active Theme
     */
    public static function theme(): string
    {
        return wp_get_theme()->get('Name');
    }

    /**
     * Active Plugins
     */
    public static function plugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugins();
    }

    /**
     * Health Score
     */
    public static function health_score(): int
    {
        $score = 100;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $score -= 10;
        }

        if (version_compare(PHP_VERSION, '8.2', '<')) {
            $score -= 20;
        }

        if ((int) ini_get('max_execution_time') < 60) {
            $score -= 10;
        }

        return max(0, $score);
    }
}