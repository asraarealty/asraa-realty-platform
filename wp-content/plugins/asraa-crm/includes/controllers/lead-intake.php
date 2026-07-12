<?php
if (!defined('ABSPATH')) exit;
/**
 * Lead Intake Handler
 *
 * Registers REST endpoints to capture leads from front-end forms and the
 * WhatsApp AI chatbot widget.
 *
 * Endpoints:
 *   POST /wp-json/asraa-crm/v1/intake        – legacy generic form intake
 *   POST /wp-json/asraa/v1/lead              – chatbot / public lead capture
 *   POST /wp-json/asraa/v1/search-properties – chatbot property search
 *   POST /wp-json/asraa/v1/ai-chat           – optional AI API proxy (OpenAI / local)
 */
add_action('rest_api_init', function () {

    register_rest_route('asraa-crm/v1', '/intake', [
        'methods'             => 'POST',
        'callback'            => 'asraa_crm_intake_handler',
        'permission_callback' => '__return_true',
    ]);

    // Public chatbot lead capture endpoint used by the WhatsApp chat widget.
    register_rest_route('asraa/v1', '/lead', [
        'methods'             => 'POST',
        'callback'            => 'asraa_public_lead_capture',
        'permission_callback' => '__return_true',
    ]);

    // Chatbot property search endpoint.
    register_rest_route('asraa/v1', '/search-properties', [
        'methods'             => 'POST',
        'callback'            => 'asraa_search_properties_handler',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('asraa/v1', '/valuation', [
        'methods'             => 'POST',
        'callback'            => 'asraa_property_valuation_handler',
        'permission_callback' => '__return_true',
    ]);

    // AI chat proxy endpoint (optional OpenAI / local provider).
    register_rest_route('asraa/v1', '/ai-chat', [
        'methods'             => 'POST',
        'callback'            => 'asraa_ai_chat_handler',
        'permission_callback' => '__return_true',
    ]);
});

if ( ! defined( 'ASRAA_MAX_PROPERTY_RESULTS' ) ) {
    define( 'ASRAA_MAX_PROPERTY_RESULTS', 5 );
}

/**
 * Resolve the best available client IP address.
 *
 * Checks common forwarded-IP headers (set by CDNs / reverse proxies) before
 * falling back to REMOTE_ADDR. Each candidate is validated as a public IPv4 or
 * IPv6 address to guard against spoofed header values.
 *
 * @return string Validated IP string, or an empty string if none found.
 */
function asraa_crm_get_client_ip() {
    $headers = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP' ];
    foreach ( $headers as $header ) {
        if ( empty( $_SERVER[ $header ] ) ) {
            continue;
        }
        // X-Forwarded-For may contain a comma-separated list; take the leftmost.
        $candidate = trim( explode( ',', wp_unslash( $_SERVER[ $header ] ) )[0] );
        if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return $candidate;
        }
    }
    return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
}

/**
 * Thin backward-compatible wrapper around asraa_parse_budget_value().
 *
 * Kept as a distinct callable name because other parts of this plugin
 * (outside this file) may already reference asraa_crm_parse_budget_value()
 * directly. The actual parsing logic lives in asraa_parse_budget_value()
 * to avoid duplicating the parsing rules in two places.
 *
 * @param mixed $value Raw budget input (numeric, or free text like "50L", "1.2Cr").
 * @return float
 */
if ( ! function_exists( 'asraa_crm_parse_budget_value' ) ) {
    function asraa_crm_parse_budget_value( $value ): float {
        return asraa_parse_budget_value( $value );
    }
}

/**
 * Calculate a simple 0-100 AI lead-quality score from the signals
 * collected by the chatbot / lead-capture form.
 *
 * Scoring model (weights sum to 100):
 *   - Base score for any chatbot-originated contact: 10
 *   - Valid phone number supplied:                    +25
 *   - Location supplied:                              +25
 *   - Property type supplied:                         +20
 *   - Budget supplied (> 0):                          +20
 *
 * @param array $data {
 *     @type float|string $budget
 *     @type string       $property_type
 *     @type string       $location
 *     @type string       $phone
 * }
 * @return int Score between 0 and 100.
 */

/**
 * Build a "click to chat" WhatsApp deep link for an assigned agent,
 * pre-filled with the lead's collected criteria.
 *
 * Agent phone number resolution order:
 *   1. User meta 'whatsapp' on the agent's WP user account (if $agent_id
 *      refers to a user).
 *   2. User meta 'phone' on the same user account.
 *   3. Post meta '_agent_whatsapp' (if $agent_id refers to an "agent" CPT post).
 *   4. Post meta '_agent_phone' on the same post.
 *   5. Site-wide default configured via the 'asraa_default_agent_whatsapp' option.
 *
 * Returns an empty string when no phone number can be resolved so callers
 * can simply skip storing/displaying the link.
 *
 * @param array $criteria { @type string $property_type @type string $location @type float|string $budget }
 * @param int   $agent_id WP user ID or agent post ID assigned to the lead (0 = none).
 * @return string Full https://wa.me/... URL, or '' if no phone is available.
 */
function asraa_crm_intake_handler( WP_REST_Request $request ) {

    // Rate-limit: one submission per IP per 60 seconds.
    // Uses a transient keyed on the client IP. The transient is set before the
    // duplicate-check so that concurrent burst requests (race window) are also
    // blocked by subsequent calls; this does not fully prevent simultaneous
    // requests in the same millisecond but is the standard WordPress approach
    // without a persistent atomic cache backend.
    $ip     = asraa_crm_get_client_ip();
    $ip_key = 'asraa_intake_' . md5( $ip );
    if ( get_transient( $ip_key ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Too many requests. Please wait a moment and try again.' ], 429 );
    }
    set_transient( $ip_key, 1, 60 );

    global $wpdb;

    $name  = sanitize_text_field( $request->get_param('name') ?? '' );
    $email = sanitize_email( $request->get_param('email') ?? '' );
    $phone = sanitize_text_field( $request->get_param('phone') ?? '' );

    if ( empty( $name ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Name is required.' ], 400 );
    }

    if ( ! empty( $email ) && ! is_email( $email ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Please provide a valid email address.' ], 400 );
    }

    $table = $wpdb->prefix . 'asraa_crm_leads';

    $wpdb->insert( $table, [
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'status'     => 'new',
        'created_at' => current_time( 'mysql' ),
    ] );

    return new WP_REST_Response( [ 'success' => true, 'id' => $wpdb->insert_id ], 201 );
}

/**
 * Chatbot / public lead capture: POST /wp-json/asraa/v1/lead
 *
 * Accepts: name, phone, intent, location, budget, property_type, source.
 *
 * Logic:
 *   1. Validate & sanitize inputs.
 *   2. Duplicate check by phone – if found, append a note and return the
 *      existing lead id so the same contact is never double-counted.
 *   3. New lead: insert row, add note,
 *      fire the asraa_crm_lead_created action for automation rules.
 */
function asraa_public_lead_capture( WP_REST_Request $request ) {

    // Rate-limit: one submission per IP per 60 seconds.
    $ip     = asraa_crm_get_client_ip();
    $ip_key = 'asraa_chatbot_' . md5( $ip );
    if ( get_transient( $ip_key ) ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'Too many requests. Please wait a moment and try again.' ],
            429
        );
    }
    set_transient( $ip_key, 1, 60 );

    global $wpdb;

    // ── 1. Sanitize ───────────────────────────────────────────────────
    $name     = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
    $phone    = sanitize_text_field( $request->get_param( 'phone' ) ?? '' );
    $email    = sanitize_email( $request->get_param( 'email' ) ?? '' );
    $message  = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );
    $source   = sanitize_text_field( $request->get_param( 'source' ) ?? '' );
    $intent   = sanitize_key( $request->get_param( 'intent' ) ?? '' );
    $location = sanitize_text_field( $request->get_param( 'location' ) ?? '' );
    $budget_input = $request->get_param( 'budget' );
    $budget   = asraa_crm_parse_budget_value( $budget_input );
    // NOTE: asraa_crm_parse_budget_value() is a thin wrapper around
    // asraa_parse_budget_value() defined further down in this file — kept as
    // a distinct callable name for backward compatibility with any other
    // code in this plugin that may already reference it directly.
    $type     = sanitize_text_field( $request->get_param( 'property_type' ) ?? ( $request->get_param( 'type' ) ?? '' ) );
    if ( empty( $source ) ) {
        $source = 'AI Chatbot';
    }
    if ( ! in_array( $intent, [ 'buy', 'sell', 'invest' ], true ) ) {
        $intent = '';
    }
    $lead_score = asraa_crm_calculate_ai_lead_score( [
        'budget'        => $budget,
        'property_type' => $type,
        'location'      => $location,
        'phone'         => $phone,
    ] );

    // ── 2. Validate ───────────────────────────────────────────────────
    if ( empty( $name ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Name is required.' ], 400 );
    }

    // Phone: digits, spaces, +, -, (, ), length 5-20, must contain at least 7 digits.
    if ( ! empty( $phone ) ) {
        if ( ! preg_match( '/^[0-9\s\+\-\(\)]{5,20}$/', $phone ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Please provide a valid phone number.' ], 400 );
        }
        $digit_count = preg_match_all( '/[0-9]/', $phone );
        if ( $digit_count < 7 ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Please provide a valid phone number.' ], 400 );
        }
    }

    if ( ! empty( $email ) && ! is_email( $email ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Please provide a valid email address.' ], 400 );
    }

    $leads_table = $wpdb->prefix . 'asraa_crm_leads';
    $notes_table = $wpdb->prefix . 'asraa_crm_notes';
    $now         = current_time( 'mysql' );

    // ── 3. Duplicate check by phone ───────────────────────────────────
    if ( ! empty( $phone ) ) {
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$leads_table} WHERE phone = %s AND is_deleted = 0 LIMIT 1",
                $phone
            )
        );

        if ( $existing_id ) {
            $existing_lead = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$leads_table} WHERE id = %d", (int) $existing_id ),
                ARRAY_A
            );
            // Append a note with the new message; update name/email if supplied.
            $update_data = [];
            if ( ! empty( $name ) ) {
                $update_data['name'] = $name;
            }
            if ( ! empty( $email ) ) {
                $update_data['email'] = $email;
            }
            if ( ! empty( $intent ) ) {
                $update_data['intent'] = $intent;
            }
            if ( ! empty( $location ) ) {
                $update_data['location'] = $location;
            }
            if ( $budget > 0 ) {
                $update_data['budget'] = $budget;
            }
            if ( ! empty( $type ) ) {
                $update_data['property_type'] = $type;
            }
            if ( ! empty( $source ) ) {
                $update_data['source'] = $source;
            }
            $update_data['lead_score'] = $lead_score;
            $update_data['last_activity'] = $now;
            $update_data['whatsapp_link'] = asraa_crm_build_agent_whatsapp_link(
                [
                    'property_type' => $type ?: ( $existing_lead['property_type'] ?? '' ),
                    'location'      => $location ?: ( $existing_lead['location'] ?? '' ),
                    'budget'        => $budget > 0 ? $budget : ( $existing_lead['budget'] ?? '' ),
                ],
                (int) ( $existing_lead['assigned_agent'] ?? 0 )
            );
            if ( ! empty( $update_data ) ) {
                $wpdb->update( $leads_table, $update_data, [ 'id' => (int) $existing_id ] );
            }

            if ( ! empty( $message ) || ! empty( $location ) || ! empty( $budget ) || ! empty( $type ) ) {
                $meta_parts = [];
                if ( $location ) {
                    $meta_parts[] = 'Location: ' . $location;
                }
                if ( $budget ) {
                    $meta_parts[] = 'Budget: ' . $budget;
                }
                if ( $type ) {
                    $meta_parts[] = 'Type: ' . $type;
                }
                $meta_text = empty( $meta_parts ) ? '' : ' [' . implode( ' | ', $meta_parts ) . ']';
                $note_text = sprintf( '[Source: %s]%s %s', $source, $meta_text, $message );
                $wpdb->insert( $notes_table, [
                    'lead_id'    => (int) $existing_id,
                    'note'       => $note_text,
                    'created_at' => $now,
                ] );
            }

            return new WP_REST_Response( [ 'success' => true, 'id' => (int) $existing_id, 'duplicate' => true ], 200 );
        }
    }

    // ── 4. Insert new lead ────────────────────────────────────────────
    $wpdb->insert( $leads_table, [
        'name'          => $name,
        'phone'         => $phone,
        'email'         => $email,
        'intent'        => $intent,
        'location'      => $location,
        'budget'        => $budget > 0 ? $budget : null,
        'property_type' => $type,
        'source'        => $source,
        'lead_score'    => $lead_score,
        'lead_stage'    => 'new',
        'status'        => 'new',
        'last_activity' => $now,
        'created_at'    => $now,
    ] );

    $lead_id = (int) $wpdb->insert_id;

    if ( ! $lead_id ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Could not save lead.' ], 500 );
    }

    // ── 5. Insert note ────────────────────────────────────────────────
    if ( ! empty( $message ) || ! empty( $location ) || ! empty( $budget ) || ! empty( $type ) ) {
        $meta_parts = [];
        if ( $location ) {
            $meta_parts[] = 'Location: ' . $location;
        }
        if ( $budget ) {
            $meta_parts[] = 'Budget: ' . $budget;
        }
        if ( $type ) {
            $meta_parts[] = 'Type: ' . $type;
        }
        $meta_text = empty( $meta_parts ) ? '' : ' [' . implode( ' | ', $meta_parts ) . ']';
        $note_text = sprintf( '[Source: %s]%s %s', $source, $meta_text, $message );
        $wpdb->insert( $notes_table, [
            'lead_id'    => $lead_id,
            'note'       => $note_text,
            'created_at' => $now,
        ] );
    }

    // ── 6. Fire CRM automation ────────────────────────────────────────
    do_action( 'asraa_crm_lead_created', $lead_id, [
        'name'          => $name,
        'phone'         => $phone,
        'email'         => $email,
        'intent'        => $intent,
        'location'      => $location,
        'budget'        => $budget,
        'property_type' => $type,
        'message'       => $message,
        'source'        => $source,
    ] );

    return new WP_REST_Response( [ 'success' => true, 'id' => $lead_id ], 201 );
}


