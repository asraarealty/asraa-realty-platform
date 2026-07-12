<?php
/**
 * Admin Menu
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Menu
{
    /**
     * Capability
     */
    private const CAPABILITY = 'manage_options';

    /**
     * Parent Slug
     */
    private const MENU_SLUG = 'asraa-growth-engine';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    /**
     * Register Menu
     */
    public function registerMenu(): void
    {
        add_menu_page(
            __('Asraa Growth Engine', 'asraa-growth-engine'),
            __('Asraa Growth Engine', 'asraa-growth-engine'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'dashboard'],
            'dashicons-chart-area',
            25
        );

        $this->addSubmenu('Dashboard', self::MENU_SLUG, 'dashboard');
        $this->addSubmenu('SEO', self::MENU_SLUG . '-seo', 'seo');
        $this->addSubmenu('Analytics', self::MENU_SLUG . '-analytics', 'analytics');
        $this->addSubmenu('AI Assistant', self::MENU_SLUG . '-ai', 'ai');
        $this->addSubmenu('Diagnostics', self::MENU_SLUG . '-diagnostics', 'diagnostics');
        $this->addSubmenu('Settings', self::MENU_SLUG . '-settings', 'settings');
    }

    /**
     * Add Submenu
     */
    private function addSubmenu(string $title, string $slug, string $view): void
    {
        add_submenu_page(
            self::MENU_SLUG,
            __($title, 'asraa-growth-engine'),
            __($title, 'asraa-growth-engine'),
            self::CAPABILITY,
            $slug,
            function () use ($view) {
                $this->render($view);
            }
        );
    }

    /**
     * Dashboard Callback
     */
    public function dashboard(): void
    {
        $this->render('dashboard');
    }

    /**
     * Render View
     */
    private function render(string $view): void
    {
        $file = __DIR__ . '/views/' . $view . '.php';

        if (!file_exists($file)) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html(sprintf('View "%s.php" not found.', $view));
            echo '</p></div>';
            return;
        }

        require $file;
    }
}