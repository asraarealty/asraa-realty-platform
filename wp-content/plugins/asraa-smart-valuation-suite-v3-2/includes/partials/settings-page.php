<?php
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
?>
<div class="wrap">
    <h1>Asraa Smart Valuation Suite – Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'asraa_svs_settings' ); ?>

        <table class="form-table">
            <tr>
                <th><label for="asraa_svs_google_maps_api_key">Google Maps API Key</label></th>
                <td>
                    <input type="text" class="regular-text" name="asraa_svs_google_maps_api_key"
                           id="asraa_svs_google_maps_api_key"
                           value="<?php echo esc_attr( get_option( 'asraa_svs_google_maps_api_key', '' ) ); ?>">
                    <p class="description">Used for Maps embeds / legacy autocomplete.</p>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_google_places_key">Google Places API Key</label></th>
                <td>
                    <input type="text" class="regular-text" name="asraa_svs_google_places_key"
                           id="asraa_svs_google_places_key"
                           value="<?php echo esc_attr( get_option( 'asraa_svs_google_places_key', '' ) ); ?>">
                    <p class="description">Used as fallback when address not found in local database. Leave blank to use the Maps key above.</p>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_default_currency">Default Currency</label></th>
                <td>
                    <input type="text" class="regular-text" name="asraa_svs_default_currency"
                           id="asraa_svs_default_currency"
                           value="<?php echo esc_attr( get_option( 'asraa_svs_default_currency', 'INR' ) ); ?>">
                    <p class="description">ISO code, e.g. INR, AED, USD, EUR, GBP.</p>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_unit_system">Area Unit</label></th>
                <td>
                    <?php $unit = get_option( 'asraa_svs_unit_system', 'sqft' ); ?>
                    <select name="asraa_svs_unit_system" id="asraa_svs_unit_system">
                        <option value="sqft" <?php selected( $unit, 'sqft' ); ?>>sq.ft</option>
                        <option value="sqm"  <?php selected( $unit, 'sqm' ); ?>>sq.m</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_global_fallback_rate">Global Fallback Rate (per unit area)</label></th>
                <td>
                    <input type="number" step="1" min="0" name="asraa_svs_global_fallback_rate"
                           id="asraa_svs_global_fallback_rate"
                           value="<?php echo esc_attr( get_option( 'asraa_svs_global_fallback_rate', 8000 ) ); ?>">
                    <p class="description">Used when no matching rate is found in any data source.</p>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_admin_whatsapp">Admin WhatsApp Number</label></th>
                <td>
                    <input type="text" class="regular-text" name="asraa_svs_admin_whatsapp"
                           id="asraa_svs_admin_whatsapp"
                           value="<?php echo esc_attr( get_option( 'asraa_svs_admin_whatsapp', '' ) ); ?>"
                           placeholder="e.g. 919876543210">
                    <p class="description">Full number with country code, digits only. A WhatsApp alert will be sent to this number on each new valuation lead.</p>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_openai_key">OpenAI API Key</label></th>
                <td>
                    <input type="password" class="regular-text" name="asraa_svs_openai_key"
                           id="asraa_svs_openai_key"
                           value="<?php echo esc_attr( get_option( 'asraa_svs_openai_key', '' ) ); ?>">
                    <p class="description">Used to generate AI valuation summaries (optional).</p>
                </td>
            </tr>
            <tr>
                <th><label for="asraa_svs_debug_mode">Debug Mode</label></th>
                <td>
                    <?php $dbg = get_option( 'asraa_svs_debug_mode', 'no' ); ?>
                    <select name="asraa_svs_debug_mode" id="asraa_svs_debug_mode">
                        <option value="no"  <?php selected( $dbg, 'no' ); ?>>Off</option>
                        <option value="yes" <?php selected( $dbg, 'yes' ); ?>>On</option>
                    </select>
                    <p class="description">Returns extra diagnostic data in AJAX responses.</p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
