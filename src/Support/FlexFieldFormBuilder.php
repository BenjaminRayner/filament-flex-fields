<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaRenderer;
use Bjanczak\FilamentFlexFields\Support\Enterprise\FieldRbacMatrix;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\AppliesFieldDefinitionMeta;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\FieldComponentFactory;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldSchemaResolver;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

class FlexFieldFormBuilder
{
    use AppliesFieldDefinitionMeta;

    public function __construct(
        private readonly FieldComponentFactory $factory = new FieldComponentFactory,
    ) {}

    /**
     * @param  iterable<FlexFieldDefinition|FamilySchemaDefinition>  $definitions
     * @return list<Component>
     */
    public function build(
        iterable $definitions,
        string $statePathPrefix = 'flex_field_values',
        ?string $rbacUserKey = null,
        ?string $tenantId = null,
        ?FamilyRenderContext $familyContext = null,
    ): array {
        $activeDefinitions = [];
        $componentsBySlug = [];

        foreach ($definitions as $definition) {
            if (! $definition->isActive || ($definition instanceof FlexFieldDefinition
                ? $this->shouldSkipDefinition($definition, $rbacUserKey, $tenantId) : ! $definition->isVisible)) {
                continue;
            }

            $activeDefinitions[] = $definition;
            if (isset($componentsBySlug[$definition->slug])) {
                throw new \InvalidArgumentException('Duplicate schema state key: '.$definition->slug);
            }
            $component = $this->makeComponent($definition, $statePathPrefix, $definition instanceof FamilySchemaDefinition
                ? ($familyContext ?? new FamilyRenderContext('form:'.$statePathPrefix, $statePathPrefix, userKey: $rbacUserKey, tenantId: $tenantId))
                : null);

            if ($component !== null) {
                $componentsBySlug[$definition->slug] = $component;
            }
        }

        $formulas = [];

        foreach ($activeDefinitions as $definition) {
            if ($definition instanceof FlexFieldDefinition && $definition->hasFormula()) {
                $formulas[$definition->slug] = (string) $definition->formula;
            }
        }

        $this->wireFormulaDependencies($componentsBySlug, $formulas, $statePathPrefix);

        return array_values($componentsBySlug);
    }

    public function makeComponent(FlexFieldDefinition|FamilySchemaDefinition $definition, string $statePathPrefix = 'flex_field_values', ?FamilyRenderContext $familyContext = null): ?Component
    {
        if ($definition instanceof FamilySchemaDefinition) {
            $field = app(FamilySchemaRenderer::class)->make(ComponentKind::Field, $definition,
                $familyContext ?? new FamilyRenderContext('form:'.$statePathPrefix, $statePathPrefix));
            if (! $field instanceof Field) {
                throw new \LogicException('Family field renderer returned a read-only surface.');
            }

            return $field;
        }

        return $this->factory->makeComponent($definition, $statePathPrefix);
    }

    /**
     * Wire live recalculation for formula/calculated fields after components were built
     * via {@see makeComponent()} (e.g. host builders that loop instead of {@see build()}).
     *
     * @param  list<FlexFieldDefinition>  $definitions
     * @param  array<string, Component>  $componentsBySlug
     */
    public function wireFormulaFields(array $definitions, array $componentsBySlug, string $statePathPrefix = ''): void
    {
        $formulas = [];

        foreach ($definitions as $definition) {
            if ($definition->hasFormula()) {
                $formulas[$definition->slug] = (string) $definition->formula;
            }
        }

        $this->wireFormulaDependencies($componentsBySlug, $formulas, $statePathPrefix);
    }

    protected function shouldSkipDefinition(FlexFieldDefinition $definition, ?string $rbacUserKey = null, ?string $tenantId = null): bool
    {
        $resolver = app(FlexFieldSchemaResolver::class);

        if (! $resolver->fieldAllowedForUser($definition, $rbacUserKey, FieldRbacMatrix::ABILITY_EDIT, $tenantId)) {
            return true;
        }

        if ($definition->hasDynamicVisibility()) {
            return false;
        }

        return ! $definition->isVisible;
    }
}