/* ============================================================
   PROPERTY SEARCH ENDPOINT
   POST /wp-json/asraa/v1/search-properties

   Properties are native WordPress content provided by the
   WP RealEstate plugin, stored as:
     - post_type          : property
     - _property_price     (meta, numeric)
     - _property_location   (meta, single TERM ID into the
                              `property_location` taxonomy)
     - _property_type       (meta, SERIALIZED array of TERM IDs
                              into the `property_type` taxonomy)
     - _thumbnail_id / _property_featured_image (meta, image)
     - _property_address    (meta, free text)
     - _property_title      (meta, free text — falls back to
                              post_title when empty)
     - _property_status     (meta, free text)

   The `property_location` and `property_type` taxonomies are used
   ONLY to resolve a human-typed keyword (e.g. "Andheri") to a term
   ID. The actual property lookup is done with meta_query against
   the post meta above — NOT tax_query — because that is how this
   plugin links a property to its location/type.

   All lookups use WP_Query / WordPress taxonomy & meta APIs — no
   custom database tables are read or written here.
============================================================ */

/**
 * Safely check whether a DB table contains a specific column.
 *
 * Retained here (unchanged) because the /valuation endpoint still
 * relies on it to read the legacy wp_asraa_svs_rates table — it is
 * NOT used by the property search logic below, which reads property
 * data entirely through WordPress meta/taxonomy APIs instead.
 *
 * @param string $table  Full table name.
 * @param string $column Column name to check.
 * @return bool
 */
