<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

/**
 * One localized discovery snapshot per request, invalidated when definitions change.
 *
 * @phpstan-import-type PlaygroundDefinition from \Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry
 */
final class PlaygroundRegistryState
{
    public ?string $key = null;

    /** @var array<string, PlaygroundDefinition> */
    public array $definitions = [];
}
