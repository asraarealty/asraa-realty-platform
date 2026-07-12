<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$style = isset($field['style']) ? $field['style'] : '';
if ( version_compare(WP_REALESTATE_PLUGIN_VERSION, '1.4.0', '>=') ) {
	$min = WP_RealEstate_Price::convert_price_exchange($min);
	$max = WP_RealEstate_Price::convert_price_exchange($max);
}
?>
<div class="clearfix form-group form-group-<?php echo esc_attr($key); ?> <?php echo esc_attr($style); ?>">
	
    <div class="form-group-inner">
		<?php
			$min_val = (!empty( $_GET[$name.'-from'] ) && $_GET[$name.'-from'] >= $min) ? $_GET[$name.'-from'] : $min;
			$max_val = (!empty( $_GET[$name.'-to'] ) && $_GET[$name.'-to'] <= $max) ? $_GET[$name.'-to'] : $max;
		?>
		<?php if ( $style == 'text' ) { ?>
			<div class="from-to-wrapper from-to-text-wrapper price">
				<div class="heading-filter-price">
					<div class="inner">
				  		<?php if ( !empty($field['placeholder']) ) { ?>
				    		<?php echo wp_kses_post($field['placeholder']); ?>
					    <?php } elseif ( !empty($field['name']) ) { ?>
					    	<?php echo wp_kses_post($field['name']); ?>
					    <?php } ?>
					    <?php
					    	if ( version_compare(WP_REALESTATE_PLUGIN_VERSION, '1.4.0', '>=') ) {
						    	$min_val_output = WP_RealEstate_Price::format_price($min_val, true, true);
						    	$max_val_output = WP_RealEstate_Price::format_price($max_val, true, true);
						    } else {
						    	$min_val_output = WP_RealEstate_Price::format_price($min_val, true);
						    	$max_val_output = WP_RealEstate_Price::format_price($max_val, true);
						    }
					    ?>
					    <span class="price-text">
					    	<span class="from-text"><?php echo trim($min_val_output); ?></span> - 
					    	<span class="to-text"><?php echo trim($max_val_output); ?></span>
					    </span>
				    </div>
			    </div>
			    <div class="price-input-wrapper">
			    	<div class="row row-padding-5">
			    		<div class="col-xs-6">
							<input type="number" name="<?php echo esc_attr($name.'-from'); ?>" class="form-control filter-from" value="<?php echo esc_attr($min_val); ?>" placeholder="<?php echo esc_attr(!empty($field['min_price_placeholder']) ? $field['min_price_placeholder'] : ''); ?>">
						</div>
						<div class="col-xs-6">
					  		<input type="number" name="<?php echo esc_attr($name.'-to'); ?>" class="form-control filter-to" value="<?php echo esc_attr($max_val); ?>" placeholder="<?php echo esc_attr(!empty($field['max_price_placeholder']) ? $field['max_price_placeholder'] : ''); ?>">
					  	</div>
			  		</div>
		  		</div>
			</div>
		<?php } elseif ( $style == 'list' ) {
			$count = 0;
			$range_size = isset($field['price_range_size']) ? $field['price_range_size'] : 1000;
			$price_range_max = isset($field['price_range_max']) ? $field['price_range_max'] : 10;
			$max_ranges = $price_range_max - 1;
		?>
			<div class="from-to-wrapper price-list">
				<div class="heading-filter-price">
					<div class="inner">
				  		<?php if ( !empty($field['placeholder']) ) { ?>
				    		<?php echo wp_kses_post($field['placeholder']); ?>
					    <?php } elseif ( !empty($field['name']) ) { ?>
					    	<?php echo wp_kses_post($field['name']); ?>
					    <?php } ?>

					    <?php
					    	if ( version_compare(WP_REALESTATE_PLUGIN_VERSION, '1.4.0', '>=') ) {
						    	$min_val_output = WP_RealEstate_Price::format_price($min_val, true, true);
						    	$max_val_output = WP_RealEstate_Price::format_price($max_val, true, true);
						    } else {
						    	$min_val_output = WP_RealEstate_Price::format_price($min_val, true);
						    	$max_val_output = WP_RealEstate_Price::format_price($max_val, true);
						    }
					    ?>

					    <span class="price-text">
					    	<span class="from-text"><?php echo trim($min_val_output); ?></span> - 
					    	<span class="to-text"><?php echo trim($max_val_output); ?></span>
					    </span>
				    </div>
			    </div>
			    <div class="price-input-wrapper">
					<ul class="price-filter">
						<li class="all-price"><?php esc_html_e( 'All Prices', 'homeo' ); ?></li>
						<?php
						for ( $range_min = 0; $range_min < ( $max + $range_size ); $range_min += $range_size ) {
							$range_max = $range_min + $range_size;
							
							if ( $min > $range_max || ( $max + $range_size ) < $range_max ) {
								continue;
							}
							
							$count++;
							if ( $count == $max_ranges ) {
								$class_active = '';
								if ( $range_min == $min_val && $range_max == $max ) {
									$class_active = 'active';
								}
								?>
								<li class="<?php echo esc_attr($class_active); ?>" data-min="<?php echo esc_attr($range_min); ?>" data-max="<?php echo esc_attr($max_val); ?>">
									<span class="price-text">
										<?php
									    	if ( version_compare(WP_REALESTATE_PLUGIN_VERSION, '1.4.0', '>=') ) {
										    	$min_val_output = WP_RealEstate_Price::format_price($range_min, true, true);
										    } else {
										    	$min_val_output = WP_RealEstate_Price::format_price($range_min, true);
										    }
									    ?>
			  							<?php echo trim($min_val_output).' + '; ?>
				  					</span>
								</li>
								<?php
								break;
							} else {
								$class_active = '';
								if ( $range_min == $min_val && $range_max == $max_val ) {
									$class_active = 'active';
								}
								?>
								<li class="<?php echo esc_attr($class_active); ?>" data-min="<?php echo esc_attr($range_min); ?>" data-max="<?php echo esc_attr($range_max); ?>">
									<?php
								    	if ( version_compare(WP_REALESTATE_PLUGIN_VERSION, '1.4.0', '>=') ) {
									    	$min_val_output = WP_RealEstate_Price::format_price($range_min, true, true);
									    	$max_val_output = WP_RealEstate_Price::format_price($range_max, true, true);
									    } else {
									    	$min_val_output = WP_RealEstate_Price::format_price($range_min, true);
									    	$max_val_output = WP_RealEstate_Price::format_price($range_max, true);
									    }
								    ?>
			  						<span class="price-text">
								    	<span class="from-text"><?php echo trim($min_val_output); ?></span> - 
								    	<span class="to-text"><?php echo trim($max_val_output); ?></span>
								    </span>
								</li>
								<?php
							}
						}
						?>
					</ul>
				</div>
				<input type="hidden" name="<?php echo esc_attr($name.'-from'); ?>" class="filter-from" value="<?php echo esc_attr($min_val); ?>">
				<input type="hidden" name="<?php echo esc_attr($name.'-to'); ?>" class="filter-to" value="<?php echo esc_attr($max_val); ?>">
			</div>
		<?php } else { ?>
	  		<div class="from-to-wrapper">
		  		<?php if ( !isset($field['show_title']) || $field['show_title'] ) { ?>
			    	<label for="<?php echo esc_attr($name); ?>" class="heading-label">
			    		<?php echo wp_kses_post($field['name']); ?>
			    	</label>
			    <?php } ?>
			    
			    <?php
			    	if ( version_compare(WP_REALESTATE_PLUGIN_VERSION, '1.4.0', '>=') ) {
				    	$min_val_output = WP_RealEstate_Price::format_price($min_val, true, true);
				    	$max_val_output = WP_RealEstate_Price::format_price($max_val, true, true);
				    } else {
				    	$min_val_output = WP_RealEstate_Price::format_price($min_val, true);
				    	$max_val_output = WP_RealEstate_Price::format_price($max_val, true);
				    }
			    ?>

				<span class="inner">
					<?php echo esc_html__('From','homeo'); ?>
					<span class="from-text"><?php echo trim($min_val_output); ?></span>
					<span class="space"><?php echo esc_html__('to','homeo'); ?></span>
					<span class="to-text"><?php echo trim($max_val_output); ?></span>
				</span>
			</div>
			<div class="price-range-slider" data-max="<?php echo esc_attr($max); ?>" data-min="<?php echo esc_attr($min); ?>"></div>
		  	<input type="hidden" name="<?php echo esc_attr($name.'-from'); ?>" class="filter-from" value="<?php echo esc_attr($min_val); ?>">
		  	<input type="hidden" name="<?php echo esc_attr($name.'-to'); ?>" class="filter-to" value="<?php echo esc_attr($max_val); ?>">
	  	<?php } ?>
  	</div>
</div><!-- /.form-group -->