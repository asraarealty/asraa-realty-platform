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