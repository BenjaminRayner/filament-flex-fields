# Opt-in application recipes

These are application examples, not package providers, migrations or installed routes.
They are not autoloaded by FLEX. Review and copy only the files relevant to an
existing application's requirement. Never overwrite an existing model or migration.

- `post-gallery/`: full Post/Gallery migration, models, policy, filename generator,
  explicit row mapper, Filament page and Blade view, plus alternative native Resource
  list/create/edit pages using the same fields and mapper. Choose one integration.
  Requires authenticated Filament,
  a configured public disk and image encoder. See the full setup, private-storage
  caveat, page registration, queue and Spatie alternatives at
  https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe .
- `scoped-select/`: paginated integer-key/name query helper. Supply the same fresh
  authorized Eloquent query for search, saved labels and validation. The application
  already owns its user table and assignee foreign key. See
  https://flex-fields.bjanczak.com/docs/scoped-select-recipe .

Neither recipe changes your application's data model automatically. The SQL mapper
does not implement a distributed database/storage transaction. Its default detaches
removed rows while retaining files for reference-aware retention. Do not combine it
with Spatie Media Library persistence for the same files.

The repository tests execute these actual source files. Documentation blocks marked
`recipe-source` are regenerated from them with `npm run docs:recipes`; CI checks drift.
