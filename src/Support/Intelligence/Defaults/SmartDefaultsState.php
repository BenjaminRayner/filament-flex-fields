<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Defaults;

final class SmartDefaultsState
{
    /** @var array<string, array<string, array<string, mixed>>> */
    public array $users = [];

    /** @var array<string, array<string, mixed>> */
    public array $tenants = [];
}
