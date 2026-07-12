<?php
if (!defined('ABSPATH')) exit;

class Asraa_Lead_Scoring_Service {

    /**
     * Calculate a lead score (0–100) based on available data.
     *
     * @param array $lead Lead row as associative array.
     * @return int
     */
    public function calculate_score( array $lead ) {
        $score = 0;

        $budget = $lead['budget'] ?? 0;
        if (!$budget && !empty($lead['budget_max'])) {
            $budget = $lead['budget_max'];
        } elseif (!$budget && !empty($lead['budget_min'])) {
            $budget = $lead['budget_min'];
        }
        if (asraa_crm_parse_budget_value($budget) > 10000000) $score += 30;
        if (!empty($lead['property_type'])) $score += 20;
        if (!empty($lead['location']) || !empty($lead['preferred_locations'])) $score += 10;
        if (!empty($lead['phone'])) $score += 20;

        return min($score, 100);
    }

    /**
     * Return a human-readable grade for a given score.
     *
     * @param int $score
     * @return string
     */
    public function get_grade( $score ) {
        if ($score >= 80) return 'A';
        if ($score >= 60) return 'B';
        if ($score >= 40) return 'C';
        return 'D';
    }
}
