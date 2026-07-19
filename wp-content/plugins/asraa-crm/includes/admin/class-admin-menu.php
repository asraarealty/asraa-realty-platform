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
        add_action( 'admin_head', array( __CLASS__, 'hide_nav_css' ) );
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

        $enhanced_css = ASRAA_CRM_URL . 'assets/css/crm-enhanced.css';
        if ( file_exists( ASRAA_CRM_PATH . 'assets/css/crm-enhanced.css' ) ) {
            wp_enqueue_style( 'asraa-crm-enhanced', $enhanced_css, array( 'asraa-crm-admin' ), ASRAA_CRM_VERSION );
        }
        $enhanced_js = ASRAA_CRM_URL . 'assets/js/crm-enhanced.js';
        if ( file_exists( ASRAA_CRM_PATH . 'assets/js/crm-enhanced.js' ) ) {
            wp_enqueue_script( 'asraa-crm-enhanced', $enhanced_js, array( 'jquery' ), ASRAA_CRM_VERSION, true );
            // Merge onto window.asraaCRM rather than overwrite it — some pages
            // (e.g. lead-view.php) set properties like leadViewId on this same
            // object inline, earlier in the page body, before this footer script
            // prints; a plain wp_localize_script() `var asraaCRM = {...}` here
            // would silently wipe those out.
            wp_add_inline_script(
                'asraa-crm-enhanced',
                'window.asraaCRM = Object.assign( window.asraaCRM || {}, ' . wp_json_encode( array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => asraa_crm_nonce(),
                ) ) . ' );',
                'before'
            );
        }

        // Groups module styling — was written but never enqueued, so the
        // groups list rendered with no card/grid styling at all.
        $groups_css = ASRAA_CRM_URL . 'assets/css/groups.css';
        if ( file_exists( ASRAA_CRM_PATH . 'assets/css/groups.css' ) ) {
            wp_enqueue_style( 'asraa-crm-groups', $groups_css, array( 'asraa-crm-admin' ), ASRAA_CRM_VERSION );
        }

        // Properties page behavior (Add/Edit modal, delete) — was written but
        // never enqueued, so "+ Add Property" and the row Edit/Delete buttons
        // did nothing. Depends on window.asraaCRM from crm-enhanced.js above.
        $properties_js = ASRAA_CRM_URL . 'assets/js/properties.js';
        if ( file_exists( ASRAA_CRM_PATH . 'assets/js/properties.js' ) ) {
            wp_enqueue_script( 'asraa-crm-properties', $properties_js, array( 'jquery', 'asraa-crm-enhanced' ), ASRAA_CRM_VERSION, true );
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

        // Campaigns (Bulk Message) is fully self-contained -- it renders its
        // own <div class="wrap"> and does its own capability check, so it's
        // registered directly rather than through render_page()/pages() to
        // avoid a nested/duplicate wrap. The class was already loaded and
        // functional (create/view/list campaigns) but never had a menu entry
        // pointing at it, so it was unreachable from the admin UI.
        if ( class_exists( 'Asraa_Campaign_Dashboard' ) ) {
            add_submenu_page(
                self::SLUG,
                __( 'Campaigns', 'asraa-crm' ),
                __( 'Campaigns', 'asraa-crm' ),
                $cap,
                'asraa-crm-campaigns',
                array( 'Asraa_Campaign_Dashboard', 'render' )
            );
        }

        // Rename first submenu to Dashboard
        global $submenu;
        if ( isset( $submenu[ self::SLUG ][0][0] ) ) {
            $submenu[ self::SLUG ][0][0] = __( 'Dashboard', 'asraa-crm' );
        }
    }

    /**
     * Detail-only pages (e.g. Lead View) are only meant to be reached via a
     * direct link with query args (&lead_id=123) — clicking them from the
     * sidebar with no args just shows a "not found" notice. Hide their <li>
     * from the rendered nav with CSS rather than unset()-ing the $submenu
     * entry, so the page stays fully registered/routable/capability-checked
     * (direct links and admin_url() lookups keep working); it's just not
     * listed for sidebar browsing.
     */
    public static function hide_nav_css() {
        $hidden_from_nav = array( 'asraa-crm-lead-view' );
        $selectors = array();
        foreach ( $hidden_from_nav as $slug ) {
            $selectors[] = '#adminmenu a[href*="page=' . esc_attr( $slug ) . '"]';
        }
        if ( ! $selectors ) return;
        echo '<style>' . implode( ',', $selectors ) . '{display:none !important;}</style>';
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
        $title = $pages[ $slug ]['title'];
        $header_partial = ASRAA_CRM_PATH . 'admin/partials/page-header.php';
        if ( file_exists( $header_partial ) ) {
            include $header_partial;
        }

        // Some pages need controller-fetched data (project/tower/unit lists from
        // their own repositories) rather than a bare include of the view file —
        // the view alone only has empty-array fallbacks for those variables.
        $controller_dispatch = array(
            'asraa-crm-properties'        => array( 'asraa_property_controller', 'properties_page' ),
            'asraa-crm-projects'          => array( 'asraa_crm_project_controller', 'projects_page' ),
            'asraa-crm-towers'            => array( 'asraa_crm_project_controller', 'towers_page' ),
            'asraa-crm-inventory'         => array( 'asraa_crm_inventory_controller', 'inventory_page' ),
            'asraa-crm-inventory-reports' => array( 'asraa_crm_inventory_controller', 'reports_page' ),
        );

        if ( isset( $controller_dispatch[ $slug ] ) ) {
            list( $global_var, $method ) = $controller_dispatch[ $slug ];
            $controller = $GLOBALS[ $global_var ] ?? null;
            if ( $controller && method_exists( $controller, $method ) ) {
                try {
                    $controller->$method();
                } catch ( \Throwable $e ) {
                    Asraa_CRM_Logger::log( 'error', 'AdminPage', $e->getMessage(), $file, $e->getLine(), $e->getTraceAsString() );
                    echo '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
                }
            } elseif ( file_exists( $file ) ) {
                // Fallback so the page still renders (with empty-array defaults)
                // if the controller somehow isn't available.
                Asraa_CRM_Logger::log( 'warning', 'AdminPage', 'Controller unavailable for ' . $slug . ', falling back to bare include', $file, 0 );
                include $file;
            }
        } elseif ( file_exists( $file ) ) {
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
