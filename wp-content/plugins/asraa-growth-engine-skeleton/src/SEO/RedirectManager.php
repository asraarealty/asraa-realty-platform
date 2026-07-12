<?php
/**
 * Redirect Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class RedirectManager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('template_redirect', [$this, 'handleRedirect']);
    }

    /**
     * Handle redirects.
     */
    public function handleRedirect(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'asraa_redirects';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        $redirect = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source=%s LIMIT 1",
                $request
            )
        );

        if (!$redirect) {
            return;
        }

        wp_redirect(
            esc_url_raw(home_url('/' . ltrim($redirect->destination, '/'))),
            intval($redirect->status_code)
        );

        exit;
    }

    /**
     * Create redirect.
     */
    public static function create(
        string $source,
        string $destination,
        int $status = 301
    ): bool {

        global $wpdb;

        return (bool) $wpdb->insert(
            $wpdb->prefix . 'asraa_redirects',
            [
                'source' => trim($source, '/'),
                'destination' => trim($destination, '/'),
                'status_code' => $status,
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Delete redirect.
     */
    public static function delete(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $wpdb->prefix . 'asraa_redirects',
            ['id' => $id]
        );
    }

    /**
     * Get all redirects.
     */
    public static function all(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}asraa_redirects ORDER BY id DESC",
            ARRAY_A
        );
    }
}