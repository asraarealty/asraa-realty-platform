<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Activator {

    public static function activate() {
        self::maybe_upgrade();
    }

    public static function maybe_upgrade() {
        $current = get_option( 'asraa_svs_db_version', '0' );
        if ( version_compare( $current, '4.0.0', '>=' ) ) {
            return;
        }

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $rates_table = $wpdb->prefix . 'asraa_svs_rates';
        $leads_table = $wpdb->prefix . 'asraa_svs_leads';

        dbDelta( "CREATE TABLE {$rates_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            city varchar(190) NOT NULL,
            area varchar(190) NOT NULL DEFAULT '',
            building varchar(190) NOT NULL DEFAULT '',
            config varchar(50) NOT NULL DEFAULT '',
            base_rate decimal(12,2) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY city_area_building_config (city, area, building, config)
        ) {$charset_collate};" );

        dbDelta( "CREATE TABLE {$leads_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            city varchar(190) NOT NULL DEFAULT '',
            area varchar(190) NOT NULL DEFAULT '',
            building varchar(190) NOT NULL DEFAULT '',
            config varchar(50) NOT NULL DEFAULT '',
            sqft int(11) NOT NULL DEFAULT 0,
            rate_used decimal(12,2) NOT NULL DEFAULT 0,
            total_price decimal(14,2) NOT NULL DEFAULT 0,
            raw_request longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_phone (email, phone)
        ) {$charset_collate};" );

        $building_table = $wpdb->prefix . 'asraa_svs_building_data';
        $vr_table       = $wpdb->prefix . 'asraa_svs_valuation_records';
        $comp_table     = $wpdb->prefix . 'asraa_svs_comparable_inputs';

        dbDelta( "CREATE TABLE {$building_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            building_name varchar(255) NOT NULL DEFAULT '',
            area varchar(190) NOT NULL DEFAULT '',
            locality varchar(190) NOT NULL DEFAULT '',
            city varchar(190) NOT NULL DEFAULT '',
            state varchar(190) NOT NULL DEFAULT '',
            country varchar(190) NOT NULL DEFAULT '',
            pincode varchar(20) NOT NULL DEFAULT '',
            latitude decimal(10,7) NOT NULL DEFAULT 0,
            longitude decimal(10,7) NOT NULL DEFAULT 0,
            avg_rate decimal(12,2) NOT NULL DEFAULT 0,
            property_type varchar(50) NOT NULL DEFAULT 'residential',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY building_city (building_name(100), city(100))
        ) {$charset_collate};" );

        dbDelta( "CREATE TABLE {$vr_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            building varchar(255) NOT NULL DEFAULT '',
            area varchar(190) NOT NULL DEFAULT '',
            locality varchar(190) NOT NULL DEFAULT '',
            city varchar(190) NOT NULL DEFAULT '',
            state varchar(190) NOT NULL DEFAULT '',
            country varchar(190) NOT NULL DEFAULT '',
            property_type varchar(50) NOT NULL DEFAULT 'residential',
            area_size decimal(10,2) NOT NULL DEFAULT 0,
            floor_num smallint(5) NULL DEFAULT NULL,
            age_years smallint(5) NULL DEFAULT NULL,
            price decimal(16,2) NOT NULL DEFAULT 0,
            rate decimal(12,2) NOT NULL DEFAULT 0,
            valuation_source varchar(100) NOT NULL DEFAULT '',
            comparable_data longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY building_city (building(100), city(100))
        ) {$charset_collate};" );

        dbDelta( "CREATE TABLE {$comp_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            valuation_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
            comp_1_price decimal(14,2) NOT NULL DEFAULT 0,
            comp_1_area decimal(10,2) NOT NULL DEFAULT 0,
            comp_2_price decimal(14,2) NOT NULL DEFAULT 0,
            comp_2_area decimal(10,2) NOT NULL DEFAULT 0,
            comp_3_price decimal(14,2) NOT NULL DEFAULT 0,
            comp_3_area decimal(10,2) NOT NULL DEFAULT 0,
            comparable_rate decimal(12,2) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY valuation_record_id (valuation_record_id)
        ) {$charset_collate};" );

        $defaults = array(
            'asraa_svs_default_currency'     => 'INR',
            'asraa_svs_global_fallback_rate' => 8000,
            'asraa_svs_google_places_key'    => '',
            'asraa_svs_unit_system'          => 'sqft',
            'asraa_svs_debug_mode'           => 'no',
        );
        foreach ( $defaults as $key => $val ) {
            if ( '' === get_option( $key, '' ) ) {
                update_option( $key, $val );
            }
        }

        update_option( 'asraa_svs_db_version', '4.0.0' );
    }
}
