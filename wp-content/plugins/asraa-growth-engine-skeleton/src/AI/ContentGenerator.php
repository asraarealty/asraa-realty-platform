<?php
/**
 * AI Content Generator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class ContentGenerator
{
    /**
     * Active Provider
     */
    protected function provider()
    {
        $provider = Manager::defaultProvider();

        switch ($provider) {

            case 'gemini':
                return new Gemini();

            case 'claude':
                return new Claude();

            case 'openai':
            default:
                return new OpenAI();

        }
    }

    /**
     * Generate Property Description
     */
    public function property(array $property): array
    {
        $prompt = PromptEngine::get(
            'property_description',
            $property
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate SEO Meta Title
     */
    public function metaTitle(string $title): array
    {
        $prompt = PromptEngine::get(
            'meta_title',
            [
                'title' => $title,
            ]
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate SEO Meta Description
     */
    public function metaDescription(string $title): array
    {
        $prompt = PromptEngine::get(
            'meta_description',
            [
                'title' => $title,
            ]
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate Blog
     */
    public function blog(string $topic): array
    {
        $prompt = PromptEngine::get(
            'blog',
            [
                'topic' => $topic,
            ]
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate FAQ
     */
    public function faq(string $topic): array
    {
        $prompt = PromptEngine::get(
            'faq',
            [
                'topic' => $topic,
            ]
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate Investment Analysis
     */
    public function investment(array $property): array
    {
        $prompt = PromptEngine::get(
            'investment_analysis',
            $property
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate Property Summary
     */
    public function summary(array $property): array
    {
        $prompt = PromptEngine::get(
            'property_summary',
            $property
        );

        return $this->provider()->generate($prompt);
    }

    /**
     * Generate Custom Prompt
     */
    public function custom(string $prompt): array
    {
        return $this->provider()->generate($prompt);
    }
}