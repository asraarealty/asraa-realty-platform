<?php
/**
 * Database Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Database Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks WordPress database health and plugin tables.';
    }

    /**
     * Scan Database
     */
    public function scan(): array
    {
        global $wpdb;

        $issues = [];
        $details = [];

        /* WordPress Database Version */

        $details['database_version'] = $wpdb->db_version();

        /* Database Prefix */

        $details['table_prefix'] = $wpdb->prefix;

        /* Total Tables */

        $tables = $wpdb->get_col('SHOW TABLES');

        $details['total_tables'] = count($tables);

        /* Required CRM Tables */

        $required = [

            $wpdb->prefix . 'asraa_crm_leads',
            $wpdb->prefix . 'asraa_crm_followups',
            $wpdb->prefix . 'asraa_crm_properties',

        ];

        foreach ($required as $table) {

            if (!in_array($table, $tables, true)) {

                $issues[] = sprintf(
                    'Missing database table: %s',
                    $table
                );

            }

        }

        /* Database Size */

        $size = $wpdb->get_results(
            "
            SELECT
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024),2) AS size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY size_mb DESC
            ",
            ARRAY_A
        );

        $details['largest_tables'] = array_slice($size, 0, 10);

        /* Autoloaded Options */

        $autoload = (int) $wpdb->get_var(
            "
            SELECT COUNT(*)
            FROM {$wpdb->options}
            WHERE autoload='yes'
            "
        );

        $details['autoload_options'] = $autoload;

        if ($autoload > 1000) {

            $issues[] = 'Large number of autoloaded options detected.';

        }

        /* Posts */

        $details['posts'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}"
        );

        /* Users */

        $details['users'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}"
        );

        /* Comments */

        $details['comments'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments}"
        );

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Verify plugin installation.',
                'Review missing database tables.',
                'Reduce unnecessary autoloaded options.',
            ],
            88
        );
    }
}