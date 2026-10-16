<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ManifestIdentity;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use InvalidArgumentException;
use ReflectionClass;

/** A render edge, not an unconditional eager asset dependency. */
final readonly class ChildSlot
{
    /** @var array<string, string> */
    public array $configurationMap;

    /** @var list<string> */
    public array $capabilities;

    /**
     * @param  class-string<ChildFactory>|null  $factory
     * @param  class-string|null  $renderer
     * @param  array<string, string>  $configurationMap  Parent option => child option.
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $name,
        public string $component,
        public ComponentKind $kind,
        public ?string $renderer = null,
        public ?string $factory = null,
        public ?ConfigurationSchema $configuration = null,
        array $configurationMap = [],
        array $capabilities = [],
        public bool $shareState = false,
    ) {
        ManifestIdentity::assert($component);
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1 || ($factory !== null && (strlen($factory) > 512 || ! is_a($factory, ChildFactory::class, true)
            || ! (new ReflectionClass($factory))->isInstantiable())) || count($configurationMap) > 128
            || ! array_is_list($capabilities) || count($capabilities) > 128 || count(array_unique($capabilities, SORT_REGULAR)) !== count($capabilities)) {
            throw new InvalidArgumentException('Invalid typed child slot contract.');
        }
        if ($renderer !== null) {
            ComponentSurface::assertRendererClass($kind, $renderer);
        }
        $targets = [];
        foreach ($configurationMap as $source => $target) {
            if (! is_string($source) || preg_match('/^[a-z][a-zA-Z0-9_]{0,95}$/D', $source) !== 1
                || ! is_string($target) || preg_match('/^[a-z][a-zA-Z0-9_]{0,95}$/D', $target) !== 1
                || ($configuration !== null && ! isset($configuration->options[$target])) || isset($targets[$target])) {
                throw new InvalidArgumentException('Child configuration mapping needs explicit unique option names.');
            }
            $targets[$target] = true;
        }
        foreach ($capabilities as $capability) {
            if (! is_string($capability)) {
                throw new InvalidArgumentException('Child capability must be an identity.');
            }
            ManifestIdentity::assert($capability);
        }
        $this->configurationMap = array_map(static fn (string $option): string => $option, $configurationMap);
        $this->capabilities = array_map(static fn (string $capability): string => $capability, $capabilities);
    }

    public function resolve(ComponentDefinition $child): ResolvedChildSlot
    {
        $contract = $child->childContract;
        if ($contract !== null && $this->renderer !== null && $this->factory !== null && $this->configuration !== null) {
            if ($this->renderer !== $contract->renderer) {
                throw new InvalidArgumentException('Explicit legacy child renderer differs from its registered contract.');
            }
            foreach ($contract->configuration->options as $name => $option) {
                if ($option->locked && (($this->configuration->options[$name] ?? null)?->export() !== $option->export())) {
                    throw new InvalidArgumentException('Explicit child configuration cannot remove a registered locked option.');
                }
            }
            $lockedBindings = array_filter($contract->bindings, fn (array $options): bool => (bool) array_filter($options, fn (string $name): bool => $contract->configuration->options[$name]->locked));
            $contract = new ChildComponentContract($child->kind, $this->renderer, $this->configuration, $this->factory, $lockedBindings);
        }
        if ($contract !== null) {
            if (($this->renderer !== null && $this->renderer !== $contract->renderer)
                || ($this->factory !== null && $this->factory !== $contract->factory)
                || ($this->configuration !== null && $this->configuration->export() !== $contract->configuration->export())) {
                throw new InvalidArgumentException('A parent cannot replace the registered child configuration contract.');
            }
        } else {
            $renderer = $this->renderer ?? ($child->surface !== null ? $child->surface->class : throw new InvalidArgumentException('Child needs a registered or explicit renderer.'));
            $contract = new ChildComponentContract($child->kind, $renderer, $this->configuration ?? new ConfigurationSchema, $this->factory ?? FluentChildFactory::class);
        }
        if ($this->kind !== $child->kind || $contract->kind !== $this->kind || ($child->surface !== null && $child->surface->class !== $contract->renderer)) {
            throw new InvalidArgumentException('Child renderer must match its registered surface kind and class.');
        }
        foreach ($this->configurationMap as $target) {
            if (! isset($contract->configuration->options[$target])) {
                throw new InvalidArgumentException('Child mapping references an unknown registered option.');
            }
        }

        return new ResolvedChildSlot($this, $contract);
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        $map = $this->configurationMap;
        ksort($map, SORT_STRING);

        return ['name' => $this->name, 'component' => $this->component, 'kind' => $this->kind->value,
            'renderer' => $this->renderer, 'factory' => $this->factory, 'configuration' => $this->configuration?->export(),
            'configurationMap' => $map, 'capabilities' => $this->capabilities, 'shareState' => $this->shareState];
    }
}
