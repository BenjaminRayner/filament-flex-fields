<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\RegistrationMetadata;

/** A definition and its local build outputs, registered together at provider boot. */
final readonly class ComponentRegistration
{
    /** @param array<string, string> $styles Built CSS files keyed by declared stylesheet identity. */
    public function __construct(
        public ComponentDefinition $definition,
        public array $styles = [],
        public ?string $entryPath = null,
        public ?AssetPackage $assetPackage = null,
        public ?RegistrationMetadata $metadata = null,
    ) {
        $metadata?->assertFor($definition->id);
    }
}
