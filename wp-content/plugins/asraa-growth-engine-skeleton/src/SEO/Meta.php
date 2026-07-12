<?php
/**
 * Meta Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Meta
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'render_meta'], 1);
    }

    /**
     * Render basic meta tags.
     */
    public function render_meta()
    {
        if (is_admin()) {
            return;
        }

        $title = wp_get_document_title();

        if (is_singular()) {
            $description = get_post_meta(get_the_ID(), '_asraa_meta_description', true);

            if (empty($description)) {
                $description = wp_trim_words(
                    wp_strip_all_tags(get_the_excerpt()),
                    30
                );
            }
        } else {
            $description = get_bloginfo('description');
        }

        echo "\n";
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta name="generator" content="Asraa Growth Engine">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    }
}