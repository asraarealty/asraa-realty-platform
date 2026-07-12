<?php

declare(strict_types=1);

namespace Asraa\GrowthEngine\Core;

class Container
{
    protected array $services = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id): ?object
    {
        return $this->services[$id] ?? null;
    }
}