<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Adjustment_Engine {

    public function floor_adjustment( $floor_num ) {
        if ( null === $floor_num ) { return 0; }
        $f = (int) $floor_num;
        if ( -1 === $f )           return 0.06;
        if ( 0 === $f )            return -0.05;
        if ( $f >= 1 && $f <= 3 ) return 0.00;
        if ( $f >= 4 && $f <= 10 ) return 0.02;
        return 0.04;
    }

    public function age_adjustment( $age_years ) {
        if ( null === $age_years ) { return 0; }
        $a = (int) $age_years;
        if ( $a <= 5 )  return 0.05;
        if ( $a <= 10 ) return 0.00;
        if ( $a <= 20 ) return -0.05;
        return -0.10;
    }

    public function size_adjustment( $area_sqft ) {
        $a = (float) $area_sqft;
        if ( $a <= 0 )   return 0;
        if ( $a < 400 )  return 0.05;
        if ( $a < 700 )  return 0.00;
        if ( $a < 1000 ) return -0.03;
        return -0.06;
    }

    public function property_type_multiplier( $type ) {
        $map = array(
            'residential' => 1.00,
            'office'      => 1.15,
            'shop'        => 1.35,
            'ground_shop' => 1.50,
            'commercial'  => 1.15,
        );
        return isset( $map[ $type ] ) ? $map[ $type ] : 1.00;
    }

    public function apply( $base_price, $floor_num, $age_years, $area_sqft, $property_type, $demand_score = 0, $location_weight = 0 ) {
        $price  = (float) $base_price;
        $price += $price * $this->floor_adjustment( $floor_num );
        $price += $price * $this->age_adjustment( $age_years );
        $price += $price * $this->size_adjustment( $area_sqft );
        $price += $price * (float) $demand_score;
        $price += $price * (float) $location_weight;
        $price *= $this->property_type_multiplier( $property_type );
        return (float) $price;
    }
}
