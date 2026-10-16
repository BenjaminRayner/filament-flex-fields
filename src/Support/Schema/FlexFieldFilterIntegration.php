<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Filament\Tables\Filters\Filter;

final class FlexFieldFilterIntegration
{
    private ?string $modelClass = null;

    private ?object $context = null;

    private ?string $tenantId = null;

    private ?string $rbacUserKey = null;

    private string $valuesColumn = '';

    /** @var list<string>|null */
    private ?array $onlySlugs = null;

    public function __construct(
        private readonly FlexFieldSchemaResolver $resolver,
        private readonly FlexFieldFilterBuilder $filterBuilder,
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

    public function valuesColumn(string $column): self
    {
        $this->valuesColumn = $column;

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
     * @return list<Filter>
     */
    public function filters(): array
    {
        $modelClass = $this->modelClass ?? ($this->context !== null ? $this->context::class : FlexFieldsConfig::getSchemaDefaultTargetType());

        $definitions = $this->resolver->definitionsForModel(
            $modelClass,
            $this->tenantId ?? $this->resolver->resolveTenantId($this->context),
            $this->rbacUserKey ?? $this->resolver->resolveRbacUserKey($this->context),
            $this->onlySlugs,
        );

        return $this->filterBuilder->build($definitions, $this->valuesColumn);
    }
}
