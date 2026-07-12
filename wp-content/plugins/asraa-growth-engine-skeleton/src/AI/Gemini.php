<?php
/**
 * Google Gemini Provider
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Gemini
{
    /**
     * API Endpoint
     */
    protected string $endpoint =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    /**
     * API Key
     */
    protected string $apiKey = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = (string) get_option(
            'asraa_ai_gemini_key',
            ''
        );
    }

    /**
     * Connected?
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
                'message' => 'Gemini API key not configured.',
            ];

        }

        $url = add_query_arg(
            [
                'key' => $this->apiKey,
            ],
            $this->endpoint
        );

        $response = wp_remote_post(
            $url,
            [
                'timeout' => 60,

                'headers' => [
                    'Content-Type' => 'application/json',
                ],

                'body' => wp_json_encode(
                    [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $prompt,
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
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
     * Property Description
     */
    public function propertyDescription(array $property): array
    {
        return $this->generate(
            sprintf(
                'Write a luxury SEO-friendly property description for %s in %s.',
                $property['title'] ?? '',
                $property['location'] ?? ''
            )
        );
    }

    /**
     * SEO Meta
     */
    public function meta(string $title): array
    {
        return $this->generate(
            "Generate SEO title and meta description for {$title}"
        );
    }

    /**
     * Blog Generator
     */
    public function blog(string $topic): array
    {
        return $this->generate(
            "Write a detailed SEO blog about {$topic}"
        );
    }

    /**
     * FAQ Generator
     */
    public function faq(string $topic): array
    {
        return $this->generate(
            "Generate 10 FAQs for {$topic}"
        );
    }

    /**
     * Lead Scoring
     */
    public function scoreLead(array $lead): array
    {
        return $this->generate(
            'Score this real estate lead: ' .
            wp_json_encode($lead)
        );
    }
}