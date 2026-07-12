<?php

namespace Asraa\GrowthEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Charts
{
    public static function visitors(): array
    {
        return [
            'labels' => [
                'Mon',
                'Tue',
                'Wed',
                'Thu',
                'Fri',
                'Sat',
                'Sun'
            ],
            'values' => [
                0,
                0,
                0,
                0,
                0,
                0,
                0
            ]
        ];
    }
}