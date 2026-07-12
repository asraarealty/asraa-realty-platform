<?php
/**
 * Description Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Description
{
    /**
     * Get description.
     */
    public static function get()
    {
        if (is_singular()) {

            $description = get_post_meta(
                get_the_ID(),
                '_asraa_meta_description',
                true
            );

            if (!empty($description)) {
                return $description;
            }

            return wp_trim_words(
                wp_strip_all_tags(get_the_excerpt()),
                30
            );
        }

        return get_bloginfo('description');
    }
}