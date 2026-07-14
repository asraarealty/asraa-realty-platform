<?php
/**
 * Price
 *
 * @package    wp-realestate
 * @author     Habq 
 * @license    GNU General Public License, version 3
 */

if ( ! defined( 'ABSPATH' ) ) {
  	exit;
}

class WP_RealEstate_Price {

	public static function init() {
	    add_action( 'init', array( __CLASS__, 'process_currency' ) );
	}
	/**
	 * Formats price
	 *
	 * @access public
	 * @param $price
	 * @return bool|string
	 */
	public static function format_price( $price, $show_null = false, $without_rate_exchange = false ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			if ( !$show_null ) {
				return false;
			}
			$price = 0;
		}
		$decimals = false;
		$money_decimals = wp_realestate_get_option('money_decimals');

		if ( wp_realestate_get_option('enable_multi_currencies') === 'yes' ) {
			$current_currency = self::get_current_currency();
			$multi_currencies = self::get_currencies_settings();

			if ( !empty($multi_currencies) && !empty($multi_currencies[$current_currency]) ) {
				$currency_args = $multi_currencies[$current_currency];
			}

			if ( !empty($currency_args) ) {
				if ( !empty($currency_args['custom_symbol']) ) {
					$symbol = $currency_args['custom_symbol'];
				} else {
					$currency = $currency_args['currency'];
					$symbol = WP_RealEstate_Price::currency_symbol($currency);
				}
				$currency_position = !empty($currency_args['currency_position']) ? $currency_args['currency_position'] : 'before';
				$rate_exchange_fee = !empty($currency_args['rate_exchange_fee']) ? $currency_args['rate_exchange_fee'] : 1;
				$decimals = true;
				$money_decimals = !empty($currency_args['money_decimals']) ? $currency_args['money_decimals'] : 1;
				if ( !$without_rate_exchange ) {
					$price = $price*$rate_exchange_fee;
				}
			} else {
				$symbol = wp_realestate_get_option('custom_symbol', '');
				if ( empty($symbol) ) {
					$currency = wp_realestate_get_option('currency', 'USD');
					$symbol = WP_RealEstate_Price::currency_symbol($currency);
				}
				$currency_position = wp_realestate_get_option('currency_position', 'before');
			}
		} else {
			$symbol = wp_realestate_get_option('custom_symbol', '');
			if ( empty($symbol) ) {
				$currency = wp_realestate_get_option('currency', 'USD');
				$symbol = WP_RealEstate_Price::currency_symbol($currency);
			}
			$currency_position = wp_realestate_get_option('currency_position', 'before');
		}

		$currency_symbol = ! empty( $symbol ) ? '<span class="suffix">'.$symbol.'</span>' : '<span class="suffix">$</span>';

		if ( wp_realestate_get_option('enable_shorten_long_number', 'no') === 'yes' ) {
			$price = self::number_shorten( $price, $decimals, $money_decimals );
		} else {
			$price = WP_RealEstate_Mixes::format_number( $price, $decimals, $money_decimals );
		}

		if ( ! empty( $currency_symbol ) ) {
			switch ($currency_position) {
				case 'before':
					$price = $currency_symbol . '<span class="price-text">'.$price.'</span>';
					break;
				case 'after':
					$price = '<span class="price-text">'.$price.'</span>' . $currency_symbol;
					break;
				case 'before_space':
					$price = $currency_symbol . ' <span class="price-text">'.$price.'</span>';
					break;
				case 'after_space':
					$price = '<span class="price-text">'.$price.'</span> ' . $currency_symbol;
					break;
			}
		}

