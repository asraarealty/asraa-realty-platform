<?php
/**
 * AI Image ALT Generator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class ImageAltGenerator
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
     * Generate ALT Text
     */
    public function generate(array $image): array
    {
        $prompt = sprintf(
            'Generate an SEO-friendly ALT text, image title, caption and description for a real estate image named "%s". Property: %s. Location: %s.',
            $image['filename'] ?? '',
            $image['property'] ?? '',
            $image['location'] ?? ''
        );

        return $this->generator->custom($prompt);
    }

    /**
     * Generate from Attachment
     */
    public function fromAttachment(int $attachmentId): array
    {
        return $this->generate([

            'filename' => basename(
                get_attached_file($attachmentId)
            ),

            'property' => get_the_title(
                wp_get_post_parent_id($attachmentId)
            ),

            'location' => get_post_meta(
                wp_get_post_parent_id($attachmentId),
                'property_city',
                true
            ),

        ]);
    }

    /**
     * Save Generated Metadata
     */
    public function save(int $attachmentId): bool
    {
        $result = $this->fromAttachment($attachmentId);

        if (
            empty($result['success']) ||
            empty($result['response'])
        ) {
            return false;
        }

        $text = '';

        // OpenAI
        if (isset($result['response']['choices'][0]['message']['content'])) {

            $text = $result['response']['choices'][0]['message']['content'];

        }

        // Gemini
        elseif (isset($result['response']['candidates'][0]['content']['parts'][0]['text'])) {

            $text = $result['response']['candidates'][0]['content']['parts'][0]['text'];

        }

        // Claude
        elseif (isset($result['response']['content'][0]['text'])) {

            $text = $result['response']['content'][0]['text'];

        }

        if (empty($text)) {
            return false;
        }

        update_post_meta(
            $attachmentId,
            '_wp_attachment_image_alt',
            sanitize_text_field($text)
        );

        wp_update_post([
            'ID' => $attachmentId,
            'post_excerpt' => sanitize_text_field($text),
            'post_content' => wp_kses_post($text),
        ]);

        return true;
    }

    /**
     * Bulk Generate
     */
    public function bulk(array $attachments): int
    {
        $updated = 0;

        foreach ($attachments as $attachmentId) {

            if ($this->save((int) $attachmentId)) {
                $updated++;
            }

        }

        return $updated;
    }

    /**
     * Preview
     */
    public function preview(int $attachmentId): array
    {
        return $this->fromAttachment($attachmentId);
    }
}