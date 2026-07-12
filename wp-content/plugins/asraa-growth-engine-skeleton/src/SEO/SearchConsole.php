<?php
/**
 * Search Console Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class SearchConsole
{
    /**
     * Settings option key.
     */
    private const OPTION = 'asraa_search_console';

    /**
     * Is Search Console connected?
     */
    public function isConnected(): bool
    {
        $settings = get_option(self::OPTION, []);

        return !empty($settings['connected']);
    }

    /**
     * Save connection.
     */
    public function saveConnection(array $data): bool
    {
        $settings = [
            'connected'   => true,
            'property'    => sanitize_text_field($data['property'] ?? ''),
            'site_url'    => esc_url_raw($data['site_url'] ?? ''),
            'access_token'=> sanitize_text_field($data['access_token'] ?? ''),
            'refresh_token'=> sanitize_text_field($data['refresh_token'] ?? ''),
            'expires_at'  => intval($data['expires_at'] ?? 0),
        ];

        return update_option(self::OPTION, $settings);
    }

    /**
     * Get settings.
     */
    public function settings(): array
    {
        return get_option(self::OPTION, []);
    }

    /**
     * Disconnect.
     */
    public function disconnect(): bool
    {
        return delete_option(self::OPTION);
    }

    /**
     * Placeholder for future API.
     */
    public function performance(): array
    {
        return [
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'position' => 0,
        ];
    }
}