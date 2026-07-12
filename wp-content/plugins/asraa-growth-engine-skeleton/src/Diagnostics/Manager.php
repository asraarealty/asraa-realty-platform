<?php
/**
 * Diagnostics Manager
 *
 * Central manager for all diagnostic scanners.
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class Manager
{
    /**
     * Registered Scanners
     *
     * @var Scanner[]
     */
    protected array $scanners = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registerDefaultScanners();
    }

    /**
     * Initialize Diagnostics
     */
    public function init(): void
    {
        add_action('admin_init', [$this, 'reload']);
    }

    /**
     * Register Built-in Scanners
     */
    protected function registerDefaultScanners(): void
    {
        $scannerClasses = [

            // Core
            DuplicateScanner::class,
            CodeScanner::class,
            HookScanner::class,

            // Filesystem
            FileScanner::class,

            // WordPress
            PluginScanner::class,
            ThemeScanner::class,

            // Website
            SEOScanner::class,
            PerformanceScanner::class,
            AssetScanner::class,

            // System
            DatabaseScanner::class,
            SecurityScanner::class,
            APIScanner::class,

        ];

        foreach ($scannerClasses as $class) {

            if (!class_exists($class)) {
                continue;
            }

            try {

                $scanner = new $class();

                if ($scanner instanceof Scanner) {
                    $this->register($scanner);
                }

            } catch (\Throwable $e) {

                error_log(
                    '[ASRAA Diagnostics] Failed loading scanner ' .
                    $class .
                    ': ' .
                    $e->getMessage()
                );

            }

        }
    }

    /**
     * Register Scanner
     */
    public function register(Scanner $scanner): void
    {
        $this->scanners[] = $scanner;
    }

    /**
     * Get Registered Scanners
     */
    public function scanners(): array
    {
        return $this->scanners;
    }

    /**
     * Total Registered Scanners
     */
    public function count(): int
    {
        return count($this->scanners);
    }

    /**
     * Scanner Exists
     */
    public function has(string $class): bool
    {
        foreach ($this->scanners as $scanner) {

            if ($scanner instanceof $class) {
                return true;
            }

        }

        return false;
    }

    /**
     * Run All Scanners
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->scanners as $scanner) {

            try {

                $results[] = [

                    'name' => $scanner->name(),

                    'description' => $scanner->description(),

                    'result' => $scanner->scan(),

                ];

            } catch (\Throwable $e) {

                $results[] = [

                    'name' => $scanner->name(),

                    'description' => $scanner->description(),

                    'result' => [

                        'status' => 'critical',

                        'score' => 0,

                        'issues' => [
                            $e->getMessage(),
                        ],

                        'recommendations' => [
                            'Review scanner implementation.',
                        ],

                        'details' => [],

                    ],

                ];

            }

        }

        return $results;
    }

    /**
     * Run Single Scanner
     */
    public function runScanner(string $class): ?array
    {
        foreach ($this->scanners as $scanner) {

            if ($scanner instanceof $class) {
                return $scanner->scan();
            }

        }

        return null;
    }

    /**
     * Clear Registered Scanners
     */
    public function clear(): void
    {
        $this->scanners = [];
    }

    /**
     * Reload Scanners
     */
    public function reload(): void
    {
        $this->clear();

        $this->registerDefaultScanners();
    }
}