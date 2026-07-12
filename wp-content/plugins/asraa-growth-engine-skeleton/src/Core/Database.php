<?php
/**
 * Database Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Database
{
    /**
     * Database Version
     */
    public const DB_VERSION = '1.0.0';

    /**
     * Option Name
     */
    private const VERSION_OPTION = 'asraa_ge_db_version';

    /**
     * WordPress Database
     *
     * @var \wpdb
     */
    protected \wpdb $wpdb;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
    }

    /**
     * Initialize Database
     */
    public function init(): void
    {
        $installed = get_option(self::VERSION_OPTION);

        if ($installed !== self::DB_VERSION) {

            $this->createTables();

            update_option(
                self::VERSION_OPTION,
                self::DB_VERSION
            );
        }
    }

    /**
     * Create All Plugin Tables
     */
    protected function createTables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($this->logsTableSQL());

        /*
         * Future tables
         *
         * dbDelta($this->reportsTableSQL());
         * dbDelta($this->scansTableSQL());
         * dbDelta($this->redirectsTableSQL());
         * dbDelta($this->automationTableSQL());
         */
    }

    /**
     * Logs Table SQL
     */
    protected function logsTableSQL(): string
    {
        $table = $this->table('logs');

        $charset = $this->wpdb->get_charset_collate();

        return "

        CREATE TABLE {$table} (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            level VARCHAR(20) NOT NULL,

            message LONGTEXT NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY level (level),

            KEY created_at (created_at)

        ) {$charset};

        ";
    }

    /**
     * Get Plugin Table Name
     */
    public function table(string $name): string
    {
        return $this->wpdb->prefix . 'asraa_ge_' . $name;
    }

    /**
     * Check Table Exists
     */
    public function exists(string $name): bool
    {
        $table = $this->table($name);

        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        ) === $table;
    }

    /**
     * Current Database Version
     */
    public function version(): string
    {
        return (string) get_option(
            self::VERSION_OPTION,
            '0'
        );
    }

    /**
     * Needs Upgrade
     */
    public function needsUpgrade(): bool
    {
        return version_compare(
            $this->version(),
            self::DB_VERSION,
            '<'
        );
    }

    /**
     * Upgrade Database
     */
    public function upgrade(): void
    {
        $this->createTables();

        update_option(
            self::VERSION_OPTION,
            self::DB_VERSION
        );
    }

    /**
     * Seed Default Options
     */
    public function seedDefaults(): void
    {
        add_option(
            'asraa_growth_engine_options',
            []
        );
    }
}