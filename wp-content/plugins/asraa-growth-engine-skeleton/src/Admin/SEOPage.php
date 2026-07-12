<?php
/**
 * SEO Admin Page
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SEOPage
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register']);
    }

    /**
     * Register Page
     */
    public function register(): void
    {
        add_submenu_page(
            'asraa-growth-engine',
            __('SEO', 'asraa-growth-engine'),
            __('SEO', 'asraa-growth-engine'),
            'manage_options',
            'asraa-seo',
            [$this, 'render']
        );
    }

    /**
     * Render Page
     */
    public function render(): void
    {
        ?>
        <div class="wrap">

            <h1>SEO Dashboard</h1>

            <p>Manage all SEO features from one place.</p>

            <div class="postbox">
                <div class="inside">

                    <h2>SEO Modules</h2>

                    <table class="widefat striped">

                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <tr>
                                <td>Meta Tags</td>
                                <td>✅ Active</td>
                            </tr>

                            <tr>
                                <td>Schema</td>
                                <td>✅ Active</td>
                            </tr>

                            <tr>
                                <td>Sitemap</td>
                                <td>✅ Active</td>
                            </tr>

                            <tr>
                                <td>Robots.txt</td>
                                <td>✅ Active</td>
                            </tr>

                            <tr>
                                <td>Redirect Manager</td>
                                <td>✅ Active</td>
                            </tr>

                            <tr>
                                <td>404 Monitor</td>
                                <td>Coming Soon</td>
                            </tr>

                            <tr>
                                <td>Search Console</td>
                                <td>Ready</td>
                            </tr>

                            <tr>
                                <td>Local SEO</td>
                                <td>Ready</td>
                            </tr>

                        </tbody>

                    </table>

                </div>
            </div>

        </div>
        <?php
    }
}