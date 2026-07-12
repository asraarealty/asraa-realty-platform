<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Valuation_Engine {

    private $ai_engine;
    private $comparable_engine;
    private $adjustment_engine;
    private $confidence_engine;
    private $building_db;
    private $formatter;

    public function __construct() {
        $currency                = get_option( 'asraa_svs_default_currency', 'INR' );
        $unit                    = get_option( 'asraa_svs_unit_system', 'sqft' );
        $this->ai_engine         = new Asraa_SVS_AI_Engine();
        $this->comparable_engine = new Asraa_SVS_Comparable_Engine();
        $this->adjustment_engine = new Asraa_SVS_Adjustment_Engine();
        $this->confidence_engine = new Asraa_SVS_Confidence_Engine();
        $this->building_db       = new Asraa_SVS_Building_Database();
        $this->formatter         = new Asraa_SVS_Output_Formatter( $currency, $unit );
    }

    public function valuate( $params ) {
        $building_name = sanitize_text_field( $params['building_name'] ?? '' );
        $area          = sanitize_text_field( $params['area']          ?? '' );
        $locality      = sanitize_text_field( $params['locality']      ?? '' );
        $city          = sanitize_text_field( $params['city']          ?? '' );
        $state         = sanitize_text_field( $params['state']         ?? '' );
        $country       = sanitize_text_field( $params['country']       ?? '' );
        $pincode       = sanitize_text_field( $params['pincode']       ?? '' );
        $latitude      = (float) ( $params['latitude']    ?? 0 );
        $longitude     = (float) ( $params['longitude']   ?? 0 );
        $property_type = sanitize_text_field( $params['property_type'] ?? 'residential' );
        $area_size     = (float) ( $params['area_size']   ?? 0 );
        $floor_num     = ( isset( $params['floor_num'] ) && '' !== $params['floor_num'] ) ? (int) $params['floor_num'] : null;
        $age_years     = ( isset( $params['age_years'] ) && '' !== $params['age_years'] ) ? (int) $params['age_years'] : null;
        $monthly_rent  = (float) ( $params['monthly_rent'] ?? 0 );
        $yield_pct     = (float) ( $params['yield_pct']    ?? 0 );

        $comps = array();
        for ( $i = 1; $i <= 3; $i++ ) {
            $cp = (float) ( $params[ "comp_{$i}_price" ] ?? 0 );
            $ca = (float) ( $params[ "comp_{$i}_area"  ] ?? 0 );
            if ( $cp > 0 && $ca > 0 ) {
                $comps[] = array( 'price' => $cp, 'area' => $ca );
            }
        }

        $ai_result     = $this->ai_engine->get_rate( $building_name, $area, $city, $property_type );
        $ai_rate       = $ai_result['rate'];
        $match_score   = $ai_result['match_score'];
        $matched_level = $ai_result['matched_level'];
        $ai_source     = $ai_result['source'];

        $ai_value = null;
        if ( $ai_rate > 0 && $area_size > 0 ) {
            $ai_value = $this->adjustment_engine->apply( $ai_rate * $area_size, $floor_num, $age_years, $area_size, $property_type );
        }

        $comp_result = ( ! empty( $comps ) ) ? $this->comparable_engine->calculate( $comps, $area_size ) : null;

        $yield_val        = null;
        $commercial_types = array( 'commercial', 'office', 'shop', 'ground_shop' );
        if ( in_array( $property_type, $commercial_types, true ) && $monthly_rent > 0 && $yield_pct > 0 ) {
            $yield_val = $this->comparable_engine->yield_value( $monthly_rent, $yield_pct );
        }

        $final_value      = 0;
        $valuation_source = 'AI';
        $comp_count       = 0;

        if ( null !== $ai_value && null !== $comp_result ) {
            $final_value      = ( $ai_value * 0.6 ) + ( $comp_result['comparable_value'] * 0.4 );
            $valuation_source = 'Hybrid (AI + Comparable)';
            $comp_count       = $comp_result['comp_count'];
        } elseif ( null !== $ai_value ) {
            $final_value      = $ai_value;
            $valuation_source = 'AI (' . $ai_source . ')';
        } elseif ( null !== $comp_result ) {
            $final_value      = $comp_result['comparable_value'];
            $valuation_source = 'Comparable';
            $comp_count       = $comp_result['comp_count'];
        } elseif ( $area_size > 0 ) {
            $global_fallback  = (float) get_option( 'asraa_svs_global_fallback_rate', 8000 );
            $final_value      = $global_fallback * $area_size;
            $ai_rate          = $global_fallback;
            $valuation_source = 'Global Fallback';
            $matched_level    = 'fallback';
            $match_score      = 0;
        }

        if ( in_array( $property_type, $commercial_types, true ) ) {
            if ( null !== $yield_val && null !== $comp_result ) {
                $final_value      = ( $yield_val + $comp_result['comparable_value'] ) / 2;
                $valuation_source = 'Commercial (Yield + Comparable)';
            } elseif ( null !== $yield_val ) {
                $final_value      = $yield_val;
                $valuation_source = 'Commercial Yield';
            }
        }

        $src_type = ( false !== strpos( $valuation_source, 'Hybrid' ) ) ? 'hybrid' : ( $comp_count > 0 ? 'comparable' : 'ai' );
        $conf     = $this->confidence_engine->score( $matched_level, $match_score, $comp_count, $src_type );

        $effective_rate = ( $area_size > 0 && $final_value > 0 ) ? $final_value / $area_size : $ai_rate;

        $unit    = get_option( 'asraa_svs_unit_system', 'sqft' );
        $details = array();
        if ( $building_name )  $details[] = 'Building: ' . $building_name;
        if ( $area || $city )  $details[] = 'Location: ' . trim( $area . ( $area && $city ? ', ' : '' ) . $city, ', ' );
        if ( $property_type )  $details[] = 'Type: ' . ucfirst( str_replace( '_', ' ', $property_type ) );
        if ( $area_size > 0 )  $details[] = 'Area: ' . $area_size . ' ' . $unit;
        if ( null !== $floor_num ) $details[] = 'Floor: ' . ( -1 === $floor_num ? 'Top Floor' : $floor_num );
        if ( null !== $age_years ) $details[] = 'Building Age: ' . $age_years . ' yrs';
        if ( $comp_count > 0 ) $details[] = 'Comparables: ' . $comp_count;

        $output = $this->formatter->build( $final_value, $effective_rate, $conf['score'], $conf['label'], $valuation_source, $details );

        $this->save( array(
            'building'         => $building_name,
            'area'             => $area,
            'locality'         => $locality,
            'city'             => $city,
            'state'            => $state,
            'country'          => $country,
            'property_type'    => $property_type,
            'area_size'        => $area_size,
            'floor_num'        => $floor_num,
            'age_years'        => $age_years,
            'price'            => $final_value,
            'rate'             => $effective_rate,
            'valuation_source' => $valuation_source,
            'comparable_data'  => wp_json_encode( $comps ),
            'pincode'          => $pincode,
            'latitude'         => $latitude,
            'longitude'        => $longitude,
        ) );

        return $output;
    }

    private function save( $data ) {
        global $wpdb;
        $vr_table = $wpdb->prefix . 'asraa_svs_valuation_records';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vr_table ) ) === $vr_table ) {
            $wpdb->insert(
                $vr_table,
                array(
                    'building'         => sanitize_text_field( $data['building'] ),
                    'area'             => sanitize_text_field( $data['area'] ),
                    'locality'         => sanitize_text_field( $data['locality'] ),
                    'city'             => sanitize_text_field( $data['city'] ),
                    'state'            => sanitize_text_field( $data['state'] ),
                    'country'          => sanitize_text_field( $data['country'] ),
                    'property_type'    => sanitize_text_field( $data['property_type'] ),
                    'area_size'        => (float) $data['area_size'],
                    'floor_num'        => isset( $data['floor_num'] ) ? $data['floor_num'] : null,
                    'age_years'        => isset( $data['age_years'] ) ? $data['age_years'] : null,
                    'price'            => (float) $data['price'],
                    'rate'             => (float) $data['rate'],
                    'valuation_source' => sanitize_text_field( $data['valuation_source'] ),
                    'comparable_data'  => $data['comparable_data'],
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%f', '%f', '%s', '%s' )
            );
        }

        if ( ! empty( $data['building'] ) && ! empty( $data['city'] ) ) {
            $this->building_db->upsert( array(
                'building_name' => $data['building'],
                'area'          => $data['area'],
                'locality'      => $data['locality'],
                'city'          => $data['city'],
                'state'         => $data['state'],
                'country'       => $data['country'],
                'pincode'       => $data['pincode'],
                'latitude'      => $data['latitude'],
                'longitude'     => $data['longitude'],
                'avg_rate'      => $data['rate'],
                'property_type' => $data['property_type'],
            ) );
        }
    }
}
