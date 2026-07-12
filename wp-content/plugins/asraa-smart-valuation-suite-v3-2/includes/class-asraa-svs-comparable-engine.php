<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Comparable_Engine {

    public function calculate( $comps, $subject_area ) {
        $rates = array();
        foreach ( $comps as $c ) {
            $price = (float) ( $c['price'] ?? 0 );
            $area  = (float) ( $c['area']  ?? 0 );
            if ( $price > 0 && $area > 0 ) {
                $rates[] = $price / $area;
            }
        }
        if ( empty( $rates ) ) { return null; }
        $comparable_rate  = array_sum( $rates ) / count( $rates );
        $comparable_value = $comparable_rate * (float) $subject_area;
        return array(
            'comparable_rate'  => round( $comparable_rate, 2 ),
            'comparable_value' => round( $comparable_value, 2 ),
            'comp_count'       => count( $rates ),
        );
    }

    public function yield_value( $monthly_rent, $yield_pct ) {
        if ( $yield_pct <= 0 ) { return 0; }
        return ( $monthly_rent * 12 ) / ( $yield_pct / 100 );
    }
}
