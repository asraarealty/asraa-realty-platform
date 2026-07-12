<?php
/**
 * Diagnostics Logger
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class Logger
{
    /**
     * Option Name
     */
    protected const OPTION = 'asraa_diagnostics_logs';

    /**
     * Maximum Logs
     */
    protected const MAX_LOGS = 500;

    /**
     * Write Log
     */
    public function write(
        string $scanner,
        string $status,
        string $message,
        array $context = []
    ): bool {

        $logs = $this->all();

        $logs[] = [

            'time'      => current_time('mysql'),

            'scanner'   => $scanner,

            'status'    => $status,

            'message'   => $message,

            'context'   => $context,

            'user'      => get_current_user_id(),

        ];

        if (count($logs) > self::MAX_LOGS) {

            $logs = array_slice(
                $logs,
                -self::MAX_LOGS
            );

        }

        return update_option(
            self::OPTION,
            $logs,
            false
        );
    }

    /**
     * Get All Logs
     */
    public function all(): array
    {
        return get_option(
            self::OPTION,
            []
        );
    }

    /**
     * Latest Logs
     */
    public function latest(int $limit = 20): array
    {
        return array_reverse(
            array_slice(
                $this->all(),
                -$limit
            )
        );
    }

    /**
     * Filter by Scanner
     */
    public function scanner(string $scanner): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static function ($log) use ($scanner) {

                    return $log['scanner'] === $scanner;

                }
            )
        );
    }

    /**
     * Filter by Status
     */
    public function status(string $status): array
    {
        return array_values(
            array_filter(
                $this->all(),
                static function ($log) use ($status) {

                    return $log['status'] === $status;

                }
            )
        );
    }

    /**
     * Clear Logs
     */
    public function clear(): bool
    {
        return delete_option(
            self::OPTION
        );
    }

    /**
     * Count Logs
     */
    public function count(): int
    {
        return count(
            $this->all()
        );
    }
}