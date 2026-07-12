<?php

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

class Config
{
    public static function get(string $key, $default = null)
    {
        return get_option($key, $default);
    }

    public static function set(string $key, $value): void
    {
        update_option($key, $value);
    }
}