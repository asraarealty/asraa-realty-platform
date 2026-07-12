<?php
/**
 * AI Blog Generator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class BlogGenerator
{
    /**
     * Content Generator
     */
    protected ContentGenerator $generator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->generator = new ContentGenerator();
    }

    /**
     * Generate Blog
     */
    public function generate(string $topic): array
    {
        return $this->generator->blog($topic);
    }

    /**
     * Save Blog Draft
     */
    public function createDraft(string $topic): int
    {
        $result = $this->generate($topic);

        if (
            empty($result['success']) ||
            empty($result['response'])
        ) {
            return 0;
        }

        $content = '';

        // OpenAI
        if (isset($result['response']['choices'][0]['message']['content'])) {

            $content = $result['response']['choices'][0]['message']['content'];

        }

        // Gemini
        elseif (isset($result['response']['candidates'][0]['content']['parts'][0]['text'])) {

            $content = $result['response']['candidates'][0]['content']['parts'][0]['text'];

        }

        // Claude
        elseif (isset($result['response']['content'][0]['text'])) {

            $content = $result['response']['content'][0]['text'];

        }

        if (empty($content)) {
            return 0;
        }

        return wp_insert_post([

            'post_title' => sanitize_text_field($topic),

            'post_content' => wp_kses_post($content),

            'post_status' => 'draft',

            'post_type' => 'post',

        ]);
    }

    /**
     * Preview Blog
     */
    public function preview(string $topic): array
    {
        return $this->generate($topic);
    }

    /**
     * Suggested Blog Topics
     */
    public function suggestions(): array
    {
        return [

            'Top Real Estate Investment Opportunities',

            'Luxury Apartments in Mumbai',

            'Commercial Property Investment Guide',

            'Buying vs Renting Property',

            'Best Locations to Invest',

            'Upcoming Infrastructure Projects',

            'Home Buying Checklist',

            'Real Estate Market Trends',

            'Property Tax Guide',

            'RERA Guide for Buyers',

        ];
    }

    /**
     * Area Blog
     */
    public function area(string $area): array
    {
        return $this->generate(
            "Complete Area Guide for {$area}"
        );
    }

    /**
     * Builder Blog
     */
    public function builder(string $builder): array
    {
        return $this->generate(
            "Builder Profile and Projects of {$builder}"
        );
    }

    /**
     * Project Blog
     */
    public function project(string $project): array
    {
        return $this->generate(
            "Complete Review of {$project}"
        );
    }
}