function asraa_table_has_column( string $table, string $column ): bool {
    global $wpdb;
    $safe_table = preg_replace( '/[^A-Za-z0-9_]/', '', $table );
    if ( empty( $safe_table ) || $safe_table !== $table ) {
        return false;
    }
    if ( strpos( $safe_table, $wpdb->prefix ) !== 0 ) {
        return false;
    }
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            DB_NAME,
            $safe_table,
            $column
        )
    );
    return $count > 0;
}

/**
 * Taxonomy slug used to resolve a location keyword to a term ID.
 * (Properties are NOT linked to this taxonomy via post terms — only
 * the term ID is stored in the `_property_location` post meta.)
 *
 * @return string
 */
function asraa_property_location_taxonomy(): string {
    return 'property_location';
}

/**
 * Taxonomy slug used to resolve a property-type keyword to a term ID.
 * (Properties store the resulting term ID(s) in the serialized
 * `_property_type` post meta — NOT as post terms.)
 *
 * @return string
 */
function asraa_property_type_taxonomy(): string {
    return 'property_type';
}

/**
 * Find the single best-matching term ID in a taxonomy for a free-text
 * keyword (exact name match first, falling back to a partial "contains"
 * search). Returns 0 when nothing matches or the taxonomy is missing.
 *
 * @param string $taxonomy
 * @param string $keyword
 * @return int
 */
function asraa_find_property_term_id( string $taxonomy, string $keyword ): int {
    $keyword = trim( (string) $keyword );
    if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || $keyword === '' ) {
        return 0;
    }

    // Exact name match first.
    $term = get_term_by( 'name', $keyword, $taxonomy );
    if ( $term && ! is_wp_error( $term ) ) {
        return (int) $term->term_id;
    }

    // Partial / "contains" match.
    $terms = get_terms( [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'name__like' => $keyword,
        'number'     => 1,
    ] );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        return (int) $terms[0]->term_id;
    }

    $terms = get_terms( [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'search'     => $keyword,
        'number'     => 1,
    ] );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        return (int) $terms[0]->term_id;
    }

    return 0;
}

/**
 * Get the parent term ID of a location term, if any (used for the
 * "nearby" fallback tier — e.g. Andheri -> Mumbai).
 *
 * @param int $term_id
 * @return int 0 when there is no parent.
 */
function asraa_get_location_parent_term_id( int $term_id ): int {
    if ( $term_id <= 0 ) {
        return 0;
    }
    $taxonomy = asraa_property_location_taxonomy();
    $term     = get_term( $term_id, $taxonomy );
    if ( ! $term || is_wp_error( $term ) || empty( $term->parent ) ) {
        return 0;
    }
    return (int) $term->parent;
}

/**
 * Resolve a property's location display name.
 *
 * Most properties (the majority of the ~85 published listings) are
 * classified with WordPress post terms in the `property_location`
 * taxonomy directly. A minority of legacy listings instead store a
 * single term ID in the `_property_location` post meta. A final
 * fallback reads the free-text map/address meta so a property is never
 * shown with a blank location.
 *
 * Resolution order:
 *   1. Taxonomy terms assigned to the post (property_location).
 *   2. Legacy `_property_location` post meta (term ID lookup).
 *   3. Free-text map/address meta (`_property_address`).
 *
 * @param int $post_id
 * @return string
 */
function asraa_get_property_location_name( int $post_id ): string {

    // 1. Taxonomy (modern properties).
    $terms = wp_get_post_terms(
        $post_id,
        asraa_property_location_taxonomy(),
        [ 'fields' => 'names' ]
    );
    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        return implode( ', ', $terms );
    }

    // 2. Legacy meta: single term ID stored in `_property_location`.
    $term_id = (int) get_post_meta( $post_id, '_property_location', true );
    if ( $term_id > 0 ) {
        $term = get_term( $term_id, asraa_property_location_taxonomy() );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term->name;
        }
    }

    // 3. Free-text map/address fallback.
    $address = get_post_meta( $post_id, '_property_address', true );
    if ( ! empty( $address ) ) {
        return sanitize_text_field( (string) $address );
    }

    return '';
}

/**
 * Resolve a property's price.
 *
 * Resolution order:
 *   1. `_property_price` post meta (the plugin's primary numeric field).
 *   2. `price` post meta (legacy / alternate field name used by some
 *      listings imported from other sources).
 *   3. 0 (never reject or error out on a missing price — the caller is
 *      expected to display 0 / "price on request" as appropriate).
 *
 * @param int $post_id
 * @return float
 */
function asraa_get_property_price( int $post_id ): float {
    $price = get_post_meta( $post_id, '_property_price', true );

    if ( $price === '' || $price === null || ! is_numeric( $price ) ) {
        $price = get_post_meta( $post_id, 'price', true );
    }

    if ( ! is_numeric( $price ) ) {
        return 0.0;
    }

    return (float) $price;
}

/**
 * Unserialize the `_property_type` post meta (an array of term IDs)
 * safely, returning an empty array on any malformed data.
 *
 * @param int $post_id
 * @return int[]
 */
function asraa_get_property_type_term_ids( int $post_id ): array {
    $raw = get_post_meta( $post_id, '_property_type', true );
    if ( empty( $raw ) ) {
        return [];
    }
    if ( is_array( $raw ) ) {
        $ids = $raw;
    } else {
        $ids = @unserialize( (string) $raw, [ 'allowed_classes' => false ] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( ! is_array( $ids ) ) {
            return [];
        }
    }
    return array_map( 'absint', array_filter( $ids, static function ( $v ) {
        return is_numeric( $v );
    } ) );
}

/**
 * Read the property-type term IDs from `_property_type` and return
 * their display names (from the `property_type` taxonomy).
 *
 * @param int $post_id
 * @return string
 */
function asraa_get_property_type_name( int $post_id ): string {

    // First try taxonomy terms (new properties)
    $terms = wp_get_post_terms(
        $post_id,
        asraa_property_type_taxonomy(),
        [ 'fields' => 'names' ]
    );

    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        return implode( ', ', $terms );
    }

    // Fallback to legacy serialized meta
    $term_ids = asraa_get_property_type_term_ids( $post_id );

    if ( empty( $term_ids ) ) {
        return '';
    }

    $names = [];

    foreach ( $term_ids as $term_id ) {
        $term = get_term( $term_id, asraa_property_type_taxonomy() );

        if ( $term && ! is_wp_error( $term ) ) {
            $names[] = $term->name;
        }
    }

    return implode( ', ', $names );
}

