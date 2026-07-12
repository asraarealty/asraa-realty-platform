<?php
/**
 * Title Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Title
{
    public function __construct()
    {
        add_filter('pre_get_document_title', [$this, 'generate_title']);
    }

    /**
     * Generate SEO title.
     */
    public function generate_title($title)
    {
        if (is_front_page()) {
            return get_bloginfo('name') . ' | ' . get_bloginfo('description');
        }

        if (is_singular()) {
            $custom = get_post_meta(get_the_ID(), '_asraa_meta_title', true);

            if (!empty($custom)) {
                return $custom;
            }
        }

        return $title;
    }
}