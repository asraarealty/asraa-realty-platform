<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function menu() {
        add_menu_page(
            __( 'Asraa Smart Valuation', 'asraa-svs' ),
            __( 'Asraa Valuation', 'asraa-svs' ),
            'manage_options',
            'asraa-svs',
            array( $this, 'settings_page' ),
            'dashicons-chart-line',
            26
        );
        add_submenu_page( 'asraa-svs', __( 'Rate Manager', 'asraa-svs' ),      __( 'Rate Manager', 'asraa-svs' ),  'manage_options', 'asraa-svs-rates',  array( $this, 'rates_page' ) );
        add_submenu_page( 'asraa-svs', __( 'Import Rates (CSV)', 'asraa-svs' ), __( 'Import Rates', 'asraa-svs' ), 'manage_options', 'asraa-svs-import', array( $this, 'import_page' ) );
        add_submenu_page( 'asraa-svs', __( 'Leads', 'asraa-svs' ),             __( 'Leads', 'asraa-svs' ),        'manage_options', 'asraa-svs-leads',  array( $this, 'leads_page' ) );
    }

    public function register_settings() {
        $settings = array(
            'asraa_svs_default_currency',
            'asraa_svs_global_fallback_rate',
            'asraa_svs_google_maps_api_key',
            'asraa_svs_google_places_key',
            'asraa_svs_admin_whatsapp',
            'asraa_svs_openai_key',
            'asraa_svs_unit_system',
            'asraa_svs_debug_mode',
        );
        foreach ( $settings as $s ) {
            register_setting( 'asraa_svs_settings', $s );
        }
    }

    public function settings_page() { include ASRAA_SVS_DIR . 'includes/partials/settings-page.php'; }
    public function rates_page()    { include ASRAA_SVS_DIR . 'includes/partials/rates-page.php'; }
    public function import_page()   { include ASRAA_SVS_DIR . 'includes/partials/import-csv.php'; }
    public function leads_page()    { include ASRAA_SVS_DIR . 'includes/partials/leads-page.php'; }
}
