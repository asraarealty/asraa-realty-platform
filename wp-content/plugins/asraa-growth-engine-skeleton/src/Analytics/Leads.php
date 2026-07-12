<?php
/**
 * Leads Analytics
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class Leads
{
    /**
     * CRM Leads Table
     */
    protected string $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'asraa_crm_leads';
    }

    /**
     * Total Leads
     */
    public function total(): int
    {
        global $wpdb;

        if (!$this->exists()) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table}"
        );
    }

    /**
     * Today's Leads
     */
    public function today(): int
    {
        global $wpdb;

        if (!$this->exists()) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$this->table}
                 WHERE DATE(created_at)=%s",
                current_time('Y-m-d')
            )
        );
    }

    /**
     * This Month
     */
    public function thisMonth(): int
    {
        global $wpdb;

        if (!$this->exists()) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$this->table}
                 WHERE YEAR(created_at)=%d
                 AND MONTH(created_at)=%d",
                current_time('Y'),
                current_time('m')
            )
        );
    }

    /**
     * Latest Leads
     */
    public function latest(int $limit = 10): array
    {
        global $wpdb;

        if (!$this->exists()) {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table}
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Lead Status
     */
    public function byStatus(): array
    {
        global $wpdb;

        if (!$this->exists()) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT stage,
                    COUNT(*) AS total
             FROM {$this->table}
             GROUP BY stage",
            ARRAY_A
        );
    }

    /**
     * Lead Sources
     */
    public function bySource(): array
    {
        global $wpdb;

        if (!$this->exists()) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT source,
                    COUNT(*) AS total
             FROM {$this->table}
             GROUP BY source
             ORDER BY total DESC",
            ARRAY_A
        );
    }

    /**
     * Conversion Rate
     */
    public function conversionRate(): float
    {
        global $wpdb;

        if (!$this->exists()) {
            return 0;
        }

        $total = $this->total();

        if ($total === 0) {
            return 0;
        }

        $closed = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$this->table}
             WHERE stage='Closed'"
        );

        return round(($closed / $total) * 100, 2);
    }

    /**
     * Hot Leads
     */
    public function hotLeads(): array
    {
        global $wpdb;

        if (!$this->exists()) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT *
             FROM {$this->table}
             WHERE priority='High'
             ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Table Exists
     */
    protected function exists(): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table
            )
        ) === $this->table;
    }
}