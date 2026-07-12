<?php
/**
 * SEO Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class SEOScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'SEO Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks the website for common SEO issues.';
    }

    /**
     * Scan
     */
    public function scan(): array
    {
        $issues = [];
        $details = [];

        /* Site Visibility */

        if (get_option('blog_public') == 0) {

            $issues[] = 'Search engines are discouraged from indexing this website.';

        }

        /* Tagline */

        if (get_option('blogdescription') === '') {

            $issues[] = 'Website tagline is empty.';

        }

        /* Home Title */

        $title = get_bloginfo('name');

        if (empty($title)) {

            $issues[] = 'Website title is empty.';

        }

        /* Permalinks */

        if (get_option('permalink_structure') === '') {

            $issues[] = 'Plain permalinks are enabled.';
        }

        /* Sitemap */

        $details['sitemap'] = home_url('/sitemap.xml');

        /* Robots */

        $details['robots'] = home_url('/robots.txt');

        /* Published Content */

        $details['posts'] = wp_count_posts('post')->publish ?? 0;

        $details['pages'] = wp_count_posts('page')->publish ?? 0;

        if (post_type_exists('property')) {

            $details['properties'] = wp_count_posts('property')->publish ?? 0;

        }

        /* Active SEO Plugins */

        if (defined('RANK_MATH_VERSION')) {

            $details['seo_plugin'][] = 'Rank Math';

        }

        if (defined('WPSEO_VERSION')) {

            $details['seo_plugin'][] = 'Yoast SEO';

        }

        if (defined('AIOSEO_VERSION')) {

            $details['seo_plugin'][] = 'All in One SEO';

        }

        if (!empty($issues)) {

            return $this->warning(
                $issues,
                [
                    'Enable search engine indexing.',
                    'Configure SEO settings.',
                    'Use pretty permalinks.',
                ],
                85
            );

        }

        return $this->success($details);
    }
}