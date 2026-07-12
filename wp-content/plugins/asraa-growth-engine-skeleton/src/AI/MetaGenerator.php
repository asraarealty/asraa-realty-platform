<?php
/**
 * AI Meta Generator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class MetaGenerator
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
     * Generate SEO Meta
     */
    public function generate(string $title): array
    {
        $metaTitle = $this->generator->metaTitle($title);

        $metaDescription = $this->generator->metaDescription($title);

        return [

            'title' => $metaTitle,

            'description' => $metaDescription,

        ];
    }

    /**
     * Generate from Post
     */
    public function fromPost(int $postId): array
    {
        return $this->generate(
            get_the_title($postId)
        );
    }

    /**
     * Save Meta
     */
    public function save(int $postId): bool
    {
        $meta = $this->fromPost($postId);

        $title = '';
        $description = '';

        if (isset($meta['title']['response']['choices'][0]['message']['content'])) {

            $title = trim(
                $meta['title']['response']['choices'][0]['message']['content']
            );

        } elseif (isset($meta['title']['response']['candidates'][0]['content']['parts'][0]['text'])) {

            $title = trim(
                $meta['title']['response']['candidates'][0]['content']['parts'][0]['text']
            );

        }

        if (isset($meta['description']['response']['choices'][0]['message']['content'])) {

            $description = trim(
                $meta['description']['response']['choices'][0]['message']['content']
            );

        } elseif (isset($meta['description']['response']['candidates'][0]['content']['parts'][0]['text'])) {

            $description = trim(
                $meta['description']['response']['candidates'][0]['content']['parts'][0]['text']
            );

        }

        if (!empty($title)) {

            update_post_meta(
                $postId,
                '_asraa_meta_title',
                sanitize_text_field($title)
            );

        }

        if (!empty($description)) {

            update_post_meta(
                $postId,
                '_asraa_meta_description',
                sanitize_textarea_field($description)
            );

        }

        return true;
    }

    /**
     * Preview
     */
    public function preview(int $postId): array
    {
        return $this->fromPost($postId);
    }
}