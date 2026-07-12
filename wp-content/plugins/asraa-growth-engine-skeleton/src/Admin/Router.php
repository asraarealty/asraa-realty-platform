<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Router
{
    public static function page(): string
    {
        return sanitize_key($_GET['page'] ?? '');
    }

    public static function is(string $page): bool
    {
        return self::page() === $page;
    }
}