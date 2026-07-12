<?php
/**
 * Performance Analytics
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Performance
{
    /**
     * Website Score
     */
    public function websiteScore(): int
    {
        $score = 100;

        if (!is_ssl()) {
            $score -= 10;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $score -= 5;
        }

        return max(0, $score);
    }

    /**
     * SEO Score
     */
    public function seoScore(): int
    {
        return 0;
    }

    /**
     * Core Web Vitals
     */
    public function coreWebVitals(): array
    {
        return [
            'lcp' => 0,
            'cls' => 0,
            'inp' => 0,
            'status' => 'Pending',
        ];
    }

    /**
     * Website Health
     */
    public function health(): array
    {
        return [

            'wordpress' => get_bloginfo('version'),

            'php' => PHP_VERSION,

            'mysql' => $this->mysqlVersion(),

            'memory' => ini_get('memory_limit'),

            'theme' => wp_get_theme()->get('Name'),

            'ssl' => is_ssl(),

            'debug' => defined('WP_DEBUG') && WP_DEBUG,

        ];
    }

    /**
     * Property Statistics
     */
    public function properties(): array
    {
        $count = wp_count_posts('property');

        return [

            'published' => $count->publish ?? 0,

            'draft' => $count->draft ?? 0,

            'pending' => $count->pending ?? 0,

        ];
    }

    /**
     * CRM Statistics
     */
    public function crm(): array
    {
        $leads = new Leads();

        return [

            'total' => $leads->total(),

            'today' => $leads->today(),

            'month' => $leads->thisMonth(),

            'conversion' => $leads->conversionRate(),

        ];
    }

    /**
     * MySQL Version
     */
    protected function mysqlVersion(): string
    {
        global $wpdb;

        return (string) $wpdb->get_var('SELECT VERSION()');
    }
}