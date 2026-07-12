<?php
/**
 * AI Property Description Generator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyDescription
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
     * Generate Description
     */
    public function generate(array $property): array
    {
        return $this->generator->property($property);
    }

    /**
     * Generate From Post
     */
    public function fromPost(int $postId): array
    {
        $property = [

            'title' => get_the_title($postId),

            'location' => get_post_meta(
                $postId,
                'property_city',
                true
            ),

            'developer' => get_post_meta(
                $postId,
                'property_developer',
                true
            ),

            'price' => get_post_meta(
                $postId,
                'price',
                true
            ),

            'configuration' => get_post_meta(
                $postId,
                'property_configuration',
                true
            ),

            'area' => get_post_meta(
                $postId,
                'property_area',
                true
            ),

            'amenities' => get_post_meta(
                $postId,
                'property_amenities',
                true
            ),

            'rera' => get_post_meta(
                $postId,
                'property_rera',
                true
            ),

            'possession' => get_post_meta(
                $postId,
                'property_possession',
                true
            ),

        ];

        return $this->generate($property);
    }

    /**
     * Save Generated Description
     */
    public function save(int $postId): bool
    {
        $result = $this->fromPost($postId);

        if (
            empty($result['success']) ||
            empty($result['response'])
        ) {
            return false;
        }

        $content = '';

        if (isset($result['response']['choices'][0]['message']['content'])) {

            $content = $result['response']['choices'][0]['message']['content'];

        } elseif (isset($result['response']['candidates'][0]['content']['parts'][0]['text'])) {

            $content = $result['response']['candidates'][0]['content']['parts'][0]['text'];

        }

        if (empty($content)) {
            return false;
        }

        wp_update_post([

            'ID' => $postId,

            'post_content' => wp_kses_post($content),

        ]);

        return true;
    }

    /**
     * Preview Description
     */
    public function preview(int $postId): string
    {
        $result = $this->fromPost($postId);

        return wp_json_encode(
            $result,
            JSON_PRETTY_PRINT
        );
    }
}