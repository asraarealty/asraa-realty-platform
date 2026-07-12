<?php
/**
 * Analytics Export Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Exports
{
    /**
     * Export Dashboard Data
     */
    public function dashboard(): array
    {
        return (new Reports())->dashboard();
    }

    /**
     * Export Executive Report
     */
    public function executive(): array
    {
        return (new Reports())->executive();
    }

    /**
     * Export SEO Report
     */
    public function seo(): array
    {
        return (new Reports())->seo();
    }

    /**
     * Export Lead Report
     */
    public function leads(): array
    {
        return (new Reports())->leads();
    }

    /**
     * Export Property Report
     */
    public function properties(): array
    {
        return (new Reports())->properties();
    }

    /**
     * JSON Export
     */
    public function json(): string
    {
        return wp_json_encode(
            $this->executive(),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * CSV Export
     */
    public function csv(): string
    {
        $report = $this->executive();

        $csv = '';

        foreach ($report as $key => $value) {

            if (is_array($value)) {
                $value = wp_json_encode($value);
            }

            $csv .= '"' . $key . '","' . $value . '"' . PHP_EOL;
        }

        return $csv;
    }

    /**
     * Excel Export (Placeholder)
     */
    public function excel(): array
    {
        return $this->executive();
    }

    /**
     * PDF Export (Placeholder)
     */
    public function pdf(): array
    {
        return $this->executive();
    }
}