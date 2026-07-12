<?php
/**
 * Code Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class CodeScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Code Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Scans PHP files for common coding issues.';
    }

    /**
     * Scan
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

        $issues = [];

        $details = [];

        $phpFiles = 0;

        foreach ($iterator as $file) {

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $phpFiles++;

            $path = $file->getPathname();

            $code = @file_get_contents($path);

            if ($code === false) {
                continue;
            }

            // Missing strict types

            if (strpos($code, 'declare(strict_types=1);') === false) {

                $issues[] = "Missing strict_types in {$path}";

            }

            // Missing namespace

            if (strpos($code, 'namespace ') === false) {

                $issues[] = "Missing namespace in {$path}";

            }

            // TODO comments

            if (preg_match('/TODO/i', $code)) {

                $details[] = "TODO found in {$path}";

            }

            // var_dump()

            if (preg_match('/var_dump\s*\(/', $code)) {

                $issues[] = "var_dump() found in {$path}";

            }

            // print_r()

            if (preg_match('/print_r\s*\(/', $code)) {

                $issues[] = "print_r() found in {$path}";

            }

            // die()

            if (preg_match('/die\s*\(/', $code)) {

                $issues[] = "die() found in {$path}";

            }

            // exit()

            if (preg_match('/exit\s*;/', $code)) {

                $issues[] = "exit found in {$path}";

            }

            // Empty class

            if (preg_match('/class\s+\w+\s*\{\s*\}/s', $code)) {

                $issues[] = "Empty class in {$path}";

            }

        }

        if (empty($issues)) {

            return $this->success([
                'php_files' => $phpFiles,
                'notes' => $details,
            ]);

        }

        return $this->warning(
            $issues,
            [
                'Remove debugging functions.',
                'Use strict types.',
                'Add namespaces.',
                'Complete TODO items.',
            ],
            85
        );
    }
}