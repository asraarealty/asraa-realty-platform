<?php
/**
 * File Scanner
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

class FileScanner extends Scanner
{
    /**
     * Scanner Name
     */
    public function name(): string
    {
        return 'File Scanner';
    }

    /**
     * Description
     */
    public function description(): string
    {
        return 'Scans plugin files for missing, empty, duplicate and suspicious files.';
    }

    /**
     * Run Scan
     */
    public function scan(): array
    {
        $issues = [];
        $details = [];

        $pluginRoot = dirname(__DIR__, 2);

        if (!is_dir($pluginRoot)) {

            return $this->critical(
                ['Plugin directory not found.'],
                ['Verify plugin installation.']
            );

        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $pluginRoot,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        $requiredFiles = [

            'asraa-growth-engine.php',
            'README.md',
            'composer.json',

        ];

        $extensions = [
            'php' => 0,
            'js'  => 0,
            'css' => 0,
            'json'=> 0,
            'md'  => 0,
        ];

        $totalFiles = 0;
        $emptyFiles = 0;
        $largeFiles = 0;
        $duplicateNames = [];

        foreach ($iterator as $file) {

            if (!$file->isFile()) {
                continue;
            }

            $totalFiles++;

            $filename = $file->getFilename();

            $extension = strtolower($file->getExtension());

            if (isset($extensions[$extension])) {
                $extensions[$extension]++;
            }

            if ($file->getSize() === 0) {

                $emptyFiles++;

                $issues[] = "Empty file: {$filename}";
            }

            if ($file->getSize() > (5 * 1024 * 1024)) {

                $largeFiles++;

                $issues[] = "Large file (>5MB): {$filename}";
            }

            if (isset($duplicateNames[$filename])) {

                $issues[] = "Duplicate filename: {$filename}";

            } else {

                $duplicateNames[$filename] = true;

            }

            if (preg_match('/\.(bak|old|zip|sql)$/i', $filename)) {

                $issues[] = "Backup file detected: {$filename}";
            }

        }

        foreach ($requiredFiles as $required) {

            if (!file_exists($pluginRoot . '/' . $required)) {

                $issues[] = "Missing required file: {$required}";
            }

        }

        $details = [

            'total_files' => $totalFiles,
            'empty_files' => $emptyFiles,
            'large_files' => $largeFiles,
            'php_files'   => $extensions['php'],
            'css_files'   => $extensions['css'],
            'js_files'    => $extensions['js'],
            'json_files'  => $extensions['json'],
            'markdown'    => $extensions['md'],

        ];

        if (empty($issues)) {

            return $this->success($details);

        }

        return $this->warning(
            $issues,
            [
                'Remove backup files.',
                'Delete empty files.',
                'Review duplicate filenames.',
                'Split oversized files where possible.',
            ],
            90
        );
    }
}