<?php
/**
 * Analytics Charts
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Charts
{
    /**
     * Visitors Chart
     */
    public function visitors(int $days = 30): array
    {
        $labels = [];
        $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {

            $labels[] = date(
                'd M',
                strtotime("-{$i} days")
            );

            $values[] = 0;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Leads Chart
     */
    public function leads(): array
    {
        return [
            'labels' => [
                'Mon','Tue','Wed','Thu','Fri','Sat','Sun'
            ],
            'values' => [
                0,0,0,0,0,0,0
            ],
        ];
    }

    /**
     * Traffic Sources
     */
    public function trafficSources(): array
    {
        return [
            'labels' => [
                'Organic',
                'Direct',
                'Referral',
                'Social',
                'Paid'
            ],
            'values' => [
                0,0,0,0,0
            ],
        ];
    }

    /**
     * Property Views
     */
    public function propertyViews(): array
    {
        return [
            'labels' => [],
            'values' => [],
        ];
    }

    /**
     * Lead Sources
     */
    public function leadSources(): array
    {
        return [
            'labels' => [],
            'values' => [],
        ];
    }

    /**
     * SEO Trend
     */
    public function seoTrend(): array
    {
        return [
            'labels' => [],
            'values' => [],
        ];
    }

    /**
     * Conversion Funnel
     */
    public function conversion(): array
    {
        return [
            'labels' => [
                'Visitors',
                'Leads',
                'Qualified',
                'Visits',
                'Sales'
            ],
            'values' => [
                0,0,0,0,0
            ],
        ];
    }

    /**
     * Dashboard Charts
     */
    public function dashboard(): array
    {
        return [

            'visitors' => $this->visitors(),

            'leads' => $this->leads(),

            'traffic' => $this->trafficSources(),

            'conversion' => $this->conversion(),

        ];
    }
}