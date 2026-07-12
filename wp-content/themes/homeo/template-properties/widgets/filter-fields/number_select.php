<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$number_style = isset($field['number_style']) ? $field['number_style'] : '';
$min_number = isset($field['min_number']) ? $field['min_number'] : 1;
$max_number = isset($field['max_number']) ? $field['max_number'] : 5;

$placeholder = !empty($field['placeholder']) ? $field['placeholder'] : sprintf(esc_html__('%s : Any', 'homeo'), $field['name']);
?>
<div class="form-group form-group-<?php echo esc_attr($key); ?> <?php echo esc_attr($number_style); ?>">
    <?php if ( !isset($field['show_title']) || $field['show_title'] ) { ?>
        <label for="<?php echo esc_attr( $args['widget_id'] ); ?>_<?php echo esc_attr($key); ?>" class="heading-label">
            <?php echo wp_kses_post($field['name']); ?>
        </label>
    <?php } ?>
    <div class="form-group-inner inner select-wrapper">
        <?php if ( !empty($field['icon']) ) { ?>
            <i class="<?php echo esc_attr( $field['icon'] ); ?>"></i>
        <?php } ?>
        <select name="<?php echo esc_attr($name); ?>" class="form-control" id="<?php echo esc_attr( $args['widget_id'] ); ?>_<?php echo esc_attr($key); ?>" data-placeholder="<?php echo esc_attr($placeholder); ?>">
            
            <option value=""><?php echo esc_html($placeholder); ?></option>
            <?php if ( $min_number <= $max_number ) {
                if ( $number_style == 'number' ) {
                    for ( $i = $min_number; $i <= $max_number; $i++ ) : ?>
                        <option value="<?php echo esc_attr( $i ); ?>" <?php selected($selected, $i); ?>>
                            <?php echo esc_attr( $i ); ?>
                        </option>
                    <?php endfor;
                } else {
                    for ( $i = $min_number; $i <= $max_number; $i++ ) : ?>
                        <option value="<?php echo esc_attr( $i ); ?>+" <?php selected($selected, $i.'+'); ?>>
                            <?php echo esc_attr( $i ); ?>+
                        </option>
                    <?php endfor;
                }
            } ?>
        </select>
    </div>
</div><!-- /.form-group -->