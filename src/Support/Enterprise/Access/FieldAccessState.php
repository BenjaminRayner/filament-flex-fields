<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise\Access;

final class FieldAccessState
{
    /** @var array<string, array<string, array<string, array<string, bool>>>> */
    public array $rules = [];

    /** @var array<string, bool> */
    public array $defaults = [];
}
