<?php
/**
 * SEO Dashboard View
 *
 * @package AsraaGrowthEngine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap asraa-growth-engine">

    <h1 class="wp-heading-inline">
        🔍 SEO Engine
    </h1>

    <p class="description">
        Manage your website's SEO, metadata, schema, indexing, and search visibility.
    </p>

    <hr>

    <style>

    .asraa-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
        gap:20px;
        margin-top:25px;
    }

    .asraa-box{
        background:#fff;
        border-left:4px solid #2271b1;
        padding:20px;
        box-shadow:0 2px 8px rgba(0,0,0,.08);
    }

    .asraa-box h3{
        margin-top:0;
    }

    .status{
        font-weight:bold;
        color:#2271b1;
    }

    </style>

    <div class="asraa-grid">

        <div class="asraa-box">
            <h3>SEO Score</h3>
            <h2>-- / 100</h2>
            <p>Calculated after analysis.</p>
        </div>

        <div class="asraa-box">
            <h3>Indexed Pages</h3>
            <h2>--</h2>
            <p>Google Search Console</p>
        </div>

        <div class="asraa-box">
            <h3>Sitemap</h3>
            <p class="status">Ready</p>
            <p>/sitemap.xml</p>
        </div>

        <div class="asraa-box">
            <h3>Robots.txt</h3>
            <p class="status">Ready</p>
        </div>

    </div>

    <br>

    <table class="widefat striped">

        <thead>
            <tr>
                <th>SEO Module</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>Meta Titles</td>
                <td>✅ Enabled</td>
            </tr>

            <tr>
                <td>Meta Descriptions</td>
                <td>✅ Enabled</td>
            </tr>

            <tr>
                <td>Open Graph</td>
                <td>✅ Enabled</td>
            </tr>

            <tr>
                <td>Twitter Cards</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Schema Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Image SEO</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>404 Monitor</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Redirect Manager</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Local SEO</td>
                <td>Coming Soon</td>
            </tr>

        </tbody>

    </table>

</div>