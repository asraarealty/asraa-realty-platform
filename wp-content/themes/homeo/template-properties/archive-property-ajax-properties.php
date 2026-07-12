<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$layout_type = homeo_get_properties_layout_type();

$properties_display_mode = homeo_get_properties_display_mode();
$property_inner_style = homeo_get_properties_inner_style();


$total = $properties->found_posts;
$per_page = $properties->query_vars['posts_per_page'];
$current = max( 1, $properties->get( 'paged', 1 ) );
$last  = min( $total, $per_page * $current );

$pre_page  = max( 0, ($properties->get( 'paged', 1 ) - 1 ) );
$i =  $per_page * $pre_page;

?>
<div class="results-count">
	<span class="last"><?php echo esc_html($last); ?></span>
</div>

<div class="items-wrapper">
	<?php if ( $properties_display_mode == 'grid' ) {
		$columns = homeo_get_properties_columns();
		$bcol = $columns ? 12/$columns : 4;

		if( $layout_type == 'half-map' ) {
			$ct = ($columns && $columns >= 2) ? 6 : 1;
		} else {
			$ct = '12';
		}
	?>
			<?php while ( $properties->have_posts() ) : $properties->the_post(); ?>
				<div class="col-sm-6 col-md-<?php echo esc_attr($bcol); ?> col-ct-<?php echo esc_attr($ct); ?> col-xs-12 <?php echo esc_attr(($i%$columns == 0)?'lg-clearfix md-clearfix':'') ?> <?php echo esc_attr(($i%2 == 0)?'sm-clearfix':'') ?>">
					<?php echo WP_RealEstate_Template_Loader::get_template_part( 'properties-styles/inner-'.$property_inner_style ); ?>
				</div>
			<?php $i++; endwhile; ?>
	<?php } else { ?>
		<?php while ( $properties->have_posts() ) : $properties->the_post(); ?>
			<?php echo WP_RealEstate_Template_Loader::get_template_part( 'properties-styles/inner-list' ); ?>
		<?php endwhile; ?>
	<?php } ?>
</div>

<div class="apus-pagination-next-link"><?php next_posts_link( '&nbsp;', $properties->max_num_pages ); ?></div>