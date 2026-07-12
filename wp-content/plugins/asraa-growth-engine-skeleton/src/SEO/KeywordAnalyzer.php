<?php
/**
 * Keyword Analyzer
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class KeywordAnalyzer
{
    /**
     * Analyze content for a keyword.
     */
    public function analyze(string $content, string $keyword): array
    {
        $content = wp_strip_all_tags($content);

        $keyword = trim(strtolower($keyword));

        if (empty($keyword)) {
            return [
                'keyword' => '',
                'count' => 0,
                'density' => 0,
                'score' => 0,
            ];
        }

        $contentLower = strtolower($content);

        $wordCount = str_word_count($contentLower);

        $occurrences = substr_count($contentLower, $keyword);

        $density = 0;

        if ($wordCount > 0) {
            $density = round(($occurrences / $wordCount) * 100, 2);
        }

        $score = $this->score($density);

        return [
            'keyword'      => $keyword,
            'count'        => $occurrences,
            'words'        => $wordCount,
            'density'      => $density,
            'score'        => $score,
            'title'        => $this->keywordInTitle($keyword),
            'description'  => $this->keywordInDescription($keyword),
            'url'          => $this->keywordInUrl($keyword),
            'headings'     => $this->keywordInHeadings($content, $keyword),
        ];
    }

    /**
     * Calculate SEO score.
     */
    protected function score(float $density): int
    {
        if ($density >= 0.8 && $density <= 2.5) {
            return 100;
        }

        if ($density >= 0.5 && $density < 0.8) {
            return 80;
        }

        if ($density > 2.5 && $density <= 4) {
            return 70;
        }

        return 40;
    }

    protected function keywordInTitle(string $keyword): bool
    {
        return stripos(wp_get_document_title(), $keyword) !== false;
    }

    protected function keywordInDescription(string $keyword): bool
    {
        $description = get_bloginfo('description');

        return stripos($description, $keyword) !== false;
    }

    protected function keywordInUrl(string $keyword): bool
    {
        return stripos(home_url(add_query_arg([], $GLOBALS['wp']->request)), $keyword) !== false;
    }

    protected function keywordInHeadings(string $content, string $keyword): bool
    {
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $matches);

        foreach ($matches[1] as $heading) {
            if (stripos(wp_strip_all_tags($heading), $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}