<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise\Schema;

use Bjanczak\FilamentFlexFields\Models\FlexFieldSchemaVersion;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use RuntimeException;

/**
 * A stable head row serializes all mutations of one schema, including its first publication.
 * Storage backend only; public payload validation belongs to SchemaRegistry.
 *
 * @internal
 *
 * @phpstan-type SchemaRecord array{version: int, schema: array<string, mixed>, checksum: string, actor: ?string, state: string, published_at: string}
 */
final class DatabaseSchemaVersionStore
{
    public function __construct(private readonly ?string $connectionName = null) {}

    /** @param array<string, mixed> $schema */
    public function publish(string $id, array $schema, string $checksum, ?string $actor, string $state, ?int $groupId, ?int $expectedVersion = null): int
    {
        self::assertExpectedVersion($expectedVersion);

        return $this->withLockedHead($id, function (Connection $connection) use ($id, $schema, $checksum, $actor, $state, $groupId, $expectedVersion): int {
            // Include existing v3 history on the first use of a freshly migrated head.
            $last = max(
                (int) $connection->table('flex_field_schema_heads')->where('schema_id', $id)->value('last_version'),
                (int) $this->query()->where('schema_id', $id)->max('version'),
            );
            if ($expectedVersion !== null && $last !== $expectedVersion) {
                throw new SchemaVersionConflict($expectedVersion, $last);
            }

            if ($last >= 4294967295) {
                throw new RuntimeException('Schema version counter is exhausted.');
            }

            $version = $last + 1;
            $this->query()->create([
                'flex_field_group_id' => $groupId,
                'schema_id' => $id,
                'version' => $version,
                'schema' => $schema,
                'checksum' => $checksum,
                'actor' => $actor,
                'state' => $state,
                'published_at' => now(),
            ]);
            $connection->table('flex_field_schema_heads')->where('schema_id', $id)->update(['last_version' => $version]);

            if ($state === 'live') {
                $this->activate($connection, $id, $version);
            }

            return $version;
        });
    }

    public function currentVersion(string $id): int
    {
        $connection = (new FlexFieldSchemaVersion)->setConnection($this->connectionName)->getConnection();

        return max((int) $connection->table('flex_field_schema_heads')->where('schema_id', $id)->value('last_version'),
            (int) $this->query()->where('schema_id', $id)->max('version'));
    }

    public static function assertExpectedVersion(?int $version): void
    {
        if ($version !== null && ($version < 0 || $version > 4294967295)) {
            throw new InvalidArgumentException('Expected schema version must be an unsigned 32-bit integer.');
        }
    }

    /** @return list<SchemaRecord> */
    public function versions(string $id): array
    {
        $records = [];

        foreach ($this->query()->where('schema_id', $id)->orderBy('version')->get() as $row) {
            if (! $row instanceof FlexFieldSchemaVersion) {
                throw new RuntimeException('Schema query returned an unexpected record type.');
            }

            $records[] = $row->toRegistryRecord();
        }

        return $records;
    }

    /** @return SchemaRecord|null */
    public function find(string $id, int $version): ?array
    {
        return $this->query()->where('schema_id', $id)->where('version', $version)->first()?->toRegistryRecord();
    }

    public function transition(string $id, int $version, string $state): void
    {
        $this->withLockedHead($id, function (Connection $connection) use ($id, $version, $state): void {
            $row = $this->query()->where('schema_id', $id)->where('version', $version)->first();

            if ($row === null) {
                throw new InvalidArgumentException("Schema [{$id}] has no version [{$version}].");
            }

            SchemaApprovalWorkflow::assertTransition($row->state, $state);

            if ($state === 'live') {
                $this->activate($connection, $id, $version);
            } elseif ($row->state === 'live') {
                $connection->table('flex_field_schema_heads')->where('schema_id', $id)->update(['live_version' => null]);
            }

            $row->update(['state' => $state]);
        });
    }

    public function purge(string $id): void
    {
        $this->withLockedHead($id, function (Connection $connection) use ($id): void {
            $last = max(
                (int) $connection->table('flex_field_schema_heads')->where('schema_id', $id)->value('last_version'),
                (int) $this->query()->where('schema_id', $id)->max('version'),
            );
            $this->query()->where('schema_id', $id)->delete();
            // Retain the counter: purging history must not reuse previously issued version IDs.
            $connection->table('flex_field_schema_heads')->where('schema_id', $id)->update(['live_version' => null, 'last_version' => $last]);
        });
    }

    /** @return Builder<FlexFieldSchemaVersion> */
    private function query(): Builder
    {
        return (new FlexFieldSchemaVersion)->setConnection($this->connectionName)->newQuery();
    }

    private function activate(Connection $connection, string $id, int $version): void
    {
        $this->query()->where('schema_id', $id)->where('version', '!=', $version)
            ->where('state', 'live')->update(['state' => 'approved']);
        $connection->table('flex_field_schema_heads')->where('schema_id', $id)->update(['live_version' => $version]);
    }

    /**
     * @template T
     *
     * @param  Closure(Connection): T  $operation
     * @return T
     */
    private function withLockedHead(string $id, Closure $operation): mixed
    {
        $connection = (new FlexFieldSchemaVersion)->setConnection($this->connectionName)->getConnection();

        return $connection->transaction(function () use ($connection, $id, $operation): mixed {
            // Duplicate INSERT IGNORE takes a shared InnoDB lock: simultaneous writers
            // then deadlock while upgrading it for FOR UPDATE. A no-op key upsert
            // acquires the exclusive row lock immediately and preserves both counters.
            $connection->table('flex_field_schema_heads')->upsert(
                [['schema_id' => $id, 'last_version' => 0]], ['schema_id'], ['schema_id'],
            );
            $head = $connection->table('flex_field_schema_heads')->where('schema_id', $id)->lockForUpdate()->first();

            if ($head === null) {
                throw new RuntimeException('Unable to lock the schema version head.');
            }

            return $operation($connection);
        }, 3);
    }
}
