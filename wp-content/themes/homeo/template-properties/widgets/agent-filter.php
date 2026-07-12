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

<form method="get" action="<?php echo WP_RealEstate_Mixes::get_agents_page_url(); ?>" class="filter-agent-form filter-listing-form">

	<?php if ( ! get_option('permalink_structure') ) {
	    $agents_page_id = wp_realestate_get_option('agents_page_id');
	    $agents_page_id = WP_RealEstate_Mixes::get_lang_post_id( $agents_page_id, 'page');
	    if ( !empty($agents_page_id) ) {
	        echo '<input type="hidden" name="p" value="' . $agents_page_id . '">';
	    }
	} ?>

	<?php $fields = WP_RealEstate_Agent_Filter::get_fields(); ?>
	<?php if ( ! empty( $instance['sort'] ) ) : ?>
		<?php
			$filtered_keys = array_filter( explode( ',', $instance['sort'] ) );
			$fields = array_merge( array_flip( $filtered_keys ), $fields );
		?>
	<?php endif; ?>

	<?php foreach ( $fields as $key => $field ) : ?>
		<?php
			if ( empty( $instance['hide_'.$key] ) && !empty($field['field_call_back']) && is_callable($field['field_call_back']) ) {
				call_user_func( $field['field_call_back'], $instance, $args, $key, $field );
			}
		?>
	<?php endforeach; ?>

	<?php if ( ! empty( $instance['button_text'] ) ) : ?>
		<div class="form-group form-group-submit">
			<button class="button btn btn-theme"><?php echo esc_attr( $instance['button_text'] ); ?></button>
		</div><!-- /.form-group -->
	<?php endif; ?>
</form>

<?php
if ( !empty($args['after_widget']) ) {
	echo wp_kses_post( $args['after_widget'] );
}
?>