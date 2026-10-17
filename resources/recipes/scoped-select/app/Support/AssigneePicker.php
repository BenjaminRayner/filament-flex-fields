<?php

declare(strict_types=1);

namespace App\Support;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Application example: integer model keys and a name attribute. Scope supplied by the application. */
final class AssigneePicker
{
    /** @param Closure(): Builder $accessibleUsers A fresh, server-authorized query on every call. */
    public function __construct(private readonly Closure $accessibleUsers) {}

    public function field(string $name = 'assignee_id'): SelectField
    {
        return SelectField::make($name)
            ->searchable()->searchDebounce(250)
            ->paginatedSearchResults()->searchResultsPageSize(40)
            ->virtualizeThreshold(60)->virtualWindowSize(16)
            ->getSearchResultsPageUsing(fn (string $search, ?string $cursor, int $pageSize): array => $this->page($search, $cursor, $pageSize))
            ->getOptionLabelUsing(fn ($value): ?string => $this->label($value))
            ->rules([$this->rule()]);
    }

    /** @return array{items: list<array{value: string, label: string}>, cursor: ?string, hasMore: bool} */
    public function page(string $search, ?string $cursor, int $pageSize): array
    {
        if (mb_strlen($search) > 120 || $pageSize < 1 || $pageSize > 100
            || ($cursor !== null && ! $this->validKey($cursor))) {
            throw ValidationException::withMessages(['assignee_id' => 'Invalid search page.']);
        }
        $query = ($this->accessibleUsers)();
        $key = $query->getModel()->getQualifiedKeyName();
        // Literal prefix search: %, _ and ! supplied by a user are not SQL wildcards.
        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search));
        $rows = $query->whereRaw("name LIKE ? ESCAPE '!'", [$prefix.'%'])
            ->when($cursor !== null, fn (Builder $query) => $query->where($key, '>', (int) $cursor))
            ->reorder($key)->limit($pageSize + 1)->get();
        $more = $rows->count() > $pageSize;
        $visible = $rows->take($pageSize);

        return [
            'items' => $visible->map(fn ($user): array => ['value' => (string) $user->getKey(), 'label' => (string) $user->name])->values()->all(),
            'cursor' => $more ? (string) $visible->last()->getKey() : null,
            'hasMore' => $more,
        ];
    }

    public function label(mixed $value): ?string
    {
        if (! $this->validKey($value)) {
            return null;
        }
        $value = ($this->accessibleUsers)()->whereKey($value)->value('name');

        return is_string($value) ? $value : null;
    }

    public function rule(): ValidationRule
    {
        return new class($this) implements ValidationRule
        {
            public function __construct(private readonly AssigneePicker $picker) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if ($this->picker->label($value) === null) {
                    $fail('Choose a user you may access.');
                }
            }
        };
    }

    private function validKey(mixed $value): bool
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,18}$/D', (string) $value) === 1
            && (string) (int) $value === (string) $value;
    }
}
