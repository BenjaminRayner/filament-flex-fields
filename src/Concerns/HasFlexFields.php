<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Data\FlexFieldSchema;
use Bjanczak\FilamentFlexFields\Data\FlexFieldValueChange;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEncryption;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldSchemaResolver;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueWriter;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueContract;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueSubmission;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use InvalidArgumentException;

trait HasFlexFields
{
    /** @var array<string, mixed>|null */
    protected ?array $flexFieldValuesBeforeChange = null;

    public static function bootHasFlexFields(): void
    {
        static::retrieved(function ($model): void {
            if (! isset($model->attributes[static::flexFieldsColumn()])) {
                $model->setAttribute(static::flexFieldsColumn(), []);
            }
        });

        static::saving(function ($model): void {
            $column = static::flexFieldsColumn();

            if (! $model->isDirty($column)) {
                return;
            }

            // Eloquent mass assignment and form dehydration also reach this boundary.
            // Prepare the entire detached payload before mutating the model or audit.
            $values = $model->getAttribute($column) ?? [];

            if (! is_array($values)) {
                throw new InvalidArgumentException('Flex field storage values must be an array.');
            }

            $definitions = $model->flexFieldStorageDefinitions();
            $prepared = FieldValueWriter::replacement($values, $definitions);
            $model->setAttribute($column, $prepared);

            if (! FlexFieldsConfig::isAuditEnabled()) {
                return;
            }

            $before = $model->flexFieldValuesBeforeChange ?? $model->getOriginal($column) ?? [];
            $after = $model->getAttribute($column) ?? [];

            if (! is_array($before)) {
                $before = [];
            }

            // Legacy plaintext must not be copied into a new audit entry when a
            // definition now requires encryption. Existing audit history is unchanged.
            $before = FieldValueWriter::replacement($before, $definitions);

            if (! is_array($after)) {
                $after = [];
            }

            $changes = $model->diffFlexFieldValues($before, $after);

            foreach ($changes as $change) {
                $model->recordFlexFieldValueChange($change);
            }

            $model->flexFieldValuesBeforeChange = null;
        });
    }

    public function initializeHasFlexFields(): void
    {
        $column = static::flexFieldsColumn();

        if (! isset($this->casts[$column])) {
            $this->casts[$column] = 'array';
        }

        if (FlexFieldsConfig::isAuditEnabled()) {
            $auditColumn = static::flexFieldAuditColumn();

            if (! isset($this->casts[$auditColumn])) {
                $this->casts[$auditColumn] = 'array';
            }
        }
    }

    public static function flexFieldsColumn(): string
    {
        return FlexFieldsConfig::getValuesColumn();
    }

