<?php
/**
 * Redirect Rule
 *
 * @package AsraaGrowthEngine
 */

declare(strict_types=1);

namespace Asraa\GrowthEngine\SEO;

if (!defined('ABSPATH')) {
    exit;
}

class RedirectRule
{
    protected string $source = '';

    protected string $destination = '';

    protected int $statusCode = 301;

    public function __construct(
        string $source = '',
        string $destination = '',
        int $statusCode = 301
    ) {
        $this->source = $this->sanitizePath($source);
        $this->destination = $this->sanitizePath($destination);
        $this->statusCode = $this->validateStatus($statusCode);
    }

    public function source(): string
    {
        return $this->source;
    }

    public function destination(): string
    {
        return $this->destination;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function toArray(): array
    {
        return [
            'source'       => $this->source,
            'destination'  => $this->destination,
            'status_code'  => $this->statusCode,
        ];
    }

    public function isValid(): bool
    {
        if ($this->source === '') {
            return false;
        }

        if ($this->destination === '') {
            return false;
        }

        if ($this->source === $this->destination) {
            return false;
        }

        return true;
    }

    protected function sanitizePath(string $path): string
    {
        $path = trim($path);

        $path = wp_parse_url($path, PHP_URL_PATH) ?: $path;

        $path = trim($path, '/');

        return sanitize_text_field($path);
    }

    protected function validateStatus(int $status): int
    {
        $allowed = [
            301,
            302,
            307,
            308,
            410,
        ];

        if (!in_array($status, $allowed, true)) {
            return 301;
        }

        return $status;
    }
}