<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\CompiledComponentGraph;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\CompiledPackageAssets;
use Bjanczak\FilamentFlexFields\Support\Components\Compilation\ComponentGraphCompiler;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ResolvedChildSlot;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\DefinitionOrigin;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\PreparedComponentRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\RegistrationMetadata;
use InvalidArgumentException;
use LogicException;
use WeakMap;

/** Compatibility registry: collect definitions, compile atomically, then freeze. */
final class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> */
    private array $definitions = [];

    /** @var array<string, AssetPackage> */
    private array $packageBindings = [];

    /** @var array<string, RegistrationMetadata> */
    private array $registrationMetadata = [];

    private ?CompiledComponentGraph $compiled = null;

    private bool $frozen = false;

    private int $revision = 0;

    /** @var WeakMap<PreparedComponentRegistration, true>|null */
    private ?WeakMap $preparedRegistrations = null;

    public static function builtIn(): self
    {
        static $definitions = null;
        if ($definitions === null) {
            $candidateDefinitions = [];
            foreach (glob(dirname(__DIR__, 3).'/resources/components/*.php') ?: [] as $path) {
                $definition = require $path;
                if (! $definition instanceof ComponentDefinition || basename($path, '.php') !== $definition->id) {
                    throw new LogicException('Invalid component definition file: '.$path);
                }
                $candidateDefinitions[] = [$definition, new RegistrationMetadata(origin: new DefinitionOrigin($path))];
            }
            $definitions = $candidateDefinitions;
        }
        $registry = new self;
        foreach ($definitions as [$definition, $metadata]) {
            $registry->register($definition, $metadata);
        }

        return $registry;
    }

    public function register(ComponentDefinition $definition, ?RegistrationMetadata $metadata = null): void
    {
        $this->assertMutable();
        if (isset($this->definitions[$definition->id])) {
            throw new LogicException('Duplicate component identity: '.$definition->id);
        }
        if (count($this->definitions) >= ComponentGraphCompiler::MAX_COMPONENTS) {
            throw new InvalidArgumentException('Component registry exceeds its node budget.');
        }
        $this->validateMetadata([$definition->id => true], $metadata === null ? [] : [$definition->id => $metadata]);
        $this->definitions[$definition->id] = $definition;
        if ($metadata !== null) {
            $this->registrationMetadata[$definition->id] = $metadata;
        }
        $this->revision++;
        $this->compiled = null;
    }

    /**
     * Commit a complete family only after all definitions and edges compile.
     * A failed attempt leaves both the live definitions and revision unchanged.
     *
     * @param  list<ComponentDefinition>  $definitions
     * @param  array<string, AssetPackage>  $packageBindings
     * @param  array<string, RegistrationMetadata>  $metadata
     */
    public function registerBatch(array $definitions, array $packageBindings = [], array $metadata = []): void
    {
        $this->commit($this->prepareBatch($definitions, $packageBindings, $metadata));
    }

    /**
     * @param  list<ComponentDefinition>  $definitions
     * @param  array<string, AssetPackage>  $packageBindings
     * @param  array<string, RegistrationMetadata>  $metadata
     */
    public function prepareBatch(array $definitions, array $packageBindings = [], array $metadata = []): PreparedComponentRegistration
    {
        $this->assertMutable();
        if (! array_is_list($definitions) || $definitions === []) {
            throw new InvalidArgumentException('A component batch needs a nonempty list of typed definitions.');
        }
        if (count($this->definitions) + count($definitions) > ComponentGraphCompiler::MAX_COMPONENTS) {
            throw new InvalidArgumentException('Component registry exceeds its node budget.');
        }
        $candidate = $this->definitions;
        $added = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof ComponentDefinition) {
                throw new InvalidArgumentException('Component batches require typed definitions.');
            }
            if (isset($candidate[$definition->id])) {
                throw new LogicException('Duplicate component identity: '.$definition->id);
            }
            $candidate[$definition->id] = $definition;
            $added[$definition->id] = true;
        }
        foreach ($packageBindings as $component => $package) {
            if (! isset($added[$component]) || ! $package instanceof AssetPackage) {
                throw new InvalidArgumentException('Package bindings belong only to typed definitions in this batch.');
            }
        }
        $this->validateMetadata($added, $metadata);
        $compiled = (new ComponentGraphCompiler)->compile($candidate, [...$this->packageBindings, ...$packageBindings]);

        $candidateMetadata = $this->registrationMetadata;
        foreach ($metadata as $component => $value) {
            $candidateMetadata[$component] = $value; // Detach caller-owned array reference aliases.
        }
        $registration = new PreparedComponentRegistration($this, $this->revision, count($definitions), $compiled, $candidateMetadata);
        $this->preparedRegistrations ??= new WeakMap;
        $this->preparedRegistrations[$registration] = true;

        return $registration;
    }

    public function commit(PreparedComponentRegistration $registration): void
    {
        $this->assertMutable();
        if (! isset($this->preparedRegistrations[$registration])) {
            throw new LogicException('A component registration must be prepared by this registry before it can be committed.');
        }
        unset($this->preparedRegistrations[$registration]);
        if (! $registration->belongsTo($this) || $registration->expectedRevision !== $this->revision) {
            throw new LogicException('A prepared component registration cannot replace a different or newer registry.');
        }
        $this->definitions = $registration->graph->definitions();
        $this->packageBindings = $registration->graph->packageAssets->bindings;
        $this->registrationMetadata = $registration->metadata;
        $this->compiled = $registration->graph;
        $this->revision += $registration->addedDefinitions;
    }

    public function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('The Flex component registry is frozen. Register extensions during service-provider boot.');
        }
    }

    public function registrationMetadata(string $component): ?RegistrationMetadata
    {
        $this->definition($component);

        return $this->registrationMetadata[$component] ?? null;
    }

    /** @param array<string, true> $added
     * @param  array<string, RegistrationMetadata>  $metadata
     */
    private function validateMetadata(array $added, array $metadata): void
    {
        foreach ($metadata as $component => $value) {
            if (! isset($added[$component]) || ! $value instanceof RegistrationMetadata) {
                throw new InvalidArgumentException('Registration metadata belongs only to typed definitions in this batch.');
            }
            $value->assertFor($component);
        }
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /** Failed compilation never freezes or replaces the last valid snapshot. */
    public function freeze(): CompiledComponentGraph
    {
        $compiled = $this->compiledGraph();
        $this->frozen = true;

        return $compiled;
    }

    public function compiledGraph(): CompiledComponentGraph
    {
        return $this->compiled ??= (new ComponentGraphCompiler)->compile($this->definitions, $this->packageBindings);
    }

    public function packageAssets(): CompiledPackageAssets
    {
        return $this->compiledGraph()->packageAssets;
    }

    public function family(string $id): ComponentFamily
    {
        return $this->compiledGraph()->families->families[$id]
            ?? throw new InvalidArgumentException('Unknown Flex component family: '.$id);
    }

    public function surface(string $component): ComponentSurface
    {
        return $this->compiledGraph()->families->surfaces[$component]
            ?? throw new InvalidArgumentException('Component has no declared Flex family surface: '.$component);
    }

    public function childSlot(string $parent, string $slot): ResolvedChildSlot
    {
        return $this->compiledGraph()->childSlot($parent, $slot);
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    public function definition(string $id): ComponentDefinition
    {
        return $this->definitions[$id] ?? throw new InvalidArgumentException('Unknown Flex component: '.$id);
    }

    /** @return array<string, ComponentDefinition> */
    public function all(): array
    {
        $this->validate();

        return $this->definitions;
    }

    public function validate(): void
    {
        $this->compiledGraph();
    }

    /** @return list<string> */
    public function stylesFor(string $id): array
    {
        return $this->compiledGraph()->stylesFor($id);
    }

    /** Full composition follows runtime edges; stylesFrom remains CSS-only.
     * @return list<string>
     */
    public function runtimeComponents(string $id): array
    {
        return $this->compiledGraph()->runtimeComponents($id);
    }

    /**
     * @param  list<string>  $capabilities
     * @return list<string>
     */
    public function rootsFor(string $id, array $capabilities = []): array
    {
        if (! array_is_list($capabilities) || count($capabilities) > 128) {
            throw new InvalidArgumentException('Invalid component capabilities.');
        }
        $this->definition($id);
        $roots = [$id => true];
        foreach ($capabilities as $capability) {
            if (! is_string($capability)) {
                throw new InvalidArgumentException('Invalid component capability.');
            }
            $dependencies = $this->definition($id)->capabilities[$capability] ?? throw new InvalidArgumentException('Unsupported capability for '.$id);
            foreach ($dependencies as $dependency) {
                $roots[$dependency] = true;
            }
        }

        return array_keys($roots);
    }
}