    public static function flexFieldAuditColumn(): string
    {
        return FlexFieldsConfig::getAuditColumn();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlexFieldValues(): array
    {
        return $this->getAttribute(static::flexFieldsColumn()) ?? [];
    }

    public function getFlexFieldValue(string $slug, mixed $default = null): mixed
    {
        $value = data_get($this->getFlexFieldValues(), $slug, $default);

        return $this->maybeDecryptFlexFieldValue($slug, $value);
    }

    public function setFlexFieldValue(string $slug, mixed $value): static
    {
        $values = FieldValueWriter::assignment($this->getFlexFieldValues(), $slug, $value, $this->flexFieldStorageDefinitions());
        $this->rememberFlexFieldValuesBeforeChange();
        $this->setAttribute(static::flexFieldsColumn(), $values);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setFlexFieldValues(array $values): static
    {
        // Storage encryption must not depend on the current user's display permissions.
        $values = FieldValueWriter::replacement($values, $this->flexFieldStorageDefinitions());

        $this->rememberFlexFieldValuesBeforeChange();
        $this->setAttribute(static::flexFieldsColumn(), $values);

        return $this;
    }

    /**
     * Apply an untrusted patch only after record and field authorization and rules.
     * Missing/denied/unavailable fields retain their stored values. The caller owns
     * tenant-scoped retrieval, its transaction, locking and the subsequent save().
     *
     * @param  list<FieldValueContract>  $contracts  Trusted server declarations.
     * @param  Closure(static): bool  $authorizeRecord  Required also for an empty patch.
     * @param  Closure(FieldValueContract): bool  $authorizeField
     */
    public function applySubmittedFlexFieldValues(mixed $values, array $contracts, Closure $authorizeRecord, Closure $authorizeField): static
    {
        $allowed = $authorizeRecord($this);
        if (! is_bool($allowed)) {
            throw new InvalidArgumentException('Record write authorization must return a boolean.');
        }
        if (! $allowed) {
            throw new AuthorizationException('Record is not writable.');
        }
        if (! array_is_list($contracts) || count($contracts) > FieldValueSubmission::MAX_FIELDS) {
            throw new InvalidArgumentException('Submission requires a bounded list of field contracts.');
        }
        $definitions = $this->flexFieldStorageDefinitions();
        $encrypted = [];
        foreach ($definitions as $definition) {
            if ($definition->isEncrypted) {
                $encrypted[$definition->slug] = true;
            }
        }
        $effective = [];
        foreach ($contracts as $contract) {
            if (! $contract instanceof FieldValueContract) {
                throw new InvalidArgumentException('Submission contracts must be typed.');
            }
            foreach ($encrypted as $path => $_) {
                if (str_starts_with($path, $contract->path.'.') || str_starts_with($contract->path, $path.'.')) {
                    throw new InvalidArgumentException('Submitted contracts must declare encrypted storage boundaries at their exact field paths.');
                }
            }
            $effective[] = isset($encrypted[$contract->path]) && ! $contract->encrypted
                ? new FieldValueContract($contract->path, $contract->rules, encrypted: true, nestedRules: $contract->nestedRules)
                : $contract;
        }
        $prepared = (new FieldValueSubmission)->prepare($values, $effective, $authorizeField);
        if ($prepared === []) {
            return $this;
        }
        $patched = FieldValueCodec::copy($this->getFlexFieldValues());
        foreach ($effective as $contract) {
            if (Arr::has($prepared, $contract->path)) {
                data_set($patched, $contract->path, data_get($prepared, $contract->path));
            }
        }
        $patched = FieldValueWriter::replacement($patched, $definitions);
        $this->rememberFlexFieldValuesBeforeChange();
        $this->setAttribute(static::flexFieldsColumn(), $patched);

        return $this;
    }

    /**
     * @return list<FlexFieldValueChange>
     */
    public function getFlexFieldAuditTrail(): array
    {
        if (! FlexFieldsConfig::isAuditEnabled()) {
            return [];
        }

        $entries = $this->getAttribute(static::flexFieldAuditColumn()) ?? [];

        if (! is_array($entries)) {
            return [];
        }

        return array_map(
            fn (array $entry): FlexFieldValueChange => FlexFieldValueChange::fromArray($entry),
            $entries,
        );
    }

    /**
     * @return list<FlexFieldSchema>
     */
    public function flexFieldSchemas(?string $tenantId = null): array
    {
        return app(FlexFieldSchemaResolver::class)->schemasForTarget(
            static::class,
            $tenantId ?? $this->resolveFlexFieldTenantId(),
        );
    }

    /**
     * @return list<FlexFieldDefinition>
     */
    public function flexFieldDefinitions(?string $tenantId = null): array
    {
        $resolver = app(FlexFieldSchemaResolver::class);

        return $resolver->definitionsForModel(
            static::class,
            $tenantId ?? $this->resolveFlexFieldTenantId(),
            $resolver->resolveRbacUserKey($this),
        );
    }

    public function resolveFlexFieldTenantId(): ?string
    {
        return app(FlexFieldSchemaResolver::class)->resolveTenantId($this);
    }

    /**
     * Scope a query to filter models by a flex field value.
     */
    public function scopeWhereFlexField(Builder $query, string $slug, mixed $operator, mixed $value = null): Builder
    {
        $column = static::flexFieldsColumn();

        return $query->where("{$column}->{$slug}", $operator, $value);
    }

    /**
     * Scope a query to filter models by a flex field value with OR logic.
     */
    public function scopeOrWhereFlexField(Builder $query, string $slug, mixed $operator, mixed $value = null): Builder
    {
        $column = static::flexFieldsColumn();

        return $query->orWhere("{$column}->{$slug}", $operator, $value);
    }

    /**
     * Scope a query to filter models by a flex field value within an array of values.
     *
     * @param  array<int|string, mixed>  $values
     */
    public function scopeWhereFlexFieldIn(Builder $query, string $slug, array $values): Builder
    {
        $column = static::flexFieldsColumn();

        return $query->whereIn("{$column}->{$slug}", $values);
    }

    /**
     * Scope a query to filter models where a flex field is null.
     */
    public function scopeWhereFlexFieldNull(Builder $query, string $slug): Builder
    {
        $column = static::flexFieldsColumn();

        return $query->whereNull("{$column}->{$slug}");
    }

    /**
     * Scope a query to filter models where a flex field is not null.
     */
    public function scopeWhereFlexFieldNotNull(Builder $query, string $slug): Builder
    {
        $column = static::flexFieldsColumn();

        return $query->whereNotNull("{$column}->{$slug}");
    }

    protected function rememberFlexFieldValuesBeforeChange(): void
    {
        if ($this->flexFieldValuesBeforeChange !== null) {
            return;
        }

        $this->flexFieldValuesBeforeChange = $this->getFlexFieldValues();
    }

    protected function recordFlexFieldValueChange(FlexFieldValueChange $change): void
    {
        if (! FlexFieldsConfig::isAuditEnabled()) {
            return;
        }

        $column = static::flexFieldAuditColumn();
        $entries = $this->getAttribute($column) ?? [];

        if (! is_array($entries)) {
            $entries = [];
        }

        $entries[] = $change->toArray();

        $maxEntries = FlexFieldsConfig::getAuditMaxEntries();

        if (count($entries) > $maxEntries) {
            $entries = array_slice($entries, count($entries) - $maxEntries);
        }

        $this->setAttribute($column, array_values($entries));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<FlexFieldValueChange>
     */
    protected function diffFlexFieldValues(array $before, array $after): array
    {
        $changes = [];

        $this->computeDeepDiff('', $before, $after, $changes);

        return $changes;
    }

    /**
     * @param  list<FlexFieldValueChange>  $changes
     */
    private function computeDeepDiff(string $prefix, array $before, array $after, array &$changes): void
    {
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($oldValue) && is_array($newValue) && array_is_list($oldValue) === array_is_list($newValue)) {
                $this->computeDeepDiff($path, $oldValue, $newValue, $changes);

                continue;
            }

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = new FlexFieldValueChange(
                slug: $path,
                oldValue: $oldValue,
                newValue: $newValue,
                userId: $this->resolveFlexFieldAuditUserId(),
                userName: $this->resolveFlexFieldAuditUserName(),
                changedAt: now()->toIso8601String(),
            );
        }
    }

    protected function resolveFlexFieldAuditUserId(): ?int
    {
        $user = auth()->user();

        return $user ? (int) $user->getAuthIdentifier() : null;
    }

    protected function resolveFlexFieldAuditUserName(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        foreach (['name', 'email'] as $attribute) {
            if (isset($user->{$attribute}) && is_string($user->{$attribute}) && filled($user->{$attribute})) {
                return $user->{$attribute};
            }
        }

        return null;
    }

    protected function maybeEncryptFlexFieldValue(string $slug, mixed $value): mixed
    {
        return FieldValueWriter::encode($value, $this->flexFieldDefinitionIsEncrypted($slug));
    }

    protected function maybeDecryptFlexFieldValue(string $slug, mixed $value): mixed
    {
        if (! $this->flexFieldDefinitionIsEncrypted($slug) || ! is_string($value)) {
            return $value;
        }

        return FlexFieldEncryption::decrypt($value);
    }

    /** @var array<string, bool>|null */
    protected ?array $flexFieldEncryptedDefinitionCache = null;

    /** @return list<FlexFieldDefinition> */
    protected function flexFieldStorageDefinitions(): array
    {
        return app(FlexFieldSchemaResolver::class)->storageDefinitionsForTarget(
            static::class,
            $this->resolveFlexFieldTenantId(),
        );
    }

    protected function flexFieldDefinitionIsEncrypted(string $slug): bool
    {
        // Models can outlive requests or change tenant context. Rebuild from the
        // scoped registry rather than retaining user-filtered metadata on the model.
        $this->flexFieldEncryptedDefinitionCache = [];

        foreach ($this->flexFieldStorageDefinitions() as $definition) {
            $this->flexFieldEncryptedDefinitionCache[$definition->slug] = $definition->isEncrypted;
        }

        return $this->flexFieldEncryptedDefinitionCache[$slug] ?? false;
    }
}
