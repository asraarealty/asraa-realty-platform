<?php
/**
 * Dashboard View
 *
 * @package AsraaGrowthEngine
 */

if (!defined('ABSPATH')) {
    exit;
}

use Asraa\GrowthEngine\Admin\Dashboard;

$stats = Dashboard::get_stats();

$plugin_version = defined('ASRAA_GE_VERSION') ? ASRAA_GE_VERSION : '1.0.0';
?>

<div class="wrap asraa-growth-engine">

    <h1 class="wp-heading-inline">
        🚀 Asraa Growth Engine
    </h1>

    <p class="description">
        Enterprise SEO • AI • Analytics • Real Estate Growth Platform
    </p>

    <hr>

    <style>

    .asraa-cards{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:20px;
        margin:25px 0;
    }

    .asraa-card{
        background:#fff;
        border-left:4px solid #2271b1;
        padding:20px;
        box-shadow:0 2px 8px rgba(0,0,0,.08);
    }

    .asraa-card h2{
        margin:0;
        font-size:28px;
    }

    .asraa-card p{
        margin:8px 0 0;
        color:#666;
    }

    .asraa-section{
        background:#fff;
        margin-top:25px;
        padding:25px;
        box-shadow:0 2px 8px rgba(0,0,0,.08);
    }

    .asraa-module-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
        gap:18px;
        margin-top:20px;
    }

    .asraa-module{
        border:1px solid #ddd;
        padding:18px;
        border-radius:8px;
        background:#fafafa;
    }

    .asraa-module h3{
        margin-top:0;
    }

    </style>

    <div class="asraa-cards">

        <div class="asraa-card">
            <h2><?php echo esc_html($stats['properties']); ?></h2>
            <p>Published Properties</p>
        </div>

        <div class="asraa-card">
            <h2><?php echo esc_html($stats['posts']); ?></h2>
            <p>Blog Posts</p>
        </div>

        <div class="asraa-card">
            <h2><?php echo esc_html($stats['pages']); ?></h2>
            <p>Pages</p>
        </div>

        <div class="asraa-card">
            <h2><?php echo esc_html($stats['users']); ?></h2>
            <p>Users</p>
        </div>

    </div>

    <div class="asraa-section">

        <h2>System Information</h2>

        <table class="widefat striped">

            <tbody>

                <tr>
                    <td><strong>Plugin Version</strong></td>
                    <td><?php echo esc_html($plugin_version); ?></td>
                </tr>

                <tr>
                    <td><strong>WordPress Version</strong></td>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>

                <tr>
                    <td><strong>PHP Version</strong></td>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>

                <tr>
                    <td><strong>Site URL</strong></td>
                    <td><?php echo esc_html(home_url()); ?></td>
                </tr>

                <tr>
                    <td><strong>Theme</strong></td>
                    <td><?php echo esc_html(wp_get_theme()->get('Name')); ?></td>
                </tr>

                <tr>
                    <td><strong>Debug Mode</strong></td>
                    <td><?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'Enabled' : 'Disabled'; ?></td>
                </tr>

            </tbody>

        </table>

    </div>

    <div class="asraa-section">

        <h2>Plugin Integrations</h2>

        <table class="widefat striped">

            <thead>

                <tr>
                    <th>Module</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

                <tr>
                    <td>Homeo Theme</td>
                    <td>✅ Active</td>
                </tr>

                <tr>
                    <td>WP RealEstate</td>
                    <td><?php echo class_exists('WP_RealEstate') ? '✅ Connected' : '❌ Missing'; ?></td>
                </tr>

                <tr>
                    <td>Asraa CRM</td>
                    <td><?php echo class_exists('Asraa_CRM') ? '✅ Connected' : 'Waiting'; ?></td>
                </tr>

                <tr>
                    <td>Google Search Console</td>
                    <td>Not Connected</td>
                </tr>

                <tr>
                    <td>Google Analytics</td>
                    <td>Not Connected</td>
                </tr>

            </tbody>

        </table>

    </div>

    <div class="asraa-section">

        <h2>Growth Engine Modules</h2>

        <div class="asraa-module-grid">

            <div class="asraa-module">
                <h3>SEO Engine</h3>
                <p>Meta, Titles, Sitemap, Robots.</p>
            </div>

            <div class="asraa-module">
                <h3>Schema Engine</h3>
                <p>Automatic JSON-LD generation.</p>
            </div>

            <div class="asraa-module">
                <h3>AI Assistant</h3>
                <p>Generate SEO content using AI.</p>
            </div>

            <div class="asraa-module">
                <h3>Property SEO</h3>
                <p>Optimize WP RealEstate listings.</p>
            </div>

            <div class="asraa-module">
                <h3>Analytics</h3>
                <p>Traffic and ranking reports.</p>
            </div>

            <div class="asraa-module">
                <h3>Automation</h3>
                <p>Automatic SEO maintenance.</p>
            </div>

            <div class="asraa-module">
                <h3>Local SEO</h3>
                <p>Google Business optimization.</p>
            </div>

            <div class="asraa-module">
                <h3>Reports</h3>
                <p>Export SEO and performance reports.</p>
            </div>

        </div>

    </div>

</div>