<?php
/**
 * Image SEO Manager
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class ImageSEO
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('wp_get_attachment_image_attributes', [$this, 'optimize'], 10, 3);
    }

    /**
     * Optimize image attributes.
     */
    public function optimize(array $attr, $attachment, $size): array
    {
        $title = get_the_title($attachment->ID);

        if (empty($attr['alt'])) {
            $attr['alt'] = $title;
        }

        if (empty($attr['title'])) {
            $attr['title'] = $title;
        }

        if (empty($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }

        if (empty($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        return $attr;
    }

    /**
     * Generate SEO filename.
     */
    public static function seoFilename(string $filename): string
    {
        $filename = strtolower($filename);

        $filename = preg_replace('/[^a-z0-9]+/', '-', $filename);

        return trim($filename, '-');
    }

    /**
     * Generate ALT text.
     */
    public static function generateAlt(string $title): string
    {
        return trim($title . ' | Asraa Realty');
    }
}