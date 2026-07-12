<?php
/**
 * Diagnostics Report
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class Report
{
    /**
     * Diagnostics Manager
     */
    protected Manager $manager;

    /**
     * Health Score
     */
    protected HealthScore $health;

    /**
     * Logger
     */
    protected Logger $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->manager = new Manager();
        $this->health = new HealthScore();
        $this->logger = new Logger();
    }

    /**
     * Generate Full Report
     */
    public function generate(): array
    {
        $results = $this->manager->run();

        $health = $this->health->calculate($results);

        return [

            'generated_at' => current_time('mysql'),

            'site' => [

                'name' => get_bloginfo('name'),

                'url' => home_url(),

                'wordpress' => get_bloginfo('version'),

                'php' => PHP_VERSION,

            ],

            'summary' => $health,

            'scanners' => $results,

            'logs' => $this->logger->latest(100),

        ];
    }

    /**
     * Export JSON
     */
    public function json(): string
    {
        return wp_json_encode(
            $this->generate(),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Export Array
     */
    public function array(): array
    {
        return $this->generate();
    }

    /**
     * Summary
     */
    public function summary(): array
    {
        return $this->generate()['summary'];
    }

    /**
     * Latest Logs
     */
    public function logs(): array
    {
        return $this->logger->latest(50);
    }
}