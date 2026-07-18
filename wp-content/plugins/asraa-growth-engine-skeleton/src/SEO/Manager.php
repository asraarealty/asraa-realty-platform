<?php
/**
 * SEO Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Manager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'boot']);
    }

    /**
     * Boot all SEO modules
     */
    public function boot()
    {
        // Defer entirely to a real SEO plugin when one is active. This
        // in-house stack (title, meta, OG, canonical, robots, sitemap,
        // schema) was booting unconditionally alongside Rank Math,
        // producing duplicate/conflicting <title>, og:*, canonical,
        // robots, and JSON-LD output — and a second sitemap renderer —
        // on every page.
        if (defined('RANK_MATH_VERSION')) {
            return;
        }

        // Core SEO
        new Meta();
        new Title();
        new Description();

        // Social SEO
        new OpenGraph();

        // Technical SEO
        new Canonical();
        new Robots();
        new Sitemap();

        // Structured Data
        new Schema();

        // Navigation
        new Breadcrumbs();
    }
}