/**
 * Resolve a property's featured image URL, trying the standard WP
 * featured image first (`_thumbnail_id`) and falling back to the
 * plugin's own `_property_featured_image` meta (which may hold either
 * an attachment ID or a raw URL).
 *
 * @param int $post_id
 * @return string
 */
function asraa_get_property_image_url( int $post_id ): string {
    $image = get_the_post_thumbnail_url( $post_id, 'large' );
    if ( $image ) {
        return esc_url_raw( $image );
    }

    $fallback = get_post_meta( $post_id, '_property_featured_image', true );
    if ( empty( $fallback ) ) {
        return '';
    }
    if ( is_numeric( $fallback ) ) {
        $url = wp_get_attachment_image_url( (int) $fallback, 'large' );
        return $url ? esc_url_raw( $url ) : '';
    }
    return esc_url_raw( (string) $fallback );
}

/**
 * Resolve a property's display title, preferring the plugin's
 * `_property_title` meta and falling back to the post_title.
 *
 * @param WP_Post $post
 * @return string
 */
function asraa_get_property_title( WP_Post $post ): string {
    $meta_title = get_post_meta( $post->ID, '_property_title', true );
    $title      = ! empty( $meta_title ) ? (string) $meta_title : get_the_title( $post );
    $title      = sanitize_text_field( $title );
    if ( $title === '' ) {
        $title = 'Property #' . (int) $post->ID;
    }
    return $title;
}

/**
 * Parse a budget value into a numeric float.
 */
function asraa_parse_budget_value( $value ): float {

    if ( is_numeric( $value ) ) {
        return (float) $value;
    }

    $value = strtolower( trim( (string) $value ) );
    $value = str_replace( [ ',', '₹' ], '', $value );

    if ( preg_match( '/([\d\.]+)\s*(cr|crore)/', $value, $m ) ) {
        return (float) $m[1] * 10000000;
    }

    if ( preg_match( '/([\d\.]+)\s*(l|lac|lakh)/', $value, $m ) ) {
        return (float) $m[1] * 100000;
    }

    return (float) preg_replace( '/[^\d\.]/', '', $value );
}

/**
 * Build a meta_query array from resolved filters.
 */
function asraa_build_property_meta_query( array $args ): array {
    $args = wp_parse_args( $args, [
        'location_term_id' => 0,
        'type_term_id'     => 0,
        'budget'           => 0.0,
        'address_keyword'  => '',
    ] );

    $meta_query = [];

    if ( (float) $args['budget'] > 0 ) {
        // Never reject a property simply because it has no stored price:
        // match posts that either have no `_property_price` meta at all,
        // OR whose price is within budget. Only listings that explicitly
        // exceed the budget are excluded.
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_property_price',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_property_price',
                'value'   => (float) $args['budget'],
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ],
        ];
    }

    if ( ! empty( $args['address_keyword'] ) ) {
        $meta_query[] = [
            'key'     => '_property_address',
            'value'   => sanitize_text_field( (string) $args['address_keyword'] ),
            'compare' => 'LIKE',
        ];
    }

    if ( empty( $meta_query ) ) {
        return [];
    }

    if ( count( $meta_query ) > 1 ) {
        array_unshift( $meta_query, [ 'relation' => 'AND' ] );
    }

    return $meta_query;
}

/**
 * Query `property` posts via WP_Query using meta_query filters that
 * match this plugin's storage format (see file header). Only
 * published listings are returned.
 *
 * @param array $args See asraa_build_property_meta_query() plus:
 *     @type int    $limit
 *     @type string $orderby
 *     @type string $order
 * @return WP_Post[]
 */
function asraa_query_properties( array $args ): array {
    $args = wp_parse_args( $args, [
        'location_term_id' => 0,
        'type_term_id'     => 0,
        'budget'           => 0.0,
        'address_keyword'  => '',
        'limit'            => ASRAA_MAX_PROPERTY_RESULTS,
        'orderby'          => 'date',
        'order'            => 'DESC',
    ] );

    $query_args = [
        'post_type'           => 'property',
        'post_status'         => 'publish',
        'posts_per_page'      => max( 1, (int) $args['limit'] ),
        'orderby'             => $args['orderby'],
        'order'               => $args['order'],
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];

    $tax_query = [];

    if ( ! empty( $args['location_term_id'] ) ) {
        $tax_query[] = [
            'taxonomy' => asraa_property_location_taxonomy(),
            'field'    => 'term_id',
            'terms'    => (int) $args['location_term_id'],
        ];
    }

    if ( ! empty( $args['type_term_id'] ) ) {
        $tax_query[] = [
            'taxonomy' => asraa_property_type_taxonomy(),
            'field'    => 'term_id',
            'terms'    => (int) $args['type_term_id'],
        ];
    }

    if ( count( $tax_query ) > 1 ) {
        $tax_query['relation'] = 'AND';
    }

    if ( ! empty( $tax_query ) ) {
        $query_args['tax_query'] = $tax_query;
    }
    $meta_query = asraa_build_property_meta_query( $args );
    if ( ! empty( $meta_query ) ) {
        $query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    }

    $query = new WP_Query( $query_args );

    return $query->posts;
}

/**
 * Check whether ANY published `property` post exists at all, ignoring
 * every filter. Used for the absolute last-resort fallback (STEP 8):
 * if the database has no properties whatsoever, the chatbot is told
 * to look the answer up on the open internet instead of showing an
 * empty-listings error.
 *
 * @return bool
 */
function asraa_any_property_exists(): bool {
    $query = new WP_Query( [
        'post_type'              => 'property',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'fields'                 => 'ids',
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ] );
    return ! empty( $query->posts );
}

/**
 * Normalize WP_Post property listings to the stable API-safe shape used
 * by the chatbot / front-end. Field names match the previously shipped
 * response exactly, with `property_type` included.
 *
 * @param WP_Post[] $posts
 * @return array
 */
function asraa_normalize_property_rows( array $posts ): array {
    $normalized = [];

    foreach ( $posts as $post ) {
        if ( ! ( $post instanceof WP_Post ) ) {
            continue;
        }

        $id    = (int) $post->ID;
        $title = asraa_get_property_title( $post );

        $price = asraa_get_property_price( $id );
        if ( $price < 0 ) {
            $price = 0;
        }

        $location      = sanitize_text_field( asraa_get_property_location_name( $id ) );
        $property_type = sanitize_text_field( asraa_get_property_type_name( $id ) );

        $status_meta = get_post_meta( $id, '_property_status', true );
        $status      = ! empty( $status_meta ) ? sanitize_key( (string) $status_meta ) : 'available';

        $image     = asraa_get_property_image_url( $id );
        $permalink = get_permalink( $id );
        $permalink = $permalink ? esc_url_raw( $permalink ) : '';

        $normalized[] = (object) [
            'id'            => $id,
            'title'         => $title,
            'price'         => $price,
            'location'      => $location,
            'city'          => $location,
            'property_type' => $property_type,
            'image_url'     => $image,
            'property_url'  => $permalink,
            'status'        => $status,
        ];
    }

    return array_slice( $normalized, 0, ASRAA_MAX_PROPERTY_RESULTS );
}

/**
 * REST callback: POST /wp-json/asraa/v1/search-properties
 *
 * Accepted params:
 *   location      (string, required unless city/area supplied)
 *   city          (string, optional)
 *   area          (string, optional)
 *   budget        (numeric/text, optional) – 0 or absent = no price filter
 *   property_type (string, optional) – absent = any type
 *   lead_id       (int, optional)    – if supplied, fires lead-qualified action
 *                                      and assigns an agent.
 *
 * Falls back progressively when no exact match is found:
 *   1. exact_location – location + type + budget match (meta_query)
 *   2. nearby         – relaxed budget, type dropped, address keyword search
 *   3. city           – parent-location match (e.g. Andheri -> Mumbai)
 *   4. global_latest  – latest published properties, no filters
 *   5. If the database contains NO properties at all, returns
 *      success=true, properties=[], fallback="internet" so the AI
 *      chatbot can answer from general knowledge instead of erroring.
 */
