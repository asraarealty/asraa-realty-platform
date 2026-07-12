<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $post;

if ( homeo_get_config('property_stats_graph_for') == 'registered' ) {
    if ( !is_user_logged_in() ) {
        return;
    }
} elseif ( homeo_get_config('property_stats_graph_for') == 'author' ) {
    if ( !is_user_logged_in() ) {
        return;
    }
    $user = wp_get_current_user();
    if ( !in_array('administrator', $user->roles) && $post->post_author !== $user->ID ) {
        return;
    }
}
?>
<div id="property-section-stats_graph" class="property-section property-page_views">
	<h3 class="title"><?php echo esc_html__( 'Page Views', 'wp-realestate' ); ?></h3>
	<div class="page_views-wrapper">
		<canvas id="property_chart_wrapper" data-property_id="<?php the_ID(); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce( 'wp-realestate-property-chart-nonce' )); ?>"></canvas>
	</div>
</div>