<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $post;
$meta_obj = WP_RealEstate_Property_Meta::get_instance($post->ID);

$suffix = wp_realestate_get_option('measurement_unit_area');
$lot_area = homeo_property_display_meta($post, 'lot_area', '', '', $suffix.':');
$beds = homeo_property_display_meta($post, 'beds', '', $meta_obj->get_post_meta_title( 'beds' ).':');
$baths = homeo_property_display_meta($post, 'baths', '', $meta_obj->get_post_meta_title( 'baths' ).':');
?>
<article <?php post_class('property-list-simple'); ?>>
    <div class="flex-middle">
        <div class="property-thumbnail-wrapper flex-middle">
            <?php homeo_property_display_image( $post, 'thumbnail' ); ?>
        </div>
        <div class="property-information">
            <?php the_title( sprintf( '<h2 class="entry-title property-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
            <?php homeo_property_display_price($post, 'no-icon-title', true); ?>
            <div class="property-metas">
                <?php 
                    echo trim($beds);
                    echo trim($baths);
                    echo trim($lot_area);
                ?>
            </div>
        </div>
    </div>
</article><!-- #post-## -->