<?php
/**
 * Theme Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class ThemeScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Theme Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Checks the active theme and child theme configuration.';
    }

    /**
     * Scan Theme
     */
    public function scan(): array
    {
        $issues = [];
        $details = [];

        $theme = wp_get_theme();

        $details['active_theme'] = $theme->get('Name');
        $details['version'] = $theme->get('Version');
        $details['stylesheet'] = $theme->get_stylesheet();
        $details['template'] = $theme->get_template();

        /* Child Theme */

        $details['child_theme'] = is_child_theme();

        if (!is_child_theme()) {

            $issues[] = 'Child theme is not active.';

        }

        /* Parent Theme */

        if (is_child_theme()) {

            $parent = $theme->parent();

            if ($parent) {

                $details['parent_theme'] = $parent->get('Name');
                $details['parent_version'] = $parent->get('Version');

            }

        }

        /* Theme Supports */

        $supports = [

            'title-tag',
            'post-thumbnails',
            'custom-logo',
            'custom-background',
            'html5',
            'menus',
            'widgets',

        ];

        foreach ($supports as $support) {

            $details['supports'][$support] = current_theme_supports($support);

            if (!current_theme_supports($support)) {

                $issues[] = "Theme does not support {$support}.";

            }

        }

        /* Screenshot */

        $details['screenshot'] = file_exists(
            get_theme_root() . '/' . $theme->get_stylesheet() . '/screenshot.png'
        );

        if (!$details['screenshot']) {

            $issues[] = 'Theme screenshot.png is missing.';
        }

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Review theme supports.',
                'Ensure child theme is active.',
                'Keep parent theme updated.',
            ],
            90
        );
    }
}