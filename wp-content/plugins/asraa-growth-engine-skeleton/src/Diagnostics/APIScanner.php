<?php
/**
 * API Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class APIScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'API Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks external API configuration and connectivity.';
    }

    /**
     * Scan APIs
     */
    public function scan(): array
    {
        $issues = [];
        $details = [];

        /*
        |---------------------------------------------------------
        | Google Maps
        |---------------------------------------------------------
        */

        $googleMaps = get_option('asraa_google_maps_api_key');

        $details['google_maps'] = !empty($googleMaps);

        if (empty($googleMaps)) {
            $issues[] = 'Google Maps API Key is missing.';
        }

        /*
        |---------------------------------------------------------
        | OpenAI
        |---------------------------------------------------------
        */

        $openAI = get_option('asraa_openai_api_key');

        $details['openai'] = !empty($openAI);

        if (empty($openAI)) {
            $issues[] = 'OpenAI API Key is missing.';
        }

        /*
        |---------------------------------------------------------
        | Google Analytics
        |---------------------------------------------------------
        */

        $ga = get_option('asraa_ga_measurement_id');

        $details['google_analytics'] = !empty($ga);

        if (empty($ga)) {
            $issues[] = 'Google Analytics Measurement ID is missing.';
        }

        /*
        |---------------------------------------------------------
        | Search Console
        |---------------------------------------------------------
        */

        $gsc = get_option('asraa_search_console_property');

        $details['search_console'] = !empty($gsc);

        if (empty($gsc)) {
            $issues[] = 'Search Console property is not configured.';
        }

        /*
        |---------------------------------------------------------
        | SMTP
        |---------------------------------------------------------
        */

        $smtp = get_option('asraa_smtp_host');

        $details['smtp'] = !empty($smtp);

        if (empty($smtp)) {
            $issues[] = 'SMTP configuration is missing.';
        }

        /*
        |---------------------------------------------------------
        | WordPress REST API
        |---------------------------------------------------------
        */

        $details['rest_api'] = rest_url();

        /*
        |---------------------------------------------------------
        | XML-RPC
        |---------------------------------------------------------
        */

        $details['xmlrpc'] = apply_filters('xmlrpc_enabled', true);

        /*
        |---------------------------------------------------------
        | Return
        |---------------------------------------------------------
        */

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Configure all required API credentials.',
                'Verify API keys are valid.',
                'Reconnect services if necessary.',
            ],
            88
        );
    }
}