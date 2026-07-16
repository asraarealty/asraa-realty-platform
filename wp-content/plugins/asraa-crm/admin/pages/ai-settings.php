<?php
if (!defined('ABSPATH')) exit;

/**
 * AI Assistant Settings Page
 *
 * Stores:
 * - asraa_ai_enabled
 * - asraa_ai_popup_locations
 * - asraa_ai_provider
 * - asraa_ai_api_key
 * - asraa_ai_api_endpoint
 */

if (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('You do not have permission to access this page.', 'asraa-crm')
    );
}

/* ── Handle form submission ─────────────────────────────────────────────── */
$saved = false;

if (
    current_user_can('manage_options') &&
    isset($_POST['asraa_ai_settings_nonce']) &&
    wp_verify_nonce(
        sanitize_text_field(
            wp_unslash($_POST['asraa_ai_settings_nonce'])
        ),
        'asraa_ai_save_settings'
    )
) {

    update_option(
        'asraa_ai_enabled',
        isset($_POST['asraa_ai_enabled']) ? 1 : 0
    );

    /* Popup locations */
    $raw_locations = sanitize_text_field(
        wp_unslash($_POST['asraa_ai_popup_locations'] ?? '')
    );

    $locations_parts = array_filter(
        array_map('trim', explode(',', $raw_locations))
    );

    $clean_locations = implode(
        ',',
        array_map(function ($loc) {
            return preg_replace(
                '/[^A-Za-z0-9 \-]/',
                '',
                $loc
            );
        }, $locations_parts)
    );

    update_option(
        'asraa_ai_popup_locations',
        $clean_locations
    );

    /* Provider */
    $provider = sanitize_text_field(
        wp_unslash($_POST['asraa_ai_provider'] ?? 'rule_based')
    );

    if (
        !in_array(
            $provider,
            ['rule_based', 'openai', 'local'],
            true
        )
    ) {
        $provider = 'rule_based';
    }

    update_option(
        'asraa_ai_provider',
        $provider
    );

    /* API key */
    $new_key = trim(
        wp_unslash($_POST['asraa_ai_api_key'] ?? '')
    );

    if (
        $new_key !== '' &&
        $new_key !== '**********'
    ) {
        update_option(
            'asraa_ai_api_key',
            $new_key
        );
    }

    /* Endpoint */
    $endpoint = trim(
        esc_url_raw(
            wp_unslash($_POST['asraa_ai_api_endpoint'] ?? '')
        )
    );

    if (
        $provider === 'local' &&
        !empty($endpoint) &&
        !filter_var($endpoint, FILTER_VALIDATE_URL)
    ) {
        $endpoint = '';
    }

    update_option(
        'asraa_ai_api_endpoint',
        $endpoint
    );

    $saved = true;
}

/* ── Read current values ────────────────────────────────────────────────── */
$enabled   = (bool) get_option(
    'asraa_ai_enabled',
    false
);

$locations = get_option(
    'asraa_ai_popup_locations',
    'Buy,Sell,Investment,Commercial'
);

$provider  = get_option(
    'asraa_ai_provider',
    'rule_based'
);

$api_key   = get_option(
    'asraa_ai_api_key',
    ''
);

$endpoint  = get_option(
    'asraa_ai_api_endpoint',
    ''
);

$masked_key = $api_key ? '**********' : '';
?>

<div class="wrap">
    <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php esc_html_e(
                    'Settings saved.',
                    'asraa-crm'
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php
        wp_nonce_field(
            'asraa_ai_save_settings',
            'asraa_ai_settings_nonce'
        );
        ?>

        <table class="form-table" role="presentation">

            <!-- Enable -->
            <tr>
                <th scope="row">
                    <label for="asraa_ai_enabled">
                        <?php esc_html_e(
                            'Enable AI Assistant',
                            'asraa-crm'
                        ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        id="asraa_ai_enabled"
                        name="asraa_ai_enabled"
                        value="1"
                        <?php checked($enabled, true); ?>
                    />
                    <p class="description">
                        <?php esc_html_e(
                            'Show AI widget on front-end pages.',
                            'asraa-crm'
                        ); ?>
                    </p>
                </td>
            </tr>

            <!-- Popup Locations -->
            <tr>
                <th scope="row">
                    <label for="asraa_ai_popup_locations">
                        <?php esc_html_e(
                            'Welcome Popup Locations',
                            'asraa-crm'
                        ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="asraa_ai_popup_locations"
                        name="asraa_ai_popup_locations"
                        value="<?php echo esc_attr($locations); ?>"
                        class="regular-text"
                    />
                    <p class="description">
                        <?php esc_html_e(
                            'Comma-separated intents (Buy,Sell,Investment,Commercial)',
                            'asraa-crm'
                        ); ?>
                    </p>
                </td>
            </tr>

            <!-- Provider -->
            <tr>
                <th scope="row">
                    <label for="asraa_ai_provider">
                        <?php esc_html_e(
                            'AI Provider',
                            'asraa-crm'
                        ); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="asraa_ai_provider"
                        name="asraa_ai_provider"
                        autocomplete="off"
                    >
                        <option value="rule_based" <?php selected($provider, 'rule_based'); ?>>
                            Rule-based
                        </option>

                        <option value="openai" <?php selected($provider, 'openai'); ?>>
                            OpenAI (GPT-4o-mini)
                        </option>

                        <option value="local" <?php selected($provider, 'local'); ?>>
                            Local / Ollama
                        </option>
                    </select>
                </td>
            </tr>

            <!-- API Key -->
            <tr id="asraa-ai-api-key-row"
                style="<?php echo $provider === 'rule_based' ? 'display:none' : ''; ?>">
                <th scope="row">
                    <label for="asraa_ai_api_key">
                        <?php esc_html_e(
                            'API Key',
                            'asraa-crm'
                        ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="password"
                        id="asraa_ai_api_key"
                        name="asraa_ai_api_key"
                        value="<?php echo esc_attr($masked_key); ?>"
                        class="regular-text"
                        autocomplete="off"
                    />
                    <p class="description">
                        Leave blank to keep current key.
                    </p>
                </td>
            </tr>

            <!-- Endpoint -->
            <tr id="asraa-ai-endpoint-row"
                style="<?php echo $provider !== 'local' ? 'display:none' : ''; ?>">
                <th scope="row">
                    <label for="asraa_ai_api_endpoint">
                        <?php esc_html_e(
                            'API Endpoint URL',
                            'asraa-crm'
                        ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="url"
                        id="asraa_ai_api_endpoint"
                        name="asraa_ai_api_endpoint"
                        value="<?php echo esc_url($endpoint); ?>"
                        class="regular-text"
                        placeholder="http://localhost:11434/api/chat"
                        autocomplete="off"
                    />
                    <p class="description">
                        Used only for local AI provider.
                    </p>
                </td>
            </tr>

        </table>

        <?php submit_button(
            esc_html__('Save Settings', 'asraa-crm')
        ); ?>
    </form>
</div>

<script>
(function () {
    var providerSel = document.getElementById('asraa_ai_provider');
    var keyRow = document.getElementById('asraa-ai-api-key-row');
    var endpointRow = document.getElementById('asraa-ai-endpoint-row');

    function updateRows() {
        var val = providerSel.value;

        keyRow.style.display =
            (val === 'rule_based') ? 'none' : '';

        endpointRow.style.display =
            (val === 'local') ? '' : 'none';
    }

    providerSel.addEventListener('change', updateRows);
    updateRows();
})();
</script>