function asraa_search_properties_handler( WP_REST_Request $request ) {

    $location      = sanitize_text_field( $request->get_param( 'location' ) ?? '' );
    $city          = sanitize_text_field( $request->get_param( 'city' ) ?? '' );
    $area          = sanitize_text_field( $request->get_param( 'area' ) ?? '' );
    $budget_raw    = $request->get_param( 'budget' ) ?? '';
    $property_type = sanitize_text_field( $request->get_param( 'property_type' ) ?? ( $request->get_param( 'type' ) ?? '' ) );
    $lead_id       = (int) ( $request->get_param( 'lead_id' ) ?? 0 );

    if ( empty( $location ) && empty( $city ) && empty( $area ) ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'location is required.' ],
            400
        );
    }

    // If city/area are missing, split free-text location such as "mumbai mira road".
    if ( empty( $city ) || empty( $area ) ) {
        $parts = preg_split( '/\s+/', trim( $location ) );
        if ( is_array( $parts ) && ! empty( $parts ) ) {
            if ( empty( $city ) ) {
                $city = sanitize_text_field( array_shift( $parts ) );
            } else {
                array_shift( $parts );
            }
            if ( empty( $area ) ) {
                $area = sanitize_text_field( implode( ' ', $parts ) );
            }
        }
    }
    if ( empty( $area ) ) {
        $area = $location;
    }
    if ( empty( $city ) ) {
        $city = $location;
    }

    // budget = 0 means "no price filter".
    $budget         = asraa_parse_budget_value( $budget_raw );
    $relaxed_budget = $budget > 0 ? ( $budget * 1.2 ) : 0;
    $limit          = max( 1, (int) ASRAA_MAX_PROPERTY_RESULTS );

    // STEP 1: resolve the location keyword to a single term ID, preferring
    // the more specific "area" input and falling back to "city".
    $location_term_id = asraa_find_property_term_id( asraa_property_location_taxonomy(), $area );
    if ( $location_term_id <= 0 ) {
        $location_term_id = asraa_find_property_term_id( asraa_property_location_taxonomy(), $city );
    }

    // Resolve property type keyword to a term ID.
    $type_term_id = ! empty( $property_type )
        ? asraa_find_property_term_id( asraa_property_type_taxonomy(), $property_type )
        : 0;
        $type_keyword = trim( $property_type );

    // STEP 2-4: exact location + type + budget, via meta_query.
    $properties = asraa_query_properties( [
        'location_term_id' => $location_term_id,
        'type_term_id'     => $type_term_id,
        'budget'           => $budget,
        'limit'            => $limit,
    ] );
    $match_level = 'exact_location';
    $exact_match = ! empty( $properties );

    // LEVEL 2: nearby — relax budget, then drop type, then fall back to an
    // address-keyword search using the raw text the user typed.
    if ( empty( $properties ) ) {
        $properties = asraa_query_properties( [
            'location_term_id' => $location_term_id,
            'type_term_id'     => $type_term_id,
            'budget'           => $relaxed_budget,
            'limit'            => $limit,
        ] );
        if ( empty( $properties ) && $type_term_id > 0 ) {
            $properties = asraa_query_properties( [
                'location_term_id' => $location_term_id,
                'budget'           => $relaxed_budget,
                'limit'            => $limit,
            ] );
        }
        if ( empty( $properties ) ) {
            $properties = asraa_query_properties( [
                'address_keyword' => trim( $area . ' ' . $city ),
                'budget'          => $relaxed_budget,
                'limit'           => $limit,
            ] );
        }
        if ( ! empty( $properties ) ) {
            $match_level = 'nearby';
        }
    }

    // LEVEL 3: city fallback — try the matched location term's parent
    // (e.g. Andheri -> Mumbai), if the taxonomy is hierarchical.
    if ( empty( $properties ) && $location_term_id > 0 ) {
        $parent_term_id = asraa_get_location_parent_term_id( $location_term_id );
        if ( $parent_term_id > 0 ) {
            $properties = asraa_query_properties( [
                'location_term_id' => $parent_term_id,
                'budget'           => $relaxed_budget,
                'limit'            => $limit,
            ] );
            if ( ! empty( $properties ) ) {
                $match_level = 'city';
            }
        }
    }

    // LEVEL 4: latest/global fallback.
    if ( empty( $properties ) ) {
        $properties  = asraa_query_properties( [ 'limit' => $limit ] );
        $match_level = 'global_latest';
    }

    // Ensure up to ASRAA_MAX_PROPERTY_RESULTS suggestions whenever possible.
    if ( count( $properties ) < $limit && $match_level !== 'global_latest' ) {
        $topups = asraa_query_properties( [ 'limit' => $limit ] );
        $merged = [];
        foreach ( $properties as $post ) {
            if ( $post instanceof WP_Post ) {
                $merged[ $post->ID ] = $post;
            }
        }
        foreach ( $topups as $post ) {
            if ( $post instanceof WP_Post ) {
                $merged[ $post->ID ] = $post;
            }
            if ( count( $merged ) >= $limit ) {
                break;
            }
        }
        $properties = array_slice( array_values( $merged ), 0, $limit );
    }

    // STEP 8: absolutely nothing in the database — let the chatbot fall
    // back to general internet/AI knowledge instead of erroring out.
    if ( empty( $properties ) && ! asraa_any_property_exists() ) {
        return new WP_REST_Response(
            [
                'success'     => true,
                'exact_match' => false,
                'match_level' => 'no_database_match',
                'properties'  => [],
                'fallback'    => 'internet',
                'message'     => 'No matching property found in our database.',
            ],
            200
        );
    }

    // Trigger lead-qualified automation.
    if ( $lead_id > 0 ) {
        /**
         * Fires after a chatbot lead has been qualified by a property search.
         *
         * @param int  $lead_id     The CRM lead ID.
         * @param bool $is_qualified Qualification status flag for automation listeners.
         */
        do_action( 'asraa_crm_lead_qualified', $lead_id, true );
    }

    $properties = asraa_normalize_property_rows( array_values( $properties ) );

    return new WP_REST_Response(
        [
            'success'     => true,
            'exact_match' => $exact_match,
            'match_level' => $match_level,
            'properties'  => $properties,
        ],
        200
    );
}

/**
 * REST callback: POST /wp-json/asraa/v1/valuation
 *
 * Reads base_rate from wp_asraa_svs_rates and returns valuation ±5%.
 */
