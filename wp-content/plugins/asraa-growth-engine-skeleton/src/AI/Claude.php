<?php
/**
 * Anthropic Claude Provider
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Claude
{
    /**
     * API Endpoint
     */
    protected string $endpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * API Key
     */
    protected string $apiKey = '';

    /**
     * Default Model
     */
    protected string $model = 'claude-sonnet-4-0';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = (string) get_option(
            'asraa_ai_claude_key',
            ''
        );
    }

    /**
     * Check Connection
     */
    public function connected(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate Content
     */
    public function generate(string $prompt): array
    {
        if (!$this->connected()) {

            return [
                'success' => false,
                'message' => 'Claude API key not configured.',
            ];

        }

        $response = wp_remote_post(
            $this->endpoint,
            [
                'timeout' => 60,

                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],

                'body' => wp_json_encode([
                    'model' => $this->model,
                    'max_tokens' => 2048,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]),
            ]
        );

        if (is_wp_error($response)) {

            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];

        }

        return [

            'success' => true,

            'response' => json_decode(
                wp_remote_retrieve_body($response),
                true
            ),

        ];
    }

    /**
     * Generate Property Description
     */
    public function propertyDescription(array $property): array
    {
        return $this->generate(
            sprintf(
                'Write a premium real estate property description for "%s" located in "%s".',
                $property['title'] ?? '',
                $property['location'] ?? ''
            )
        );
    }

    /**
     * Generate Blog
     */
    public function blog(string $topic): array
    {
        return $this->generate(
            "Write a detailed SEO blog about {$topic}."
        );
    }

    /**
     * Generate Meta Tags
     */
    public function meta(string $title): array
    {
        return $this->generate(
            "Generate an SEO title and meta description for {$title}."
        );
    }

    /**
     * Generate FAQs
     */
    public function faq(string $topic): array
    {
        return $this->generate(
            "Generate 10 SEO-friendly FAQs for {$topic}."
        );
    }

    /**
     * Score Lead
     */
    public function scoreLead(array $lead): array
    {
        return $this->generate(
            'Analyze and score this real estate lead: ' .
            wp_json_encode($lead)
        );
    }
}