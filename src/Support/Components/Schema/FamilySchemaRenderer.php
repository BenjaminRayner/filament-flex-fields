<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/** One adapter/renderer path for optional family surfaces, never a switch over vendor class names. */
final class FamilySchemaRenderer
{
    public function __construct(private readonly ComponentRegistry $registry) {}

    private function adapter(ComponentFamily $family): FamilySchemaAdapter
    {
        $schema = $family->schema ?? throw new InvalidArgumentException('Family has no dynamic schema adapter: '.$family->id);

        return app()->build($schema->adapter);
    }

    public function normalize(FamilySchemaDefinition $definition): FamilySchemaDefinition
    {
        $family = $this->registry->family($definition->family);
        if ($definition->configurationVersion > $family->configurationVersion || $definition->valueVersion !== $family->valueVersion) {
            throw new InvalidArgumentException('Unsupported family configuration/value version: '.$family->id);
        }
        $config = $definition->config;
        if ($definition->configurationVersion !== $family->configurationVersion) {
            $config = $this->adapter($family)->migrateConfiguration($config, $definition->configurationVersion, $family->configurationVersion);
        }
        $schema = $family->schema ?? throw new InvalidArgumentException('Family has no dynamic schema adapter: '.$family->id);
        $schema->configuration->validateLayer($config);

        return FamilySchemaDefinition::fromArray(array_replace($definition->export(), [
            'config' => $config, 'configurationVersion' => $family->configurationVersion,
        ]));
    }

    public function resolve(FamilySchemaDefinition $definition, FamilyRenderContext $context): ResolvedFamilySchema
    {
        $definition = $this->normalize($definition);
        $family = $this->registry->family($definition->family);
        $schema = $family->schema ?? throw new InvalidArgumentException('Family has no dynamic schema adapter.');
        $adapter = $this->adapter($family);
        if (! $adapter->authorize($definition, $context)) {
            throw new AuthorizationException('Family surface is not authorized.');
        }
        $config = $schema->configuration->resolve([$schema->packageDefaults, $context->applicationDefaults, $context->panelDefaults, $definition->config]);

        return new ResolvedFamilySchema($family, $definition, $adapter, $context, $config);
    }

    public function make(ComponentKind $kind, FamilySchemaDefinition $definition, FamilyRenderContext $context): Field|Column|Entry
    {
        if (! in_array($kind, [ComponentKind::Field, ComponentKind::Column, ComponentKind::Entry], true)) {
            throw new InvalidArgumentException('Dynamic schema renderer accepts field, column or entry.');
        }
        $resolved = $this->resolve($definition, $context);
        $definition = $resolved->definition;
        $family = $resolved->family;
        $id = $family->surfaces[$kind->value] ?? throw new InvalidArgumentException('Family does not offer '.$kind->value.': '.$family->id);
        $class = $this->registry->surface($id)->class;
        $statePath = $context->statePath($definition->slug);
        $surface = $class::make($kind === ComponentKind::Field ? $statePath : 'flex_'.$definition->slug);
        $expected = match ($kind) {
            ComponentKind::Field => Field::class, ComponentKind::Column => Column::class, ComponentKind::Entry => Entry::class
        };
        if (! $surface instanceof $expected) {
            throw new InvalidArgumentException('Family renderer returned the wrong surface kind.');
        }
        if ($surface instanceof Column || $surface instanceof Entry) {
            $surface->getStateUsing(static fn (mixed $record): mixed => data_get($record, $statePath));
        }
        $resolved->adapter->configure($surface, $definition, $context, $resolved->configuration);
        $surface->label($definition->label);
        if ($surface instanceof Field) {
            $rules = $resolved->rules();
            $surface->statePath($statePath)->key($context->identity($id, $definition->slug))
                ->id(null)->rules($rules);
            if ($definition->isRequired) {
                $surface->required();
            }
            if (! $definition->isVisible) {
                $surface->visible(false);
            }
            if ($definition->isDisabled || $definition->isReadOnly) {
                $surface->disabled()->dehydrated(false)->validatedWhenNotDehydrated(false);
            }
        }

        return $surface;
    }
}
