<?php
/**
 * Base Diagnostics Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Scanner
{
    /**
     * Scanner name.
     */
    abstract public function name(): string;

    /**
     * Scanner description.
     */
    abstract public function description(): string;

    /**
     * Run scanner.
     *
     * Must return:
     * [
     *   'status' => 'success|warning|critical',
     *   'score' => 100,
     *   'issues' => [],
     *   'recommendations' => [],
     *   'details' => []
     * ]
     */
    abstract public function scan(): array;

    /**
     * Success response.
     */
    protected function success(
        array $details = [],
        array $recommendations = []
    ): array {

        return [
            'status' => 'success',
            'score' => 100,
            'issues' => [],
            'recommendations' => $recommendations,
            'details' => $details,
        ];
    }

    /**
     * Warning response.
     */
    protected function warning(
        array $issues,
        array $recommendations = [],
        int $score = 80
    ): array {

        return [
            'status' => 'warning',
            'score' => $score,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'details' => [],
        ];
    }

    /**
     * Critical response.
     */
    protected function critical(
        array $issues,
        array $recommendations = [],
        int $score = 30
    ): array {

        return [
            'status' => 'critical',
            'score' => $score,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'details' => [],
        ];
    }

    /**
     * Information response.
     */
    protected function info(array $details): array
    {
        return [
            'status' => 'info',
            'score' => 100,
            'issues' => [],
            'recommendations' => [],
            'details' => $details,
        ];
    }
}