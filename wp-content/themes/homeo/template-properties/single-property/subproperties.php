<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $post;
$subproperties_columns = homeo_get_config('property_subproperties_number',2);

$args = array(
    'post_per_page' => -1,
    'meta_query' => array(
        array(
            'key'       => WP_REALESTATE_PROPERTY_PREFIX . 'parent_property',
            'value'     => $post->ID,
            'compare'   => '==',
        )
    )
);
$loop = WP_RealEstate_Query::get_posts($args);
if ( $loop->have_posts() ) {
?>
    <div class="widget property-subproperties">
        <h3 class="widget-title"><?php esc_html_e('Subproperties', 'homeo'); ?></h3>
        <div class="row">
            <?php $i = 1; while ( $loop->have_posts() ) : $loop->the_post();
                $classes = '';
                if ( $i%$subproperties_columns == 1 ) {
                    $classes .= ' md-clearfix lg-clearfix';
                }
                if ( $i%2 == 1 ) {
                    $classes .= ' sm-clearfix';
                }
            ?>
                <div class="col-xs-12 col-sm-6 col-md-<?php echo esc_attr(12 / $subproperties_columns); ?> <?php echo esc_attr($classes); ?>">
                    <?php echo WP_RealEstate_Template_Loader::get_template_part( 'properties-styles/inner-grid' ); ?>
                </div>
            <?php $i++; endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
    </div>
<?php }