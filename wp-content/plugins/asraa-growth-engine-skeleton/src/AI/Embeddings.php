<?php
/**
 * AI Embeddings
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Embeddings
{
    /**
     * Database Table
     */
    protected string $table;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'asraa_ai_embeddings';
    }

    /**
     * Index a WordPress Post
     */
    public function indexPost(int $postId): bool
    {
        global $wpdb;

        $post = get_post($postId);

        if (!$post) {
            return false;
        }

        return (bool) $wpdb->replace(
            $this->table,
            [
                'object_id'   => $postId,
                'object_type' => $post->post_type,
                'title'       => $post->post_title,
                'content'     => wp_strip_all_tags($post->post_content),
                'updated_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Index All Content
     */
    public function indexAll(): int
    {
        $count = 0;

        $posts = get_posts([
            'post_type' => ['post', 'page', 'property'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($posts as $post) {

            if ($this->indexPost($post->ID)) {
                $count++;
            }

        }

        return $count;
    }

    /**
     * Search Knowledge Base
     */
    public function search(string $keyword, int $limit = 10): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE title LIKE %s
                 OR content LIKE %s
                 ORDER BY updated_at DESC
                 LIMIT %d",
                '%' . $wpdb->esc_like($keyword) . '%',
                '%' . $wpdb->esc_like($keyword) . '%',
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Delete Index
     */
    public function delete(int $objectId): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->table,
            [
                'object_id' => $objectId,
            ],
            [
                '%d',
            ]
        );
    }

    /**
     * Total Indexed Records
     */
    public function total(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table}"
        );
    }

    /**
     * Rebuild Index
     */
    public function rebuild(): int
    {
        global $wpdb;

        $wpdb->query(
            "TRUNCATE TABLE {$this->table}"
        );

        return $this->indexAll();
    }
}