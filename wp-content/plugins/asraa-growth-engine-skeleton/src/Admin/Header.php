<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Header
{
    public static function render()
    {
        ?>

<div class="asraa-header">

    <div class="logo">

        <h2>Asraa Growth Engine</h2>

    </div>

    <div class="version">

        Version <?php echo esc_html(ASRAA_GE_VERSION); ?>

    </div>

</div>

<?php
    }
}