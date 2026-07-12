<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Footer
{
    public static function render()
    {
        ?>

<div class="asraa-footer">

    <hr>

    <p>

        © <?php echo date('Y'); ?>

        Asraa Realty

    </p>

</div>

<?php
    }
}