<?php
/**
 * Broken Link Monitor
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class BrokenLinkMonitor
{
    /**
     * Scan website.
     */
    public function scan(): array
    {
        $results = [];

        $posts = get_posts([
            'post_type'      => ['post', 'page', 'property'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {

            preg_match_all(
                '/href=["\']([^"\']+)["\']/i',
                $post->post_content,
                $matches
            );

            if (empty($matches[1])) {
                continue;
            }

            foreach ($matches[1] as $url) {

                // Only scan internal links
                if (strpos($url, home_url()) !== 0) {
                    continue;
                }

                $path = wp_parse_url($url, PHP_URL_PATH);

                if (!$path) {
                    continue;
                }

                if (get_page_by_path(trim($path, '/'))) {
                    continue;
                }

                $results[] = [
                    'post_id'    => $post->ID,
                    'post_title' => get_the_title($post->ID),
                    'broken_url' => esc_url_raw($url),
                ];
            }
        }

        return $results;
    }

    /**
     * Save scan results.
     */
    public function save(array $links): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'asraa_broken_links';

        foreach ($links as $link) {

            $wpdb->insert(
                $table,
                [
                    'post_id'     => $link['post_id'],
                    'post_title'  => $link['post_title'],
                    'broken_url'  => $link['broken_url'],
                    'created_at'  => current_time('mysql'),
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }

    /**
     * Get all broken links.
     */
    public function all(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}asraa_broken_links ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Delete all records.
     */
    public function clear(): void
    {
        global $wpdb;

        $wpdb->query(
            "TRUNCATE TABLE {$wpdb->prefix}asraa_broken_links"
        );
    }
}