<?php
/**
 * Health Score Calculator
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class HealthScore
{
    /**
     * Calculate overall health score.
     *
     * @param array $results Scanner results.
     * @return array
     */
    public function calculate(array $results): array
    {
        $score = 100;

        $critical = 0;
        $warnings = 0;
        $passed = 0;

        foreach ($results as $result) {

            if (empty($result['result'])) {
                continue;
            }

            $scan = $result['result'];

            switch ($scan['status']) {

                case 'critical':
                    $critical++;
                    $score -= 15;
                    break;

                case 'warning':
                    $warnings++;
                    $score -= 5;
                    break;

                case 'success':
                    $passed++;
                    break;
            }

        }

        if ($score < 0) {
            $score = 0;
        }

        return [

            'health_score' => $score,

            'critical' => $critical,

            'warnings' => $warnings,

            'passed' => $passed,

            'grade' => $this->grade($score),

        ];
    }

    /**
     * Grade
     */
    protected function grade(int $score): string
    {
        if ($score >= 95) {
            return 'A+';
        }

        if ($score >= 90) {
            return 'A';
        }

        if ($score >= 80) {
            return 'B';
        }

        if ($score >= 70) {
            return 'C';
        }

        if ($score >= 60) {
            return 'D';
        }

        return 'F';
    }
}