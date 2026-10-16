<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\SubmissionPath;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Explicit stored-data conversion; errors cannot mutate the source or write a record. */
final class FamilySchemaMigration
{
    public function __construct(private readonly ComponentRegistry $registry, private readonly FamilySchemaRenderer $renderer) {}

    /** @param Closure(FamilySchemaDefinition, FamilyRenderContext): bool $authorizeMigration Application record/configure/migrate policy. */
    public function prepare(FamilyValueSnapshot $snapshot, FamilyRenderContext $context, Closure $authorizeMigration): FamilySchemaMigrationResult
    {
        return $this->prepareMany([$snapshot], $context, $authorizeMigration)[0];
    }

    /**
     * @param  list<FamilyValueSnapshot>  $snapshots
     * @param  Closure(FamilySchemaDefinition, FamilyRenderContext): bool  $authorizeMigration
     * @return list<FamilySchemaMigrationResult>
     */
    public function prepareMany(array $snapshots, FamilyRenderContext $context, Closure $authorizeMigration): array
    {
        if (! array_is_list($snapshots) || count($snapshots) > 256) {
            throw new InvalidArgumentException('Family migration requires a bounded snapshot list.');
        }
        $tree = new SubmissionPath;
        $envelopes = [];
        foreach ($snapshots as $snapshot) {
            if (! $snapshot instanceof FamilyValueSnapshot) {
                throw new InvalidArgumentException('Family migration requires typed stored snapshots.');
            }
            $tree->add($context->statePath($snapshot->definition->slug));
            $envelopes[] = ['definition' => $snapshot->definition->export(), 'value' => $snapshot->value];
        }
        FieldValueCodec::assertCompatible($envelopes);
        $stages = [];
        foreach ($snapshots as $snapshot) {
            $stages[] = $this->stage($snapshot, $context, $authorizeMigration);
        }
        $payload = [];
        $rules = [];
        $results = [];
        foreach ($stages as [$snapshot, $resolved, $adapter, $valueRules]) {
            $source = $snapshot->definition;
            $target = $resolved->definition;
            $value = $snapshot->value;
            if ($source->valueVersion !== $target->valueVersion) {
                if (! $adapter instanceof FamilyValueMigration) {
                    throw new InvalidArgumentException('Missing declared stored value migration.');
                }
                $value = $adapter->migrateStoredValue($value, $source, $target, $context);
            }
            $after = new FamilyValueSnapshot($target, $value);
            FamilyValueSnapshotCodec::encode($after);
            $path = $context->statePath($target->slug);
            data_set($payload, $path, $after->value);
            $rules[$path] = $valueRules;
            $results[] = new FamilySchemaMigrationResult($snapshot, $after);
        }
        FieldValueCodec::assertCompatible($payload);
        $validated = Validator::make($payload, $rules)->validate();
        foreach (array_keys($rules) as $path) {
            if (! Arr::has($validated, $path)) {
                throw new InvalidArgumentException('Stored family migration rules cannot discard the value.');
            }
        }

        return $results;
    }

    /**
     * @param  Closure(FamilySchemaDefinition, FamilyRenderContext): bool  $authorizeMigration
     * @return array{FamilyValueSnapshot, ResolvedFamilySchema, FamilySchemaAdapter, list<string|object>}
     */
    private function stage(FamilyValueSnapshot $snapshot, FamilyRenderContext $context, Closure $authorizeMigration): array
    {
        FamilyValueSnapshotCodec::encode($snapshot);
        $source = $snapshot->definition;
        $family = $this->registry->family($source->family);
        $schema = $family->schema ?? throw new InvalidArgumentException('Family has no dynamic schema adapter: '.$family->id);
        $adapter = app()->build($schema->adapter);
        if (! $this->authorized($authorizeMigration, $source, $context) || ! $adapter->authorize($source, $context)) {
            throw new AuthorizationException('Stored family migration is not authorized.');
        }
        $config = $source->config;
        if ($source->configurationVersion !== $family->configurationVersion) {
            $config = $adapter->migrateConfiguration($config, $source->configurationVersion, $family->configurationVersion);
        }
        $target = FamilySchemaDefinition::fromArray(array_replace($source->export(), [
            'config' => $config, 'configurationVersion' => $family->configurationVersion, 'valueVersion' => $family->valueVersion,
        ]));
        $resolved = $this->renderer->resolve($target, $context);
        if (! $this->authorized($authorizeMigration, $target, $context)) {
            throw new AuthorizationException('Migrated family configuration is not authorized.');
        }
        if ($source->valueVersion !== $target->valueVersion) {
            if (! $adapter instanceof FamilyValueMigration || ! $adapter->supportsValueMigration($source->valueVersion, $target->valueVersion)) {
                throw new InvalidArgumentException('No declared stored value migration for family: '.$source->family);
            }
        }

        return [$snapshot, $resolved, $adapter, $resolved->rules(requireValueRules: true)];
    }

    /** @param Closure(FamilySchemaDefinition, FamilyRenderContext): bool $policy */
    private function authorized(Closure $policy, FamilySchemaDefinition $definition, FamilyRenderContext $context): bool
    {
        $allowed = $policy($definition, $context);
        if (! is_bool($allowed)) {
            throw new InvalidArgumentException('Family migration policy must return a boolean.');
        }

        return $allowed;
    }
}
