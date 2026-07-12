<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Address_Autocomplete {

    public function search( $query ) {
        if ( strlen( trim( $query ) ) < 2 ) { return array(); }

        $suggestions = array();
        $seen        = array();

        $building_db = new Asraa_SVS_Building_Database();
        foreach ( $building_db->search( $query ) as $r ) {
            $key = strtolower( $r->building_name . '|' . $r->city );
            if ( isset( $seen[ $key ] ) ) { continue; }
            $seen[ $key ]  = true;
            $suggestions[] = $this->from_building_data( $r );
        }

        if ( count( $suggestions ) >= 8 ) { return array_slice( $suggestions, 0, 10 ); }

        global $wpdb;

        $vr_table = $wpdb->prefix . 'asraa_svs_valuation_records';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vr_table ) ) === $vr_table ) {
            $like = '%' . $wpdb->esc_like( $query ) . '%';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT building, area, locality, city, state, country, property_type, AVG(rate) as avg_rate
                 FROM `{$vr_table}`
                 WHERE building LIKE %s OR area LIKE %s OR city LIKE %s
                 GROUP BY building, city LIMIT 5",
                $like, $like, $like
            ) );
            foreach ( (array) $rows as $r ) {
                $key = strtolower( $r->building . '|' . $r->city );
                if ( isset( $seen[ $key ] ) || empty( $r->building ) ) { continue; }
                $seen[ $key ]  = true;
                $suggestions[] = array(
                    'source'        => 'history',
                    'label'         => trim( $r->building . ( $r->area ? ', ' . $r->area : '' ) . ( $r->city ? ', ' . $r->city : '' ), ', ' ),
                    'building_name' => $r->building,
                    'area'          => $r->area,
                    'locality'      => $r->locality,
                    'city'          => $r->city,
                    'state'         => $r->state,
                    'country'       => $r->country,
                    'pincode'       => '',
                    'latitude'      => '',
                    'longitude'     => '',
                    'avg_rate'      => round( (float) $r->avg_rate ),
                    'property_type' => $r->property_type,
                );
            }
        }

        if ( count( $suggestions ) >= 8 ) { return array_slice( $suggestions, 0, 10 ); }

        $rates_table = $wpdb->prefix . 'asraa_svs_rates';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rates_table ) ) === $rates_table ) {
            $like = '%' . $wpdb->esc_like( $query ) . '%';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT building, area, city, AVG(base_rate) as avg_rate FROM `{$rates_table}`
                 WHERE building LIKE %s OR area LIKE %s OR city LIKE %s
                 GROUP BY building, city LIMIT 5",
                $like, $like, $like
            ) );
            foreach ( (array) $rows as $r ) {
                $key = strtolower( $r->building . '|' . $r->city );
                if ( isset( $seen[ $key ] ) || empty( $r->building ) ) { continue; }
                $seen[ $key ]  = true;
                $suggestions[] = array(
                    'source'        => 'rates',
                    'label'         => trim( $r->building . ( $r->area ? ', ' . $r->area : '' ) . ( $r->city ? ', ' . $r->city : '' ), ', ' ),
                    'building_name' => $r->building,
                    'area'          => $r->area,
                    'locality'      => '',
                    'city'          => $r->city,
                    'state'         => '',
                    'country'       => '',
                    'pincode'       => '',
                    'latitude'      => '',
                    'longitude'     => '',
                    'avg_rate'      => round( (float) $r->avg_rate ),
                    'property_type' => '',
                );
            }
        }

        return array_slice( $suggestions, 0, 10 );
    }

    private function from_building_data( $r ) {
        $parts = array_filter( array( $r->building_name, $r->area, $r->city ) );
        return array(
            'source'        => 'local',
            'label'         => implode( ', ', $parts ),
            'building_name' => $r->building_name,
            'area'          => $r->area,
            'locality'      => $r->locality,
            'city'          => $r->city,
            'state'         => $r->state,
            'country'       => $r->country,
            'pincode'       => $r->pincode,
            'latitude'      => $r->latitude,
            'longitude'     => $r->longitude,
            'avg_rate'      => $r->avg_rate,
            'property_type' => $r->property_type,
        );
    }
}
