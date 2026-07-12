<?php
/**
 * Analytics Dashboard View
 *
 * @package AsraaGrowthEngine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap asraa-growth-engine">

    <h1 class="wp-heading-inline">
        📊 Analytics Center
    </h1>

    <p class="description">
        Monitor traffic, SEO performance, leads, and business growth.
    </p>

    <hr>

    <style>

    .asraa-grid{
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
        font-size:30px;
    }

    .asraa-card p{
        color:#666;
    }

    </style>

    <div class="asraa-grid">

        <div class="asraa-card">
            <h2>0</h2>
            <p>Total Visitors</p>
        </div>

        <div class="asraa-card">
            <h2>0</h2>
            <p>Organic Traffic</p>
        </div>

        <div class="asraa-card">
            <h2>0</h2>
            <p>Generated Leads</p>
        </div>

        <div class="asraa-card">
            <h2>0%</h2>
            <p>Conversion Rate</p>
        </div>

    </div>

    <table class="widefat striped">

        <thead>
            <tr>
                <th>Analytics Module</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>Google Analytics 4</td>
                <td>Not Connected</td>
            </tr>

            <tr>
                <td>Google Search Console</td>
                <td>Not Connected</td>
            </tr>

            <tr>
                <td>Keyword Rankings</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Core Web Vitals</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>PageSpeed Insights</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Property Analytics</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Lead Analytics</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Traffic Sources</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Conversion Tracking</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Weekly Reports</td>
                <td>Coming Soon</td>
            </tr>

        </tbody>

    </table>

</div>