<?php
/**
 * AI FAQ Generator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class FAQGenerator
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
     * Generate FAQs
     */
    public function generate(string $topic): array
    {
        return $this->generator->faq($topic);
    }

    /**
     * Generate FAQs from Post
     */
    public function fromPost(int $postId): array
    {
        return $this->generate(
            get_the_title($postId)
        );
    }

    /**
     * Save FAQs
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
            return false;
        }

        update_post_meta(
            $postId,
            '_asraa_faq_content',
            wp_kses_post($content)
        );

        return true;
    }

    /**
     * Preview FAQs
     */
    public function preview(int $postId): array
    {
        return $this->fromPost($postId);
    }

    /**
     * Generate FAQ Schema
     */
    public function schema(int $postId): array
    {
        $faq = get_post_meta(
            $postId,
            '_asraa_faq_content',
            true
        );

        return [

            '@context' => 'https://schema.org',

            '@type' => 'FAQPage',

            'content' => $faq,

        ];
    }
}