<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Confidence_Engine {

    public function score( $matched_level, $match_score, $comp_count, $valuation_source ) {
        $base = 50;

        $level_bonus = array( 'building' => 30, 'area' => 20, 'city' => 10, 'fallback' => 0 );
        $base += isset( $level_bonus[ $matched_level ] ) ? $level_bonus[ $matched_level ] : 0;
        $base += (int) round( $match_score * 15 );

        if ( $comp_count >= 5 )     $base += 15;
        elseif ( $comp_count >= 3 ) $base += 8;
        elseif ( $comp_count > 0 )  $base += 3;

        if ( 'hybrid' === $valuation_source ) { $base += 5; }

        $s = max( 40, min( 97, $base ) );

        if ( $s >= 90 )     $label = 'Very High';
        elseif ( $s >= 80 ) $label = 'High';
        elseif ( $s >= 65 ) $label = 'Medium';
        else                $label = 'Low';

        if ( $comp_count >= 5 )     $cl = 'high';
        elseif ( $comp_count >= 3 ) $cl = 'medium';
        else                        $cl = 'low';

        return array( 'score' => $s, 'label' => $label, 'comp_confidence' => $cl );
    }
}
