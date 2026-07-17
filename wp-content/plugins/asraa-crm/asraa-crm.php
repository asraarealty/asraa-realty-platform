<?php
/**
 * Plugin Name: Asraa CRM
 * Description: SaaS-ready CRM for real estate – lead pipeline, property management, deals, campaigns, automation, and client portal management.
 * Version: 5.3.1
 * Author: Asraa Realty
 * Text Domain: asraa-crm
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
   CONSTANTS
============================================================ */
if ( ! defined( 'ASRAA_CRM_PATH' ) )    define( 'ASRAA_CRM_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'ASRAA_CRM_URL' ) )     define( 'ASRAA_CRM_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'ASRAA_CRM_FILE' ) )    define( 'ASRAA_CRM_FILE', __FILE__ );
if ( ! defined( 'ASRAA_CRM_VERSION' ) ) define( 'ASRAA_CRM_VERSION', '5.3.1' );
if ( ! defined( 'ASRAA_CRM_LOG_DIR' ) ) define( 'ASRAA_CRM_LOG_DIR', ASRAA_CRM_PATH . 'logs' );

/* ============================================================
   SAFE REQUIRE HELPER (prevents fatal on missing file / class)
============================================================ */
if ( ! function_exists( 'asraa_crm_safe_require' ) ) {
    function asraa_crm_safe_require( $relative_path ) {
        $full = ASRAA_CRM_PATH . ltrim( $relative_path, '/' );
        if ( file_exists( $full ) ) {
            try {
                require_once $full;
                return true;
            } catch ( \Throwable $e ) {
                if ( function_exists( 'asraa_crm_log' ) ) {
                    asraa_crm_log( 'fatal', 'Loader', 'Failed to require ' . $relative_path . ': ' . $e->getMessage(), $full, $e->getLine(), $e->getTraceAsString() );
                } else {
                    error_log( '[Asraa CRM] require failed for ' . $relative_path . ': ' . $e->getMessage() );
                }
                return false;
            }
        }
        return false;
    }
}

/* ============================================================
   LOGGER (loaded very early)
============================================================ */
require_once ASRAA_CRM_PATH . 'includes/core/class-logger.php';

