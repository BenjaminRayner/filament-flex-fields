<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use InvalidArgumentException;
use LogicException;

/** Reject conflicting owners before publishing a compiled graph. */
final class ComponentOwnershipValidator
{
    /** @param array<string, ComponentDefinition> $definitions */
    public function validate(array $definitions): void
    {
        $styles = [];
        $entries = [];
        $destinations = [];
        foreach ($definitions as $definition) {
            if ($definition->entry !== null) {
                if (isset($entries[$definition->entry])) {
                    throw new InvalidArgumentException('Use a component dependency instead of registering its JS entry again: '.$definition->entry);
                }
                $entries[$definition->entry] = $definition->id;
            }
            foreach ($definition->styles as $style) {
                if (isset($styles[$style])) {
                    throw new LogicException('Stylesheet has multiple owners: '.$style);
                }
                $styles[$style] = $definition->id;
            }
            foreach ($definition->publications as $resource) {
                foreach ($destinations as $directory) {
                    if ($directory === $resource->publicDirectory
                        || str_starts_with($directory, $resource->publicDirectory.'/')
                        || str_starts_with($resource->publicDirectory, $directory.'/')) {
                        throw new LogicException('Overlapping publication ownership: '.$resource->publicDirectory);
                    }
                }
                $destinations[] = $resource->publicDirectory;
            }
        }
    }
}
