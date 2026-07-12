<?php
/**
 * SEO Audit Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class SEOAudit
{
    /**
     * Run SEO audit for a post.
     */
    public function audit(int $postId): array
    {
        $post = get_post($postId);

        if (!$post) {
            return [];
        }

        $report = [];

        $score = 100;

        // Title
        $title = get_the_title($postId);

        if (strlen($title) < 30 || strlen($title) > 60) {
            $score -= 10;
            $report[] = 'SEO title should be between 30 and 60 characters.';
        }

        // Description
        $description = get_post_meta(
            $postId,
            '_asraa_meta_description',
            true
        );

        if (empty($description)) {
            $score -= 15;
            $report[] = 'Meta description is missing.';
        } elseif (strlen($description) > 160) {
            $score -= 5;
            $report[] = 'Meta description is too long.';
        }

        // Featured image
        if (!has_post_thumbnail($postId)) {
            $score -= 10;
            $report[] = 'Featured image is missing.';
        }

        // Content length
        $content = wp_strip_all_tags($post->post_content);

        $words = str_word_count($content);

        if ($words < 300) {
            $score -= 15;
            $report[] = 'Content should contain at least 300 words.';
        }

        // Internal links
        $internal = new InternalLinks();

        $internalScore = $internal->score($postId);

        if ($internalScore < 50) {
            $score -= 10;
            $report[] = 'Add more internal links.';
        }

        // Keyword analysis
        $keyword = get_post_meta(
            $postId,
            '_asraa_focus_keyword',
            true
        );

        if (!empty($keyword)) {

            $analyzer = new KeywordAnalyzer();

            $keywordData = $analyzer->analyze(
                $post->post_content,
                $keyword
            );

            if ($keywordData['score'] < 80) {
                $score -= 10;
                $report[] = 'Improve keyword optimization.';
            }

        } else {

            $report[] = 'Focus keyword is not configured.';
        }

        $score = max(0, min(100, $score));

        return [

            'score' => $score,

            'status' => $this->status($score),

            'recommendations' => $report,

        ];
    }

    /**
     * SEO status.
     */
    protected function status(int $score): string
    {
        if ($score >= 90) {
            return 'Excellent';
        }

        if ($score >= 75) {
            return 'Good';
        }

        if ($score >= 60) {
            return 'Needs Improvement';
        }

        return 'Poor';
    }
}