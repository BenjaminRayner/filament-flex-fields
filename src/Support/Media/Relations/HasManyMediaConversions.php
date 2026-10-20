<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Relations;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use LogicException;

/** Replace a SQL projection of a complete, ready server-owned conversion plan; never delete bytes. */
final readonly class HasManyMediaConversions
{
    /**
     * @param  Closure(Model): bool  $authorize  Recheck the original row and its owning application's permissions.
     * @param  Closure(Model, MediaReceipt, ?string): MediaWrite  $authority  Server policy for original (null name) or conversion.
     */
    public function __construct(
        public string $relation,
        private Closure $authorize,
        private Closure $authority,
        public MediaConversionColumns $columns = new MediaConversionColumns,
        public string $sourceDiskColumn = 'disk',
        public string $sourcePathColumn = 'path',
        public int $maxVariants = 32,
    ) {
        foreach ([$relation, $sourceDiskColumn, $sourcePathColumn] as $identifier) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/D', $identifier)) {
                throw new InvalidArgumentException('Conversion mapping requires explicit simple identifiers.');
            }
        }
        if (strcasecmp($sourceDiskColumn, $sourcePathColumn) === 0 || $maxVariants < 1 || $maxVariants > 100) {
            throw new InvalidArgumentException('Invalid conversion mapping options.');
        }
    }

    /** Read before preparing a new complete projection; never trust a browser-supplied revision for authorization. */
    public function revision(Model $originalRow): string
    {
        if (! $originalRow->exists) {
            throw new LogicException('A conversion projection requires a persisted original row.');
        }
        $query = $originalRow->newQuery();
        $query->whereKey($originalRow->getKey());
        $fresh = $query->firstOrFail();
        if (($this->authorize)($fresh) !== true) {
            throw new AuthorizationException('This media conversion collection is not available.');
        }
        $builder = $this->query($fresh)->getQuery();
        $builder->reorder();
        $builder->limit($this->maxVariants + 1);
        $rows = $builder->get();

        return $this->fingerprint($fresh, $rows->all());
    }

    /**
     * Only pass receipts obtained from ImageBundle or an authorized READY queued snapshot.
     * The authority callback must prove each named receipt belongs to this original/plan.
     * It is not sufficient to authorize the disk or directory alone.
     *
     * @param  array<string, MediaReceipt>  $variants  Complete ready plan; [] explicitly clears its SQL index.
     */
    public function sync(Model $originalRow, MediaReceipt $original, array $variants, string $expectedRevision): void
    {
        if (! $originalRow->exists || $originalRow->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Project conversions inside the original row save transaction.');
        }
        if (count($variants) > $this->maxVariants || ! preg_match('/^[a-f0-9]{64}$/D', $expectedRevision)) {
            throw new MediaFailure('relation_budget', 'Conversion projection exceeds its configured budget.');
        }
        $originalRow->getConnection()->transaction(function () use ($originalRow, $original, $variants, $expectedRevision): void {
            $query = $originalRow->newQuery();
            $query->whereKey($originalRow->getKey());
            $query->lockForUpdate();
            $fresh = $query->firstOrFail();
            if (($this->authorize)($fresh) !== true) {
                throw new AuthorizationException('This media conversion collection is not available.');
            }
            if ($original->adapter !== 'disk' || $fresh->getAttribute($this->sourceDiskColumn) !== $original->disk
                || $fresh->getAttribute($this->sourcePathColumn) !== $original->path) {
                throw new MediaFailure('relation_source_changed', 'The indexed original no longer matches this conversion plan.');
            }
            $relation = $this->query($fresh);
            $builder = $relation->getQuery();
            $builder->reorder();
            $builder->limit($this->maxVariants + 1);
            $builder->lockForUpdate();
            $existing = $builder->get();
            $revision = $this->fingerprint($fresh, $existing->all());
            if ($existing->count() > $this->maxVariants || $existing->pluck($this->columns->name)->unique()->count() !== $existing->count()) {
                throw new MediaFailure('relation_rows_invalid', 'Stored conversion rows exceed the budget or contain duplicate names.');
            }
            $media = app(MediaOs::class);
            $media->verify($original, ($this->authority)($fresh, $original, null));
            $attributes = [];
            $identities = [];
            foreach ($variants as $name => $receipt) {
                if (! is_string($name) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $name) || ! $receipt instanceof MediaReceipt
                    || $receipt->adapter !== 'disk' || $receipt->disk === '' || strlen($receipt->disk) > 128
                    || preg_match('/[\x00-\x1f\x7f]/', $receipt->disk) || $receipt->path === $original->path && $receipt->disk === $original->disk
                    || ! preg_match('/^[a-f0-9]{64}$/D', $receipt->file->sha256) || $receipt->file->bytes < 1
                    || ! str_starts_with($receipt->file->mimeType, 'image/') || strlen($receipt->file->mimeType) > 128
                    || $receipt->file->width === null || $receipt->file->width < 1 || $receipt->file->height === null || $receipt->file->height < 1
                    || $receipt->operationId === '' || strlen($receipt->operationId) > 255 || preg_match('/[\x00-\x1f\x7f]/', $receipt->operationId)) {
                    throw new MediaFailure('relation_state_invalid', 'Invalid named conversion receipt.');
                }
                MediaAddress::path($receipt->path);
                if (strlen($receipt->path) > 512 || isset($identities[$receipt->disk.'\0'.$receipt->path])) {
                    throw new MediaFailure('relation_state_invalid', 'Conversions must identify distinct bounded paths.');
                }
                $identities[$receipt->disk.'\0'.$receipt->path] = true;
                $media->verify($receipt, ($this->authority)($fresh, $receipt, $name));
                $attributes[$name] = [$this->columns->name => $name, $this->columns->disk => $receipt->disk,
                    $this->columns->path => $receipt->path, $this->columns->mimeType => $receipt->file->mimeType,
                    $this->columns->bytes => $receipt->file->bytes, $this->columns->sha256 => $receipt->file->sha256,
                    $this->columns->operationId => $receipt->operationId, $this->columns->width => $receipt->file->width,
                    $this->columns->height => $receipt->file->height];
            }
            $matches = $existing->count() === count($attributes);
            foreach ($existing as $row) {
                foreach ($attributes[$row->getAttribute($this->columns->name)] ?? [] as $column => $value) {
                    $matches = $matches && (string) $row->getAttribute($column) === (string) $value;
                }
                $matches = $matches && isset($attributes[$row->getAttribute($this->columns->name)]);
            }
            if ($matches) {
                $originalRow->unsetRelation($this->relation);

                return;
            }
            if (! hash_equals($revision, $expectedRevision)) {
                throw new MediaFailure('relation_conflict', 'This conversion index changed. Reload it before saving again.');
            }
            // Verify the whole plan before the first SQL mutation; a model veto still rolls back its savepoint.
            $indexed = $existing->keyBy(fn (Model $row): string => $row->getAttribute($this->columns->name));
            foreach ($attributes as $name => $values) {
                $row = $indexed->get($name) ?? $this->query($fresh)->make();
                $row->forceFill($values);
                if ($row->isDirty() && $row->saveOrFail() !== true) {
                    throw new MediaFailure('relation_write_rejected', 'A conversion row could not be saved.');
                }
            }
            foreach ($existing as $row) {
                if (! isset($attributes[$row->getAttribute($this->columns->name)]) && $row->delete() !== true) {
                    throw new MediaFailure('relation_write_rejected', 'A conversion row could not be detached.');
                }
            }
            $builder = $this->query($fresh)->getQuery();
            $builder->reorder();
            $builder->limit($this->maxVariants + 1);
            $builder->lockForUpdate();
            $after = $builder->get();
            if ($after->count() !== count($attributes)) {
                throw new MediaFailure('relation_projection_changed', 'Model hooks changed the conversion mapping.');
            }
            foreach ($after as $row) {
                $expected = $attributes[$row->getAttribute($this->columns->name)] ?? null;
                if ($expected === null) {
                    throw new MediaFailure('relation_projection_changed', 'Model hooks changed the conversion mapping.');
                }
                foreach ($expected as $column => $value) {
                    if ((string) $row->getAttribute($column) !== (string) $value) {
                        throw new MediaFailure('relation_projection_changed', 'Model hooks changed the conversion mapping.');
                    }
                }
            }
            $originalRow->unsetRelation($this->relation);
        });
    }

    /** @param array<int, Model> $rows */
    private function fingerprint(Model $owner, array $rows): string
    {
        if (count($rows) > $this->maxVariants) {
            throw new MediaFailure('relation_budget', 'Conversion projection exceeds its configured budget.');
        }
        $state = [];
        foreach ($rows as $row) {
            $name = $row->getAttribute($this->columns->name);
            if (! is_string($name) || isset($state[$name])) {
                throw new MediaFailure('relation_rows_invalid', 'Invalid conversion index rows.');
            }
            $state[$name] = [(string) $row->getKey()];
            foreach (get_object_vars($this->columns) as $column) {
                $state[$name][] = (string) $row->getAttribute($column);
            }
        }
        ksort($state);

        return hash('sha256', json_encode([$owner::class, $owner->getConnection()->getName(),
            $owner->getConnection()->getDatabaseName(), $owner->getConnection()->getTablePrefix(), $owner->getTable(), (string) $owner->getKey(),
            $owner->getAttribute($this->sourceDiskColumn), $owner->getAttribute($this->sourcePathColumn),
            $this->relation, $this->sourceDiskColumn, $this->sourcePathColumn, get_object_vars($this->columns), $state], JSON_THROW_ON_ERROR));
    }

    /** @return HasMany<Model, Model> */
    private function query(Model $original): HasMany
    {
        $relation = $original->{$this->relation}();
        if (! $relation instanceof HasMany || ! $relation->getParent()->is($original)
            || $relation->getRelated()->getConnection() !== $original->getConnection()
            || in_array(SoftDeletes::class, class_uses_recursive($relation->getRelated()), true)) {
            throw new LogicException('Conversions require a simple hasMany relation on the same connection.');
        }
        $related = $relation->getRelated();
        $protected = array_map(static fn (?string $key): string => strtolower($key ?? ''),
            [$related->getKeyName(), $relation->getForeignKeyName(), $related->getCreatedAtColumn(), $related->getUpdatedAtColumn()]);
        if (array_intersect(array_map(strtolower(...), array_values(get_object_vars($this->columns))), $protected)) {
            throw new LogicException('Conversion mapping cannot overwrite model keys or timestamps.');
        }
        $builder = $relation->getQuery()->getQuery();
        if ($builder->joins || $builder->groups || $builder->unions || $builder->havings || $builder->limit !== null || $builder->offset !== null) {
            throw new LogicException('Conversions require an unpaginated hasMany relation without joins or grouping.');
        }

        return $relation;
    }
}
