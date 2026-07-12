<?php
/**
 * Diagnostics View
 *
 * @package AsraaGrowthEngine
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">

    <h1>System Diagnostics</h1>

    <p>Run health checks for the website.</p>

    <div id="asraa-diagnostics">

        <button class="button button-primary">
            Run Diagnostics
        </button>

        <div id="diagnostics-results" style="margin-top:20px;">
            Diagnostics have not been run yet.
        </div>

    </div>

</div>