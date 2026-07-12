<?php
/**
 * Dashboard Widgets
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Widgets
{
    /**
     * Dashboard Cards
     */
    public function cards(): array
    {
        $performance = new Performance();
        $leads       = new Leads();
        $traffic     = new Traffic();

        return [

            [
                'title' => 'Website Score',
                'value' => $performance->websiteScore(),
                'icon'  => 'dashicons-performance',
                'color' => 'green',
            ],

            [
                'title' => 'SEO Score',
                'value' => $performance->seoScore(),
                'icon'  => 'dashicons-chart-area',
                'color' => 'blue',
            ],

            [
                'title' => 'Total Leads',
                'value' => $leads->total(),
                'icon'  => 'dashicons-groups',
                'color' => 'orange',
            ],

            [
                'title' => 'Today's Leads',
                'value' => $leads->today(),
                'icon'  => 'dashicons-calendar-alt',
                'color' => 'purple',
            ],

            [
                'title' => 'Monthly Leads',
                'value' => $leads->thisMonth(),
                'icon'  => 'dashicons-chart-line',
                'color' => 'green',
            ],

            [
                'title' => 'Conversion Rate',
                'value' => $leads->conversionRate() . '%',
                'icon'  => 'dashicons-yes-alt',
                'color' => 'teal',
            ],

            [
                'title' => 'Published Properties',
                'value' => wp_count_posts('property')->publish ?? 0,
                'icon'  => 'dashicons-admin-home',
                'color' => 'red',
            ],

            [
                'title' => 'Organic Traffic',
                'value' => $traffic->overview()['organic'],
                'icon'  => 'dashicons-chart-bar',
                'color' => 'blue',
            ],

        ];
    }

    /**
     * Quick Stats
     */
    public function quickStats(): array
    {
        return [

            'posts'      => wp_count_posts('post')->publish ?? 0,

            'pages'      => wp_count_posts('page')->publish ?? 0,

            'users'      => count_users()['total_users'] ?? 0,

            'plugins'    => count(get_plugins()),

            'themes'     => count(wp_get_themes()),

            'media'      => wp_count_posts('attachment')->inherit ?? 0,

        ];
    }

    /**
     * Dashboard Notices
     */
    public function notices(): array
    {
        return [

            'Website health is normal.',

            'SEO audit available.',

            'Google Analytics connection pending.',

            'Search Console connection pending.',

        ];
    }
}