<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use Bjanczak\FilamentFlexFields\Support\Components\Compilation\CompiledComponentGraph;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;

/**
 * A validated snapshot bound to one registry and its exact pre-commit revision.
 *
 * @internal Issued by ComponentRegistry; constructing a snapshot does not authorize a commit.
 */
final readonly class PreparedComponentRegistration
{
    /** @var array<string, RegistrationMetadata> */
    public array $metadata;

    /** @param array<string, RegistrationMetadata> $metadata */
    public function __construct(
        private ComponentRegistry $registry,
        public int $expectedRevision,
        public int $addedDefinitions,
        public CompiledComponentGraph $graph,
        array $metadata = [],
    ) {
        $this->metadata = ArraySnapshot::copy($metadata);
    }

    public function belongsTo(ComponentRegistry $registry): bool
    {
        return $this->registry === $registry;
    }
}
