<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Building_Database {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_svs_building_data';
    }

    public function search( $query ) {
        global $wpdb;
        if ( strlen( trim( $query ) ) < 2 ) {
            return array();
        }
        $like = '%' . $wpdb->esc_like( trim( $query ) ) . '%';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}`
                 WHERE building_name LIKE %s
                    OR area          LIKE %s
                    OR locality      LIKE %s
                    OR city          LIKE %s
                 ORDER BY
                     CASE WHEN building_name LIKE %s THEN 0
                          WHEN area          LIKE %s THEN 1
                          WHEN locality      LIKE %s THEN 2
                          ELSE 3 END,
                     avg_rate DESC
                 LIMIT 10",
                $like, $like, $like, $like,
                $like, $like, $like
            )
        );
    }

    public function find_by_name_city( $building_name, $city ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}` WHERE LOWER(building_name) = LOWER(%s) AND LOWER(city) = LOWER(%s) LIMIT 1",
                $building_name,
                $city
            )
        );
    }

    public function upsert( $data ) {
        global $wpdb;

        $building_name = isset( $data['building_name'] ) ? (string) $data['building_name'] : '';
        $city          = isset( $data['city'] ) ? (string) $data['city'] : '';

        if ( '' === $building_name || '' === $city ) {
            return 0;
        }

        $existing = $this->find_by_name_city( $building_name, $city );

        if ( $existing ) {
            $update  = array();
            $formats = array();

            $new_rate = isset( $data['avg_rate'] ) ? (float) $data['avg_rate'] : 0;
            if ( $new_rate > 0 ) {
                $blended            = ( ( (float) $existing->avg_rate * 3 ) + $new_rate ) / 4;
                $update['avg_rate'] = round( $blended, 2 );
                $formats[]          = '%f';
            }

            foreach ( array( 'area', 'locality', 'state', 'country', 'pincode', 'property_type' ) as $f ) {
                if ( ! empty( $data[ $f ] ) && empty( $existing->$f ) ) {
                    $update[ $f ] = sanitize_text_field( $data[ $f ] );
                    $formats[]    = '%s';
                }
            }
            foreach ( array( 'latitude', 'longitude' ) as $f ) {
                if ( ! empty( $data[ $f ] ) && empty( $existing->$f ) ) {
                    $update[ $f ] = (float) $data[ $f ];
                    $formats[]    = '%f';
                }
            }

            if ( ! empty( $update ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->update( $this->table, $update, array( 'id' => $existing->id ), $formats, array( '%d' ) );
            }
            return $existing->id;
        }

        $wpdb->insert(
            $this->table,
            array(
                'building_name' => sanitize_text_field( $building_name ),
                'area'          => sanitize_text_field( $data['area'] ?? '' ),
                'locality'      => sanitize_text_field( $data['locality'] ?? '' ),
                'city'          => sanitize_text_field( $city ),
                'state'         => sanitize_text_field( $data['state'] ?? '' ),
                'country'       => sanitize_text_field( $data['country'] ?? '' ),
                'pincode'       => sanitize_text_field( $data['pincode'] ?? '' ),
                'latitude'      => (float) ( $data['latitude'] ?? 0 ),
                'longitude'     => (float) ( $data['longitude'] ?? 0 ),
                'avg_rate'      => (float) ( $data['avg_rate'] ?? 0 ),
                'property_type' => sanitize_text_field( $data['property_type'] ?? 'residential' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s' )
        );
        return $wpdb->insert_id;
    }
}
