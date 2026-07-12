<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_asraa_svs_get_valuation',        array( $this, 'handle_valuation' ) );
        add_action( 'wp_ajax_nopriv_asraa_svs_get_valuation', array( $this, 'handle_valuation' ) );
        /* v4 action aliases */
        add_action( 'wp_ajax_asraa_svs_calculate',            array( $this, 'handle_valuation' ) );
        add_action( 'wp_ajax_nopriv_asraa_svs_calculate',     array( $this, 'handle_valuation' ) );
        add_action( 'wp_ajax_asraa_svs_save',                 array( $this, 'handle_save' ) );
        add_action( 'wp_ajax_nopriv_asraa_svs_save',          array( $this, 'handle_save' ) );
        add_action( 'wp_ajax_asraa_svs_autocomplete',         array( $this, 'handle_autocomplete' ) );
        add_action( 'wp_ajax_nopriv_asraa_svs_autocomplete',  array( $this, 'handle_autocomplete' ) );
    }

    private function clean_location( $text ) {
        if ( empty( $text ) ) { return ''; }
        $text  = strtolower( trim( $text ) );
        $parts = explode( ',', $text );
        $text  = trim( $parts[0] );
        $text  = str_replace( array( '/', '\\' ), ' ', $text );
        foreach ( array( 'district', 'state', 'region', 'maharashtra', 'india', 'suburban', 'city' ) as $w ) {
            $text = str_replace( $w, '', $text );
        }
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }

    private function get_action_signal( $confidence, $demand ) {
        $confidence = (int) $confidence;
        $demand     = strtolower( (string) $demand );
        if ( $confidence >= 85 && in_array( $demand, array( 'high', 'very high' ), true ) ) {
            return array( 'label' => 'Sell', 'summary' => 'Strong seller-side conditions — listing at or near this guidance can attract serious, qualified buyers.' );
        }
        if ( $confidence >= 70 && 'low' !== $demand ) {
            return array( 'label' => 'Hold', 'summary' => 'Signals are healthy; you can hold around this band while staying slightly flexible on price.' );
        }
        return array( 'label' => 'Buy', 'summary' => 'This micro-market looks buyer-friendly; motivated purchasers may be able to negotiate attractive terms.' );
    }

    private function generate_ai_text( $location, $config, $total_label, $rate_label, $demand, $confidence ) {
        $api_key = get_option( 'asraa_svs_openai_key', '' );
        $config  = $config ? $config : 'residential';

        if ( ! $api_key ) {
            $parts   = array();
            $parts[] = sprintf(
                'For this %s asset in %s, the indicated value of %s is broadly aligned with prevailing benchmarks for comparable properties.',
                $config,
                $location ? $location : 'the locality',
                $total_label
            );
            if ( in_array( strtolower( $demand ), array( 'high', 'very high' ), true ) ) {
                $parts[] = 'Buyer interest is typically elevated in this corridor, especially for well-presented and correctly priced listings.';
            } elseif ( 'medium' === strtolower( $demand ) ) {
                $parts[] = 'Demand appears balanced; buyers are price-conscious but transact decisively when the value proposition is clear.';
            } else {
                $parts[] = 'This pocket behaves more price-sensitively, so sharper pricing and stronger presentation can materially improve traction.';
            }
            $parts[] = sprintf(
                'This guidance reflects an effective rate of %s, with an overall confidence of around %d%% based on the available data.',
                $rate_label,
                $confidence
            );
            return '<p>' . esc_html( implode( ' ', $parts ) ) . '</p>';
        }

        $prompt = sprintf(
            "You are a professional real estate analyst.\nWrite a 3–5 sentence valuation summary.\n\nLocation: %s\nConfiguration: %s\nIndicative Value: %s\nRate: %s\nDemand: %s\nConfidence: %d%%\n\nTone: neutral, concise, data-driven. No bullet points, no disclaimers.\n",
            $location ? $location : 'the locality', $config, $total_label, $rate_label, $demand, $confidence
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array(
                    'model'      => 'gpt-4.1-mini',
                    'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
                    'max_tokens' => 220,
                ) ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) { return '<p>AI summary temporarily unavailable.</p>'; }
        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $json['choices'][0]['message']['content'] ) ) {
            return '<p>' . esc_html( $json['choices'][0]['message']['content'] ) . '</p>';
        }
        return '<p>AI summary could not be generated.</p>';
    }

    public function handle_autocomplete() {
        check_ajax_referer( 'asraa_svs_valuation', 'nonce' );
        $query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        if ( strlen( trim( $query ) ) < 2 ) { wp_send_json_success( array() ); }
        $engine = new Asraa_SVS_Address_Autocomplete();
        wp_send_json_success( $engine->search( $query ) );
    }

    public function handle_valuation() {
        check_ajax_referer( 'asraa_svs_valuation', 'nonce' );

        $s  = static function ( $key ) { return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; };
        $sf = static function ( $key ) { return isset( $_POST[ $key ] ) ? (float) $_POST[ $key ] : 0; };

        $building_name = $s('building_name') ?: $s('building');
        $area          = $s('area');
        $locality      = $s('locality');
        $city          = $s('city');
        $state         = $s('state');
        $country       = $s('country');
        $pincode       = $s('pincode');
        $latitude      = $sf('latitude');
        $longitude     = $sf('longitude');
        $property_type = $s('property_type') ?: $s('config') ?: 'residential';
        $area_size     = $sf('area_size') ?: (float) ( isset( $_POST['sqft'] ) ? intval( $_POST['sqft'] ) : 0 );
        $floor_num     = $s('floor_num');
        $age_years     = $s('age_years');
        $name          = $s('name');
        $email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone         = $s('phone');

        if ( $area_size <= 0 || empty( $email ) || empty( $phone ) ) {
            error_log( 'Asraa SVS: validation failed area_size=' . $area_size . ' email=' . $email . ' phone=' . $phone );
            wp_send_json_error( array( 'message' => 'Please fill all required fields: area/sqft, email and phone are required.' ) );
        }
        if ( ! is_email( $email ) ) {
            error_log( 'Asraa SVS: invalid email=' . $email );
            wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
        }

        $engine = new Asraa_SVS_Valuation_Engine();
        $output = $engine->valuate( array(
            'building_name' => $building_name,
            'area'          => $area,
            'locality'      => $locality,
            'city'          => $city,
            'state'         => $state,
            'country'       => $country,
            'pincode'       => $pincode,
            'latitude'      => $latitude,
            'longitude'     => $longitude,
            'property_type' => $property_type,
            'area_size'     => $area_size,
            'floor_num'     => $floor_num,
            'age_years'     => $age_years,
        ) );

        $loc_label   = trim( $area . ( $area && $city ? ', ' : '' ) . $city, ', ' );
        $currency    = get_option( 'asraa_svs_default_currency', 'INR' );
        $symbols     = array( 'INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'AED ', 'SGD' => 'S$', 'AUD' => 'A$' );
        $symbol      = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
        $unit        = get_option( 'asraa_svs_unit_system', 'sqft' );
        $signal      = $this->get_action_signal( $output['confidence'], $output['demand'] );
        $signal_html = sprintf( '<p><strong>AI signal: %s</strong> – %s</p>', esc_html( $signal['label'] ), esc_html( $signal['summary'] ) );
        $ai_body     = $this->generate_ai_text(
            $loc_label ?: $city,
            $property_type,
            $output['message_main'],
            $symbol . number_format( (float) $output['base_rate'] ) . ' per ' . $unit,
            $output['demand'],
            $output['confidence']
        );
        $output['ai_html']         = $signal_html . $ai_body;
        $output['ai_signal_label'] = $signal['label'];
        /* v4 key aliases for JS compatibility */
        $output['mid_price']  = $output['fair_price'];
        $output['high_price'] = $output['premium_price'];

        /* Save lead to CRM */
        global $wpdb;
        $crm_leads = $wpdb->prefix . 'asraa_crm_leads';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $crm_leads ) ) === $crm_leads ) {
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$crm_leads} WHERE email = %s AND is_deleted = 0",
                $email
            ) );
            if ( $existing_id ) {
                $lead_id = (int) $existing_id;
            } else {
                $wpdb->insert(
                    $crm_leads,
                    array(
                        'name'          => $name,
                        'email'         => $email,
                        'phone'         => $phone,
                        'property_type' => strtoupper( $property_type ),
                        'status'        => 'new',
                        'created_at'    => current_time( 'mysql' ),
                    ),
                    array( '%s', '%s', '%s', '%s', '%s', '%s' )
                );
                $lead_id = (int) $wpdb->insert_id;
            }

            /* Store valuation data as a note */
            if ( $lead_id ) {
                $crm_notes = $wpdb->prefix . 'asraa_crm_notes';
                $note_text = sprintf(
                    '[Valuation] Source: Smart Valuation Form | Type: %s | Area: %s %s | Location: %s | Age: %s yrs | Floor: %s | Value: %s',
                    strtoupper( $property_type ),
                    $area_size,
                    get_option( 'asraa_svs_unit_system', 'sqft' ),
                    trim( $area . ( $area && $city ? ', ' : '' ) . $city, ', ' ),
                    $age_years ? $age_years : '—',
                    $floor_num !== '' ? $floor_num : '—',
                    $output['message_main']
                );
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $crm_notes ) ) === $crm_notes ) {
                    $wpdb->insert(
                        $crm_notes,
                        array(
                            'lead_id'    => $lead_id,
                            'note'       => $note_text,
                            'user_id'    => 0,
                            'created_at' => current_time( 'mysql' ),
                        ),
                        array( '%d', '%s', '%d', '%s' )
                    );
                }

                /* Admin WhatsApp alert */
                $admin_wa = get_option( 'asraa_svs_admin_whatsapp', '' );
                $admin_wa = preg_replace( '/\D/', '', $admin_wa );
                $crm_url  = admin_url( 'admin.php?page=asraa-crm-leads&lead_id=' . $lead_id );
                if ( $admin_wa ) {
                    $wa_msg = sprintf(
                        "🏠 *New Valuation Lead*\n\n*Name:* %s\n*Phone:* %s\n*Email:* %s\n*Type:* %s\n*Area:* %s %s\n*Location:* %s\n*Estimate:* %s\n\nCRM: %s",
                        $name,
                        $phone,
                        $email,
                        strtoupper( $property_type ),
                        $area_size,
                        get_option( 'asraa_svs_unit_system', 'sqft' ),
                        trim( $area . ( $area && $city ? ', ' : '' ) . $city, ', ' ),
                        $output['message_main'],
                        $crm_url
                    );
                    $wa_link = 'https://wa.me/' . $admin_wa . '?text=' . rawurlencode( $wa_msg );
                    $output['admin_wa_link'] = $wa_link;
                }

                /* Admin email notification */
                $admin_email = get_option( 'admin_email' );
                $email_body  = "<p><strong>New Valuation Lead</strong></p>"
                    . "<p>Name: {$name}<br>Phone: {$phone}<br>Email: {$email}<br>"
                    . "Type: " . strtoupper( $property_type ) . "<br>"
                    . "Area: {$area_size} " . get_option( 'asraa_svs_unit_system', 'sqft' ) . "<br>"
                    . "Location: " . trim( $area . ( $area && $city ? ', ' : '' ) . $city, ', ' ) . "<br>"
                    . "Estimate: " . $output['message_main'] . "</p>"
                    . "<p><a href='" . esc_url( $crm_url ) . "'>View in CRM</a></p>";
                if ( isset( $wa_link ) ) {
                    $email_body .= "<p><a href='" . esc_url( $wa_link ) . "'>📲 Send WhatsApp to Admin</a></p>";
                }
                wp_mail(
                    $admin_email,
                    'New Valuation Lead: ' . $name,
                    $email_body,
                    array( 'Content-Type: text/html; charset=UTF-8' )
                );
            }
        }

        if ( get_option( 'asraa_svs_debug_mode', 'no' ) === 'yes' ) {
            $output['debug'] = array( 'params' => array( 'building' => $building_name, 'area' => $area, 'city' => $city, 'area_size' => $area_size ) );
        }

        error_log( 'Asraa SVS: valuation success for email=' . $email . ' area_size=' . $area_size );
        wp_send_json_success( $output );
    }

    public function handle_save() {
        check_ajax_referer( 'asraa_svs_valuation', 'nonce' );
        wp_send_json_success( array( 'message' => 'Valuation saved successfully.' ) );
    }
}
