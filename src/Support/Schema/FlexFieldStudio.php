<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;

final class FlexFieldStudio
{
    public function __construct(
        private readonly FlexFieldSchemaResolver $resolver,
        private readonly FlexFieldFormBuilder $formBuilder,
        private readonly FlexFieldTableBuilder $tableBuilder,
        private readonly FlexFieldInfolistBuilder $infolistBuilder,
    ) {}

    public function form(): FlexFieldFormIntegration
    {
        return new FlexFieldFormIntegration($this->resolver, $this->formBuilder);
    }

    public function table(): FlexFieldTableIntegration
    {
        return new FlexFieldTableIntegration($this->resolver, $this->tableBuilder);
    }

    public function infolist(): FlexFieldInfolistIntegration
    {
        return new FlexFieldInfolistIntegration($this->resolver, $this->infolistBuilder);
    }

    public function filters(): FlexFieldFilterIntegration
    {
        return new FlexFieldFilterIntegration($this->resolver, app(FlexFieldFilterBuilder::class));
    }

    /**
     * @return list<FlexFieldDefinition>
     */
    public function definitionsForModel(string $modelClass, ?object $context = null, ?array $onlySlugs = null): array
    {
        return $this->resolver->definitionsForModel(
            $modelClass,
            $this->resolver->resolveTenantId($context),
            $this->resolver->resolveRbacUserKey($context),
            $onlySlugs,
        );
    }
}
