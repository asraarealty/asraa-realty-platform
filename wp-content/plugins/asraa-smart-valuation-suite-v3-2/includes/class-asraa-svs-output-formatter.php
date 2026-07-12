<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Output_Formatter {

    private $symbol;
    private $unit;

    public function __construct( $currency = 'INR', $unit = 'sqft' ) {
        $this->unit = $unit;
        $symbols    = array( 'INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'AED ', 'SGD' => 'S$', 'AUD' => 'A$' );
        $this->symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
    }

    public function format_price( $amount ) {
        return $this->symbol . number_format( (float) $amount );
    }

    public function format_rate( $rate ) {
        return $this->symbol . number_format( (float) $rate ) . ' per ' . $this->unit;
    }

    public function build( $final_value, $base_rate, $confidence, $confidence_label, $valuation_source, $details = array() ) {
        if ( $confidence >= 90 )     $pct = 0.05;
        elseif ( $confidence >= 80 ) $pct = 0.07;
        elseif ( $confidence >= 65 ) $pct = 0.10;
        else                         $pct = 0.15;

        $low_price     = $final_value * ( 1 - $pct );
        $fair_price    = $final_value;
        $premium_price = $final_value * ( 1 + $pct );
        $quick_sale    = $final_value * ( 1 - $pct * 1.4 );

        $global_fallback = (float) get_option( 'asraa_svs_global_fallback_rate', 8000 );
        if ( $base_rate >= $global_fallback * 1.25 )     $demand = 'High';
        elseif ( $base_rate <= $global_fallback * 0.75 ) $demand = 'Low';
        else                                              $demand = 'Medium';

        $all_details = array_merge(
            $details,
            array(
                'Fair Value: '       . $this->format_price( $fair_price ),
                'Low Estimate: '     . $this->format_price( $low_price ),
                'Premium Estimate: ' . $this->format_price( $premium_price ),
                'Quick Sale: '       . $this->format_price( $quick_sale ),
                'Rate: '             . $this->format_rate( $base_rate ),
                'Confidence: '       . $confidence . '% (' . $confidence_label . ')',
                'Market Demand: '    . $demand,
                'Valuation Method: ' . $valuation_source,
            )
        );

        $html = '<ul>';
        foreach ( $all_details as $line ) {
            $html .= '<li>' . esc_html( $line ) . '</li>';
        }
        $html .= '</ul>';

        return array(
            'total_price'      => $final_value,
            'base_rate'        => $base_rate,
            'low_price'        => round( $low_price ),
            'fair_price'       => round( $fair_price ),
            'premium_price'    => round( $premium_price ),
            'quick_sale_price' => round( $quick_sale ),
            'message_main'     => $this->format_price( $fair_price ),
            'message_sub'      => 'Rate: ' . $this->format_rate( $base_rate ) . ' · Source: ' . $valuation_source,
            'range_label'      => $this->format_price( $low_price ) . ' – ' . $this->format_price( $premium_price ),
            'range_text'       => 'Estimated value range: ' . $this->format_price( $low_price ) . ' – ' . $this->format_price( $premium_price ),
            'confidence'       => $confidence,
            'confidence_label' => $confidence_label,
            'demand'           => $demand,
            'valuation_source' => $valuation_source,
            'details_html'     => $html,
        );
    }
}
