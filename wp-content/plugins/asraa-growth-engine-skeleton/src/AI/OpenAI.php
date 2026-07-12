<?php
/**
 * OpenAI Provider
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class OpenAI
{
    /**
     * API Endpoint
     */
    protected string $endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * API Key
     */
    protected string $apiKey = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = (string) get_option('asraa_ai_openai_key', '');
    }

    /**
     * Check Connection
     */
    public function connected(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate AI Response
     */
    public function generate(string $prompt, string $model = 'gpt-5'): array
    {
        if (!$this->connected()) {
            return [
                'success' => false,
                'message' => 'OpenAI API key not configured.',
            ];
        }

        $response = wp_remote_post(
            $this->endpoint,
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $body = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        return [
            'success' => true,
            'response' => $body,
        ];
    }

    /**
     * Generate Property Description
     */
    public function propertyDescription(array $property): array
    {
        $prompt = sprintf(
            "Write a premium SEO-friendly property description for %s located in %s. Mention amenities, connectivity, investment potential and use a professional real estate tone.",
            $property['title'] ?? '',
            $property['location'] ?? ''
        );

        return $this->generate($prompt);
    }

    /**
     * Generate Meta Tags
     */
    public function meta(string $title): array
    {
        return $this->generate(
            "Generate SEO title and meta description for: {$title}"
        );
    }

    /**
     * Generate Blog
     */
    public function blog(string $topic): array
    {
        return $this->generate(
            "Write a detailed SEO blog about {$topic}"
        );
    }

    /**
     * Generate FAQs
     */
    public function faq(string $topic): array
    {
        return $this->generate(
            "Generate 10 frequently asked questions with answers about {$topic}"
        );
    }

    /**
     * Lead Analysis
     */
    public function scoreLead(array $lead): array
    {
        return $this->generate(
            "Score this real estate lead and explain why: " .
            wp_json_encode($lead)
        );
    }
}