function asraa_property_valuation_handler( WP_REST_Request $request ) {
    global $wpdb;

    $location = sanitize_text_field( $request->get_param( 'location' ) ?? '' );
    $type     = sanitize_text_field( $request->get_param( 'type' ) ?? ( $request->get_param( 'property_type' ) ?? '' ) );
    $sqft     = (float) preg_replace( '/[^0-9.]/', '', (string) ( $request->get_param( 'sqft' ) ?? '' ) );

    if ( empty( $location ) || $sqft <= 0 ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'location and sqft are required.' ],
            400
        );
    }

    $rates_table = $wpdb->prefix . 'asraa_svs_rates';
    $like        = '%' . $wpdb->esc_like( $location ) . '%';

    $wheres = [];
    $args   = [];
    if ( asraa_table_has_column( $rates_table, 'area' ) ) {
        $wheres[] = 'area LIKE %s';
        $args[]   = $like;
    }
    if ( asraa_table_has_column( $rates_table, 'city' ) ) {
        $wheres[] = 'city LIKE %s';
        $args[]   = $like;
    }
    if ( empty( $wheres ) ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'Valuation rate table is missing required columns.' ],
            500
        );
    }

    $where_clause = '(' . implode( ' OR ', $wheres ) . ')';
    if ( ! empty( $type ) && asraa_table_has_column( $rates_table, 'property_type' ) ) {
        $where_clause .= ' AND property_type = %s';
        $args[] = $type;
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is trusted from $wpdb->prefix; dynamic placeholders are prepared via $wpdb->prepare with $args
    $rate = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT AVG(base_rate) FROM {$rates_table} WHERE {$where_clause}",
            ...$args
        )
    );

    if ( $rate <= 0 ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'No valuation rate found for the requested location.' ],
            404
        );
    }

    $valuation = $rate * $sqft;
    $min_value = $valuation * 0.95;
    $max_value = $valuation * 1.05;

    return new WP_REST_Response(
        [
            'success'      => true,
            'base_rate'    => $rate,
            'sqft'         => $sqft,
            'valuation'    => $valuation,
            'range_min'    => $min_value,
            'range_max'    => $max_value,
            'currency'     => 'INR',
        ],
        200
    );
}

/* ============================================================
   AI CHAT PROXY ENDPOINT
   POST /wp-json/asraa/v1/ai-chat
============================================================ */

/**
 * Parse a raw AI reply string that may be a JSON object returned
 * by a structured-output model (gpt-4o-mini with response_format:json_object).
 *
 * @param  string $raw  Raw text from the model.
 * @return array|null   Structured array on success, null if not valid JSON or missing keys.
 */
function asraa_parse_ai_json_reply( string $raw ): ?array {
    $trimmed = trim( $raw );
    if ( $trimmed === '' || $trimmed[0] !== '{' ) {
        return null;
    }
    $parsed = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $parsed ) || ! isset( $parsed['reply'] ) ) {
        return null;
    }
    $action = isset( $parsed['action'] ) && in_array( $parsed['action'], [ 'search', 'none' ], true )
        ? $parsed['action']
        : 'none';
    $intent = sanitize_key( (string) ( $parsed['intent'] ?? '' ) );
    if ( ! in_array( $intent, [ 'buy', 'sell', 'invest' ], true ) ) {
        $intent = '';
    }
    return [
        'reply'    => sanitize_textarea_field( (string) $parsed['reply'] ),
        'action'   => $action,
        'location' => sanitize_text_field( (string) ( $parsed['location'] ?? '' ) ),
        'city'     => sanitize_text_field( (string) ( $parsed['city']     ?? '' ) ),
        'budget'   => asraa_parse_budget_value( $parsed['budget'] ?? 0 ),
        'type'     => sanitize_text_field( (string) ( $parsed['type']     ?? '' ) ),
        'intent'   => $intent,
    ];
}

/**
 * Build a structured AI response array from a plain-text reply that may
 * contain the legacy SEARCH_PROPERTIES trigger token.
 *
 * @param  string $reply         Plain-text reply (may contain SEARCH_PROPERTIES).
 * @param  string $location      Currently known location (from session context).
 * @param  string $budget        Currently known budget (from session context).
 * @param  string $property_type Currently known property type (from session context).
 * @param  string $intent        Currently known intent (buy|sell|invest).
 * @return array  Full structured response (success, reply, action, location, city, budget, type).
 */
function asraa_build_ai_structured_reply( string $reply, string $location, string $budget, string $property_type, string $intent = '' ): array {
    $action = 'none';
    $clean  = $reply;
    if ( strpos( $reply, 'SEARCH_PROPERTIES' ) !== false ) {
        $action = 'search';
        $clean  = trim( str_replace( 'SEARCH_PROPERTIES', '', $reply ) );
    }
    return [
        'success'  => true,
        'reply'    => $clean,
        'action'   => $action,
        'mode'     => ( $action === 'search' ) ? 'search' : 'advisory',
        'location' => $location,
        'city'     => '',
        'budget'   => asraa_parse_budget_value( $budget ?: '0' ),
        'type'     => $property_type,
        'intent'   => sanitize_key( $intent ),
    ];
}

/**
 * Proxy user messages to the configured AI provider (OpenAI or local).
 *
 * Accepted params:
 *   messages           (array, required) – conversation history in
 *                                          [{role, content}] format
 *   last_user_message  (string)          – shortcut for the latest user input
 *   context            (string)          – hint passed from the JS (ignored)
 *   location           (string)          – current collected location
 *   budget             (string)          – current collected budget
 *   property_type      (string)          – current collected type
 *
 * Returns: { reply, action, location, city, budget, type }
 */
