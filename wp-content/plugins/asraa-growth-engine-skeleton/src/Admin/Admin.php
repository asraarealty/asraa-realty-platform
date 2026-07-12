<?php
/**
 * Admin Bootstrap
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadFiles();

        $this->boot();
    }

    /**
     * Load Admin Classes
     */
    private function loadFiles(): void
    {
        $files = [

            // Core
            'Menu.php',
            'Dashboard.php',
            'Settings.php',
            'Assets.php',
            'Ajax.php',

            // Utilities
            'Widgets.php',
            'Cards.php',
            'Charts.php',
            'Notices.php',
            'SystemHealth.php',
            'Helpers.php',

            // Future Framework (loaded only, not booted yet)
            'Router.php',
            'Layout.php',
            'Header.php',
            'Footer.php',
            'Sidebar.php',
            'Page.php',

            // Future Pages
            'SEOPage.php',
            'AnalyticsPage.php',
            'AIPage.php',
            'PropertySEOPage.php',
            'SearchConsolePage.php',
            'GoogleAnalyticsPage.php',
            'ReportsPage.php',
            'RedirectsPage.php',
            'BrokenLinksPage.php',
            'LogsPage.php',
            'AutomationPage.php',

        ];

        foreach ($files as $file) {

            $path = __DIR__ . '/' . $file;

            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Boot Admin
     */
    public function boot(): void
    {
        if (class_exists(Menu::class)) {
            new Menu();
        }

        if (class_exists(Dashboard::class)) {
            new Dashboard();
        }

        if (class_exists(Settings::class)) {
            new Settings();
        }

        if (class_exists(Assets::class)) {
            new Assets();
        }

        if (class_exists(Ajax::class)) {
            new Ajax();
        }

        /**
         * Future modules
         *
         * Enable these after migrating the admin UI
         * from views/*.php to the Page/Layout framework.
         *
         * new Router();
         * new Layout();
         * new Header();
         * new Footer();
         * new Sidebar();
         *
         * new SEOPage();
         * new AnalyticsPage();
         * new AIPage();
         * new PropertySEOPage();
         * new SearchConsolePage();
         * new GoogleAnalyticsPage();
         * new ReportsPage();
         * new RedirectsPage();
         * new BrokenLinksPage();
         * new LogsPage();
         * new AutomationPage();
         */
    }
}