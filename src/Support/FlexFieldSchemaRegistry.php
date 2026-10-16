<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Data\FlexFieldSchema;
use Closure;

class FlexFieldSchemaRegistry
{
    /** @var array<string, FlexFieldSchema> */
    protected array $schemas = [];

    /** @var array<string, list<FlexFieldSchema>> */
    protected array $targetSchemasCache = [];

    /** @var array<string, list<FlexFieldDefinition>> */
    protected array $targetFieldsCache = [];

    public function __construct(
        protected FlexFieldSchemaMigrator $migrator = new FlexFieldSchemaMigrator,
    ) {}

    /** @var Closure(string): mixed|null */
    private ?Closure $targetLoader = null;

    /** @var Closure(string): mixed|null */
    private ?Closure $keyLoader = null;

    /** @var array<string, bool> */
    private array $loadedTargets = [];

    /** @var array<string, bool> */
    private array $loadedKeys = [];

    /** @param Closure(string): mixed|null $targetLoader
     * @param  Closure(string): mixed|null  $keyLoader
     */
    public function loadUsing(?Closure $targetLoader, ?Closure $keyLoader = null): self
    {
        $this->targetLoader = $targetLoader;
        $this->keyLoader = $keyLoader;
        $this->loadedTargets = [];
        $this->loadedKeys = [];
        $this->targetSchemasCache = [];
        $this->targetFieldsCache = [];

        return $this;
    }

    public function register(FlexFieldSchema $schema): self
    {
        $this->schemas[$schema->key] = $schema;

        $this->targetSchemasCache = [];
        $this->targetFieldsCache = [];

        return $this;
    }

    public function unregister(string $key): self
    {
        unset($this->schemas[$key]);

        $this->targetSchemasCache = [];
        $this->targetFieldsCache = [];

        return $this;
    }

    /**
     * @param  array<string, array<string, mixed>>  $schemas
     */
    public function registerFromConfig(array $schemas): self
    {
        $prepared = [];
        foreach ($schemas as $key => $schema) {
            $schema = $this->migrator->migrate($schema);
            $prepared[] = FlexFieldSchema::make($key, (string) $schema['target'])
                ->label($schema['label'] ?? null)
                ->active((bool) ($schema['active'] ?? true))
                ->sort((int) ($schema['sort'] ?? 0))
                ->version((int) ($schema['version'] ?? FlexFieldSchema::CURRENT_VERSION))
                ->sections($schema['sections'] ?? [])
                ->fields($schema['fields'] ?? []);
        }
        // A malformed later definition must not leave an earlier one installed.
        foreach ($prepared as $schema) {
            $this->register($schema);
        }

        return $this;
    }

    public function find(string $key): ?FlexFieldSchema
    {
        if ($this->keyLoader !== null && ! isset($this->loadedKeys[$key])) {
            $this->loadedKeys[$key] = true;

            try {
                ($this->keyLoader)($key);
            } catch (\Throwable $exception) {
                unset($this->loadedKeys[$key]);

                throw $exception;
            }
        }

        return $this->schemas[$key] ?? null;
    }

    /**
     * @return list<FlexFieldSchema>
     */
    public function forTarget(string $targetType, bool $includeInactive = false): array
    {
        if ($this->targetLoader !== null && ! isset($this->loadedTargets[$targetType])) {
            $this->loadedTargets[$targetType] = true;

            try {
                ($this->targetLoader)($targetType);
            } catch (\Throwable $exception) {
                unset($this->loadedTargets[$targetType]);

                throw $exception;
            }
        }

        if ($includeInactive) {
            return array_values(array_filter($this->schemas, static fn (FlexFieldSchema $schema): bool => $schema->targetType === $targetType));
        }

        if (isset($this->targetSchemasCache[$targetType])) {
            return $this->targetSchemasCache[$targetType];
        }

        return $this->targetSchemasCache[$targetType] = array_values(collect($this->schemas)
            ->filter(fn (FlexFieldSchema $schema): bool => $schema->isActive && $schema->targetType === $targetType)
            ->sortBy('sort')
            ->values()
            ->all());
    }

    /**
     * @return list<FlexFieldDefinition>
     */
    public function fieldsForTarget(string $targetType): array
    {
        if (isset($this->targetFieldsCache[$targetType])) {
            return $this->targetFieldsCache[$targetType];
        }

        return $this->targetFieldsCache[$targetType] = array_values(collect($this->forTarget($targetType))
            ->flatMap(fn (FlexFieldSchema $schema): array => $schema->getFields())
            ->sortBy('sort')
            ->values()
            ->all());
    }
}
