<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array[] $projects Active projects */
$projects = $projects ?? [];
?>

<div class="wrap">

<div style="margin:16px 0;">
    <button class="button asraa-report-tab-btn button-primary" data-tab="by_project">Available by Project</button>
    <button class="button asraa-report-tab-btn" data-tab="heatmap">Tower Heatmap</button>
    <button class="button asraa-report-tab-btn" data-tab="sold_week">Sold Units</button>
    <button class="button asraa-report-tab-btn" data-tab="dead_inventory">Dead Inventory (&gt;30 days)</button>
</div>

<div id="asraa-report-output" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;min-height:200px;">
    <p style="color:#888; text-align:center; margin-top:40px;">⬆️ Click a tab above to load report data.</p>
</div>
</div>

<style>
.asraa-report-tab-btn { margin-right:6px; }
.asraa-heatmap-table th, .asraa-heatmap-table td { padding:8px 12px; }
</style>

<script>
(function($){
    const nonce = '<?php echo esc_js( wp_create_nonce( 'asraa_crm_nonce' ) ); ?>';
    // ajaxurl is already defined globally by WP core on every wp-admin page.

    $(document).on('click', '.asraa-report-tab-btn', function(){
        const $btn = $(this);
        const tab  = $btn.data('tab');

        $('.asraa-report-tab-btn').removeClass('button-primary');
        $btn.addClass('button-primary');

        $('#asraa-report-output').html('<p style="color:#888;text-align:center;padding:40px;">Loading…</p>');

        $.post(ajaxurl, { action:'asraa_load_report_tab', tab, nonce }, function(res){
            if (res.success) {
                $('#asraa-report-output').html(res.data.html);
            } else {
                $('#asraa-report-output').html('<p style="color:red;">Failed to load report.</p>');
            }
        });
    });

    // Auto-load first tab.
    $('.asraa-report-tab-btn[data-tab="by_project"]').trigger('click');
})(jQuery);
</script>
