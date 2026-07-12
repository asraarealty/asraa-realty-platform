<?php
/**
 * Plugin Name: Asraa Smart Valuation Suite
 * Description: Property valuation with smart address autocomplete, comparable engine, AI insights and self-learning building database.
 * Version: 4.1.0
 * Author: Asraa Realty
 * Text Domain: asraa-svs
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ASRAA_SVS_VERSION', '4.1.0' );
define( 'ASRAA_SVS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASRAA_SVS_URL', plugin_dir_url( __FILE__ ) );

require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-activator.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-building-database.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-adjustment-engine.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-ai-engine.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-comparable-engine.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-confidence-engine.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-output-formatter.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-address-autocomplete.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-valuation-engine.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-admin.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-public.php';
require_once ASRAA_SVS_DIR . 'includes/class-asraa-svs-ajax.php';

register_activation_hook( __FILE__, array( 'Asraa_SVS_Activator', 'activate' ) );

function asraa_svs_init() {
    Asraa_SVS_Activator::maybe_upgrade();
    if ( is_admin() ) {
        new Asraa_SVS_Admin();
    }
    new Asraa_SVS_Public();
    new Asraa_SVS_Ajax();
}
add_action( 'plugins_loaded', 'asraa_svs_init' );
