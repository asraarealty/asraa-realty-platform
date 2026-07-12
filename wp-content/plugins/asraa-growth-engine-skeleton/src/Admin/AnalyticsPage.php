<?php
/**
 * Analytics Admin Page
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AnalyticsPage
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register']);
    }

    /**
     * Register Menu
     */
    public function register(): void
    {
        add_submenu_page(
            'asraa-growth-engine',
            __('Analytics', 'asraa-growth-engine'),
            __('Analytics', 'asraa-growth-engine'),
            'manage_options',
            'asraa-analytics',
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

            <h1>📊 Analytics Dashboard</h1>

            <p>
                Google Analytics, Search Console and Lead Analytics
            </p>

            <hr>

            <div class="postbox">

                <h2 class="hndle">
                    <span>Overview</span>
                </h2>

                <div class="inside">

                    <table class="widefat striped">

                        <tbody>

                        <tr>
                            <td>Total Visitors</td>
                            <td>--</td>
                        </tr>

                        <tr>
                            <td>Organic Traffic</td>
                            <td>--</td>
                        </tr>

                        <tr>
                            <td>Leads Generated</td>
                            <td>--</td>
                        </tr>

                        <tr>
                            <td>Conversion Rate</td>
                            <td>--</td>
                        </tr>

                        <tr>
                            <td>Bounce Rate</td>
                            <td>--</td>
                        </tr>

                        <tr>
                            <td>Average Session</td>
                            <td>--</td>
                        </tr>

                        </tbody>

                    </table>

                </div>

            </div>

            <br>

            <div class="postbox">

                <h2 class="hndle">
                    <span>Google Services</span>
                </h2>

                <div class="inside">

                    <table class="widefat striped">

                        <tbody>

                        <tr>
                            <td>Google Analytics</td>
                            <td>
                                <?php
                                echo Settings::get('google_analytics_id')
                                    ? '✅ Connected'
                                    : '❌ Not Configured';
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td>Search Console</td>
                            <td>
                                <?php
                                echo Settings::get('search_console_property')
                                    ? '✅ Connected'
                                    : '❌ Not Configured';
                                ?>
                            </td>
                        </tr>

                        </tbody>

                    </table>

                </div>

            </div>

            <br>

            <div class="postbox">

                <h2 class="hndle">
                    <span>Coming Soon</span>
                </h2>

                <div class="inside">

                    <ul style="list-style:disc;padding-left:20px;">

                        <li>Live Google Analytics 4</li>

                        <li>Search Console API</li>

                        <li>Top Landing Pages</li>

                        <li>Keyword Tracking</li>

                        <li>Traffic Sources</li>

                        <li>Goal Tracking</li>

                        <li>Lead Attribution</li>

                        <li>Weekly Reports</li>

                    </ul>

                </div>

            </div>

        </div>

        <?php
    }
}