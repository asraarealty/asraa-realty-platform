<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wp_query;

if ( get_query_var( 'paged' ) ) {
    $paged = get_query_var( 'paged' );
} elseif ( get_query_var( 'page' ) ) {
    $paged = get_query_var( 'page' );
} else {
    $paged = 1;
}

$query_args = array(
	'post_type' => 'agency',
    'post_status' => 'publish',
    'post_per_page' => wp_realestate_get_option('number_agencies_per_page', 10),
    'paged' => $paged,
);
$params = array();
$taxs = ['category', 'location'];
foreach ($taxs as $tax) {
	if ( is_tax('agency_'.$tax) ) {
		$term = $wp_query->queried_object;
		if ( isset( $term->term_id) ) {
			$params['filter-'.$tax] = $term->term_id;
		}
	}
}

if ( WP_RealEstate_Abstract_Filter::has_filter() ) {
	$params = array_merge($params, $_GET);
}
$agencies = WP_RealEstate_Query::get_posts($query_args, $params);


if ( isset( $_REQUEST['load_type'] ) && WP_RealEstate_Mixes::is_ajax_request() ) {
	if ( 'items' !== $_REQUEST['load_type'] ) {
        echo WP_RealEstate_Template_Loader::get_template_part('archive-agency-ajax-full', array('agencies' => $agencies));
	} else {
		echo WP_RealEstate_Template_Loader::get_template_part('archive-agency-ajax-agencies', array('agencies' => $agencies));
	}

} else {
	get_header();
	$sidebar_configs = homeo_get_agencies_layout_configs();

	$agencies_page = WP_RealEstate_Mixes::get_agencies_page_url();
	$display_mode = homeo_get_agencies_display_mode();
	$display_mode_html = homeo_display_mode_form($display_mode, $agencies_page);
	homeo_render_breadcrumbs($display_mode_html);
	?>
	<section id="main-container" class="main-content  <?php echo apply_filters('homeo_agency_content_class', 'container');?> inner">
		
		<?php if ( homeo_get_agencies_layout_sidebar() == 'main' && is_active_sidebar( 'agencies-filter-top-sidebar' ) ) { ?>
			<div class="agencies-filter-top-sidebar-wrapper filter-top-sidebar-wrapper <?php echo apply_filters('homeo_page_content_class', 'container');?>">
		   		<?php dynamic_sidebar( 'agencies-filter-top-sidebar' ); ?>
		   	</div>
		<?php } ?>

		<?php homeo_before_content( $sidebar_configs ); ?>
		<div class="row">
			<?php homeo_display_sidebar_left( $sidebar_configs ); ?>

			<div id="main-content" class="col-sm-12 <?php echo esc_attr($sidebar_configs['main']['class']); ?>">
				<div id="main" class="site-main layout-type-grid" role="main">

					<?php
						echo WP_RealEstate_Template_Loader::get_template_part('loop/agency/archive-inner', array('agencies' => $agencies));

						echo WP_RealEstate_Template_Loader::get_template_part('loop/agency/pagination', array('agencies' => $agencies));
					?>

				</div><!-- .site-main -->
			</div><!-- .content-area -->
			
			<?php homeo_display_sidebar_right( $sidebar_configs ); ?>
			
		</div>
	</section>
	<?php get_footer();
}