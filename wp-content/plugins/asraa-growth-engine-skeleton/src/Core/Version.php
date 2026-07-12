<?php

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

class Version
{
    public static function get(): string
    {
        return ASRAA_GE_VERSION;
    }
}