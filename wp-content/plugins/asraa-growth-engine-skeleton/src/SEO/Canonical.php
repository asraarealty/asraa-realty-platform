<?php
/**
 * Canonical URL
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Canonical
{
    public function __construct()
    {
        add_action('wp_head', [$this, 'render'], 2);
    }

    public function render()
    {
        if (is_admin()) {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">' . PHP_EOL;
    }
}