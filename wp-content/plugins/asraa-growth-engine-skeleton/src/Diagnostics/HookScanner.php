<?php
/**
 * Hook Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class HookScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Hook Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Scans for duplicate WordPress hooks and registrations.';
    }

    /**
     * Run Scan
     */
    public function scan(): array
    {
        $root = dirname(__DIR__);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        $patterns = [
            'add_action'        => '/add_action\s*\(\s*[\'"]([^\'"]+)/',
            'add_filter'        => '/add_filter\s*\(\s*[\'"]([^\'"]+)/',
            'register_rest'     => '/register_rest_route\s*\(\s*[\'"]([^\'"]+)/',
            'add_menu_page'     => '/add_menu_page\s*\(/',
            'add_submenu_page'  => '/add_submenu_page\s*\(/',
            'wp_ajax'           => '/wp_ajax_([a-zA-Z0-9_\-]+)/',
        ];

        $found = [];
        $issues = [];
        $details = [];

        foreach ($iterator as $file) {

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = @file_get_contents($file->getPathname());

            if ($code === false) {
                continue;
            }

            foreach ($patterns as $type => $regex) {

                if (preg_match_all($regex, $code, $matches)) {

                    foreach ($matches[1] ?? [] as $value) {

                        $key = $type . ':' . $value;

                        if (isset($found[$key])) {

                            $issues[] = sprintf(
                                'Duplicate %s: %s',
                                $type,
                                $value
                            );

                            $details[] = [
                                'type' => $type,
                                'hook' => $value,
                                'first' => $found[$key],
                                'second' => $file->getPathname(),
                            ];

                        } else {

                            $found[$key] = $file->getPathname();

                        }

                    }

                }

            }

        }

        if (empty($issues)) {

            return $this->success([
                'registered_hooks' => count($found),
            ]);

        }

        return $this->warning(
            $issues,
            [
                'Remove duplicate hooks.',
                'Review duplicate menu registrations.',
                'Review duplicate AJAX actions.',
            ],
            80
        );
    }
}