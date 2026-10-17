# Media: choose who owns persistence

For a controller, service, import command or job without an upload control, use
[Standalone Media OS](media-os.md). The field's completion hooks do not substitute
for the service API's receipt and staging contracts.

Choose the application's storage model before configuring upload chrome:

| Existing requirement | Integration |
| --- | --- |
| Attribute holding disk paths | Native disk-backed field and matching attribute/cast |
| One SQL row per original in a related gallery | Application relation hydration/save mapping; complete gallery recipe below |
| Spatie Media Library model | FLEX Spatie adapter; keep collections, conversions and Spatie persistence |

Do not add an upload JSON column to a Post that should own related gallery rows.
The field's transient path list is not automatically the SQL representation. Do
not replace FLEX's relationship hooks with a custom `saveRelationshipsUsing()`
just to persist gallery rows. Scope all incoming paths/IDs to the authorized
record, collection and actual admitted uploads; never attach arbitrary paths.

For a custom disk-backed save flow, follow the mounted field's
`completeMediaUploadsAfterSave()` contract after the successful database commit.
Read the complete recipe before using that hook: a stored file is not yet an
accepted record reference. Database rollback is not a disk/S3 transaction. Keep
cleanup/retention and existing ownership checks explicit.

For native Resource create/edit pages, use the recipe's explicit related-row
mapping inside the page's database transaction. Native RecordSaved handling then
accepts referenced uploads after commit; do not add a duplicate custom completion
hook. The same GalleryFields/GalleryRows helpers serve both integration styles.

Configure named conversions before selecting one. `previewConversion('thumb')`
chooses a preview; it does not define or generate that conversion.
`fileViewerConversion('large')` chooses an available configured viewer conversion.
Existing external systems can use `previewUrlUsing()` and `fileViewerUrlUsing()`
to resolve their own authorized URLs. Verify the exact mode and method in the
installed public API before adapting it.

Native queued conversions require a worker, encoder support and reachable
storage. Spatie mode uses Spatie's own conversions, naming and queue settings;
do not enable native processing on top of them or mirror the same originals into
another gallery table. Separate conversion SQL rows are an optional application
index for API/gallery use, not a default Spatie requirement.

Custom stored basenames use the supported
`getUploadedFileNameForStorageUsing()` callback. Return a safe, collision-resistant
basename; configure folders separately. Do not accept absolute paths or overwrite
the same fixed destination. Conversion names identify variants, not guessed suffixes.

Full application recipes and contracts:
- https://flex-fields.bjanczak.com/docs/file-upload-api — method groups, inherited
  Filament options and the optional Spatie field. Inspect actual installed traits
  and signatures for callbacks; discovery is not a complete fluent-method list.
- https://flex-fields.bjanczak.com/docs/file-upload-storage-and-validation
- https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources
- https://flex-fields.bjanczak.com/docs/file-upload-metadata
- https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe
- https://flex-fields.bjanczak.com/docs/file-upload-resource-recipe
- https://flex-fields.bjanczak.com/docs/file-upload-gallery-api
- https://flex-fields.bjanczak.com/docs/file-upload-events-and-recovery
- https://flex-fields.bjanczak.com/docs/media-os-spatie

Verify two galleries, upload/edit/reorder, save/reload, a rejected save, pending
conversion and failed worker. Do not silently delete retained originals or run
production workers/migrations as a side effect of demonstrating an example.

Complete opt-in application files ship at `resources/recipes/post-gallery`. Adapt them
after reading the gallery guide; do not autoload or run their migration blindly.
