<?php
/**
 * Prompt Engine
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class PromptEngine
{
    /**
     * Get Prompt
     */
    public static function get(string $type, array $data = []): string
    {
        switch ($type) {

            case 'property_description':

                return sprintf(
                    "Write a premium SEO-friendly real estate property description for '%s' located in '%s'. Mention configuration, amenities, connectivity, lifestyle, investment potential, and conclude with a compelling call-to-action.",
                    $data['title'] ?? '',
                    $data['location'] ?? ''
                );

            case 'meta_title':

                return sprintf(
                    "Generate an SEO title under 60 characters for '%s'.",
                    $data['title'] ?? ''
                );

            case 'meta_description':

                return sprintf(
                    "Generate an SEO meta description under 160 characters for '%s'.",
                    $data['title'] ?? ''
                );

            case 'blog':

                return sprintf(
                    "Write a detailed SEO blog about '%s' with H1, H2, FAQs, conclusion and a professional tone.",
                    $data['topic'] ?? ''
                );

            case 'faq':

                return sprintf(
                    "Generate 10 frequently asked questions with answers about '%s'.",
                    $data['topic'] ?? ''
                );

            case 'lead_score':

                return sprintf(
                    "Analyze this real estate lead and return Priority (Hot/Warm/Cold), Score (0-100), Reason and Recommended Next Action. Lead Data: %s",
                    wp_json_encode($data)
                );

            case 'property_summary':

                return sprintf(
                    "Summarize the following property for a buyer in less than 120 words: %s",
                    wp_json_encode($data)
                );

            case 'investment_analysis':

                return sprintf(
                    "Analyze this property as a long-term investment and provide pros, cons, risks and opportunities: %s",
                    wp_json_encode($data)
                );

            default:

                return $data['prompt'] ?? '';
        }
    }

    /**
     * Available Prompt Types
     */
    public static function types(): array
    {
        return [

            'property_description',

            'meta_title',

            'meta_description',

            'blog',

            'faq',

            'lead_score',

            'property_summary',

            'investment_analysis',

        ];
    }
}