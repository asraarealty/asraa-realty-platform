<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Sidebar
{
    public static function render()
    {
?>

<div class="asraa-sidebar">

<ul>

<li><a href="?page=asraa-growth-engine">Dashboard</a></li>

<li><a href="?page=asraa-growth-engine-seo">SEO</a></li>

<li><a href="?page=asraa-growth-engine-ai">AI</a></li>

<li><a href="?page=asraa-growth-engine-analytics">Analytics</a></li>

<li><a href="?page=asraa-growth-engine-property">Property</a></li>

<li><a href="?page=asraa-growth-engine-settings">Settings</a></li>

</ul>

</div>

<?php
    }
}