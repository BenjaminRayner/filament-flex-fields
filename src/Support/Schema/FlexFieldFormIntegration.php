<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldSectionType;
use Bjanczak\FilamentFlexFields\Support\Enterprise\FieldRbacMatrix;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

final class FlexFieldFormIntegration
{
    private ?string $modelClass = null;

    private ?object $context = null;

    private ?string $tenantId = null;

    private ?string $rbacUserKey = null;

    private string $statePathPrefix = '';

    private ?string $sectionLabel = null;

    private bool $collapsible = false;

    /** @var list<string>|null */
    private ?array $onlySlugs = null;

    public function __construct(
        private readonly FlexFieldSchemaResolver $resolver,
        private readonly FlexFieldFormBuilder $formBuilder,
    ) {}

    public function forModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    public function record(?object $record): self
    {
        $this->context = $record;

        return $this;
    }

    public function tenantId(?string $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function rbacUserKey(?string $userKey): self
    {
        $this->rbacUserKey = $userKey;

        return $this;
    }

    public function statePath(string $path): self
    {
        $this->statePathPrefix = $path;

        return $this;
    }

    public function sectionLabel(?string $label): self
    {
        $this->sectionLabel = $label;

        return $this;
    }

    public function collapsible(bool $collapsible = true): self
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * @param  list<string>  $slugs
     */
    public function only(array $slugs): self
    {
        $this->onlySlugs = array_values($slugs);

        return $this;
    }

    /**
     * @return list<FlexFieldDefinition>
     */
    public function definitions(): array
    {
        $modelClass = $this->resolveModelClass();

        return $this->resolver->definitionsForModel(
            $modelClass,
            $this->resolveTenantId(),
            $this->resolveRbacUserKey(),
            $this->onlySlugs,
            FieldRbacMatrix::ABILITY_EDIT,
        );
    }

    /**
     * @return list<Component|Section>
     */
    public function layout(): array
    {
        $sections = $this->resolver->sectionsForTarget(
            $this->resolveModelClass(),
            $this->resolveTenantId(),
        );

        if ($sections === []) {
            return [$this->section()];
        }

        $definitions = collect($this->definitions());
        $prefix = $this->statePathPrefix !== ''
            ? $this->statePathPrefix
            : FlexFieldsConfig::getValuesColumn();
        $record = FlexFieldSectionLayoutHelper::resolveRecord($this->context);

        $layout = [];

        foreach ($sections as $sectionDefinition) {
            $sectionFields = $definitions
                ->filter(fn (FlexFieldDefinition $definition): bool => $definition->sectionId === $sectionDefinition->id)
                ->values()
                ->all();

            if ($sectionFields === []) {
                continue;
            }

            $built = $this->formBuilder->build($sectionFields, $prefix, $this->resolveRbacUserKey(), $this->resolveTenantId());

            if ($sectionDefinition->type === FlexFieldSectionType::Headless) {
                FlexFieldSectionLayoutHelper::applySectionVisibilityToComponents($built, $sectionDefinition, $prefix, $record);
                array_push($layout, ...$built);

                continue;
            }

            $section = Section::make($sectionDefinition->label)
                ->schema($built);

            if ($sectionDefinition->type === FlexFieldSectionType::Fieldset) {
                $section->compact();
            }

            if ($this->collapsible && $sectionDefinition->type === FlexFieldSectionType::Section) {
                $section->collapsible();
            }

            if ($sectionDefinition->description !== null) {
                $section->description($sectionDefinition->description);
            }

            FlexFieldSectionLayoutHelper::applySectionVisibilityToSection($section, $sectionDefinition, $prefix, $record);

            $layout[] = $section;
        }

        $ungrouped = $definitions
            ->filter(fn (FlexFieldDefinition $definition): bool => $definition->sectionId === null)
            ->values()
            ->all();

        if ($ungrouped !== []) {
            $ungroupedSection = Section::make(fn () => $this->sectionLabel ?? __('filament-flex-fields::default.schema.custom_fields_section'))
                ->schema($this->formBuilder->build($ungrouped, $prefix, $this->resolveRbacUserKey(), $this->resolveTenantId()));

            if ($this->collapsible) {
                $ungroupedSection->collapsible();
            }

            $layout[] = $ungroupedSection;
        }

        return $layout;
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        $prefix = $this->statePathPrefix !== ''
            ? $this->statePathPrefix
            : FlexFieldsConfig::getValuesColumn();

        return $this->formBuilder->build(
            $this->definitions(),
            $prefix,
            $this->resolveRbacUserKey(),
            $this->resolveTenantId(),
        );
    }

    public function section(): Section
    {
        $section = Section::make(fn () => $this->sectionLabel ?? __('filament-flex-fields::default.schema.custom_fields_section'))
            ->schema($this->components());

        if ($this->collapsible) {
            $section->collapsible();
        }

        return $section;
    }

    private function resolveModelClass(): string
    {
        if ($this->modelClass !== null) {
            return $this->modelClass;
        }

        if ($this->context !== null) {
            return $this->context::class;
        }

        return FlexFieldsConfig::getSchemaDefaultTargetType();
    }

    private function resolveTenantId(): ?string
    {
        if ($this->tenantId !== null) {
            return filled($this->tenantId) ? $this->tenantId : null;
        }

        return $this->resolver->resolveTenantId($this->context);
    }

    private function resolveRbacUserKey(): ?string
    {
        if ($this->rbacUserKey !== null) {
            return filled($this->rbacUserKey) ? $this->rbacUserKey : null;
        }

        return $this->resolver->resolveRbacUserKey($this->context);
    }
}
