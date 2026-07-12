<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Widgets
{
    public static function card(
        string $title,
        string $value,
        string $icon = '📊'
    ): void {

        ?>

        <div class="asraa-widget">

            <div class="asraa-widget-icon">

                <?php echo esc_html($icon); ?>

            </div>

            <div>

                <h3><?php echo esc_html($value); ?></h3>

                <p><?php echo esc_html($title); ?></p>

            </div>

        </div>

        <?php

    }
}