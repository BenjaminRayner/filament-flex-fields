---
title: "A large, authorized assignee picker"
sidebarTitle: "Scoped picker recipe"
description: "Use one access scope for cursor search, selected labels and submitted-ID validation without preloading users."
icon: "users-round"
---

Keep your existing Resource and save flow. This recipe stores one integer user ID
in an existing nullable `assignee_id` foreign key. Labels and dropdown HTML are not
stored. Your application owns that column, its foreign key and assignment policy.

## Prerequisites

An authenticated Filament panel with FLEX registered, an Eloquent user model with
integer keys and a `name` attribute, and an application query describing exactly
which users the current actor may assign. The example below assumes your
application already has a `team_id` scope and denies access without a team.
Replace that query with your real policy; do not add a team column just to copy it.

Pagination bounds the database result. Virtualization bounds the mounted rows.
Neither grants permission to a record. Submitted IDs and selected labels must
use the same access scope as search, including when the form is inside a modal.

## Copy the application helper

The full file ships in `resources/recipes/scoped-select`. It is opt-in application
code, not a new FLEX API or a generated database migration.

{/* recipe-source: resources/recipes/scoped-select/app/Support/AssigneePicker.php */}
```php app/Support/AssigneePicker.php
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
```
{/* /recipe-source */}

## Use it in an existing schema

```php
use App\Models\User;
use App\Support\AssigneePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

$picker = new AssigneePicker(function (): Builder {
    $team = Auth::user()?->getAttribute('team_id');

    return User::query()
        ->when($team === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
        ->where('team_id', $team);
});

// Append this field alongside your existing native/FLEX controls.
$picker->field('assignee_id')->label('Assignee')->required();
```

Rebuild the scope from the authenticated server context on every request. Do not
accept a team ID or SQL scope from public Livewire state. Add appropriate indexes
for your real dataset and query plan; prefix matching and collation vary by DB.
The cursor is a last-seen integer key, not an authorization token. Reset it when
the search changes. These examples are not intended for UUID keys or relevance
ordering without adapting the cursor contract.

## Save and reopen

Use the Resource's normal validated save. A custom Livewire form uses `fill()` and
`getState()` as described in [Saving & validation](/docs/data-and-validation).
Do not silently remove the helper's validation rule when adding domain rules.

| Try | Expected result |
| --- | --- |
| Search a 10,000-user fixture | Each page returns at most 40 options and a next cursor |
| Load the next page | Stable key order, no duplicate IDs |
| Search `%` or `_` | Match those literal characters, not every user |
| Save and reopen | The saved ID resolves through the same authorized query |
| Submit another team's ID | Validation fails, even if the browser sends it directly |
| Revoke access after selection | Label lookup and submission no longer authorize it |
| Rapid search changes / open in modal | The shared Select runtime handles requests and overlay lifecycle |

The package tests execute the shipped query helper against a 10,000-row SQLite
fixture. That is a query and validation check, not a promise about your database's
query time or all-device frame rates. Measure your actual query plan and page.

See [Select configuration](/docs/selectfield) for rendering, keyboard behavior,
rate limits and mobile sheets.
