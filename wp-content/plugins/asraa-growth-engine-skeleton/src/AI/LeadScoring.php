<?php
/**
 * AI Lead Scoring
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class LeadScoring
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
     * Score Lead using AI
     */
    public function score(array $lead): array
    {
        $prompt = PromptEngine::get(
            'lead_score',
            $lead
        );

        return $this->generator->custom($prompt);
    }

    /**
     * Score Lead from CRM
     */
    public function fromCRM(int $leadId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'asraa_crm_leads';

        $lead = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id=%d",
                $leadId
            ),
            ARRAY_A
        );

        if (!$lead) {
            return [
                'success' => false,
                'message' => 'Lead not found.',
            ];
        }

        return $this->score($lead);
    }

    /**
     * Save AI Score
     */
    public function save(int $leadId): bool
    {
        global $wpdb;

        $result = $this->fromCRM($leadId);

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
            $leadId,
            '_asraa_ai_lead_score',
            wp_kses_post($content)
        );

        return true;
    }

    /**
     * Preview Score
     */
    public function preview(int $leadId): array
    {
        return $this->fromCRM($leadId);
    }

    /**
     * Priority
     */
    public function priority(int $score): string
    {
        if ($score >= 85) {
            return 'Hot';
        }

        if ($score >= 60) {
            return 'Warm';
        }

        return 'Cold';
    }

    /**
     * Next Action
     */
    public function recommendation(string $priority): string
    {
        switch ($priority) {

            case 'Hot':
                return 'Assign immediately and schedule a site visit.';

            case 'Warm':
                return 'Follow up within 24 hours.';

            default:
                return 'Add to automated nurturing campaign.';
        }
    }
}