<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise;

use Bjanczak\FilamentFlexFields\Models\FlexFieldSchemaVersion;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\DatabaseSchemaVersionStore;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\SchemaApprovalWorkflow;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\SchemaMemoryState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\SchemaVersionConflict;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaImportExport;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SchemaRegistry
{
    public const string STATE_DRAFT = 'draft';

    public const string STATE_REVIEW = 'review';

    public const string STATE_LIVE = 'live';

    public const string STATE_APPROVED = 'approved';

    private static ?SchemaImportExport $importExport = null;

    private static function state(?string $connectionName = null): SchemaMemoryState
    {
        $state = RequestState::resolve(SchemaMemoryState::class);

        return $connectionName === null ? $state : ($state->connections[$connectionName] ??= new SchemaMemoryState);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function publish(
        string $schemaId,
        array $schema,
        ?string $actor = null,
        string $state = self::STATE_DRAFT,
        ?int $flexFieldGroupId = null,
        ?string $connectionName = null,
        ?int $expectedVersion = null,
    ): int {
        self::assertSchemaId($schemaId);
        self::assertApprovalState($state);
        DatabaseSchemaVersionStore::assertExpectedVersion($expectedVersion);
        self::importExport()->assertValid($schema);

        $checksum = self::checksum($schema);
        $publishedAt = now()->toIso8601String();

        if (self::usesDatabase($connectionName)) {
            return (new DatabaseSchemaVersionStore($connectionName))->publish($schemaId, $schema, $checksum, $actor, $state, $flexFieldGroupId, $expectedVersion);
        }

        $last = self::state($connectionName)->counters[$schemaId] ?? 0;
        if ($expectedVersion !== null && $expectedVersion !== $last) {
            throw new SchemaVersionConflict($expectedVersion, $last);
        }
        if ($last >= 4294967295) {
            throw new RuntimeException('Schema version counter is exhausted.');
        }
        $version = $last + 1;
        self::state($connectionName)->counters[$schemaId] = $version;

        self::state($connectionName)->versions[$schemaId][] = [
            'version' => $version,
            'schema' => $schema,
            'checksum' => $checksum,
            'actor' => $actor,
            'state' => $state,
            'published_at' => $publishedAt,
        ];

        self::retireMemoryLiveVersions($schemaId, $version, $state, $connectionName);

        return $version;
    }

    /** Last issued version, including purged history; zero means no publication. */
    public static function currentVersion(string $schemaId, ?string $connectionName = null): int
    {
        self::assertSchemaId($schemaId);
        if (self::usesDatabase($connectionName)) {
            return (new DatabaseSchemaVersionStore($connectionName))->currentVersion($schemaId);
        }

        return self::state($connectionName)->counters[$schemaId] ?? 0;
    }

    /**
     * @return list<array{version: int, schema: array<string, mixed>, checksum: string, actor: ?string, state: string, published_at: string}>
     */
    public static function versions(string $schemaId, ?string $connectionName = null): array
    {
        self::assertSchemaId($schemaId);

        if (self::usesDatabase($connectionName)) {
            return (new DatabaseSchemaVersionStore($connectionName))->versions($schemaId);
        }

        return self::state($connectionName)->versions[$schemaId] ?? [];
    }

    /**
     * @return array{version: int, schema: array<string, mixed>, checksum: string, actor: ?string, state: string, published_at: string}
     */
    public static function rollback(string $schemaId, int $version, ?int $flexFieldGroupId = null, ?string $actor = null, ?string $connectionName = null, ?int $expectedVersion = null): array
    {
        $record = self::findVersion($schemaId, $version, $connectionName);

        if ($record === null) {
            throw new InvalidArgumentException("Schema [{$schemaId}] has no version [{$version}].");
        }

        $newVersion = self::publish(
            $schemaId,
            $record['schema'],
            $actor,
            self::STATE_LIVE,
            $flexFieldGroupId,
            $connectionName,
            $expectedVersion,
        );

        return self::findVersion($schemaId, $newVersion, $connectionName)
            ?? throw new RuntimeException('Published rollback version could not be read.');
    }

    public static function setApprovalState(string $schemaId, int $version, string $state, ?string $connectionName = null): void
    {
        self::assertSchemaId($schemaId);
        self::assertApprovalState($state);

        if (self::usesDatabase($connectionName)) {
            (new DatabaseSchemaVersionStore($connectionName))->transition($schemaId, $version, $state);

            return;
        }

        $index = self::memoryVersionIndex($schemaId, $version, $connectionName);

        if ($index === null) {
            throw new InvalidArgumentException("Schema [{$schemaId}] has no version [{$version}].");
        }

        SchemaApprovalWorkflow::assertTransition(self::state($connectionName)->versions[$schemaId][$index]['state'], $state);
        self::state($connectionName)->versions[$schemaId][$index]['state'] = $state;
        self::retireMemoryLiveVersions($schemaId, $version, $state, $connectionName);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function checksum(array $schema): string
    {
        return self::importExport()->checksum($schema);
    }

    public static function clear(): void
    {
        self::state()->versions = [];
        self::state()->counters = [];
        self::state()->connections = [];
        self::$importExport = null;
        self::state()->availableTables = [];
    }

    public static function resetRuntimeCache(): void
    {
        self::clear();
    }

    /** Explicit scoped data deletion; never called by runtime cache reset. */
    public static function purgeHistory(string $schemaId, ?string $connectionName = null): void
    {
        self::assertSchemaId($schemaId);

        if (self::usesDatabase($connectionName)) {
            (new DatabaseSchemaVersionStore($connectionName))->purge($schemaId);
        }

        unset(self::state($connectionName)->versions[$schemaId]);
    }

    private static function retireMemoryLiveVersions(string $id, int $version, string $state, ?string $connectionName): void
    {
        if ($state !== self::STATE_LIVE) {
            return;
        }

        $versions = self::state($connectionName)->versions[$id] ?? [];

        foreach ($versions as $index => $record) {
            if ($record['version'] !== $version && $record['state'] === self::STATE_LIVE) {
                $record['state'] = self::STATE_APPROVED;
                $versions[$index] = $record;
            }
        }

        self::state($connectionName)->versions[$id] = $versions;
    }

    public static function usesDatabase(?string $connectionName = null): bool
    {
        if (! self::persistenceEnabled()) {
            return false;
        }

        if (! self::tableExists($connectionName)) {
            throw new RuntimeException('Persistent schema registry requires the schema versions and schema heads migrations.');
        }

        return true;
    }

    private static function persistenceEnabled(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return false;
            }

            return (bool) config('filament-flex-fields.schema.registry_persistence', true);
        } catch (Throwable) {
            return false;
        }
    }

    private static function tableExists(?string $connectionName): bool
    {
        if (! function_exists('app') || ! app()->bound('db')) {
            return false;
        }

        $connection = (new FlexFieldSchemaVersion)->setConnection($connectionName)->getConnection();
        $key = json_encode([$connection->getName(), $connection->getDatabaseName()], JSON_THROW_ON_ERROR);

        if (isset(self::state($connectionName)->availableTables[$key])) {
            return true;
        }

        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasTable('flex_field_schema_versions') || ! $schema->hasTable('flex_field_schema_heads')) {
            return false;
        }

        return self::state($connectionName)->availableTables[$key] = true;
    }

    private static function assertSchemaId(string $id): void
    {
        if (! mb_check_encoding($id, 'UTF-8') || trim($id) === '' || mb_strlen($id) > 255 || str_contains($id, "\0")) {
            throw new InvalidArgumentException('Schema id must contain between 1 and 255 characters and no NUL byte.');
        }
    }

    private static function importExport(): SchemaImportExport
    {
        return self::$importExport ??= new SchemaImportExport;
    }

    private static function assertApprovalState(string $state): void
    {
        SchemaApprovalWorkflow::assertState($state);
    }

    /**
     * @return array{version: int, schema: array<string, mixed>, checksum: string, actor: ?string, state: string, published_at: string}|null
     */
    private static function findVersion(string $schemaId, int $version, ?string $connectionName = null): ?array
    {
        if (self::usesDatabase($connectionName)) {
            return (new DatabaseSchemaVersionStore($connectionName))->find($schemaId, $version);
        }

        $index = self::memoryVersionIndex($schemaId, $version, $connectionName);

        if ($index === null) {
            return null;
        }

        return self::state($connectionName)->versions[$schemaId][$index];
    }

    private static function memoryVersionIndex(string $schemaId, int $version, ?string $connectionName): ?int
    {
        foreach (self::state($connectionName)->versions[$schemaId] ?? [] as $index => $record) {
            if ($record['version'] === $version) {
                return $index;
            }
        }

        return null;
    }
}
