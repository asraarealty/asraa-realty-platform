<?php
/**
 * AI Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Manager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'boot']);
    }

    /**
     * Boot AI Engine
     */
    public function boot(): void
    {
        // AI Providers
        new OpenAI();
        new Gemini();
        new Claude();

        // Core Engine
        new PromptEngine();

        // Content Generation
        new ContentGenerator();
        new PropertyDescription();
        new MetaGenerator();
        new FAQGenerator();
        new BlogGenerator();
        new ImageAltGenerator();

        // CRM Intelligence
        new LeadScoring();

        // AI Assistant
        new Chatbot();

        // Knowledge Base
        new Embeddings();

        // Workflow Automation
        new Automation();
    }

    /**
     * Available Providers
     */
    public static function providers(): array
    {
        return [
            'openai' => 'OpenAI',
            'gemini' => 'Google Gemini',
            'claude' => 'Anthropic Claude',
        ];
    }

    /**
     * Default Provider
     */
    public static function defaultProvider(): string
    {
        return get_option(
            'asraa_ai_provider',
            'openai'
        );
    }

    /**
     * Check API Key
     */
    public static function hasApiKey(string $provider): bool
    {
        return !empty(
            get_option(
                'asraa_ai_' . $provider . '_key'
            )
        );
    }
}