<?php

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

class Loader
{
    public function run(): void
    {
        do_action('asraa_growth_engine_loaded');
    }
}