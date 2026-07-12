<?php
/**
 * Internal Links Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class InternalLinks
{
    /**
     * Find internal linking opportunities.
     */
    public function suggestions(int $postId): array
    {
        $suggestions = [];

        $post = get_post($postId);

        if (!$post) {
            return [];
        }

        $words = array_unique(
            preg_split('/\s+/', strtolower(wp_strip_all_tags($post->post_title)))
        );

        $posts = get_posts([
            'post_type'      => ['post', 'page', 'property'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'exclude'        => [$postId],
        ]);

        foreach ($posts as $item) {

            $score = 0;

            foreach ($words as $word) {

                if (strlen($word) < 4) {
                    continue;
                }

                if (
                    stripos($item->post_title, $word) !== false ||
                    stripos($item->post_content, $word) !== false
                ) {
                    $score++;
                }
            }

            if ($score > 0) {

                $suggestions[] = [
                    'post_id' => $item->ID,
                    'title'   => $item->post_title,
                    'url'     => get_permalink($item->ID),
                    'score'   => $score,
                ];
            }
        }

        usort(
            $suggestions,
            function ($a, $b) {
                return $b['score'] <=> $a['score'];
            }
        );

        return array_slice($suggestions, 0, 10);
    }

    /**
     * Find orphan pages.
     */
    public function orphanPages(): array
    {
        $orphans = [];

        $posts = get_posts([
            'post_type'      => ['post', 'page', 'property'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {

            $url = get_permalink($post->ID);

            global $wpdb;

            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(ID)
                     FROM {$wpdb->posts}
                     WHERE post_content LIKE %s",
                    '%' . $wpdb->esc_like($url) . '%'
                )
            );

            if ($count <= 1) {
                $orphans[] = [
                    'post_id' => $post->ID,
                    'title'   => $post->post_title,
                    'url'     => $url,
                ];
            }
        }

        return $orphans;
    }

    /**
     * Calculate internal linking score.
     */
    public function score(int $postId): int
    {
        $post = get_post($postId);

        if (!$post) {
            return 0;
        }

        preg_match_all(
            '/<a\s[^>]*href=["\']([^"\']+)["\']/i',
            $post->post_content,
            $matches
        );

        $internal = 0;

        foreach ($matches[1] as $link) {

            if (strpos($link, home_url()) === 0) {
                $internal++;
            }
        }

        if ($internal >= 10) {
            return 100;
        }

        return min(100, $internal * 10);
    }
}