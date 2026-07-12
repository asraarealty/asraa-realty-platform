<?php
/**
 * Robots Manager
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Robots
{
    public function __construct()
    {
        add_filter('wp_robots', [$this, 'robots']);
    }

    public function robots($robots)
    {
        $robots['max-image-preview'] = 'large';
        $robots['max-snippet'] = -1;
        $robots['max-video-preview'] = -1;

        return $robots;
    }
}