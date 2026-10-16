<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/** A component owns its reusable option schema; parents reference it without copying mappings. */
final readonly class ChildComponentContract
{
    /** @var array<string, mixed> */
    public array $packageDefaults;

    /** @var array<string, list<string>> */
    public array $bindings;

    /**
     * @param  class-string  $renderer
     * @param  class-string<ChildFactory>  $factory
     * @param  array<string, list<string>>  $bindings  Trusted PHP method => declared option arguments.
     * @param  array<string, mixed>  $packageDefaults
     */
    public function __construct(
        public ComponentKind $kind,
        public string $renderer,
        public ConfigurationSchema $configuration = new ConfigurationSchema,
        public string $factory = FluentChildFactory::class,
        array $bindings = [],
        array $packageDefaults = [],
    ) {
        ComponentSurface::assertRendererClass($kind, $renderer);
        if (strlen($factory) > 512 || ! is_a($factory, ChildFactory::class, true) || ! (new ReflectionClass($factory))->isInstantiable() || count($bindings) > 128) {
            throw new InvalidArgumentException('Child contract requires a bounded typed factory and bindings.');
        }
        $configuration->validateLayer($packageDefaults);
        $this->packageDefaults = ConfigurationSchema::copyArray($packageDefaults);
        $consumed = [];
        foreach ($bindings as $method => $arguments) {
            if (! is_string($method) || ! array_is_list($arguments) || $arguments === [] || count($arguments) > 16) {
                throw new InvalidArgumentException('Child binding needs a method and bounded ordered option arguments.');
            }
            foreach ($arguments as $option) {
                if (! is_string($option) || ! isset($configuration->options[$option])) {
                    throw new InvalidArgumentException('Child binding references an undeclared option.');
                }
                $consumed[$option] = true;
            }
            self::assertMethod($renderer, $method, count($arguments));
        }
        if ($factory === FluentChildFactory::class) {
            foreach ($configuration->options as $name => $option) {
                if (! isset($consumed[$name])) {
                    self::assertMethod($renderer, $name, 1);
                    $bindings[$name] = [$name];
                }
            }
        }
        $this->bindings = array_map(static fn (array $arguments): array => array_map(static fn (string $option): string => $option, $arguments), $bindings);
    }

    /** @param class-string $renderer */
    private static function assertMethod(string $renderer, string $method, int $arguments): void
    {
        if (preg_match('/^[a-z][a-zA-Z0-9_]{0,95}$/D', $method) !== 1 || ! method_exists($renderer, $method)) {
            throw new InvalidArgumentException('Unknown declared child configuration method: '.$method);
        }
        $reflection = new ReflectionMethod($renderer, $method);
        $return = $reflection->getReturnType();
        if (! $reflection->isPublic() || $reflection->isStatic() || ! $return instanceof ReflectionNamedType
            || ! in_array($return->getName(), ['static', 'self'], true)
            || $arguments < $reflection->getNumberOfRequiredParameters()
            || (! $reflection->isVariadic() && $arguments > $reflection->getNumberOfParameters())) {
            throw new InvalidArgumentException('Child configuration binding must call a compatible public fluent method: '.$method);
        }
    }

    /** @param array<string, mixed> $configuration */
    public function apply(Component|Column|Action $child, array $configuration, bool $lockedOnly = false): void
    {
        foreach ($this->bindings as $method => $options) {
            if ($lockedOnly && ! array_filter($options, fn (string $option): bool => $this->configuration->options[$option]->locked)) {
                continue;
            }
            $arguments = [];
            foreach ($options as $option) {
                if (array_key_exists($option, $configuration)) {
                    $arguments[] = $configuration[$option];
                }
            }
            if ($arguments === []) {
                continue;
            }
            if (count($arguments) !== count($options)) {
                throw new InvalidArgumentException('Incomplete child option group for '.$method);
            }
            if ($child !== $child->{$method}(...$arguments)) {
                throw new InvalidArgumentException('Child configuration method must preserve its renderer instance.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        return ['kind' => $this->kind->value, 'renderer' => $this->renderer, 'factory' => $this->factory,
            'configuration' => $this->configuration->export(), 'bindings' => $this->bindings,
            'packageDefaults' => ConfigurationSchema::canonicalData($this->packageDefaults)];
    }
}
