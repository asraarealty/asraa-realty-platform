<?php
/**
 * AI Dashboard View
 *
 * @package AsraaGrowthEngine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap asraa-growth-engine">

    <h1 class="wp-heading-inline">
        🤖 AI Engine
    </h1>

    <p class="description">
        AI Content Generation, SEO Writing, Property Descriptions and Automation.
    </p>

    <hr>

    <style>

    .asraa-ai-grid{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
        gap:20px;
        margin-top:25px;
    }

    .asraa-ai-card{
        background:#fff;
        border-left:4px solid #2271b1;
        padding:20px;
        box-shadow:0 2px 8px rgba(0,0,0,.08);
    }

    .asraa-ai-card h3{
        margin:0 0 10px;
    }

    .badge{
        display:inline-block;
        padding:4px 10px;
        border-radius:20px;
        background:#2271b1;
        color:#fff;
        font-size:12px;
    }

    .coming{
        color:#999;
    }

    </style>

    <div class="asraa-ai-grid">

        <div class="asraa-ai-card">
            <h3>AI Status</h3>
            <span class="badge">Ready</span>
            <p>Engine initialized successfully.</p>
        </div>

        <div class="asraa-ai-card">
            <h3>Connected Model</h3>
            <h2>None</h2>
            <p>Connect OpenAI, Gemini or Claude.</p>
        </div>

        <div class="asraa-ai-card">
            <h3>Credits Used</h3>
            <h2>0</h2>
            <p>No AI requests yet.</p>
        </div>

        <div class="asraa-ai-card">
            <h3>Generated Content</h3>
            <h2>0</h2>
            <p>Total AI generated items.</p>
        </div>

    </div>

    <br>

    <table class="widefat striped">

        <thead>
            <tr>
                <th>AI Module</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>SEO Title Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Meta Description Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Property Description Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Blog Writer</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Landing Page Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>FAQ Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Image Prompt Generator</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>SEO Audit AI</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>AI Chat Assistant</td>
                <td>Coming Soon</td>
            </tr>

            <tr>
                <td>Automation Engine</td>
                <td>Coming Soon</td>
            </tr>

        </tbody>

    </table>

</div>