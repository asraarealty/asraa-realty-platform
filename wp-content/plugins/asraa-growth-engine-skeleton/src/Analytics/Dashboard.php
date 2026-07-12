<?php
/**
 * Analytics Dashboard
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard
{
    /**
     * Dashboard Summary
     */
    public function summary(): array
    {
        return [

            'users'        => $this->users(),
            'sessions'     => $this->sessions(),
            'pageviews'    => $this->pageViews(),
            'properties'   => $this->properties(),
            'posts'        => (int) (wp_count_posts('post')->publish ?? 0),
            'pages'        => (int) (wp_count_posts('page')->publish ?? 0),
            'leads'        => $this->leads(),
            'seo_score'    => $this->seoScore(),
            'health_score' => $this->healthScore(),

        ];
    }

    /**
     * Total Users
     */
    protected function users(): int
    {
        $users = count_users();

        return (int) ($users['total_users'] ?? 0);
    }

    /**
     * Sessions
     *
     * Reserved for Google Analytics 4.
     */
    protected function sessions(): int
    {
        return 0;
    }

    /**
     * Page Views
     *
     * Reserved for Google Analytics 4.
     */
    protected function pageViews(): int
    {
        return 0;
    }

    /**
     * Published Properties
     */
    protected function properties(): int
    {
        if (!post_type_exists('property')) {
            return 0;
        }

        $count = wp_count_posts('property');

        return (int) ($count->publish ?? 0);
    }

    /**
     * CRM Leads
     */
    protected function leads(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'asraa_crm_leads';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        if ($exists !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}"
        );
    }

    /**
     * SEO Score
     *
     * Placeholder until SEO Scanner is implemented.
     */
    protected function seoScore(): int
    {
        return 0;
    }

    /**
     * Website Health Score
     */
    protected function healthScore(): int
    {
        $score = 100;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $score -= 10;
        }

        if (version_compare(PHP_VERSION, '8.2', '<')) {
            $score -= 10;
        }

        if (!is_ssl()) {
            $score -= 10;
        }

        return max(0, $score);
    }

    /**
     * Website Health
     */
    public function health(): array
    {
        return [

            'wordpress' => get_bloginfo('version'),

            'php' => PHP_VERSION,

            'theme' => wp_get_theme()->get('Name'),

            'memory_limit' => ini_get('memory_limit'),

            'upload_max_filesize' => ini_get('upload_max_filesize'),

            'max_execution_time' => ini_get('max_execution_time'),

            'ssl' => is_ssl(),

            'cron' => (bool) wp_next_scheduled(
                'asraa_growth_engine_cron'
            ),

            'timezone' => wp_timezone_string(),

            'site_url' => home_url(),

        ];
    }

    /**
     * Quick Statistics
     */
    public function quickStats(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return [

            'plugins' => count(get_plugins()),

            'themes' => count(wp_get_themes()),

            'media' => (int) (
                wp_count_posts('attachment')->inherit ?? 0
            ),

        ];
    }

    /**
     * Server Information
     */
    public function server(): array
    {
        return [

            'php' => PHP_VERSION,

            'mysql' => $GLOBALS['wpdb']->db_version(),

            'memory_limit' => ini_get('memory_limit'),

            'max_execution_time' => ini_get('max_execution_time'),

            'upload_max_filesize' => ini_get('upload_max_filesize'),

            'server' => $_SERVER['SERVER_SOFTWARE'] ?? '',

        ];
    }
}