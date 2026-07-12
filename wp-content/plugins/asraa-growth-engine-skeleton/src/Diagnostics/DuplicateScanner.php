<?php
/**
 * Duplicate Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class DuplicateScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'Duplicate Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Detects duplicate PHP classes and duplicate file names.';
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

        $classes = [];
        $files = [];

        $issues = [];
        $details = [];

        foreach ($iterator as $file) {

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilename();

            // Duplicate filenames
            if (isset($files[$filename])) {

                $issues[] = sprintf(
                    'Duplicate filename: %s',
                    $filename
                );

                $details[] = [
                    'type' => 'file',
                    'name' => $filename,
                    'first' => $files[$filename],
                    'second' => $file->getPathname(),
                ];

            } else {

                $files[$filename] = $file->getPathname();

            }

            $contents = @file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            // Detect class declarations
            if (preg_match(
                '/class\s+([A-Za-z0-9_]+)/',
                $contents,
                $match
            )) {

                $class = $match[1];

                if (isset($classes[$class])) {

                    $issues[] = sprintf(
                        'Duplicate class: %s',
                        $class
                    );

                    $details[] = [
                        'type' => 'class',
                        'name' => $class,
                        'first' => $classes[$class],
                        'second' => $file->getPathname(),
                    ];

                } else {

                    $classes[$class] = $file->getPathname();

                }

            }

        }

        if (empty($issues)) {

            return $this->success(
                [
                    'files_scanned' => count($files),
                    'classes_found' => count($classes),
                ]
            );

        }

        return $this->warning(
            $issues,
            [
                'Review duplicate files.',
                'Review duplicate classes.',
            ],
            75
        );
    }
}