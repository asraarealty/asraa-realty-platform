<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_AI_Engine {

    private $global_fallback;

    public function __construct() {
        $this->global_fallback = (float) get_option( 'asraa_svs_global_fallback_rate', 8000 );
    }

    private function fuzzy_match( $needle, $haystack ) {
        $needle   = strtolower( trim( (string) $needle ) );
        $haystack = strtolower( trim( (string) $haystack ) );
        if ( '' === $needle || '' === $haystack ) { return 0.0; }
        if ( $needle === $haystack ) { return 1.0; }
        if ( false !== strpos( $haystack, $needle ) || false !== strpos( $needle, $haystack ) ) { return 0.85; }
        similar_text( $needle, $haystack, $pct );
        return max( 0.0, min( 1.0, $pct / 100 ) );
    }

    public function get_rate( $building, $area, $city, $property_type = '' ) {
        global $wpdb;

        $bd_table = $wpdb->prefix . 'asraa_svs_building_data';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bd_table ) ) === $bd_table ) {
            if ( ! empty( $building ) && ! empty( $city ) ) {
                $like_b = '%' . $wpdb->esc_like( $building ) . '%';
                $like_c = '%' . $wpdb->esc_like( $city ) . '%';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT avg_rate FROM `{$bd_table}` WHERE building_name LIKE %s AND city LIKE %s AND avg_rate > 0 LIMIT 1",
                    $like_b, $like_c
                ) );
                if ( $row && (float) $row->avg_rate > 0 ) {
                    return array( 'rate' => (float) $row->avg_rate, 'source' => 'Building DB', 'match_score' => 0.90, 'matched_level' => 'building' );
                }
            }
            if ( ! empty( $area ) && ! empty( $city ) ) {
                $like_a = '%' . $wpdb->esc_like( $area ) . '%';
                $like_c = '%' . $wpdb->esc_like( $city ) . '%';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $avg = $wpdb->get_var( $wpdb->prepare(
                    "SELECT AVG(avg_rate) FROM `{$bd_table}` WHERE (area LIKE %s OR locality LIKE %s) AND city LIKE %s AND avg_rate > 0",
                    $like_a, $like_a, $like_c
                ) );
                if ( $avg > 0 ) {
                    return array( 'rate' => (float) $avg, 'source' => 'Area Average (Building DB)', 'match_score' => 0.70, 'matched_level' => 'area' );
                }
            }
        }

        $rates_table = $wpdb->prefix . 'asraa_svs_rates';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rates_table ) ) === $rates_table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows       = $wpdb->get_results( "SELECT * FROM `{$rates_table}` LIMIT 5000" );
            $best_rate  = null;
            $best_score = 0.0;
            foreach ( (array) $rows as $r ) {
                $s = ( 0.60 * $this->fuzzy_match( $building, $r->building ) )
                   + ( 0.25 * $this->fuzzy_match( $area, $r->area ) )
                   + ( 0.15 * $this->fuzzy_match( $city, $r->city ) );
                if ( $s > $best_score ) { $best_score = $s; $best_rate = $r; }
            }
            if ( $best_rate && $best_score >= 0.40 && (float) $best_rate->base_rate > 0 ) {
                $lvl = $best_score >= 0.75 ? 'building' : ( $best_score >= 0.55 ? 'area' : 'city' );
                return array( 'rate' => (float) $best_rate->base_rate, 'source' => sprintf( 'Rate DB (score: %.2f)', $best_score ), 'match_score' => $best_score, 'matched_level' => $lvl );
            }
        }

        $vr_table = $wpdb->prefix . 'asraa_svs_valuation_records';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vr_table ) ) === $vr_table ) {
            if ( ! empty( $building ) && ! empty( $city ) ) {
                $like_b = '%' . $wpdb->esc_like( $building ) . '%';
                $like_c = '%' . $wpdb->esc_like( $city ) . '%';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $avg = $wpdb->get_var( $wpdb->prepare(
                    "SELECT AVG(rate) FROM `{$vr_table}` WHERE building LIKE %s AND city LIKE %s AND rate > 0",
                    $like_b, $like_c
                ) );
                if ( $avg > 0 ) {
                    return array( 'rate' => (float) $avg, 'source' => 'Historical Records (building)', 'match_score' => 0.75, 'matched_level' => 'building' );
                }
            }
            if ( ! empty( $area ) && ! empty( $city ) ) {
                $like_a = '%' . $wpdb->esc_like( $area ) . '%';
                $like_c = '%' . $wpdb->esc_like( $city ) . '%';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $avg = $wpdb->get_var( $wpdb->prepare(
                    "SELECT AVG(rate) FROM `{$vr_table}` WHERE area LIKE %s AND city LIKE %s AND rate > 0",
                    $like_a, $like_c
                ) );
                if ( $avg > 0 ) {
                    return array( 'rate' => (float) $avg, 'source' => 'Historical Records (area)', 'match_score' => 0.55, 'matched_level' => 'area' );
                }
            }
        }

        return array( 'rate' => $this->global_fallback, 'source' => 'Global Fallback', 'match_score' => 0.0, 'matched_level' => 'fallback' );
    }
}
