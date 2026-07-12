<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Debug log
 */
function asraa_crm_debug_log( $message ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[ASRAA CRM] ' . $message );
    }
}

/* ============================================================
   NONCE HELPERS
============================================================ */

function asraa_crm_nonce() {
    return wp_create_nonce( 'asraa_crm_nonce' );
}

function asraa_crm_verify_ajax_nonce() {
    if ( ! check_ajax_referer( 'asraa_crm_nonce', 'nonce', false ) ) {
        wp_send_json_error(
            [ 'message' => 'Security check failed.' ],
            403
        );
    }
}

/* ============================================================
   CAPABILITY HELPERS
============================================================ */

function asraa_crm_require_ajax_cap() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error(
            [ 'message' => 'Permission denied.' ],
            403
        );
    }
}

function asraa_crm_require_admin_cap() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            [ 'message' => 'Admin permission required.' ],
            403
        );
    }
}

/* ============================================================
   SANITIZATION HELPERS
============================================================ */

function asraa_crm_sanitize_color( $color, $default = '#6b7280' ) {
    $clean = sanitize_hex_color( $color );
    return $clean ? $clean : $default;
}

/* ============================================================
   FORMAT HELPERS
============================================================ */

function asraa_crm_format_date( $mysql_date ) {
    if ( empty( $mysql_date ) || $mysql_date === '0000-00-00 00:00:00' ) {
        return '—';
    }

    return wp_date(
        get_option( 'date_format' ),
        strtotime( $mysql_date )
    );
}

function asraa_crm_format_currency( $amount ) {
    return '₹' . number_format( (float) $amount, 2 );
}

/* ============================================================
   BUDGET PARSER
============================================================ */

if ( ! function_exists( 'asraa_crm_parse_budget_value' ) ) {
    function asraa_crm_parse_budget_value( $raw ) {

        if ( is_numeric( $raw ) ) {
            return (float) $raw;
        }

        $text = strtolower( trim( (string) $raw ) );

        if ( empty( $text ) ) {
            return 0;
        }

        $number = (float) preg_replace( '/[^0-9.]/', '', $text );

        if ( strpos( $text, 'cr' ) !== false ) {
            return $number * 10000000;
        }

        if ( strpos( $text, 'lakh' ) !== false ) {
            return $number * 100000;
        }

        if ( strpos( $text, 'k' ) !== false ) {
            return $number * 1000;
        }

        return $number;
    }
}

/* ============================================================
   LEAD SCORE ENGINE
============================================================ */

function asraa_crm_calculate_ai_lead_score( array $lead ) {

    $score = 0;

    if ( ! empty( $lead['budget'] ) ) {
        $budget = asraa_crm_parse_budget_value( $lead['budget'] );

        if ( $budget >= 10000000 ) {
            $score += 30;
        }
    }

    if ( ! empty( $lead['property_type'] ) ) {
        $score += 20;
    }

    if ( ! empty( $lead['location'] ) ) {
        $score += 20;
    }

    if ( ! empty( $lead['phone'] ) ) {
        $score += 20;
    }

    if ( ! empty( $lead['email'] ) ) {
        $score += 10;
    }

    return $score;
}

/* ============================================================
   LEAD STAGES
============================================================ */

function asraa_crm_get_lead_stages() {
    return [
        'new',
        'contacted',
        'interested',
        'visit_scheduled',
        'closed'
    ];
}

function asraa_crm_sanitize_lead_stage( $stage, $default = 'new' ) {
    $stage = sanitize_key( $stage );

    if ( in_array( $stage, asraa_crm_get_lead_stages(), true ) ) {
        return $stage;
    }

    return $default;
}

function asraa_crm_get_status_for_stage( $lead_stage ) {

    $map = [
        'new'             => 'new',
        'contacted'       => 'contacted',
        'interested'      => 'contacted',
        'visit_scheduled' => 'contacted',
        'closed'          => 'converted',
    ];

    return isset( $map[ $lead_stage ] )
        ? $map[ $lead_stage ]
        : 'new';
}

/* ============================================================
   USER PHONE
============================================================ */

function asraa_crm_get_user_phone_number( $user_id ) {

    $keys = [
        'phone',
        'mobile',
        'billing_phone',
        'whatsapp_number'
    ];

    foreach ( $keys as $key ) {
        $value = get_user_meta( $user_id, $key, true );

        if ( ! empty( $value ) ) {
            return preg_replace( '/\D/', '', $value );
        }
    }

    return '';
}

/* ============================================================
   WHATSAPP LINK
============================================================ */

function asraa_crm_build_agent_whatsapp_link( array $lead, $agent_id ) {

    $phone = asraa_crm_get_user_phone_number( $agent_id );

    if ( empty( $phone ) ) {
        return '';
    }

    $property_type = $lead['property_type'] ?? 'property';
    $location      = $lead['location'] ?? 'Unknown';
    $budget        = $lead['budget'] ?? 'N/A';

    $message = "Hi, lead for {$property_type} in {$location}, budget ₹{$budget}";

    return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $message );
}

/* ============================================================
   AUTOMATION TRIGGER
============================================================ */

function asraa_crm_fire_trigger( $event, $context = [] ) {
    do_action(
        'asraa_crm_' . sanitize_key( $event ),
        $context
    );

    asraa_crm_debug_log(
        'Trigger fired: ' . $event
    );
}

/* ============================================================
   QUICK PROPERTY SAVE (OLD LEGACY VERSION)
============================================================ */

if ( ! function_exists( 'asraa_save_quick_property_old' ) ) {

    function asraa_save_quick_property_old( $agent_id, $raw_input, $notes = '' ) {

        global $wpdb;

        if ( empty( $raw_input ) ) {
            return false;
        }

        $raw_input = strtoupper( trim( $raw_input ) );
        $parts     = preg_split( '/\s+/', $raw_input );

        if ( count( $parts ) < 6 ) {
            return false;
        }

        $transaction_type = strtolower( $parts[0] );
        $property_type    = sanitize_text_field( $parts[1] );
        $city             = sanitize_text_field( $parts[2] );
        $price_raw        = $parts[3];
        $area             = sanitize_text_field( $parts[4] );
        $status           = sanitize_text_field( $parts[5] );

        $price = asraa_crm_parse_budget_value( $price_raw );

        $table = $wpdb->prefix . 'asraa_crm_properties';

        $inserted = $wpdb->insert(
            $table,
            [
                'title'            => $property_type . ' in ' . $city,
                'transaction_type' => $transaction_type,
                'property_type'    => $property_type,
                'builder_name'     => 'Agent Direct',
                'city'             => $city,
                'area'             => $area,
                'location'         => $city,
                'price'            => $price,
                'status'           => $status,
                'image_url'        => '',
                'created_at'       => current_time( 'mysql' ),
            ]
        );

        if ( ! $inserted ) {
            asraa_crm_debug_log( 'Quick property insert failed.' );
            return false;
        }

        asraa_crm_fire_trigger(
            'property_created',
            [
                'agent_id'    => $agent_id,
                'property_id' => $wpdb->insert_id,
                'notes'       => $notes,
            ]
        );

        return true;
    }
}