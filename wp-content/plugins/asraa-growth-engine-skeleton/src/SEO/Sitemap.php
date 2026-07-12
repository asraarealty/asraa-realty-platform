<?php
/**
 * XML Sitemap Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Sitemap
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_rewrite']);
        add_action('template_redirect', [$this, 'render']);
    }

    /**
     * Register sitemap rewrite
     */
    public function register_rewrite()
    {
        add_rewrite_rule(
            '^asraa-sitemap\.xml$',
            'index.php?asraa_sitemap=1',
            'top'
        );

        add_filter('query_vars', function ($vars) {
            $vars[] = 'asraa_sitemap';
            return $vars;
        });
    }

    /**
     * Render sitemap
     */
    public function render()
    {
        if (get_query_var('asraa_sitemap') != 1) {
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

<?php

$post_types = [
    'page',
    'post',
    'property'
];

foreach ($post_types as $type) {

    $posts = get_posts([
        'post_type'      => $type,
        'post_status'    => 'publish',
        'posts_per_page' => -1
    ]);

    foreach ($posts as $post) {

?>
<url>
    <loc><?php echo esc_url(get_permalink($post->ID)); ?></loc>
    <lastmod><?php echo esc_html(get_the_modified_date('c', $post->ID)); ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
</url>
<?php

    }

}

?>

</urlset>

<?php

        exit;
    }
}