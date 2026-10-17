# Large pickers and font previews

Use `SelectField` from
`Bjanczak\FilamentFlexFields\Filament\Forms\Components`. Virtualization limits
rendered rows; it does not paginate a database query. For a remote/large dataset,
use `searchable()`, `paginatedSearchResults()`, `searchResultsPageSize()` and
`getSearchResultsPageUsing()`, with `getOptionLabelUsing()` for a saved selection.

The page callback receives `string $search, ?string $cursor, int $pageSize` and
returns:

```php
[
    'items' => [
        ['value' => 'record-id', 'label' => 'Display label'],
    ],
    'cursor' => null,
    'hasMore' => false,
];
```

For another page return a nonempty advancing cursor. Limit option leaves to the
requested page size. Reuse the same application-owned access scope in the page
query, selected-label resolver and submitted-ID validation. A client cursor or
search term never grants record access. Avoid `Model::all()` or unbounded `pluck()`
to display one saved selection. Use stable values; do not save the label or row HTML.

Keep existing search rate limits and translated error states. Fast query changes
must not let an old response replace newer results. Test rapid scroll, additional
pages, keyboard selection, an existing value after reload and the modal variant.
`virtualizeThreshold()`, `virtualRowHeight()` and `virtualWindowSize()` configure
the rendered window independently of `searchResultsPageSize()`.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;

FontSelect::make('heading_font')
    ->previewSelectedFont(false)
    ->categories(['serif'])
    ->subsets(['latin-ext'])
    ->required();
```

This keeps the selected label in the normal input font while visible dropdown
rows receive preview leases. It stores a stable font ID, not a CSS URL. Preview
subsets are not a full content font installation; load website/panel fonts through
the application's font pipeline. Never remove application-owned fonts or styles.

Guides:
- https://flex-fields.bjanczak.com/docs/selectfield
- https://flex-fields.bjanczak.com/docs/font-select

A complete, tested application helper ships at
`resources/recipes/scoped-select/app/Support/AssigneePicker.php`. It uses one fresh
authorized query for bounded keyset pages, selected labels and validation.
Guide: https://flex-fields.bjanczak.com/docs/scoped-select-recipe .