		return $price;
	}

	public static function convert_price_exchange( $price ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			$price = 0;
		}
		if ( wp_realestate_get_option('enable_multi_currencies') === 'yes' ) {
			$current_currency = self::get_current_currency();
			$multi_currencies = self::get_currencies_settings();

			if ( !empty($multi_currencies) && !empty($multi_currencies[$current_currency]) ) {
				$currency_args = $multi_currencies[$current_currency];
			}

			if ( !empty($currency_args) ) {
				$rate_exchange_fee = !empty($currency_args['rate_exchange_fee']) ? $currency_args['rate_exchange_fee'] : 1;
				$price = $price*$rate_exchange_fee;
			}
		}

		return $price;
	}

	public static function convert_current_currency_to_default( $price ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			$price = 0;
		}
		if ( wp_realestate_get_option('enable_multi_currencies') === 'yes' ) {
			$current_currency = self::get_current_currency();
			$multi_currencies = self::get_currencies_settings();

			if ( !empty($multi_currencies) && !empty($multi_currencies[$current_currency]) ) {
				$currency_args = $multi_currencies[$current_currency];
			}

			if ( !empty($currency_args) ) {
				$rate_exchange_fee = !empty($currency_args['rate_exchange_fee']) ? $currency_args['rate_exchange_fee'] : 1;

				$price = $price*(1/$rate_exchange_fee);
			}
		}

		return $price;
	}

	public static function get_current_currency() {
		if ( wp_realestate_get_option('enable_multi_currencies') === 'yes' ) {
			$current_currency = !empty($_COOKIE['wp_realestate_currency']) ? $_COOKIE['wp_realestate_currency'] : wp_realestate_get_option('currency', 'USD');
		} else {
			$current_currency = wp_realestate_get_option('currency', 'USD');
		}
		return $current_currency;
	}
	/**
	 * Get full list of currency codes.
	 *
	 * Currency symbols and names should follow the Unicode CLDR recommendation (http://cldr.unicode.org/translation/currency-names)
	 *
	 * @return array
	 */
	public static function get_currencies() {
		$currencies = array_unique(
			apply_filters(
				'wp-realestate-currencies',
				array(
					'AED' => __( 'United Arab Emirates dirham', 'wp-realestate' ),
					'AFN' => __( 'Afghan afghani', 'wp-realestate' ),
					'ALL' => __( 'Albanian lek', 'wp-realestate' ),
					'AMD' => __( 'Armenian dram', 'wp-realestate' ),
					'ANG' => __( 'Netherlands Antillean guilder', 'wp-realestate' ),
					'AOA' => __( 'Angolan kwanza', 'wp-realestate' ),
					'ARS' => __( 'Argentine peso', 'wp-realestate' ),
					'AUD' => __( 'Australian dollar', 'wp-realestate' ),
					'AWG' => __( 'Aruban florin', 'wp-realestate' ),
					'AZN' => __( 'Azerbaijani manat', 'wp-realestate' ),
					'BAM' => __( 'Bosnia and Herzegovina convertible mark', 'wp-realestate' ),
					'BBD' => __( 'Barbadian dollar', 'wp-realestate' ),
					'BDT' => __( 'Bangladeshi taka', 'wp-realestate' ),
					'BGN' => __( 'Bulgarian lev', 'wp-realestate' ),
					'BHD' => __( 'Bahraini dinar', 'wp-realestate' ),
					'BIF' => __( 'Burundian franc', 'wp-realestate' ),
					'BMD' => __( 'Bermudian dollar', 'wp-realestate' ),
					'BND' => __( 'Brunei dollar', 'wp-realestate' ),
					'BOB' => __( 'Bolivian boliviano', 'wp-realestate' ),
					'BRL' => __( 'Brazilian real', 'wp-realestate' ),
					'BSD' => __( 'Bahamian dollar', 'wp-realestate' ),
					'BTC' => __( 'Bitcoin', 'wp-realestate' ),
					'BTN' => __( 'Bhutanese ngultrum', 'wp-realestate' ),
					'BWP' => __( 'Botswana pula', 'wp-realestate' ),
					'BYR' => __( 'Belarusian ruble (old)', 'wp-realestate' ),
					'BYN' => __( 'Belarusian ruble', 'wp-realestate' ),
					'BZD' => __( 'Belize dollar', 'wp-realestate' ),
					'CAD' => __( 'Canadian dollar', 'wp-realestate' ),
					'CDF' => __( 'Congolese franc', 'wp-realestate' ),
					'CHF' => __( 'Swiss franc', 'wp-realestate' ),
					'CLP' => __( 'Chilean peso', 'wp-realestate' ),
					'CNY' => __( 'Chinese yuan', 'wp-realestate' ),
					'COP' => __( 'Colombian peso', 'wp-realestate' ),
					'CRC' => __( 'Costa Rican col&oacute;n', 'wp-realestate' ),
					'CUC' => __( 'Cuban convertible peso', 'wp-realestate' ),
					'CUP' => __( 'Cuban peso', 'wp-realestate' ),
					'CVE' => __( 'Cape Verdean escudo', 'wp-realestate' ),
					'CZK' => __( 'Czech koruna', 'wp-realestate' ),
					'DJF' => __( 'Djiboutian franc', 'wp-realestate' ),
					'DKK' => __( 'Danish krone', 'wp-realestate' ),
					'DOP' => __( 'Dominican peso', 'wp-realestate' ),
					'DZD' => __( 'Algerian dinar', 'wp-realestate' ),
					'EGP' => __( 'Egyptian pound', 'wp-realestate' ),
					'ERN' => __( 'Eritrean nakfa', 'wp-realestate' ),
					'ETB' => __( 'Ethiopian birr', 'wp-realestate' ),
					'EUR' => __( 'Euro', 'wp-realestate' ),
					'FJD' => __( 'Fijian dollar', 'wp-realestate' ),
					'FKP' => __( 'Falkland Islands pound', 'wp-realestate' ),
					'GBP' => __( 'Pound sterling', 'wp-realestate' ),
					'GEL' => __( 'Georgian lari', 'wp-realestate' ),
					'GGP' => __( 'Guernsey pound', 'wp-realestate' ),
					'GHS' => __( 'Ghana cedi', 'wp-realestate' ),
					'GIP' => __( 'Gibraltar pound', 'wp-realestate' ),
					'GMD' => __( 'Gambian dalasi', 'wp-realestate' ),
					'GNF' => __( 'Guinean franc', 'wp-realestate' ),
					'GTQ' => __( 'Guatemalan quetzal', 'wp-realestate' ),
					'GYD' => __( 'Guyanese dollar', 'wp-realestate' ),
					'HKD' => __( 'Hong Kong dollar', 'wp-realestate' ),
					'HNL' => __( 'Honduran lempira', 'wp-realestate' ),
					'HRK' => __( 'Croatian kuna', 'wp-realestate' ),
					'HTG' => __( 'Haitian gourde', 'wp-realestate' ),
					'HUF' => __( 'Hungarian forint', 'wp-realestate' ),
					'IDR' => __( 'Indonesian rupiah', 'wp-realestate' ),
					'ILS' => __( 'Israeli new shekel', 'wp-realestate' ),
					'IMP' => __( 'Manx pound', 'wp-realestate' ),
					'INR' => __( 'Indian rupee', 'wp-realestate' ),
					'IQD' => __( 'Iraqi dinar', 'wp-realestate' ),
					'IRR' => __( 'Iranian rial', 'wp-realestate' ),
					'IRT' => __( 'Iranian toman', 'wp-realestate' ),
					'ISK' => __( 'Icelandic kr&oacute;na', 'wp-realestate' ),
					'JEP' => __( 'Jersey pound', 'wp-realestate' ),
					'JMD' => __( 'Jamaican dollar', 'wp-realestate' ),
					'JOD' => __( 'Jordanian dinar', 'wp-realestate' ),
					'JPY' => __( 'Japanese yen', 'wp-realestate' ),
					'KES' => __( 'Kenyan shilling', 'wp-realestate' ),
					'KGS' => __( 'Kyrgyzstani som', 'wp-realestate' ),
					'KHR' => __( 'Cambodian riel', 'wp-realestate' ),
					'KMF' => __( 'Comorian franc', 'wp-realestate' ),
					'KPW' => __( 'North Korean won', 'wp-realestate' ),
					'KRW' => __( 'South Korean won', 'wp-realestate' ),
					'KWD' => __( 'Kuwaiti dinar', 'wp-realestate' ),
					'KYD' => __( 'Cayman Islands dollar', 'wp-realestate' ),
					'KZT' => __( 'Kazakhstani tenge', 'wp-realestate' ),
					'LAK' => __( 'Lao kip', 'wp-realestate' ),
					'LBP' => __( 'Lebanese pound', 'wp-realestate' ),
					'LKR' => __( 'Sri Lankan rupee', 'wp-realestate' ),
					'LRD' => __( 'Liberian dollar', 'wp-realestate' ),
					'LSL' => __( 'Lesotho loti', 'wp-realestate' ),
					'LYD' => __( 'Libyan dinar', 'wp-realestate' ),
					'MAD' => __( 'Moroccan dirham', 'wp-realestate' ),
					'MDL' => __( 'Moldovan leu', 'wp-realestate' ),
					'MGA' => __( 'Malagasy ariary', 'wp-realestate' ),
					'MKD' => __( 'Macedonian denar', 'wp-realestate' ),
					'MMK' => __( 'Burmese kyat', 'wp-realestate' ),
					'MNT' => __( 'Mongolian t&ouml;gr&ouml;g', 'wp-realestate' ),
					'MOP' => __( 'Macanese pataca', 'wp-realestate' ),
					'MRU' => __( 'Mauritanian ouguiya', 'wp-realestate' ),
					'MUR' => __( 'Mauritian rupee', 'wp-realestate' ),
					'MVR' => __( 'Maldivian rufiyaa', 'wp-realestate' ),
					'MWK' => __( 'Malawian kwacha', 'wp-realestate' ),
					'MXN' => __( 'Mexican peso', 'wp-realestate' ),
					'MYR' => __( 'Malaysian ringgit', 'wp-realestate' ),
					'MZN' => __( 'Mozambican metical', 'wp-realestate' ),
					'NAD' => __( 'Namibian dollar', 'wp-realestate' ),
					'NGN' => __( 'Nigerian naira', 'wp-realestate' ),
					'NIO' => __( 'Nicaraguan c&oacute;rdoba', 'wp-realestate' ),
					'NOK' => __( 'Norwegian krone', 'wp-realestate' ),
					'NPR' => __( 'Nepalese rupee', 'wp-realestate' ),
					'NZD' => __( 'New Zealand dollar', 'wp-realestate' ),
					'OMR' => __( 'Omani rial', 'wp-realestate' ),
					'PAB' => __( 'Panamanian balboa', 'wp-realestate' ),
					'PEN' => __( 'Sol', 'wp-realestate' ),
					'PGK' => __( 'Papua New Guinean kina', 'wp-realestate' ),
					'PHP' => __( 'Philippine peso', 'wp-realestate' ),
					'PKR' => __( 'Pakistani rupee', 'wp-realestate' ),
					'PLN' => __( 'Polish z&#x142;oty', 'wp-realestate' ),
					'PRB' => __( 'Transnistrian ruble', 'wp-realestate' ),
					'PYG' => __( 'Paraguayan guaran&iacute;', 'wp-realestate' ),
					'QAR' => __( 'Qatari riyal', 'wp-realestate' ),
					'RON' => __( 'Romanian leu', 'wp-realestate' ),
					'RSD' => __( 'Serbian dinar', 'wp-realestate' ),
					'RUB' => __( 'Russian ruble', 'wp-realestate' ),
					'RWF' => __( 'Rwandan franc', 'wp-realestate' ),
					'SAR' => __( 'Saudi riyal', 'wp-realestate' ),
					'SBD' => __( 'Solomon Islands dollar', 'wp-realestate' ),
					'SCR' => __( 'Seychellois rupee', 'wp-realestate' ),
					'SDG' => __( 'Sudanese pound', 'wp-realestate' ),
					'SEK' => __( 'Swedish krona', 'wp-realestate' ),
					'SGD' => __( 'Singapore dollar', 'wp-realestate' ),
					'SHP' => __( 'Saint Helena pound', 'wp-realestate' ),
					'SLL' => __( 'Sierra Leonean leone', 'wp-realestate' ),
					'SOS' => __( 'Somali shilling', 'wp-realestate' ),
					'SRD' => __( 'Surinamese dollar', 'wp-realestate' ),
					'SSP' => __( 'South Sudanese pound', 'wp-realestate' ),
					'STN' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra', 'wp-realestate' ),
					'SYP' => __( 'Syrian pound', 'wp-realestate' ),
					'SZL' => __( 'Swazi lilangeni', 'wp-realestate' ),
					'THB' => __( 'Thai baht', 'wp-realestate' ),
					'TJS' => __( 'Tajikistani somoni', 'wp-realestate' ),
					'TMT' => __( 'Turkmenistan manat', 'wp-realestate' ),
					'TND' => __( 'Tunisian dinar', 'wp-realestate' ),
					'TOP' => __( 'Tongan pa&#x2bb;anga', 'wp-realestate' ),
					'TRY' => __( 'Turkish lira', 'wp-realestate' ),
					'TTD' => __( 'Trinidad and Tobago dollar', 'wp-realestate' ),
					'TWD' => __( 'New Taiwan dollar', 'wp-realestate' ),
					'TZS' => __( 'Tanzanian shilling', 'wp-realestate' ),
					'UAH' => __( 'Ukrainian hryvnia', 'wp-realestate' ),
					'UGX' => __( 'Ugandan shilling', 'wp-realestate' ),
					'USD' => __( 'United States (US) dollar', 'wp-realestate' ),
					'UYU' => __( 'Uruguayan peso', 'wp-realestate' ),
					'UZS' => __( 'Uzbekistani som', 'wp-realestate' ),
					'VEF' => __( 'Venezuelan bol&iacute;var', 'wp-realestate' ),
					'VES' => __( 'Bol&iacute;var soberano', 'wp-realestate' ),
					'VND' => __( 'Vietnamese &#x111;&#x1ed3;ng', 'wp-realestate' ),
					'VUV' => __( 'Vanuatu vatu', 'wp-realestate' ),
					'WST' => __( 'Samoan t&#x101;l&#x101;', 'wp-realestate' ),
					'XAF' => __( 'Central African CFA franc', 'wp-realestate' ),
					'XCD' => __( 'East Caribbean dollar', 'wp-realestate' ),
					'XOF' => __( 'West African CFA franc', 'wp-realestate' ),
					'XPF' => __( 'CFP franc', 'wp-realestate' ),
					'YER' => __( 'Yemeni rial', 'wp-realestate' ),
					'ZAR' => __( 'South African rand', 'wp-realestate' ),
					'ZMW' => __( 'Zambian kwacha', 'wp-realestate' ),
				)
			)
		);

		return $currencies;
	}

	/**
	 * Get all available Currency symbols.
	 *
	 * Currency symbols and names should follow the Unicode CLDR recommendation (http://cldr.unicode.org/translation/currency-names)
	 *
	 * @since 4.1.0
	 * @return array
	 */
	public static function get_currency_symbols() {

		$symbols = apply_filters(
			'wp-realestate-currency-symbols',
			array(
				'AED' => '&#x62f;.&#x625;',
				'AFN' => '&#x60b;',
				'ALL' => 'L',
				'AMD' => 'AMD',
				'ANG' => '&fnof;',
				'AOA' => 'Kz',
				'ARS' => '&#36;',
				'AUD' => '&#36;',
				'AWG' => 'Afl.',
				'AZN' => 'AZN',
				'BAM' => 'KM',
				'BBD' => '&#36;',
				'BDT' => '&#2547;&nbsp;',
				'BGN' => '&#1083;&#1074;.',
				'BHD' => '.&#x62f;.&#x628;',
				'BIF' => 'Fr',
				'BMD' => '&#36;',
				'BND' => '&#36;',
				'BOB' => 'Bs.',
				'BRL' => '&#82;&#36;',
				'BSD' => '&#36;',
				'BTC' => '&#8383;',
				'BTN' => 'Nu.',
				'BWP' => 'P',
				'BYR' => 'Br',
				'BYN' => 'Br',
				'BZD' => '&#36;',
				'CAD' => '&#36;',
				'CDF' => 'Fr',
				'CHF' => '&#67;&#72;&#70;',
				'CLP' => '&#36;',
				'CNY' => '&yen;',
				'COP' => '&#36;',
				'CRC' => '&#x20a1;',
				'CUC' => '&#36;',
				'CUP' => '&#36;',
				'CVE' => '&#36;',
				'CZK' => '&#75;&#269;',
				'DJF' => 'Fr',
				'DKK' => 'DKK',
				'DOP' => 'RD&#36;',
				'DZD' => '&#x62f;.&#x62c;',
				'EGP' => 'EGP',
				'ERN' => 'Nfk',
				'ETB' => 'Br',
				'EUR' => '&euro;',
				'FJD' => '&#36;',
				'FKP' => '&pound;',
				'GBP' => '&pound;',
				'GEL' => '&#x20be;',
				'GGP' => '&pound;',
				'GHS' => '&#x20b5;',
				'GIP' => '&pound;',
				'GMD' => 'D',
				'GNF' => 'Fr',
				'GTQ' => 'Q',
				'GYD' => '&#36;',
				'HKD' => '&#36;',
				'HNL' => 'L',
				'HRK' => 'kn',
				'HTG' => 'G',
				'HUF' => '&#70;&#116;',
				'IDR' => 'Rp',
				'ILS' => '&#8362;',
				'IMP' => '&pound;',
				'INR' => '&#8377;',
				'IQD' => '&#x639;.&#x62f;',
				'IRR' => '&#xfdfc;',
				'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
				'ISK' => 'kr.',
				'JEP' => '&pound;',
				'JMD' => '&#36;',
				'JOD' => '&#x62f;.&#x627;',
				'JPY' => '&yen;',
				'KES' => 'KSh',
				'KGS' => '&#x441;&#x43e;&#x43c;',
				'KHR' => '&#x17db;',
				'KMF' => 'Fr',
				'KPW' => '&#x20a9;',
				'KRW' => '&#8361;',
				'KWD' => '&#x62f;.&#x643;',
				'KYD' => '&#36;',
				'KZT' => '&#8376;',
				'LAK' => '&#8365;',
				'LBP' => '&#x644;.&#x644;',
				'LKR' => '&#xdbb;&#xdd4;',
				'LRD' => '&#36;',
				'LSL' => 'L',
				'LYD' => '&#x644;.&#x62f;',
				'MAD' => '&#x62f;.&#x645;.',
				'MDL' => 'MDL',
				'MGA' => 'Ar',
				'MKD' => '&#x434;&#x435;&#x43d;',
				'MMK' => 'Ks',
				'MNT' => '&#x20ae;',
				'MOP' => 'P',
				'MRU' => 'UM',
				'MUR' => '&#x20a8;',
				'MVR' => '.&#x783;',
				'MWK' => 'MK',
				'MXN' => '&#36;',
				'MYR' => '&#82;&#77;',
				'MZN' => 'MT',
				'NAD' => 'N&#36;',
				'NGN' => '&#8358;',
				'NIO' => 'C&#36;',
				'NOK' => '&#107;&#114;',
				'NPR' => '&#8360;',
				'NZD' => '&#36;',
				'OMR' => '&#x631;.&#x639;.',
				'PAB' => 'B/.',
				'PEN' => 'S/',
				'PGK' => 'K',
				'PHP' => '&#8369;',
				'PKR' => '&#8360;',
				'PLN' => '&#122;&#322;',
				'PRB' => '&#x440;.',
				'PYG' => '&#8370;',
				'QAR' => '&#x631;.&#x642;',
				'RMB' => '&yen;',
				'RON' => 'lei',
				'RSD' => '&#1088;&#1089;&#1076;',
				'RUB' => '&#8381;',
				'RWF' => 'Fr',
				'SAR' => '&#x631;.&#x633;',
				'SBD' => '&#36;',
				'SCR' => '&#x20a8;',
				'SDG' => '&#x62c;.&#x633;.',
				'SEK' => '&#107;&#114;',
				'SGD' => '&#36;',
				'SHP' => '&pound;',
				'SLL' => 'Le',
				'SOS' => 'Sh',
				'SRD' => '&#36;',
				'SSP' => '&pound;',
				'STN' => 'Db',
				'SYP' => '&#x644;.&#x633;',
				'SZL' => 'L',
				'THB' => '&#3647;',
				'TJS' => '&#x405;&#x41c;',
				'TMT' => 'm',
				'TND' => '&#x62f;.&#x62a;',
				'TOP' => 'T&#36;',
				'TRY' => '&#8378;',
				'TTD' => '&#36;',
				'TWD' => '&#78;&#84;&#36;',
				'TZS' => 'Sh',
				'UAH' => '&#8372;',
				'UGX' => 'UGX',
				'USD' => '&#36;',
				'UYU' => '&#36;',
				'UZS' => 'UZS',
				'VEF' => 'Bs F',
				'VES' => 'Bs.S',
				'VND' => '&#8363;',
				'VUV' => 'Vt',
				'WST' => 'T',
				'XAF' => 'CFA',
				'XCD' => '&#36;',
				'XOF' => 'CFA',
				'XPF' => 'Fr',
				'YER' => '&#xfdfc;',
				'ZAR' => '&#82;',
				'ZMW' => 'ZK',
			)
		);

		return $symbols;
	}

	/**
	 * Get Currency symbol.
	 *
	 * Currency symbols and names should follow the Unicode CLDR recommendation (http://cldr.unicode.org/translation/currency-names)
	 *
	 * @param string $currency Currency. (default: '').
	 * @return string
	 */
	public static function currency_symbol( $currency = '' ) {
		
		$symbols = self::get_currency_symbols();

		$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';

		return apply_filters( 'wp-realestate-currency-symbol', $currency_symbol, $currency );
	}

	public static function get_currencies_settings() {
		$currency = wp_realestate_get_option('currency', 'USD');
		$return = array(
			$currency => array(
				'currency' => $currency,
				'currency_position' => wp_realestate_get_option('currency_position', 'before'),
				'money_decimals' => wp_realestate_get_option('money_decimals', ''),
				'rate_exchange_fee' => 1,
				'custom_symbol' => wp_realestate_get_option('custom_symbol', ''),
			)
		);
		$multi_currencies = wp_realestate_get_option('multi_currencies');
		if ( !empty($multi_currencies) ) {
			foreach ($multi_currencies as $multi_currency) {
				if ( !empty($multi_currency['currency']) && $multi_currency['currency'] != $currency) {
					$return[$multi_currency['currency']] = $multi_currency;
				}
			}
		}

		return $return;
	}

	public static function number_shorten($number, $decimals = false, $money_decimals = 0 ) {

		if ( empty( $number ) || ! is_numeric( $number ) ) {
			return 0;
		}

        $divisors = self::get_shorten_divisors();

	    // Loop through each $divisor and find the
	    // lowest amount that matches
	    foreach ($divisors as $key => $value) {
	        if (abs($number) < ($value['divisor'] * 1000)) {
	            $number = $number / $value['divisor'];
	            return WP_RealEstate_Mixes::format_number($number, $decimals, $money_decimals) . $value['key'];
	            break;
	        }
	    }

	    return WP_RealEstate_Mixes::format_number($number, $decimals, $money_decimals);
	}

	public static function get_shorten_divisors() {

        $divisors = array(
            '' => [
	            	'divisor' => pow(1000, 0),
	            	'key' => ''
	            ], // 1000^0 == 1
        );

        $shorten = wp_realestate_get_option('shorten_thousand');
        if ( !empty($shorten['enable']) && $shorten['enable'] == 'on' ) {
        	$key = __('K', 'wp-realestate');
        	if ( !empty($shorten['key']) ) {
        		$key = $shorten['key'];
        	}
        	$divisors['thousand'] = [
            	'divisor' => pow(1000, 1),
            	'key' => $key
            ];
        }

        $shorten = wp_realestate_get_option('shorten_million');
        if ( !empty($shorten['enable']) && $shorten['enable'] == 'on' ) {
        	$key = __('M', 'wp-realestate');
        	if ( !empty($shorten['key']) ) {
        		$key = $shorten['key'];
        	}
        	$divisors['million'] = [
            	'divisor' => pow(1000, 2),
            	'key' => $key
            ];
        }

        $shorten = wp_realestate_get_option('shorten_billion');
        if ( !empty($shorten['enable']) && $shorten['enable'] == 'on' ) {
        	$key = __('B', 'wp-realestate');
        	if ( !empty($shorten['key']) ) {
        		$key = $shorten['key'];
        	}
        	$divisors['billion'] = [
            	'divisor' => pow(1000, 3),
            	'key' => $key
            ];
        }

        $shorten = wp_realestate_get_option('shorten_trillion');
        if ( !empty($shorten['enable']) && $shorten['enable'] == 'on' ) {
        	$key = __('T', 'wp-realestate');
        	if ( !empty($shorten['key']) ) {
        		$key = $shorten['key'];
        	}
        	$divisors['trillion'] = [
            	'divisor' => pow(1000, 4),
            	'key' => $key
            ];
        }

        $shorten = wp_realestate_get_option('shorten_quadrillion');
        if ( !empty($shorten['enable']) && $shorten['enable'] == 'on' ) {
        	$key = __('Qa', 'wp-realestate');
        	if ( !empty($shorten['key']) ) {
        		$key = $shorten['key'];
        	}
        	$divisors['quadrillion'] = [
            	'divisor' => pow(1000, 5),
            	'key' => $key
            ];
        }

        $shorten = wp_realestate_get_option('shorten_quintillion');
        if ( !empty($shorten['enable']) && $shorten['enable'] == 'on' ) {
        	$key = __('Qi', 'wp-realestate');
        	if ( !empty($shorten['key']) ) {
        		$key = $shorten['key'];
        	}
        	$divisors['quintillion'] = [
            	'divisor' => pow(1000, 6),
            	'key' => $key
            ];
        }

	    return apply_filters('wp_realestate_get_shorten_divisors', $divisors);
	}

	public static function process_currency() {
		if ( !empty($_GET['currency']) && wp_realestate_get_option('enable_multi_currencies') === 'yes' ) {
			setcookie('wp_realestate_currency', sanitize_text_field($_GET['currency']), time() + (86400 * 30), "/" );
			$_COOKIE['wp_realestate_currency'] = sanitize_text_field($_GET['currency']);
		}		
	}
}

WP_RealEstate_Price::init();