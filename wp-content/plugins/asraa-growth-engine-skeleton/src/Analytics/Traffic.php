<?php
/**
 * Traffic Analytics
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Traffic
{
    /**
     * Traffic Overview
     */
    public function overview(): array
    {
        return [
            'organic' => 0,
            'direct' => 0,
            'referral' => 0,
            'social' => 0,
            'paid' => 0,
            'email' => 0,
            'other' => 0,
        ];
    }

    /**
     * Top Landing Pages
     */
    public function landingPages(int $limit = 10): array
    {
        return [];
    }

    /**
     * Top Exit Pages
     */
    public function exitPages(int $limit = 10): array
    {
        return [];
    }

    /**
     * Property Views
     */
    public function propertyViews(): array
    {
        $properties = [];

        $posts = get_posts([
            'post_type' => 'property',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {

            $properties[] = [

                'id' => $post->ID,

                'title' => $post->post_title,

                'views' => (int) get_post_meta(
                    $post->ID,
                    '_asraa_views',
                    true
                ),

            ];

        }

        return $properties;
    }

    /**
     * Device Statistics
     */
    public function devices(): array
    {
        return [

            'desktop' => 0,

            'mobile' => 0,

            'tablet' => 0,

        ];
    }

    /**
     * Browser Statistics
     */
    public function browsers(): array
    {
        return [

            'Chrome' => 0,

            'Edge' => 0,

            'Safari' => 0,

            'Firefox' => 0,

            'Other' => 0,

        ];
    }

    /**
     * Country Statistics
     */
    public function countries(): array
    {
        return [];
    }

    /**
     * City Statistics
     */
    public function cities(): array
    {
        return [];
    }

    /**
     * Daily Visitors
     */
    public function dailyVisitors(int $days = 30): array
    {
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {

            $data[] = [

                'date' => date(
                    'Y-m-d',
                    strtotime("-{$i} days")
                ),

                'visitors' => 0,

            ];

        }

        return $data;
    }

    /**
     * Monthly Visitors
     */
    public function monthlyVisitors(): array
    {
        return [];
    }
}