/* ============================================================
   FATAL ERROR HANDLER (captures fatal errors into custom log)
============================================================ */
register_shutdown_function( function() {
    $err = error_get_last();
    if ( ! $err ) return;
    if ( in_array( $err['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
        if ( isset( $err['file'] ) && strpos( $err['file'], 'asraa-crm' ) !== false ) {
            if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                Asraa_CRM_Logger::log( 'fatal', 'PHP', $err['message'], $err['file'], (int) $err['line'] );
            }
        }
    }
});

set_error_handler( function( $severity, $message, $file, $line ) {
    if ( ! ( error_reporting() & $severity ) ) return false;
    if ( strpos( (string) $file, 'asraa-crm' ) === false ) return false;
    $map = array(
        E_WARNING => 'warning', E_USER_WARNING => 'warning',
        E_NOTICE  => 'notice',  E_USER_NOTICE  => 'notice',
        E_DEPRECATED => 'deprecated', E_USER_DEPRECATED => 'deprecated',
        E_STRICT => 'notice',
    );
    $sev = isset( $map[ $severity ] ) ? $map[ $severity ] : 'notice';
    if ( class_exists( 'Asraa_CRM_Logger' ) ) {
        Asraa_CRM_Logger::log( $sev, 'PHP', $message, $file, (int) $line );
    }
    return false; // let PHP handle normally
}, E_ALL );

/* ============================================================
   INSTALL / ACTIVATION
============================================================ */
register_activation_hook( __FILE__, 'asraa_crm_install' );

function asraa_crm_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Ensure logs dir exists
    if ( ! is_dir( ASRAA_CRM_LOG_DIR ) ) {
        @wp_mkdir_p( ASRAA_CRM_LOG_DIR );
        @file_put_contents( ASRAA_CRM_LOG_DIR . '/.htaccess', "Order Deny,Allow\nDeny from all\n" );
        @file_put_contents( ASRAA_CRM_LOG_DIR . '/index.html', '' );
    }

    // Load roles
    if ( file_exists( ASRAA_CRM_PATH . 'includes/core/class-roles.php' ) ) {
        require_once ASRAA_CRM_PATH . 'includes/core/class-roles.php';
        if ( class_exists( 'Asraa_CRM_Roles' ) && method_exists( 'Asraa_CRM_Roles', 'register' ) ) {
            Asraa_CRM_Roles::register();
        }
    }

    $charset_collate = $wpdb->get_charset_collate();

    // LEADS TABLE
    $leads_table = $wpdb->prefix . 'asraa_crm_leads';
    dbDelta( "
        CREATE TABLE {$leads_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'new',
            lead_stage VARCHAR(30) NOT NULL DEFAULT 'new',
            stage_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            assigned_to BIGINT UNSIGNED DEFAULT NULL,
            assigned_agent BIGINT UNSIGNED DEFAULT NULL,
            group_id BIGINT UNSIGNED DEFAULT NULL,
            intent VARCHAR(30) NOT NULL DEFAULT '',
            location VARCHAR(255) NOT NULL DEFAULT '',
            budget DECIMAL(15,2) DEFAULT NULL,
            budget_min DECIMAL(15,2) DEFAULT NULL,
            budget_max DECIMAL(15,2) DEFAULT NULL,
            property_type VARCHAR(100) NOT NULL DEFAULT '',
            preferred_locations TEXT DEFAULT NULL,
            source VARCHAR(100) NOT NULL DEFAULT '',
            lead_score INT NOT NULL DEFAULT 0,
            whatsapp_link TEXT DEFAULT NULL,
            last_activity DATETIME DEFAULT NULL,
            timeline VARCHAR(100) NOT NULL DEFAULT '',
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY phone (phone),
            KEY assigned_to (assigned_to),
            KEY assigned_agent (assigned_agent),
            KEY group_id (group_id),
            KEY lead_stage (lead_stage),
            KEY last_activity (last_activity),
            KEY status (status),
            KEY is_deleted (is_deleted)
        ) {$charset_collate};
    " );

    // FOLLOWUPS TABLE
    $followups_table = $wpdb->prefix . 'asraa_crm_followups';
    dbDelta( "
        CREATE TABLE {$followups_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            agent_id BIGINT UNSIGNED DEFAULT NULL,
            follow_date DATE NOT NULL,
            note TEXT DEFAULT NULL,
            is_done TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY agent_id (agent_id),
            KEY follow_date (follow_date)
        ) {$charset_collate};
    " );

    // NOTES TABLE
    $notes_table = $wpdb->prefix . 'asraa_crm_notes';
    dbDelta( "
        CREATE TABLE {$notes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id)
        ) {$charset_collate};
    " );

    // PROPERTIES TABLE
    $properties_table = $wpdb->prefix . 'asraa_crm_properties';
    dbDelta( "
        CREATE TABLE {$properties_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            transaction_type VARCHAR(20) NOT NULL DEFAULT 'sale',
            property_type VARCHAR(100) NOT NULL DEFAULT '',
            builder_name VARCHAR(255) NOT NULL DEFAULT '',
            city VARCHAR(100) NOT NULL DEFAULT '',
            area VARCHAR(150) NOT NULL DEFAULT '',
            location VARCHAR(255) NOT NULL DEFAULT '',
            price DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'available',
            image_url TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY city (city),
            KEY area (area),
            KEY location (location),
            KEY price (price),
            KEY status (status),
            KEY transaction_type (transaction_type),
            KEY property_type (property_type)
        ) {$charset_collate};
    " );

    // BROKER FEED TABLE
    asraa_crm_run_broker_feed_table_migration();

    // GROUPS TABLE
    $groups_table = $wpdb->prefix . 'asraa_crm_groups';
    dbDelta( "
        CREATE TABLE {$groups_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            color VARCHAR(7) DEFAULT '#6b7280',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY group_name (group_name)
        ) {$charset_collate};
    " );

    $default_groups = array(
        array( 'group_name' => 'Client',    'description' => 'Property buyers and clients',       'color' => '#2563eb' ),
        array( 'group_name' => 'Agent',     'description' => 'Real estate agents and brokers',    'color' => '#16a34a' ),
        array( 'group_name' => 'Developer', 'description' => 'Property developers and builders',  'color' => '#9333ea' ),
    );
    foreach ( $default_groups as $g ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$groups_table} WHERE group_name = %s", $g['group_name'] ) );
        if ( ! $exists ) {
            $wpdb->insert( $groups_table, array_merge( $g, array(
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ) ) );
        }
    }

    // STAGES
    $stages_table = $wpdb->prefix . 'asraa_crm_stages';
    dbDelta( "
        CREATE TABLE {$stages_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_final TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) {$charset_collate};
    " );

    // WHATSAPP TEMPLATES
    $wa_table = $wpdb->prefix . 'asraa_crm_whatsapp_templates';
    dbDelta( "
        CREATE TABLE {$wa_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};
    " );

    // EMAIL TEMPLATES
    $email_table = $wpdb->prefix . 'asraa_crm_email_templates';
    dbDelta( "
        CREATE TABLE {$email_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            category VARCHAR(50) DEFAULT '',
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};
    " );

    // MESSAGE LOG
    $log_table = $wpdb->prefix . 'asraa_crm_message_log';
    dbDelta( "
        CREATE TABLE {$log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            campaign_id BIGINT UNSIGNED DEFAULT NULL,
            message_type VARCHAR(20) NOT NULL,
            template_id BIGINT UNSIGNED DEFAULT NULL,
            recipient VARCHAR(255) DEFAULT '',
            subject VARCHAR(255) DEFAULT '',
            content TEXT NOT NULL,
            sent_at DATETIME NOT NULL,
            sent_by BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY sent_at (sent_at)
        ) {$charset_collate};
    " );

    // BULK CAMPAIGNS
    $campaigns_table = $wpdb->prefix . 'asraa_crm_bulk_campaigns';
    dbDelta( "
        CREATE TABLE {$campaigns_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_name VARCHAR(255) NOT NULL,
            message_type VARCHAR(20) NOT NULL,
            template_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            leads_count INT NOT NULL DEFAULT 0,
            sent_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};
    " );

    asraa_crm_run_leads_table_migrations();

    // AGENT HIERARCHY
    $hierarchy_table = $wpdb->prefix . 'asraa_crm_agent_hierarchy';
    dbDelta( "
        CREATE TABLE {$hierarchy_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            manager_id BIGINT UNSIGNED DEFAULT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'agent',
            level INT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY manager_id (manager_id)
        ) {$charset_collate};
    " );

    // DEALS
    $deals_table = $wpdb->prefix . 'asraa_crm_deals';
    dbDelta( "
        CREATE TABLE {$deals_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED DEFAULT NULL,
            property_id BIGINT UNSIGNED DEFAULT NULL,
            agent_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            deal_value DECIMAL(15,2) NOT NULL DEFAULT 0,
            stage VARCHAR(30) NOT NULL DEFAULT 'prospect',
            expected_close_date DATE DEFAULT NULL,
            commission_plan_id BIGINT UNSIGNED DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY agent_id (agent_id),
            KEY stage (stage)
        ) {$charset_collate};
    " );

    // DEAL ACTIVITIES
    $deal_activities_table = $wpdb->prefix . 'asraa_crm_deal_activities';
    dbDelta( "
        CREATE TABLE {$deal_activities_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deal_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY deal_id (deal_id)
        ) {$charset_collate};
    " );

    // COMMISSION PLANS
    $commission_plans_table = $wpdb->prefix . 'asraa_crm_commission_plans';
    dbDelta( "
        CREATE TABLE {$commission_plans_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_name VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'percentage',
            rate DECIMAL(10,4) NOT NULL DEFAULT 2.0000,
            description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};
    " );

    // COMMISSIONS
    $commissions_table = $wpdb->prefix . 'asraa_crm_commissions';
    dbDelta( "
        CREATE TABLE {$commissions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deal_id BIGINT UNSIGNED NOT NULL,
            agent_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED DEFAULT NULL,
            commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            commission_rate DECIMAL(10,4) NOT NULL DEFAULT 0,
            deal_value DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            paid_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY deal_id (deal_id),
            KEY agent_id (agent_id),
            KEY status (status)
        ) {$charset_collate};
    " );

    // AUTOMATION RULES
    $automation_table = $wpdb->prefix . 'asraa_crm_automation_rules';
    dbDelta( "
        CREATE TABLE {$automation_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_name VARCHAR(255) NOT NULL,
            trigger_event VARCHAR(50) NOT NULL,
            conditions LONGTEXT DEFAULT NULL,
            actions LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY trigger_event (trigger_event)
        ) {$charset_collate};
    " );

    // AUTOMATION LOGS
    $automation_logs_table = $wpdb->prefix . 'asraa_crm_automation_logs';
    dbDelta( "
        CREATE TABLE {$automation_logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_id BIGINT UNSIGNED NOT NULL,
            trigger_event VARCHAR(50) NOT NULL,
            context LONGTEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            notes TEXT DEFAULT NULL,
            executed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY rule_id (rule_id),
            KEY trigger_event (trigger_event),
            KEY executed_at (executed_at)
        ) {$charset_collate};
    " );

    // ACTIVITIES
    $activities_table = $wpdb->prefix . 'asraa_crm_activities';
    dbDelta( "
        CREATE TABLE {$activities_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'note',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            body TEXT DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY type (type),
            KEY created_at (created_at)
        ) {$charset_collate};
    " );

    update_option( 'asraa_crm_db_version', ASRAA_CRM_VERSION, false );
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'asraa_crm_deactivate' );
function asraa_crm_deactivate() {
    flush_rewrite_rules();
}

/* ============================================================
   MIGRATIONS
============================================================ */
function asraa_crm_run_leads_table_migrations() {
    global $wpdb;
    $leads_table = $wpdb->prefix . 'asraa_crm_leads';
    // If leads table doesn't yet exist, skip.
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $leads_table ) ) !== $leads_table ) {
        return;
    }
    $columns = array(
        'stage_id'       => "ALTER TABLE {$leads_table} ADD stage_id BIGINT UNSIGNED NOT NULL DEFAULT 1",
        'group_id'       => "ALTER TABLE {$leads_table} ADD group_id BIGINT UNSIGNED DEFAULT NULL",
        'intent'         => "ALTER TABLE {$leads_table} ADD intent VARCHAR(30) NOT NULL DEFAULT ''",
        'location'       => "ALTER TABLE {$leads_table} ADD location VARCHAR(255) NOT NULL DEFAULT ''",
        'budget'         => "ALTER TABLE {$leads_table} ADD budget DECIMAL(15,2) DEFAULT NULL",
        'lead_score'     => "ALTER TABLE {$leads_table} ADD lead_score INT NOT NULL DEFAULT 0",
        'lead_stage'     => "ALTER TABLE {$leads_table} ADD lead_stage VARCHAR(30) NOT NULL DEFAULT 'new'",
        'assigned_agent' => "ALTER TABLE {$leads_table} ADD assigned_agent BIGINT UNSIGNED DEFAULT NULL",
        'last_activity'  => "ALTER TABLE {$leads_table} ADD last_activity DATETIME DEFAULT NULL",
        'source'         => "ALTER TABLE {$leads_table} ADD source VARCHAR(100) NOT NULL DEFAULT ''",
        'whatsapp_link'  => "ALTER TABLE {$leads_table} ADD whatsapp_link TEXT DEFAULT NULL",
    );
    foreach ( $columns as $column => $sql ) {
        $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$leads_table} LIKE %s", $column ) );
        if ( empty( $exists ) ) {
            $wpdb->query( $sql );
        }
    }
}

function asraa_crm_run_properties_table_migrations() {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'asraa_crm_properties';
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $properties_table ) ) !== $properties_table ) {
        return;
    }
    $columns = array(
        'area'            => "ALTER TABLE {$properties_table} ADD area VARCHAR(150) NOT NULL DEFAULT ''",
        'location'        => "ALTER TABLE {$properties_table} ADD location VARCHAR(255) NOT NULL DEFAULT ''",
        'source_post_id'  => "ALTER TABLE {$properties_table} ADD source_post_id BIGINT UNSIGNED DEFAULT NULL, ADD KEY source_post_id (source_post_id)",
    );
    foreach ( $columns as $column => $sql ) {
        $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$properties_table} LIKE %s", $column ) );
        if ( empty( $exists ) ) {
            $wpdb->query( $sql );
        }
    }
}

function asraa_crm_run_broker_feed_table_migration() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $broker_feed_table = $wpdb->prefix . 'asraa_broker_feed';
    $charset_collate = $wpdb->get_charset_collate();
    dbDelta( "
        CREATE TABLE {$broker_feed_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            project_name VARCHAR(255) DEFAULT '',
            property_type VARCHAR(100) DEFAULT '',
            transaction_type VARCHAR(50) DEFAULT '',
            configuration VARCHAR(100) DEFAULT '',
            location VARCHAR(255) DEFAULT '',
            city VARCHAR(100) DEFAULT '',
            locality VARCHAR(150) DEFAULT '',
            area VARCHAR(100) DEFAULT '',
            carpet_area VARCHAR(100) DEFAULT '',
            available_units INT DEFAULT 1,
            price DECIMAL(15,2) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'available',
            image_url TEXT DEFAULT NULL,
            source_agent_id BIGINT UNSIGNED DEFAULT 0,
            source_agent_name VARCHAR(255) DEFAULT '',
            source_agent_phone VARCHAR(30) DEFAULT '',
            source_group VARCHAR(255) DEFAULT '',
            raw_message LONGTEXT DEFAULT NULL,
            approval_status VARCHAR(20) DEFAULT 'pending',
            is_public TINYINT(1) DEFAULT 0,
            slug VARCHAR(255) DEFAULT '',
            meta_title VARCHAR(255) DEFAULT '',
            meta_description TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY approval_status (approval_status),
            KEY is_public (is_public),
            KEY property_type (property_type),
            KEY transaction_type (transaction_type),
            KEY slug (slug)
        ) {$charset_collate};
    " );

    $columns = array(
        'slug' => "ALTER TABLE {$broker_feed_table} ADD COLUMN slug VARCHAR(255) NOT NULL DEFAULT ''",
        'meta_title' => "ALTER TABLE {$broker_feed_table} ADD COLUMN meta_title VARCHAR(255) NOT NULL DEFAULT ''",
        'meta_description' => "ALTER TABLE {$broker_feed_table} ADD COLUMN meta_description TEXT NULL DEFAULT NULL",
        'notes' => "ALTER TABLE {$broker_feed_table} ADD COLUMN notes TEXT NULL DEFAULT NULL",
        'updated_at' => "ALTER TABLE {$broker_feed_table} ADD COLUMN updated_at DATETIME NULL DEFAULT NULL",
        'source_agent_phone' => "ALTER TABLE {$broker_feed_table} ADD COLUMN source_agent_phone VARCHAR(30) NOT NULL DEFAULT ''",
    );
    foreach ( $columns as $column => $sql ) {
        $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$broker_feed_table} LIKE %s", $column ) );
        if ( empty( $exists ) ) {
            $wpdb->query( $sql );
        }
    }
}

function asraa_crm_maybe_upgrade_database() {
    $current = (string) get_option( 'asraa_crm_db_version', '0.0.0' );
    if ( version_compare( $current, ASRAA_CRM_VERSION, '<' ) ) {
        asraa_crm_run_leads_table_migrations();
        asraa_crm_run_properties_table_migrations();
        asraa_crm_run_broker_feed_table_migration();
        update_option( 'asraa_crm_db_version', ASRAA_CRM_VERSION, false );
    }
}
add_action( 'plugins_loaded', 'asraa_crm_maybe_upgrade_database' );

/* ============================================================
   HELPERS
============================================================ */
asraa_crm_safe_require( 'includes/helpers.php' );

/* ============================================================
   CORE
============================================================ */
asraa_crm_safe_require( 'includes/core/class-roles.php' );
asraa_crm_safe_require( 'includes/core/class-subscriptions.php' );
asraa_crm_safe_require( 'includes/core/class-seo-guard.php' );
if ( class_exists( 'Asraa_CRM_Seo_Guard' ) ) {
    global $asraa_crm_seo_guard;
    if ( ! isset( $asraa_crm_seo_guard ) || ! $asraa_crm_seo_guard instanceof Asraa_CRM_Seo_Guard ) {
        $asraa_crm_seo_guard = new Asraa_CRM_Seo_Guard();
    }
}

/* ============================================================
   AUTO-LOAD REPOSITORIES (all files)
============================================================ */
$__asraa_repo_dir = ASRAA_CRM_PATH . 'includes/repositories';
if ( is_dir( $__asraa_repo_dir ) ) {
    foreach ( (array) glob( $__asraa_repo_dir . '/*.php' ) as $__f ) {
        if ( is_file( $__f ) ) {
            try { require_once $__f; } catch ( \Throwable $e ) {
                if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                    Asraa_CRM_Logger::log( 'error', 'Loader', 'Repository load failed: ' . $e->getMessage(), $__f, $e->getLine() );
                }
            }
        }
    }
}

/* ============================================================
   AUTO-LOAD SERVICES (all files)
============================================================ */
$__asraa_svc_dir = ASRAA_CRM_PATH . 'includes/services';
if ( is_dir( $__asraa_svc_dir ) ) {
    foreach ( (array) glob( $__asraa_svc_dir . '/*.php' ) as $__f ) {
        if ( is_file( $__f ) ) {
            try { require_once $__f; } catch ( \Throwable $e ) {
                if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                    Asraa_CRM_Logger::log( 'error', 'Loader', 'Service load failed: ' . $e->getMessage(), $__f, $e->getLine() );
                }
            }
        }
    }
}

/* ============================================================
   POST TYPES
============================================================ */
asraa_crm_safe_require( 'includes/class-agent-post-type.php' );
if ( class_exists( 'Asraa_Agent_Post_Type' ) ) {
    global $asraa_agent_post_type;
    if ( ! isset( $asraa_agent_post_type ) || ! $asraa_agent_post_type instanceof Asraa_Agent_Post_Type ) {
        $asraa_agent_post_type = new Asraa_Agent_Post_Type();
    }
}

/* ============================================================
   AUTO-LOAD CONTROLLERS (all files)
============================================================ */
$__asraa_ctrl_dir = ASRAA_CRM_PATH . 'includes/controllers';
if ( is_dir( $__asraa_ctrl_dir ) ) {
    foreach ( (array) glob( $__asraa_ctrl_dir . '/*.php' ) as $__f ) {
        if ( is_file( $__f ) ) {
            try { require_once $__f; } catch ( \Throwable $e ) {
                if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                    Asraa_CRM_Logger::log( 'error', 'Loader', 'Controller load failed: ' . $e->getMessage(), $__f, $e->getLine() );
                }
            }
        }
    }
}

/* ============================================================
   CONTROLLER INSTANTIATION (idempotent, safe)
============================================================ */
function asraa_crm_bootstrap_controllers() {
    $controllers = array(
        'Asraa_Agent_Quick_Post_Controller'      => 'asraa_agent_quick_post_controller',
        'Asraa_Broker_Feed_Controller'           => 'asraa_broker_feed_controller',
        'Asraa_Lead_Controller'                  => 'asraa_lead_controller',
        'Asraa_Property_Controller'              => 'asraa_property_controller',
        'Asraa_Deal_Controller'                  => 'asraa_deal_controller',
        'Asraa_Commission_Controller'            => 'asraa_commission_controller',
        'Asraa_Automation_Controller'            => 'asraa_automation_controller',
        'Asraa_CRM_Project_Controller'           => 'asraa_crm_project_controller',
        'Asraa_CRM_Site_Visit_Controller'        => 'asraa_crm_site_visit_controller',
        'Asraa_CRM_Inventory_Controller'         => 'asraa_crm_inventory_controller',
        'Asraa_CRM_Client_Portal_Controller'     => 'asraa_crm_client_portal_controller',
        // Bug fix (audit 2026-07-15): this service registers its own hooks in __construct()
        // (asraa_crm_site_visit_completed / booking_confirmed / booking_cancelled) but was
        // never instantiated anywhere in the plugin, so those automations silently never fired.
        'Asraa_CRM_Inventory_Automation_Service' => 'asraa_crm_inventory_automation_service',
    );
    foreach ( $controllers as $class => $global_var ) {
        if ( class_exists( $class ) ) {
            try {
                global $$global_var;
                if ( ! isset( $$global_var ) || ! $$global_var instanceof $class ) {
                    $$global_var = new $class();
                }
            } catch ( \Throwable $e ) {
                if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                    Asraa_CRM_Logger::log( 'error', 'Bootstrap', 'Instantiation failed: ' . $class . ' - ' . $e->getMessage(), __FILE__, __LINE__ );
                }
            }
        }
    }

    // REST API
    if ( class_exists( 'Asraa_CRM_REST_API' ) ) {
        try {
            global $asraa_crm_rest_api;
            if ( ! isset( $asraa_crm_rest_api ) || ! $asraa_crm_rest_api instanceof Asraa_CRM_REST_API ) {
                $asraa_crm_rest_api = new Asraa_CRM_REST_API();
            }
        } catch ( \Throwable $e ) {
            if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                Asraa_CRM_Logger::log( 'error', 'Bootstrap', 'REST API init failed: ' . $e->getMessage(), __FILE__, __LINE__ );
            }
        }
    }

    // Public dashboard
    if ( class_exists( 'Asraa_Frontend_Dashboard' ) ) {
        try {
            global $asraa_frontend_dashboard;
            if ( ! isset( $asraa_frontend_dashboard ) || ! $asraa_frontend_dashboard instanceof Asraa_Frontend_Dashboard ) {
                $asraa_frontend_dashboard = new Asraa_Frontend_Dashboard();
            }
        } catch ( \Throwable $e ) {
            if ( class_exists( 'Asraa_CRM_Logger' ) ) {
                Asraa_CRM_Logger::log( 'error', 'Bootstrap', 'Frontend init failed: ' . $e->getMessage(), __FILE__, __LINE__ );
            }
        }
    }
}
add_action( 'plugins_loaded', 'asraa_crm_bootstrap_controllers', 20 );

/* ============================================================
   PUBLIC
============================================================ */
asraa_crm_safe_require( 'public/class-frontend-dashboard.php' );
asraa_crm_safe_require( 'public/class-broker-feed-public.php' );
asraa_crm_safe_require( 'public/class-broker-form-shortcode.php' );
asraa_crm_safe_require( 'public/class-broker-feed-shortcode.php' );
if ( class_exists( 'Asraa_Broker_Feed_Public' ) ) {
    global $asraa_broker_feed_public;
    if ( ! isset( $asraa_broker_feed_public ) || ! $asraa_broker_feed_public instanceof Asraa_Broker_Feed_Public ) {
        $asraa_broker_feed_public = new Asraa_Broker_Feed_Public();
    }
}
if ( class_exists( 'Asraa_Broker_Form_Shortcode' ) ) {
    global $asraa_broker_form_shortcode;
    if ( ! isset( $asraa_broker_form_shortcode ) || ! $asraa_broker_form_shortcode instanceof Asraa_Broker_Form_Shortcode ) {
        $asraa_broker_form_shortcode = new Asraa_Broker_Form_Shortcode();
    }
}
if ( class_exists( 'Asraa_Broker_Feed_Shortcode' ) ) {
    global $asraa_broker_feed_shortcode;
    if ( ! isset( $asraa_broker_feed_shortcode ) || ! $asraa_broker_feed_shortcode instanceof Asraa_Broker_Feed_Shortcode ) {
        $asraa_broker_feed_shortcode = new Asraa_Broker_Feed_Shortcode();
    }
}

/* ============================================================
   ADMIN
============================================================ */
if ( is_admin() ) {
    asraa_crm_safe_require( 'admin/class-campaign-dashboard.php' );
    asraa_crm_safe_require( 'includes/admin/class-admin-menu.php' );
    if ( class_exists( 'Asraa_CRM_Admin_Menu' ) ) {
        Asraa_CRM_Admin_Menu::init();
    }
}

