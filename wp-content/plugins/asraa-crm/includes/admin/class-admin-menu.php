<?php
/**
 * Asraa CRM Admin Menu
 * Central registration of all admin pages so every controller page works.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Asraa_CRM_Admin_Menu' ) ) :

class Asraa_CRM_Admin_Menu {

    const SLUG = 'asraa-crm';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enqueue( $hook ) {
        if ( strpos( (string) $hook, 'asraa' ) === false ) return;
        $css = ASRAA_CRM_URL . 'assets/css/admin.css';
        if ( file_exists( ASRAA_CRM_PATH . 'assets/css/admin.css' ) ) {
            wp_enqueue_style( 'asraa-crm-admin', $css, array(), ASRAA_CRM_VERSION );
        }
        $js = ASRAA_CRM_URL . 'assets/js/admin.js';
        if ( file_exists( ASRAA_CRM_PATH . 'assets/js/admin.js' ) ) {
            wp_enqueue_script( 'asraa-crm-admin', $js, array( 'jquery' ), ASRAA_CRM_VERSION, true );
        }
    }

    public static function register() {
        $cap = 'manage_options';

        add_menu_page(
            __( 'Asraa CRM', 'asraa-crm' ),
            __( 'Asraa CRM', 'asraa-crm' ),
            $cap,
            self::SLUG,
            array( __CLASS__, 'render_page' ),
            'dashicons-building',
            26
        );

        $pages = self::pages();
        foreach ( $pages as $slug => $meta ) {
            add_submenu_page(
                self::SLUG,
                $meta['title'],
                $meta['title'],
                $cap,
                $slug,
                array( __CLASS__, 'render_page' )
            );
        }

        // Rename first submenu to Dashboard
        global $submenu;
        if ( isset( $submenu[ self::SLUG ][0][0] ) ) {
            $submenu[ self::SLUG ][0][0] = __( 'Dashboard', 'asraa-crm' );
        }
    }

    /**
     * Map: submenu slug => ['title' => ..., 'file' => admin/pages/<file>.php]
     */
    public static function pages() {
        return array(
            self::SLUG                        => array( 'title' => __( 'Dashboard', 'asraa-crm' ),         'file' => 'dashboard.php' ),
            'asraa-crm-leads'                 => array( 'title' => __( 'Leads', 'asraa-crm' ),             'file' => 'leads.php' ),
            'asraa-crm-lead-add'              => array( 'title' => __( 'Add Lead', 'asraa-crm' ),          'file' => 'leads-add.php' ),
            'asraa-crm-lead-view'             => array( 'title' => __( 'Lead View', 'asraa-crm' ),         'file' => 'lead-view.php' ),
            'asraa-crm-leads-import'          => array( 'title' => __( 'Import Leads', 'asraa-crm' ),      'file' => 'leads-import.php' ),
            'asraa-crm-followups'             => array( 'title' => __( 'Follow-ups', 'asraa-crm' ),        'file' => 'followups.php' ),
            'asraa-crm-notes'                 => array( 'title' => __( 'Notes', 'asraa-crm' ),             'file' => 'notes.php' ),
            'asraa-crm-groups'                => array( 'title' => __( 'Groups', 'asraa-crm' ),            'file' => 'groups.php' ),
            'asraa-crm-groups-add'            => array( 'title' => __( 'Add Group', 'asraa-crm' ),         'file' => 'groups-add.php' ),
            'asraa-crm-groups-edit'           => array( 'title' => __( 'Edit Group', 'asraa-crm' ),        'file' => 'groups-edit.php' ),
            'asraa-crm-properties'            => array( 'title' => __( 'Properties', 'asraa-crm' ),        'file' => 'properties.php' ),
            'asraa-crm-projects'              => array( 'title' => __( 'Projects', 'asraa-crm' ),          'file' => 'projects-v2.php' ),
            'asraa-crm-towers'                => array( 'title' => __( 'Towers', 'asraa-crm' ),            'file' => 'towers.php' ),
            'asraa-crm-inventory'             => array( 'title' => __( 'Inventory', 'asraa-crm' ),         'file' => 'inventory.php' ),
            'asraa-crm-inventory-reports'     => array( 'title' => __( 'Inventory Reports', 'asraa-crm' ), 'file' => 'inventory-reports.php' ),
            'asraa-crm-deals'                 => array( 'title' => __( 'Deals', 'asraa-crm' ),             'file' => 'deals.php' ),
            'asraa-crm-commissions'           => array( 'title' => __( 'Commissions', 'asraa-crm' ),       'file' => 'commissions.php' ),
            'asraa-crm-site-visits'           => array( 'title' => __( 'Site Visits', 'asraa-crm' ),       'file' => 'site-visits.php' ),
            'asraa-crm-broker-feed'           => array( 'title' => __( 'Broker Feed', 'asraa-crm' ),       'file' => 'broker-feed.php' ),
            'asraa-crm-agent-hierarchy'       => array( 'title' => __( 'Agent Hierarchy', 'asraa-crm' ),   'file' => 'agent-hierarchy.php' ),
            'asraa-crm-agent-quick-post'      => array( 'title' => __( 'Quick Post', 'asraa-crm' ),        'file' => 'agent-quick-post.php' ),
            'asraa-crm-email-templates'       => array( 'title' => __( 'Email Templates', 'asraa-crm' ),   'file' => 'email-templates.php' ),
            'asraa-crm-whatsapp-templates'    => array( 'title' => __( 'WhatsApp Templates', 'asraa-crm' ),'file' => 'whatsapp-templates.php' ),
            'asraa-crm-automation'            => array( 'title' => __( 'Automation', 'asraa-crm' ),        'file' => 'automation.php' ),
            'asraa-crm-ai-settings'           => array( 'title' => __( 'AI Settings', 'asraa-crm' ),       'file' => 'ai-settings.php' ),
        );
    }

    public static function render_page() {
        $slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG;
        $pages = self::pages();
        if ( ! isset( $pages[ $slug ] ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Asraa CRM', 'asraa-crm' ) . '</h1><p>' . esc_html__( 'Page not found.', 'asraa-crm' ) . '</p></div>';
            return;
        }
        $file = ASRAA_CRM_PATH . 'admin/pages/' . $pages[ $slug ]['file'];
        echo '<div class="wrap asraa-crm-wrap">';
        if ( file_exists( $file ) ) {
            try {
                include $file;
            } catch ( \Throwable $e ) {
                Asraa_CRM_Logger::log( 'error', 'AdminPage', $e->getMessage(), $file, $e->getLine(), $e->getTraceAsString() );
                echo '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
            }
        } else {
            Asraa_CRM_Logger::log( 'warning', 'AdminPage', 'Missing admin page file', $file, 0 );
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Admin page file is missing.', 'asraa-crm' ) . ' ' . esc_html( $pages[ $slug ]['file'] ) . '</p></div>';
        }
        echo '</div>';
    }
}

endif;
