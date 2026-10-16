<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Filament\Support\Services\RelationshipJoiner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/** One query boundary for model search, suggestions and selected-record batches. */
final class UserSelectScopedQuery
{
    public function __construct(
        private readonly UserSelect $select,
        private readonly UserSelectRecordMapper $mapper,
    ) {}

    /** @return Builder<Model> */
    public function build(?string $search = null, bool $fullRecord = false): Builder
    {
        if ($this->select->hasRelationship()) {
            $relationship = Relation::noConstraints(fn () => $this->select->getRelationship());
            if (! $relationship instanceof Relation) {
                throw new InvalidArgumentException('UserSelect requires a resolvable relationship.');
            }
            $query = app(RelationshipJoiner::class)->prepareQueryForNoConstraints($relationship);
            $query = $this->select->applyUserSelectRelationshipScope($query, $search);
        } else {
            $model = $this->select->getUserModel();
            if ($model === null || ! is_subclass_of($model, Model::class)) {
                throw new InvalidArgumentException('UserSelect optionModel() must name an Eloquent model.');
            }
            $query = $model::query();
        }

        if (($modifier = $this->select->getModifyQueryUsing()) !== null) {
            $modified = $this->select->evaluate($modifier, ['query' => $query, 'search' => $search]);
            if ($modified !== null && ! $modified instanceof Builder) {
                throw new InvalidArgumentException('UserSelect query() must return an Eloquent builder or null.');
            }
            $query = $modified ?? $query;
        }

        if ($fullRecord || $this->mapper->needsFullModelForResolvers()) {
            return $query;
        }
        $columns = array_values(array_unique(array_filter([
            $query->getModel()->getQualifiedKeyName(),
            $query->qualifyColumn($this->mapper->resolveModelKeyName()),
            $this->select->getNameColumn(), $this->select->getEmailColumn(),
            $this->select->getAvatarColumn(), $this->select->getVerificationColumn(),
        ], static fn (?string $column): bool => filled($column))));
        $columns = array_map(fn (string $column): string => str_contains($column, '.')
            ? $column : $query->qualifyColumn($column), $columns);

        // Keep explicit aliases/computed columns supplied by the integrator.
        if ($query->getQuery()->columns === null) {
            $query->select($columns);
        } else {
            $query->addSelect($columns);
        }

        return $query;
    }
}
