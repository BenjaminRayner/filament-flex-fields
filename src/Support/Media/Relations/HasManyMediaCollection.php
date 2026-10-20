<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Relations;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/** SQL reference mapping only: no upload, conversion, receipt acceptance or physical deletion. */
final readonly class HasManyMediaCollection
{
    /**
     * @param  Closure(Model): string  $directory  Trusted directory derived from the freshly authorized parent.
     * @param  Closure(Model, string): bool  $authorize  Called for read/write, after fetching the current parent.
     * @param  Closure(Model, string, Model|null): bool  $admit  Prove new paths belong to this upload/session; existing rows are supplied separately.
     */
    public function __construct(
        public string $relation,
        public string $disk,
        public string $collection,
        private Closure $directory,
        private Closure $authorize,
        private Closure $admit,
        public MediaRelationColumns $columns = new MediaRelationColumns,
        public int $maxItems = 100,
    ) {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/D', $relation)
            || $disk === '' || strlen($disk) > 128 || preg_match('/[\x00-\x1f\x7f]/', $disk)
            || ! preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $collection) || $maxItems < 1 || $maxItems > 1000) {
            throw new InvalidArgumentException('Invalid has-many media collection configuration.');
        }
    }

    public function read(Model $owner): MediaRelationSnapshot
    {
        $fresh = $this->owner($owner, 'read');

        return $this->snapshot($fresh, $this->rows($fresh));
    }

    public function references(Model $owner, string $path): bool
    {
        $fresh = $this->owner($owner, 'read');
        $this->validatePath($this->prefix($fresh), $path);

        return $this->query($fresh)->where($this->columns->path, $path)->exists();
    }

    /**
     * Pass validated stored paths, not TemporaryUploadedFile objects or browser-supplied row IDs.
     * An outer transaction is required so parent save and reference changes share one commit.
     *
     * @param  list<string>  $paths
     * @param  array<string, string>  $names
     * @param  array<string, array<string, mixed>>  $metadata  Validated application metadata; omission preserves existing metadata.
     */
    public function sync(Model $owner, array $paths, array $names, string $expectedRevision, array $metadata = []): MediaRelationSnapshot
    {
        if (! $owner->exists || $owner->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Synchronize a persisted media parent inside its save transaction.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedRevision) || ! array_is_list($paths)
            || count($paths) > $this->maxItems || count($names) > 1000 || count($metadata) > $this->maxItems
            || ($metadata !== [] && $this->columns->metadata === null) || array_diff(array_keys($metadata), $paths) !== []) {
            throw new MediaFailure('relation_state_invalid', 'Invalid media relation state or revision.');
        }

        // A savepoint also rolls back relation changes if a caller catches a model-event failure.
        return $owner->getConnection()->transaction(function () use ($owner, $paths, $names, $expectedRevision, $metadata): MediaRelationSnapshot {
            $fresh = $this->owner($owner, 'write', lock: true);
            $rows = $this->rows($fresh, lock: true);
            $before = $this->snapshot($fresh, $rows);
            $indexed = $rows->keyBy(fn (Model $row): string => $row->getAttribute($this->columns->path));
            $prefix = $this->prefix($fresh);
            $desiredNames = [];
            $desiredMetadata = [];
            foreach ($paths as $path) {
                if (! is_string($path)) {
                    throw new MediaFailure('relation_path_rejected', 'A file is not available to this media collection.');
                }
                $this->validatePath($prefix, $path);
                $existing = $indexed->get($path);
                if (($this->admit)($fresh, $path, $existing) !== true || ! Storage::disk($this->disk)->exists($path)) {
                    throw new MediaFailure('relation_path_rejected', 'A file is not available to this media collection.');
                }
                $name = $names[$path] ?? $before->names[$path] ?? basename($path);
                if (! is_string($name) || strlen($name) > 1024 || preg_match('/[\x00-\x1f\x7f]/', $name) || preg_match('//u', $name) !== 1) {
                    throw new MediaFailure('relation_state_invalid', 'Original file names must be bounded valid text.');
                }
                $desiredNames[$path] = Str::limit($name, 255, '');
                if ($this->columns->metadata !== null) {
                    $desiredMetadata[$path] = MediaRelationMetadata::normalize($metadata[$path] ?? $before->metadata[$path] ?? []);
                }
            }
            if (count(array_unique($paths)) !== count($paths)) {
                throw new MediaFailure('relation_state_invalid', 'Submit distinct stored files.');
            }
            $this->assertUnclaimed($fresh, $paths, $rows);
            // A lost-response retry is safe when the requested state is already committed.
            if ($before->paths === $paths && $before->names === $desiredNames && $before->metadata === $desiredMetadata) {
                $owner->unsetRelation($this->relation);

                return $before;
            }
            if (! hash_equals($before->revision, $expectedRevision)) {
                throw new MediaFailure('relation_conflict', 'This media collection changed. Reload it before saving again.');
            }
            foreach ($paths as $position => $path) {
                $row = $indexed->get($path)
                    ?? $this->query($fresh)->make();
                $row->forceFill([$this->columns->disk => $this->disk, $this->columns->collection => $this->collection,
                    $this->columns->path => $path, $this->columns->originalName => $desiredNames[$path], $this->columns->order => $position]);
                if ($this->columns->metadata !== null) {
                    $row->setAttribute($this->columns->metadata, $desiredMetadata[$path]);
                }
                if ($row->isDirty() && $row->saveOrFail() !== true) {
                    throw new MediaFailure('relation_write_rejected', 'A media row could not be saved.');
                }
            }
            foreach ($rows as $row) {
                if (! in_array($row->getAttribute($this->columns->path), $paths, true) && $row->delete() !== true) {
                    throw new MediaFailure('relation_write_rejected', 'A media row could not be detached.');
                }
            }
            $after = $this->snapshot($fresh, $this->rows($fresh, lock: true));
            if ($after->paths !== $paths || $after->names !== $desiredNames || $after->metadata !== $desiredMetadata) {
                throw new MediaFailure('relation_projection_changed', 'Model hooks changed the media mapping unexpectedly.');
            }
            $owner->unsetRelation($this->relation);

            return $after;
        });
    }

    private function owner(Model $owner, string $operation, bool $lock = false): Model
    {
        if (! $owner->exists || $owner->getKey() === null) {
            throw new LogicException('Media relation mapping requires a persisted parent.');
        }
        $query = $owner->newQuery()->whereKey($owner->getKey());
        if ($lock) {
            $query->lockForUpdate();
        }
        $fresh = $query->firstOrFail();
        if (($this->authorize)($fresh, $operation) !== true) {
            throw new AuthorizationException('This media collection is not available.');
        }

        return $fresh;
    }

    /** @return HasMany<Model, Model> */
    private function query(Model $owner): HasMany
    {
        $relation = $owner->{$this->relation}();
        if (! $relation instanceof HasMany || ! $relation->getParent()->is($owner)
            || $relation->getRelated()->getConnection() !== $owner->getConnection()) {
            throw new LogicException('Use a hasMany relation on the same database connection as its parent.');
        }
        $related = $relation->getRelated();
        if ($this->columns->metadata !== null && ($related->getCasts()[$this->columns->metadata] ?? null) !== 'array') {
            throw new LogicException('The mapped metadata column requires an Eloquent array cast.');
        }
        $protected = [$related->getKeyName(), $relation->getForeignKeyName(), $related->getCreatedAtColumn(), $related->getUpdatedAtColumn()];
        $protected = array_map(static fn (?string $column): string => strtolower($column ?? ''), $protected);
        if (array_intersect(array_map(strtolower(...), $this->columns->all()), $protected) !== [] || in_array(SoftDeletes::class, class_uses_recursive($related), true)) {
            throw new LogicException('Media mapping cannot overwrite keys/timestamps or use a soft-deleted relation.');
        }
        $builder = $relation->getQuery()->getQuery();
        if ($builder->joins || $builder->groups || $builder->unions || $builder->limit !== null || $builder->offset !== null || $builder->havings) {
            throw new LogicException('Use a simple hasMany relation without joins, grouping or pagination.');
        }

        $relation->where($this->columns->disk, $this->disk);
        $relation->where($this->columns->collection, $this->collection);

        return $relation;
    }

    /** @return Collection<int, Model> */
    private function rows(Model $owner, bool $lock = false): Collection
    {
        $relation = $this->query($owner);
        $builder = $relation->getQuery();
        $builder->reorder();
        $builder->orderBy($this->columns->order);
        $builder->orderBy($relation->getRelated()->getQualifiedKeyName());
        $builder->limit($this->maxItems + 1);
        if ($lock) {
            // Current locking reads avoid a pre-existing MySQL REPEATABLE READ snapshot.
            $builder->lockForUpdate();
        }
        $rows = $builder->get();
        if ($rows->count() > $this->maxItems) {
            throw new MediaFailure('relation_budget', 'This media collection exceeds the configured row limit.');
        }

        return $rows;
    }

    /** @param Collection<int, Model> $rows */
    private function snapshot(Model $owner, Collection $rows): MediaRelationSnapshot
    {
        $paths = [];
        $names = [];
        $values = [];
        $metadata = [];
        $prefix = $this->prefix($owner);
        foreach ($rows as $row) {
            $path = $row->getAttribute($this->columns->path);
            $name = $row->getAttribute($this->columns->originalName);
            $order = filter_var($row->getAttribute($this->columns->order), FILTER_VALIDATE_INT);
            if (! is_string($path) || ! is_string($name) || $order === false || $order < 0 || isset($names[$path])) {
                throw new MediaFailure('relation_rows_invalid', 'Stored media rows do not match the configured mapping.');
            }
            $this->validatePath($prefix, $path);
            $paths[] = $path;
            $names[$path] = $name;
            $values[] = [(string) $row->getKey(), $path, $name, $order];
            if ($this->columns->metadata !== null) {
                $metadata[$path] = MediaRelationMetadata::normalize($row->getAttribute($this->columns->metadata) ?? []);
                $values[] = $metadata[$path];
            }
        }
        $revision = hash('sha256', json_encode([$owner::class, $owner->getConnection()->getName(),
            $owner->getConnection()->getDatabaseName(), $owner->getConnection()->getTablePrefix(), $owner->getTable(), (string) $owner->getKey(),
            $this->relation, $this->disk, $this->collection, $this->columns->all(), $values], JSON_THROW_ON_ERROR));

        return new MediaRelationSnapshot($paths, $names, $revision, $metadata);
    }

    private function prefix(Model $owner): string
    {
        return MediaAddress::path(($this->directory)($owner)).'/';
    }

    private function validatePath(string $prefix, string $path): void
    {
        MediaAddress::path($path);
        if (strlen($path) > 512 || preg_match('//u', $path) !== 1 || ! str_starts_with($path, $prefix)) {
            throw new MediaFailure('relation_path_rejected', 'A file is not available to this media collection.');
        }
    }

    /**
     * @param  list<string>  $paths
     * @param  Collection<int, Model>  $existing
     */
    private function assertUnclaimed(Model $owner, array $paths, Collection $existing): void
    {
        if ($paths === []) {
            return;
        }
        $related = $this->query($owner)->getRelated();
        $query = $related->newQueryWithoutScopes()->where($this->columns->disk, $this->disk)->whereIn($this->columns->path, $paths);
        if ($existing->isNotEmpty()) {
            $query->whereNotIn($related->getKeyName(), $existing->modelKeys());
        }
        if ($query->exists()) {
            throw new MediaFailure('relation_path_rejected', 'A file is already referenced outside this media collection.');
        }
    }
}
