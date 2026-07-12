<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $post;
$meta_obj = WP_RealEstate_Property_Meta::get_instance($post->ID);

$suffix = wp_realestate_get_option('measurement_unit_area');
$lot_area = homeo_property_display_meta($post, 'lot_area', '', '', $suffix);
$beds = homeo_property_display_meta($post, 'beds', '',$meta_obj->get_post_meta_title( 'beds' ).':' );
$baths = homeo_property_display_meta($post, 'baths', '',$meta_obj->get_post_meta_title( 'baths' ).':' );
$type = homeo_property_display_type($post,'',false);
?>
<div class="description inner">
	<?php if( !empty($type) || !empty($beds) || !empty($baths) || !empty($lot_area)  ){ ?>
		<div class="detail-metas-top">
	        <?php 
	            echo trim($type);
	            echo trim($beds);
	            echo trim($baths);
	            echo trim($lot_area);
	        ?>
	    </div>
    <?php } ?>
    <h3 class="title"><?php esc_html_e('Overview', 'homeo'); ?></h3>
    <div class="description-inner">
        <?php the_content(); ?>
        <?php do_action('wp-realestate-single-property-description', $post); ?>
    </div>
</div>