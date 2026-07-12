<?php
/**
 * Open Graph Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class OpenGraph
{
    public function __construct()
    {
        add_action('wp_head', [$this, 'render'], 5);
    }

    public function render()
    {
        if (is_admin()) {
            return;
        }

        global $post;

        $title = wp_get_document_title();
        $description = Description::get();
        $url = home_url(add_query_arg([], $GLOBALS['wp']->request));

        $image = '';

        if (is_singular() && has_post_thumbnail($post)) {
            $image = get_the_post_thumbnail_url($post->ID, 'full');
        }

        ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr($title); ?>">
<meta property="og:description" content="<?php echo esc_attr($description); ?>">
<meta property="og:url" content="<?php echo esc_url($url); ?>">
<?php if (!empty($image)) : ?>
<meta property="og:image" content="<?php echo esc_url($image); ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?php bloginfo('name'); ?>">
<?php
    }
}