<?php
/**
 * AI Chatbot
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\AI;

if (!defined('ABSPATH')) {
    exit;
}

class Chatbot
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
     * Ask AI
     */
    public function ask(string $question): array
    {
        $prompt = $this->buildPrompt($question);

        return $this->generator->custom($prompt);
    }

    /**
     * Build Prompt
     */
    protected function buildPrompt(string $question): string
    {
        return sprintf(
            "You are Asraa Realty's AI Assistant.

Answer professionally and accurately.

You help customers with:

- Buying property
- Selling property
- Renting
- Commercial Real Estate
- Investment
- Home Loans
- Property Valuation
- Builders
- Projects
- Localities
- Legal Documentation

If the information is unknown, clearly say so rather than inventing an answer.

Customer Question:

%s",
            $question
        );
    }

    /**
     * Property Assistant
     */
    public function property(array $property, string $question): array
    {
        $prompt = sprintf(
            "Property Information:

%s

Customer Question:

%s",
            wp_json_encode($property),
            $question
        );

        return $this->generator->custom($prompt);
    }

    /**
     * Investment Assistant
     */
    public function investment(array $property): array
    {
        return $this->generator->custom(
            "Analyze this property as an investment and provide ROI insights:\n\n" .
            wp_json_encode($property)
        );
    }

    /**
     * Loan Assistant
     */
    public function loan(array $customer): array
    {
        return $this->generator->custom(
            "Guide this customer about home loan eligibility:\n\n" .
            wp_json_encode($customer)
        );
    }

    /**
     * Builder Assistant
     */
    public function builder(string $builder): array
    {
        return $this->generator->custom(
            "Provide a professional overview of builder: {$builder}"
        );
    }

    /**
     * Area Assistant
     */
    public function locality(string $location): array
    {
        return $this->generator->custom(
            "Explain why {$location} is a good location for buying property."
        );
    }

    /**
     * Conversation History
     */
    public function history(int $userId): array
    {
        return get_user_meta(
            $userId,
            '_asraa_ai_chat_history',
            true
        ) ?: [];
    }

    /**
     * Save Conversation
     */
    public function saveHistory(
        int $userId,
        string $question,
        string $answer
    ): void {

        $history = $this->history($userId);

        $history[] = [

            'time' => current_time('mysql'),

            'question' => $question,

            'answer' => $answer,

        ];

        update_user_meta(
            $userId,
            '_asraa_ai_chat_history',
            $history
        );
    }

    /**
     * Clear History
     */
    public function clearHistory(int $userId): void
    {
        delete_user_meta(
            $userId,
            '_asraa_ai_chat_history'
        );
    }
}