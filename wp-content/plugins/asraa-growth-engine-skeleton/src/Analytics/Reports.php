<?php
/**
 * Reports Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Reports
{
    /**
     * Executive Report
     */
    public function executive(): array
    {
        $performance = new Performance();
        $traffic     = new Traffic();
        $leads       = new Leads();

        return [

            'generated' => current_time('mysql'),

            'website' => $performance->websiteScore(),

            'seo' => $performance->seoScore(),

            'health' => $performance->health(),

            'traffic' => $traffic->overview(),

            'crm' => $performance->crm(),

            'properties' => $performance->properties(),

            'latest_leads' => $leads->latest(5),

        ];
    }

    /**
     * SEO Report
     */
    public function seo(): array
    {
        return [

            'score' => 0,

            'indexed_pages' => 0,

            'meta_missing' => 0,

            'schema_errors' => 0,

            'broken_links' => 0,

            'redirects' => 0,

            'generated' => current_time('mysql'),

        ];
    }

    /**
     * Lead Report
     */
    public function leads(): array
    {
        $leads = new Leads();

        return [

            'total' => $leads->total(),

            'today' => $leads->today(),

            'month' => $leads->thisMonth(),

            'conversion_rate' => $leads->conversionRate(),

            'sources' => $leads->bySource(),

            'status' => $leads->byStatus(),

        ];
    }

    /**
     * Property Report
     */
    public function properties(): array
    {
        $performance = new Performance();

        return $performance->properties();
    }

    /**
     * Dashboard Report
     */
    public function dashboard(): array
    {
        return [

            'performance' => (new Performance())->websiteScore(),

            'traffic' => (new Traffic())->overview(),

            'crm' => (new Leads())->total(),

            'health' => (new Performance())->health(),

        ];
    }

    /**
     * Export JSON
     */
    public function export(): string
    {
        return wp_json_encode(
            $this->executive(),
            JSON_PRETTY_PRINT
        );
    }
}