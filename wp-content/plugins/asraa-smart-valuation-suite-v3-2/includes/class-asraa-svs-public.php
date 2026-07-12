<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Asraa_SVS_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_shortcode( 'asraa_valuation_form', array( $this, 'render_form' ) );
    }

    public function enqueue_scripts() {
        global $post;

        /* Only enqueue on pages that contain the valuation shortcode */
        if ( ! $post || ! has_shortcode( $post->post_content, 'asraa_valuation_form' ) ) {
            return;
        }

        $plugin_url = ASRAA_SVS_URL;
        $ver        = ASRAA_SVS_VERSION;

        wp_enqueue_style( 'asraa-svs-public', $plugin_url . 'assets/css/public.css', array(), $ver );

        wp_enqueue_script(
            'asraa-svs-autocomplete',
            $plugin_url . 'assets/js/autocomplete.js',
            array( 'jquery' ),
            $ver,
            true
        );
        wp_enqueue_script(
            'asraa-svs-engine',
            $plugin_url . 'assets/js/valuation-engine.js',
            array( 'jquery', 'asraa-svs-autocomplete' ),
            $ver,
            true
        );
        wp_enqueue_script(
            'asraa-svs-public-v4',
            $plugin_url . 'assets/js/public-v4.js',
            array( 'jquery', 'asraa-svs-autocomplete', 'asraa-svs-engine' ),
            $ver,
            true
        );

        $places_key = get_option( 'asraa_svs_google_places_key', '' );
        if ( '' === $places_key ) {
            $places_key = get_option( 'asraa_svs_google_maps_api_key', '' );
        }

        wp_localize_script( 'asraa-svs-public-v4', 'ASRAA_SVS', array(
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'asraa_svs_valuation' ),
            'google_places_key'  => $places_key,
            'unit'               => get_option( 'asraa_svs_unit_system', 'sqft' ),
            'action_autocomplete' => 'asraa_svs_autocomplete',
            'action_calculate'   => 'asraa_svs_calculate',
            'action_save'        => 'asraa_svs_save',
        ) );
    }

    public function render_form() {
        return $this->render_smart_valuation_form();
    }

    public function render_smart_valuation_form() {
        $unit = esc_html( get_option( 'asraa_svs_unit_system', 'sqft' ) );
        ob_start();
        ?>

<div class="asraa-svs-wrapper">

    <form class="asraa-svs-valuation-form" autocomplete="off" novalidate>

        <!-- Smart address search -->
        <div class="asraa-svs-field asraa-svs-field--address">
            <label for="asraa-svs-address-search">Building / Project / Address *</label>
            <div class="asraa-svs-ac-wrap">
                <input type="text" id="asraa-svs-address-search" name="property_address" autocomplete="off"
                       placeholder="Start typing a building, project or address…">
                <div id="asraa-svs-ac-dropdown" class="asraa-svs-ac-dropdown" style="display:none;"></div>
            </div>
            <div class="asraa-svs-address-filled" id="asraa-svs-address-filled" style="display:none;"></div>
        </div>

        <!-- Hidden autofill fields -->
        <input type="hidden" name="building_name" id="asraa-field-building_name">
        <input type="hidden" name="area"          id="asraa-field-area">
        <input type="hidden" name="locality"      id="asraa-field-locality">
        <input type="hidden" name="city"          id="asraa-field-city">
        <input type="hidden" name="state"         id="asraa-field-state">
        <input type="hidden" name="country"       id="asraa-field-country">
        <input type="hidden" name="pincode"       id="asraa-field-pincode">
        <input type="hidden" name="latitude"      id="asraa-field-latitude">
        <input type="hidden" name="longitude"     id="asraa-field-longitude">

        <!-- Property type toggle -->
        <div class="asraa-svs-field">
            <label>Property Type *</label>
            <div class="asraa-svs-type-toggle">
                <label class="asraa-svs-type-pill"><input type="radio" name="property_type" value="residential" checked> Residential</label>
                <label class="asraa-svs-type-pill"><input type="radio" name="property_type" value="commercial"> Commercial</label>
                <label class="asraa-svs-type-pill"><input type="radio" name="property_type" value="office"> Office</label>
                <label class="asraa-svs-type-pill"><input type="radio" name="property_type" value="shop"> Shop</label>
            </div>
        </div>

        <!-- Area size -->
        <div class="asraa-svs-field">
            <label>Area (<?php echo $unit; ?>) *</label>
            <input type="number" name="area_size" min="1" required placeholder="e.g. 850">
        </div>

        <!-- Floor + age -->
        <div class="asraa-svs-field asraa-svs-field--row">
            <div>
                <label>Floor Number <span>(optional)</span></label>
                <input type="number" name="floor_num" placeholder="0 = Ground, -1 = Top">
            </div>
            <div>
                <label>Building Age (years) <span>(optional)</span></label>
                <input type="number" name="age_years" min="0" placeholder="e.g. 5">
            </div>
        </div>

        <!-- Contact -->
        <div class="asraa-svs-field">
            <label>Name</label>
            <input type="text" name="name" placeholder="Your name">
        </div>
        <div class="asraa-svs-field">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="your@email.com">
        </div>
        <div class="asraa-svs-field">
            <label>Phone *</label>
            <input type="tel" name="phone" required placeholder="+91 98765 43210">
        </div>

        <div class="asraa-svs-error"></div>

        <button type="button" class="asraa-svs-btn" onclick="if(window.AsraaValuation){AsraaValuation.calculate();}">Get Valuation</button>

    </form>

</div>

<!-- RESULT POPUP -->
<div id="asraa-svs-premium-modal" class="asraa-svs-modal">
    <div class="asraa-svs-modal-content">
        <button class="asraa-svs-close">&times;</button>
        <div class="asraa-svs-modal-main"></div>
        <div class="asraa-svs-modal-sub"></div>
        <div class="asraa-svs-price-cards" id="asraa-svs-price-cards"></div>
        <div class="asraa-svs-range"></div>
        <div class="asraa-svs-confidence-pill">
            <span class="asraa-svs-confidence-text"></span>
        </div>
        <div class="asraa-svs-details"></div>
        <div class="asraa-svs-ai-body"></div>
        <a href="#" target="_blank" class="asraa-svs-whatsapp">Share on WhatsApp</a>
    </div>
</div>

        <?php
        return ob_get_clean();
    }
}