function asraa_ai_chat_handler( WP_REST_Request $request ) {

    error_log('AI CHAT ENDPOINT HIT');
    error_log('MESSAGE: ' . print_r($request->get_params(), true));

    $ip = asraa_crm_get_client_ip();

    // Rate-limit: one call per IP per 5 seconds.
    $ip     = asraa_crm_get_client_ip();
    $ip_key = 'asraa_ai_chat_' . md5( $ip );
    if ( get_transient( $ip_key ) ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'Too many requests. Please wait a moment.' ],
            429
        );
    }
    set_transient( $ip_key, 1, 5 );

    // Extract and sanitize all incoming params.
    $current_msg   = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );
    $last_msg      = sanitize_textarea_field( $request->get_param( 'last_user_message' ) ?? $current_msg );
    $context       = sanitize_text_field( $request->get_param( 'context' )       ?? '' );
    $lead_id       = absint( $request->get_param( 'lead_id' ) ?? 0 );
    $ai_mode       = sanitize_text_field( $request->get_param( 'ai_mode' )       ?? '' );
    $location      = sanitize_text_field( $request->get_param( 'location' )      ?? '' );
    $budget        = sanitize_text_field( $request->get_param( 'budget' )        ?? '' );
    $property_type = sanitize_text_field( $request->get_param( 'property_type' ) ?? '' );

    // Validate ai_mode whitelist
    if ( ! in_array( $ai_mode, [ 'buy', 'sell', 'invest', '' ], true ) ) {
        $ai_mode = '';
    }

    $api_key  = get_option( 'asraa_ai_api_key', '' );
    error_log('API KEY EXISTS: ' . (!empty($api_key) ? 'YES' : 'NO'));
    $effective_msg = $current_msg ?: $last_msg;
    if ( empty( $api_key ) ) {
        $rule_reply = asraa_ai_rule_reply( $effective_msg, $ai_mode, $location, $budget, $property_type );
        return new WP_REST_Response(
            asraa_build_ai_structured_reply( $rule_reply, $location, $budget, $property_type, $ai_mode ),
            200
        );
    }

    // ── Build context-enriched system prompt ─────────────────────────
    $ctx_parts = [];
    if ( $ai_mode )       $ctx_parts[] = 'Mode: '          . $ai_mode;
    if ( $location )      $ctx_parts[] = 'Location: '      . $location;
    if ( $budget )        $ctx_parts[] = 'Budget: '        . $budget;
    if ( $property_type ) $ctx_parts[] = 'Property type: ' . $property_type;

    $context_block = $ctx_parts
        ? "\n\nCurrent session context:\n- " . implode( "\n- ", $ctx_parts )
        : '';

    $mode_instruction = '';
    switch ( $ai_mode ) {
        case 'sell':
            $mode_instruction = "\nThe user wants to SELL or get a valuation. Collect: location, configuration (BHK/type), area in sq ft.";
            break;
        case 'invest':
            $mode_instruction = "\nThe user is interested in INVESTMENT. Give practical ROI guidance and suggest suitable options by location and budget.";
            break;
        default:
            $mode_instruction = "\nHelp the user find a property to BUY. Collect: location, budget, property type.";
    }

    $system_prompt = 'You are Asraa AI, a professional real estate consultant.'
        . "\n\nYour job:"
        . "\n- understand user intent from natural language"
        . "\n- extract location, budget, property type"
        . "\n- guide user like expert"
        . "\n- give insights (price per sqft, market trends)"
        . "\n- never act like a form"
        . "\n- answer general advisory questions clearly when users ask market/trend/area guidance"
        . "\n- ask one question at a time, do not repeat questions already answered"
        . "\n- never promise fake listings or fake agent callbacks"
        . "\n- never generate fake properties"
        . "\n- if unknown, be transparent and keep guidance factual"
        . "\n\nModes:"
        . "\n- advisory mode: for general questions, trends, guidance, or incomplete criteria; set action=\"none\""
        . "\n- search mode: only when user clearly provides location AND (budget OR type); set action=\"search\""
        . "\nIf criteria are incomplete, answer the question first and then ask only the single missing detail."
        . "\nUse intent=\"buy\" for home purchase queries, intent=\"sell\" for selling/valuation queries, and intent=\"invest\" for investment/ROI queries."
        . "\n\nReturn ONLY valid JSON in this exact shape:"
        . "\n{\"reply\":\"natural response\",\"action\":\"none\",\"location\":\"\",\"city\":\"\",\"budget\":0,\"type\":\"\",\"intent\":\"buy\"}"
        . "\n- budget must always be numeric"
        . "\n- action must be \"search\" or \"none\""
        . "\n- intent must be one of \"buy\", \"sell\", \"invest\""
        . "\n- do not include markdown fences or any text outside JSON"
        . $mode_instruction
        . $context_block;

    // ── Build message array ───────────────────────────────────────────
    $raw_messages = $request->get_param( 'messages' );
    $messages     = [];

    $messages[] = [ 'role' => 'system', 'content' => $system_prompt ];

    // Append validated conversation history.
    if ( is_array( $raw_messages ) ) {
        foreach ( $raw_messages as $msg ) {
            if ( ! is_array( $msg ) ) continue;
            $role    = sanitize_text_field( isset( $msg['role'] )    ? $msg['role']    : '' );
            $content = sanitize_textarea_field( isset( $msg['content'] ) ? $msg['content'] : '' );
            if ( ! in_array( $role, [ 'user', 'assistant' ], true ) ) continue;
            if ( empty( $content ) ) continue;
            $messages[] = [ 'role' => $role, 'content' => $content ];
        }
    }

    // Ensure the current message is present (avoid empty conversation).
    if ( $effective_msg && ( empty( $messages ) || count( $messages ) === 1 ) ) {
        $messages[] = [ 'role' => 'user', 'content' => $effective_msg ];
    }

    if ( count( $messages ) <= 1 ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => 'Message payload is required.' ],
            400
        );
    }

    // ── Call OpenAI provider ────────────────────────────────────────────
    $response = asraa_ai_call_openai( $messages, $api_key );

    // If the AI call returned an error response, return the AI service error.
    $response_data = $response->get_data();
    if ( empty( $response_data['success'] ) ) {
        return new WP_REST_Response(
            [ 'success' => false, 'message' => sanitize_text_field( (string) ( $response_data['message'] ?? 'AI service unavailable.' ) ) ],
            503
        );
    }

    // ── Post-process the raw reply into a structured response ─────────
    $raw_reply = $response_data['reply'] ?? '';

    // Try to parse as JSON (OpenAI response_format:json_object path).
    $structured = asraa_parse_ai_json_reply( $raw_reply );
    if ( $structured ) {
        $structured['mode'] = ( $structured['action'] === 'search' ) ? 'search' : 'advisory';
        return new WP_REST_Response( array_merge( [ 'success' => true ], $structured ), 200 );
    }

    // Fall back to SEARCH_PROPERTIES token detection in plain-text reply.
    return new WP_REST_Response( asraa_build_ai_structured_reply( $raw_reply, $location, $budget, $property_type, $ai_mode ), 200 );
}

/**
 * Generate a rule-based scripted reply based on the current conversation state.
 *
 * This is used when:
 *   a) The provider is set to 'rule_based' (no AI API).
 *   b) An API call fails and we need a graceful fallback.
 *
 * @param string $message       The latest user message (sanitized).
 * @param string $ai_mode       Current mode: buy|sell|invest (may be empty).
 * @param string $location      Collected location (may be empty).
 * @param string $budget        Collected budget (may be empty).
 * @param string $property_type Collected property type (may be empty).
 * @return string  Plain-text reply (no HTML; *bold* markdown is acceptable).
 */
function asraa_ai_rule_reply(
    string $message,
    string $ai_mode,
    string $location,
    string $budget,
    string $property_type
): string {

    $lower = strtolower( $message );
    $safe_location = esc_html( $location );
    $safe_property_type = esc_html( $property_type );
    // Keep only digits/dots, then collapse repeated decimal points to one.
    $budget_clean = preg_replace( '/[^0-9.]/', '', (string) $budget );
    $budget_numeric = preg_replace( '/\.(?=.*\.)/', '', $budget_clean );
    $has_budget = (float) $budget_numeric > 0;

    // If we have location + (budget or type) → trigger search (non-positive budget = unknown).
    if ( $location && ( $has_budget || $property_type ) ) {
        if ( $property_type ) {
            return 'Great, I\'ll shortlist *' . $safe_property_type . '* options in *' . $safe_location . '* now. SEARCH_PROPERTIES';
        }
        return 'Great, I\'ll shortlist properties in *' . $safe_location . '* within your budget now. SEARCH_PROPERTIES';
    }

    // Sell / valuation intent
    if ( preg_match( '/\bsell\b|valuation|value.*property|price.*my.*property/', $lower ) ) {
        return '🏷️ I can help with property valuation! Please share the *location*, *configuration* (e.g. 2BHK), and *area in sq ft*.';
    }

    // Investment intent
    if ( preg_match( '/\binvest\b|investment|roi|return/', $lower ) ) {
        $inv_location = $location ? esc_html( $location ) : 'your preferred area';
        return '📈 Great choice! I can help with ROI-focused options. Share your *location*, *budget*, and *property type* so I can show live options for *' . $inv_location . '*.';
    }

    // Greeting
    if ( preg_match( '/hi|hello|hey|namaste|assalamu|good (morning|afternoon|evening)/', $lower ) ) {
        if ( ! $location ) {
            return '👋 Welcome! Which *location* are you exploring right now?';
        }
        if ( ! $has_budget ) {
            return '👋 Great to connect. What is your *budget*?';
        }
        if ( ! $property_type ) {
            return '👋 Great to connect. What *type of property* are you looking for?';
        }
        return '👋 Great to connect. Would you like me to shortlist options now or schedule a site visit?';
    }

    // Missing location
    if ( ! $location ) {
        return 'Which *location* are you interested in?';
    }

    // Have location, missing budget
    if ( $location && ! $budget ) {
        return 'Great choice — *' . $location . '*! What is your *budget*?';
    }

    // Have location + budget, missing type
    if ( $location && $budget && ! $property_type ) {
        return 'What *type of property* are you looking for? (Apartment, Villa, Townhouse, Studio, Office)';
    }

    // Generic fallback
    return 'I\'m here to help you find the perfect property! Could you tell me your preferred *location* and *budget*?';
}

