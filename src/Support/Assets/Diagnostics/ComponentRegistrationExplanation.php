<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use InvalidArgumentException;

/** Server-only projection. Source locations require an explicit operator opt-in. */
final class ComponentRegistrationExplanation
{
    /** @return array<string, mixed> */
    public function inventory(ComponentRegistry $registry, bool $locations = false): array
    {
        $definitions = $registry->all();
        if (count($definitions) > 8192) {
            throw new InvalidArgumentException('Registration diagnostics exceed their definition budget.');
        }
        ksort($definitions, SORT_STRING);
        $known = 0;
        $deprecated = 0;
        $entries = [];
        foreach ($definitions as $id => $_) {
            $entries[$id] = $this->explain($registry, $id, $locations);
            $known += (int) $entries[$id]['originKnown'];
            $deprecated += (int) ($entries[$id]['deprecation'] !== null);
        }

        return ['format' => 1, 'scope' => 'server-installation', 'registryRevision' => $registry->revision(),
            'originsKnown' => $known, 'originsUnknown' => count($definitions) - $known,
            'deprecatedComponents' => $deprecated, 'definitions' => $entries];
    }

    /** @return array<string, mixed> */
    public function explain(ComponentRegistry $registry, string $component, bool $locations = false): array
    {
        $metadata = $registry->registrationMetadata($component);
        $origin = $metadata?->origin;
        $deprecation = $metadata?->deprecation;
        $package = $registry->packageAssets()->bindings[$component] ?? null;

        return ['originKnown' => $origin !== null,
            'location' => $locations && $origin !== null ? ['file' => $origin->file, 'line' => $origin->line] : null,
            'package' => $package?->manifest->package,
            'deprecation' => $deprecation?->export(),
            'replacementInstalled' => $deprecation === null ? null : $registry->has($deprecation->replacement)];
    }
}
