<?php
/**
 * AI Automation Engine
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Automation
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('save_post', [$this, 'propertySaved'], 20, 3);

        add_action('add_attachment', [$this, 'imageUploaded']);

        add_action('asraa_daily_ai_jobs', [$this, 'dailyTasks']);
    }

    /**
     * Property Saved
     */
    public function propertySaved(
        int $postId,
        \WP_Post $post,
        bool $update
    ): void {

        if (wp_is_post_revision($postId)) {
            return;
        }

        if ($post->post_type !== 'property') {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        // Generate Property Description
        (new PropertyDescription())->save($postId);

        // Generate SEO Meta
        (new MetaGenerator())->save($postId);

        // Generate FAQ
        (new FAQGenerator())->save($postId);

        // Index Knowledge Base
        (new Embeddings())->indexPost($postId);
    }

    /**
     * Image Uploaded
     */
    public function imageUploaded(int $attachmentId): void
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        (new ImageAltGenerator())->save($attachmentId);
    }

    /**
     * Lead Created
     */
    public function leadCreated(int $leadId): void
    {
        (new LeadScoring())->save($leadId);
    }

    /**
     * Daily Automation
     */
    public function dailyTasks(): void
    {
        (new Embeddings())->indexAll();
    }

    /**
     * Enable Scheduler
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled('asraa_daily_ai_jobs')) {

            wp_schedule_event(
                time(),
                'daily',
                'asraa_daily_ai_jobs'
            );

        }
    }

    /**
     * Disable Scheduler
     */
    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(
            'asraa_daily_ai_jobs'
        );

        if ($timestamp) {

            wp_unschedule_event(
                $timestamp,
                'asraa_daily_ai_jobs'
            );

        }
    }

    /**
     * Manual Run
     */
    public function run(): void
    {
        $this->dailyTasks();
    }

    /**
     * Automation Status
     */
    public function status(): array
    {
        return [

            'scheduler' => wp_next_scheduled(
                'asraa_daily_ai_jobs'
            ),

            'provider' => Manager::defaultProvider(),

            'openai' => Manager::hasApiKey('openai'),

            'gemini' => Manager::hasApiKey('gemini'),

            'claude' => Manager::hasApiKey('claude'),

        ];
    }
}