/**
 * Call OpenAI Chat Completions API.
 *
 * @param array  $messages  Conversation history including system prompt.
 * @param string $api_key   OpenAI API key.
 * @return WP_REST_Response
 */
function asraa_ai_call_openai( array $messages, string $api_key ): WP_REST_Response {

    error_log('OPENAI REQUEST START');
    error_log('OPENAI API KEY LENGTH: ' . strlen($api_key));

    $response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . trim($api_key),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'           => 'gpt-4o-mini',
                'messages'        => $messages,
                'max_tokens'      => 300,
                'temperature'     => 0.6,
                'response_format' => [ 'type' => 'json_object' ],
            ] ),
        ]
    );

    error_log('OPENAI REQUEST COMPLETE');

    if ( is_wp_error( $response ) ) {

        error_log(
            'OPENAI WP ERROR: ' .
            $response->get_error_message()
        );

        return new WP_REST_Response(
            [
                'success' => false,
                'message' => 'AI service unavailable.'
            ],
            503
        );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    $raw_body    = wp_remote_retrieve_body( $response );

    error_log('OPENAI STATUS CODE: ' . $status_code);
    error_log('OPENAI RAW RESPONSE: ' . $raw_body);

    $body = json_decode( $raw_body, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {

        error_log(
            'OPENAI JSON ERROR: ' .
            json_last_error_msg()
        );

        return new WP_REST_Response(
            [
                'success' => false,
                'message' => 'Invalid AI response.'
            ],
            502
        );
    }

    if ( isset( $body['error'] ) ) {

        error_log(
            'OPENAI API ERROR: ' .
            print_r( $body['error'], true )
        );

        return new WP_REST_Response(
            [
                'success' => false,
                'message' => $body['error']['message'] ?? 'OpenAI API Error'
            ],
            502
        );
    }

    $reply = '';

    if (
        isset(
            $body['choices'][0]['message']['content']
        )
    ) {
        $reply = $body['choices'][0]['message']['content'];
    }

    error_log('OPENAI REPLY: ' . $reply);

    if ( empty( $reply ) ) {

        error_log('OPENAI EMPTY REPLY');

        return new WP_REST_Response(
            [
                'success' => false,
                'message' => 'Empty AI response.'
            ],
            502
        );
    }

    return new WP_REST_Response(
        [
            'success' => true,
            'reply'   => sanitize_textarea_field( $reply )
        ],
        200
    );
}
/**
 * Call a local / self-hosted AI (e.g. Ollama) that speaks the OpenAI chat format.
 *
 * @param array  $messages  Conversation history including system prompt.
 * @param string $endpoint  Full URL of the local chat endpoint.
 * @param string $api_key   Optional bearer token (may be empty for local installs).
 * @return WP_REST_Response
 */
function asraa_ai_call_local( array $messages, string $endpoint, string $api_key ): WP_REST_Response {

    // Validate that the endpoint is an http/https URL.
    $clean_endpoint = esc_url_raw( $endpoint );
    if ( empty( $clean_endpoint ) || ! preg_match( '#^https?://#i', $clean_endpoint ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid local AI endpoint.' ], 503 );
    }

    $headers = [ 'Content-Type' => 'application/json' ];
    if ( ! empty( $api_key ) ) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    $response = wp_remote_post(
        $clean_endpoint,
        [
            'timeout' => 30,
            'headers' => $headers,
            'body'    => wp_json_encode( [
                'model'    => 'llama3',
                'messages' => $messages,
                'stream'   => false,
            ] ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Local AI service unavailable.' ], 503 );
    }

    $body  = json_decode( wp_remote_retrieve_body( $response ), true );
    $reply = '';
    if ( isset( $body['choices'][0]['message']['content'] ) ) {
        $reply = $body['choices'][0]['message']['content'];  // OpenAI-compatible
    } elseif ( isset( $body['message']['content'] ) ) {
        $reply = $body['message']['content'];                 // Ollama format
    }

    if ( empty( $reply ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Empty AI response.' ], 502 );
    }

    return new WP_REST_Response( [ 'success' => true, 'reply' => sanitize_textarea_field( $reply ) ], 200 );
}

/* ============================================================
   CHATBOT FOLLOW-UP AUTOMATION
   Schedules WP-Cron reminders when the AI chatbot qualifies a lead.
============================================================ */

/**
 * Schedule automated follow-up tasks after a chatbot lead is qualified.
 *
 * Fires on the `asraa_crm_lead_qualified` action hook triggered by the
 * /search-properties REST endpoint after a successful property lookup.
 *
 * Follow-up schedule:
 *   - 10 minutes
 *   - Day 1  (24 h)
 *   - Day 2  (48 h)
 *   - Day 3  (72 h)
 *
 * @param int  $lead_id   CRM lead ID.
 * @param bool $qualified Whether the lead was qualified.
 */
function asraa_chatbot_schedule_followups( int $lead_id, bool $qualified ): void {
    if ( ! $qualified || $lead_id <= 0 ) {
        return;
    }

    $now     = time();
    $offsets = [
        '10min' => 10 * MINUTE_IN_SECONDS,
        'day1'  => DAY_IN_SECONDS,
        'day2'  => 2 * DAY_IN_SECONDS,
        'day3'  => 3 * DAY_IN_SECONDS,
    ];

    foreach ( $offsets as $stage => $offset ) {
        wp_schedule_single_event(
            $now + $offset,
            'asraa_chatbot_followup',
            [ $lead_id, $stage ]
        );
    }
}
add_action( 'asraa_crm_lead_qualified', 'asraa_chatbot_schedule_followups', 20, 2 );

/**
 * WP-Cron callback that creates a follow-up record for the given lead.
 *
 * @param int    $lead_id  CRM lead ID.
 * @param string $stage    Stage key (10min|day1|day2|day3).
 */
function asraa_chatbot_followup_handler( int $lead_id, string $stage ): void {
    global $wpdb;

    if ( $lead_id <= 0 ) {
        return;
    }

    $leads_table     = $wpdb->prefix . 'asraa_crm_leads';
    $followups_table = $wpdb->prefix . 'asraa_crm_followups';

    // Abort if lead was deleted.
    $exists = $wpdb->get_var(
        $wpdb->prepare( "SELECT id FROM {$leads_table} WHERE id = %d AND is_deleted = 0 LIMIT 1", $lead_id )
    );
    if ( ! $exists ) {
        return;
    }

    $labels = [
        '10min' => '10-minute',
        'day1'  => 'Day 1',
        'day2'  => 'Day 2',
        'day3'  => 'Day 3',
    ];
    $label = $labels[ $stage ] ?? $stage;

    $wpdb->insert( $followups_table, [
        'lead_id'     => $lead_id,
        'agent_id'    => 0,
        'follow_date' => current_time( 'mysql' ),
        'note'        => sprintf( '[AI Chatbot] %s automated follow-up', sanitize_text_field( $label ) ),
        'is_done'     => 0,
        'created_at'  => current_time( 'mysql' ),
    ] );
}
add_action( 'asraa_chatbot_followup', 'asraa_chatbot_followup_handler', 10, 2 );