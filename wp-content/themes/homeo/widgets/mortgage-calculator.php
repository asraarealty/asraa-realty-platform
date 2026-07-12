<?php
extract( $args );
extract( $instance );

$title = apply_filters('widget_title', $instance['title']);

if ( $title ) {
    echo trim($before_title)  . trim( $title ) . $after_title;
}

if ( function_exists('wp_realestate_get_option') ) {
	if ( method_exists('WP_RealEstate_Price', 'get_current_currency') ) {
		$current_currency = WP_RealEstate_Price::get_current_currency();
		$multi_currencies = WP_RealEstate_Price::get_currencies_settings();

		if ( !empty($multi_currencies) && !empty($multi_currencies[$current_currency]) ) {
			$currency_args = $multi_currencies[$current_currency];
		}

		if ( !empty($currency_args) ) {
			$currency_symbol = !empty($currency_args['custom_symbol']) ? $currency_args['custom_symbol'] : '';
			if ( empty($currency_symbol) ) {
				$currency = !empty($currency_args['currency']) ? $currency_args['currency'] : 'USD';
				$currency_symbol = WP_RealEstate_Price::currency_symbol($currency);
			}
		}
	} else {
		$currency_symbol = wp_realestate_get_option('currency_symbol');
	}
}
if ( empty($currency_symbol) ) {
	$currency_symbol = '$';
}

?>

<div class="apus-mortgage-calculator">
	<div class="form-group">
		<input class="form-control" id="apus_mortgage_property_price" type="text" placeholder="<?php esc_html_e('Price', 'homeo'); ?>">
		<span class="unit"><?php echo esc_attr($currency_symbol); ?></span>
	</div>
	<div class="form-group">
		<input class="form-control" id="apus_mortgage_deposit" type="text" placeholder="<?php esc_html_e('Deposit', 'homeo'); ?>">
		<span class="unit"><?php echo esc_attr($currency_symbol); ?></span>
	</div>
	<div class="form-group">
		<input class="form-control" id="apus_mortgage_interest_rate" type="text" placeholder="<?php esc_html_e('Rate', 'homeo'); ?>">
		<span class="unit"><?php esc_html_e('%', 'homeo'); ?></span>
	</div>
	<div class="form-group">
		<input class="form-control" id="apus_mortgage_term_years" type="text" placeholder="<?php esc_html_e('Loan Term', 'homeo'); ?>">
		<span class="unit"><?php esc_html_e('Year', 'homeo'); ?></span>
	</div>
	<button id="btn_mortgage_get_results" class="btn btn-theme-second btn-block"><?php esc_html_e('Calculate', 'homeo'); ?></button>
	<div class="apus_mortgage_results"></div>
</div>