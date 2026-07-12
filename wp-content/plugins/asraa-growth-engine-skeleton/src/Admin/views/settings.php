<?php
/**
 * Settings View
 *
 * @package AsraaGrowthEngine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">

    <h1>⚙️ Asraa Growth Engine Settings</h1>

    <form method="post" action="options.php">

        <?php
        settings_fields('asraa_growth_engine');
        do_settings_sections('asraa-growth-engine-settings');
        submit_button('Save Settings');
        ?>

    </form>

</div>