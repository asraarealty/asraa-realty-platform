<?php
/**
 * Google Analytics Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

class GoogleAnalytics
{
    /**
     * Option key.
     */
    private const OPTION = 'asraa_ga4';

    /**
     * Connected?
     */
    public function isConnected(): bool
    {
        $settings = get_option(self::OPTION, []);

        return !empty($settings['connected']);
    }

    /**
     * Save connection.
     */
    public function connect(array $data): bool
    {
        return update_option(
            self::OPTION,
            [
                'connected' => true,
                'property_id' => sanitize_text_field($data['property_id'] ?? ''),
                'measurement_id' => sanitize_text_field($data['measurement_id'] ?? ''),
                'api_secret' => sanitize_text_field($data['api_secret'] ?? ''),
                'created' => current_time('mysql'),
            ]
        );
    }

    /**
     * Disconnect.
     */
    public function disconnect(): bool
    {
        return delete_option(self::OPTION);
    }

    /**
     * Settings.
     */
    public function settings(): array
    {
        return get_option(self::OPTION, []);
    }

    /**
     * Dashboard summary.
     */
    public function summary(): array
    {
        return [

            'users' => 0,

            'sessions' => 0,

            'page_views' => 0,

            'bounce_rate' => 0,

            'avg_session' => 0,

            'conversions' => 0,

        ];
    }

    /**
     * Property analytics.
     */
    public function propertyAnalytics(): array
    {
        return [];
    }

    /**
     * Traffic channels.
     */
    public function channels(): array
    {
        return [
            'Organic Search' => 0,
            'Direct' => 0,
            'Referral' => 0,
            'Social' => 0,
            'Paid Search' => 0,
        ];
    }
}