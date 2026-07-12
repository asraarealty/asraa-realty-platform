<?php
/**
 * Property SEO Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class PropertySEO
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'injectPropertySchema']);
    }

    /**
     * Inject property SEO tags.
     */
    public function injectPropertySchema(): void
    {
        if (is_admin()) {
            return;
        }

        if (!is_singular('property')) {
            return;
        }

        global $post;

        $price = get_post_meta($post->ID, 'price', true);
        $city  = get_post_meta($post->ID, 'property_city', true);
        $area  = get_post_meta($post->ID, 'property_area', true);

        ?>
<meta name="property:type" content="RealEstate">

<meta name="property:price" content="<?php echo esc_attr($price); ?>">

<meta name="property:city" content="<?php echo esc_attr($city); ?>">

<meta name="property:area" content="<?php echo esc_attr($area); ?>">
<?php
    }

    /**
     * Generate SEO title.
     */
    public static function generateTitle(int $postId): string
    {
        $title = get_the_title($postId);

        $city = get_post_meta($postId, 'property_city', true);

        return trim($title . ' | Property in ' . $city);
    }

    /**
     * Generate SEO description.
     */
    public static function generateDescription(int $postId): string
    {
        $excerpt = get_the_excerpt($postId);

        if (empty($excerpt)) {
            $excerpt = wp_trim_words(
                strip_tags(get_post_field('post_content', $postId)),
                30
            );
        }

        return $excerpt;
    }
}