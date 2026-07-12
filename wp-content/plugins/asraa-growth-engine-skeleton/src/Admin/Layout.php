<?php
/**
 * Layout Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Layout
{
    /**
     * Render Header
     */
    public static function header(string $title = '')
    {
        ?>
        <div class="wrap asraa-growth-engine">

            <h1 class="wp-heading-inline">

                🚀 <?php echo esc_html($title); ?>

            </h1>

            <hr>

            <div class="asraa-admin-container">

        <?php
    }

    /**
     * Render Footer
     */
    public static function footer()
    {
        ?>

            </div>

        </div>

        <?php
    }
}