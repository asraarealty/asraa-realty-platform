<?php
/**
 * Schema.org Manager
 *
 * @package AsraaGrowthEngine
 */

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Schema
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'output_schema'], 20);
    }

    /**
     * Output JSON-LD Schema
     */
    public function output_schema()
    {
        if (is_admin()) {
            return;
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type"    => "WebSite",
            "name"     => get_bloginfo('name'),
            "url"      => home_url('/'),
            "description" => get_bloginfo('description'),
            "publisher" => [
                "@type" => "Organization",
                "name"  => get_bloginfo('name'),
                "url"   => home_url('/'),
                "logo"  => [
                    "@type" => "ImageObject",
                    "url" => get_site_icon_url()
                ]
            ]
        ];

        if (is_singular()) {

            $schema = [
                "@context" => "https://schema.org",
                "@type" => "Article",
                "headline" => get_the_title(),
                "description" => Description::get(),
                "datePublished" => get_the_date('c'),
                "dateModified" => get_the_modified_date('c'),
                "mainEntityOfPage" => get_permalink(),
                "author" => [
                    "@type" => "Person",
                    "name" => get_the_author()
                ],
                "publisher" => [
                    "@type" => "Organization",
                    "name" => get_bloginfo('name'),
                    "logo" => [
                        "@type" => "ImageObject",
                        "url" => get_site_icon_url()
                    ]
                ]
            ];

            if (has_post_thumbnail()) {

                $schema['image'] = get_the_post_thumbnail_url(
                    get_the_ID(),
                    'full'
                );

            }

        }

        echo '<script type="application/ld+json">';
        echo wp_json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        echo '</script>';
    }
}