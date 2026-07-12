<?php
/**
 * Local SEO Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class LocalSEO
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'outputLocalSchema'], 20);
    }

    /**
     * Output Local Business JSON-LD.
     */
    public function outputLocalSchema(): void
    {
        if (is_admin()) {
            return;
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "RealEstateAgent",
            "name" => get_bloginfo('name'),
            "url" => home_url('/'),
            "description" => get_bloginfo('description'),
            "telephone" => get_option('asraa_phone', ''),
            "email" => get_option('admin_email'),
            "image" => get_site_icon_url(),
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => get_option('asraa_address', ''),
                "addressLocality" => get_option('asraa_city', ''),
                "addressRegion" => get_option('asraa_state', ''),
                "postalCode" => get_option('asraa_zip', ''),
                "addressCountry" => get_option('asraa_country', 'IN')
            ]
        ];

        echo '<script type="application/ld+json">';
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo '</script>';
    }

    /**
     * Save business information.
     */
    public static function saveBusiness(array $data): void
    {
        update_option('asraa_phone', sanitize_text_field($data['phone'] ?? ''));
        update_option('asraa_address', sanitize_text_field($data['address'] ?? ''));
        update_option('asraa_city', sanitize_text_field($data['city'] ?? ''));
        update_option('asraa_state', sanitize_text_field($data['state'] ?? ''));
        update_option('asraa_zip', sanitize_text_field($data['zip'] ?? ''));
        update_option('asraa_country', sanitize_text_field($data['country'] ?? 'IN'));
    }

    /**
     * Get business data.
     */
    public static function getBusiness(): array
    {
        return [
            'phone' => get_option('asraa_phone', ''),
            'address' => get_option('asraa_address', ''),
            'city' => get_option('asraa_city', ''),
            'state' => get_option('asraa_state', ''),
            'zip' => get_option('asraa_zip', ''),
            'country' => get_option('asraa_country', 'IN'),
        ];
    }
}