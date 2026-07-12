<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( !empty($args['before_widget']) ) {
	echo wp_kses_post( $args['before_widget'] );
}

if ( ! empty( $instance['title'] ) ) {
	echo wp_kses_post( $args['before_title'] );
	echo esc_attr( $instance['title'] );
	echo wp_kses_post( $args['after_title'] );
}
homeo_load_select2();
?>

<form method="get" action="<?php echo WP_RealEstate_Mixes::get_properties_page_url(); ?>" class="filter-property-form filter-listing-form">

	<?php if ( ! get_option('permalink_structure') ) {
        $properties_page_id = wp_realestate_get_option('properties_page_id');
        $properties_page_id = WP_RealEstate_Mixes::get_lang_post_id( $properties_page_id, 'page');
        if ( !empty($properties_page_id) ) {
            echo '<input type="hidden" name="p" value="' . $properties_page_id . '">';
        }
    } ?>
    
	<?php $fields = $fields_adv = WP_RealEstate_Property_Filter::get_fields(); ?>
	<?php if ( ! empty( $instance['sort'] ) ) : ?>
		<?php
			$filtered_keys = array_filter( explode( ',', $instance['sort'] ) );
			$fields = array_merge( array_flip( $filtered_keys ), $fields );
		?>
	<?php endif; ?>
	<div class="main-filter clearfix">
		<?php foreach ( $fields as $key => $field ) : ?>
			<?php
				if ( empty( $instance['hide_'.$key] ) && !empty($field['field_call_back']) && is_callable($field['field_call_back']) ) {
					call_user_func( $field['field_call_back'], $instance, $args, $key, $field );
				}
			?>
		<?php endforeach; ?>

		
	</div>	
	<?php if ( ! empty( $instance['show_adv_fields'] ) ) : ?>
		<div class="more-advanced clearfix">
			<a href="#toggle_adv" class="filter-toggle-adv visiable-line">
				<i class="flaticon-more"></i> <span><?php esc_html_e('Advanced Search', 'homeo'); ?></span>
			</a>
		</div>
		<div class="filter-advance-fields clearfix">
			<?php if ( ! empty( $instance['sort_adv'] ) ) : ?>
				<?php
				$filtered_keys = array_filter( explode( ',', $instance['sort_adv'] ) );
				$fields_adv = array_merge( array_flip( $filtered_keys ), $fields_adv );
				?>
			<?php endif; ?>

			<?php foreach ( $fields_adv as $key => $field ) : ?>
				
				<?php
					if ( empty( $instance['hide_adv_'.$key] ) && !empty($field['field_call_back']) && is_callable($field['field_call_back']) ) {
						call_user_func( $field['field_call_back'], $instance, $args, $key, $field );
					}
				?>

			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	
	<?php if ( ! empty( $instance['button_text'] ) ) : ?>
		<div class="form-group no-margin">
			<button class="button btn"><?php echo esc_attr( $instance['button_text'] ); ?></button>
		</div><!-- /.form-group -->
	<?php endif; ?>
</form>

<?php
if ( !empty($args['after_widget']) ) {
	echo wp_kses_post( $args['after_widget'] );
}
?>

