<?php
/**
 * Breadcrumb Manager
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Breadcrumbs
{
    public function output()
    {
        echo '<nav class="asraa-breadcrumb">';
        echo '<a href="' . esc_url(home_url()) . '">Home</a>';

        if (is_singular()) {
            echo ' » ';
            the_title();
        }

        echo '</nav>';
    }
}