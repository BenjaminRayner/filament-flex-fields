# Standalone Media OS: choose the service before the interface

Media OS is a PHP service API as well as the engine behind FLEX media controls.
For an import, controller, command or job, do not introduce `FlexFileUpload`, a
Filament schema, a JSON field group or a browser component just to store a file.
Use the installed `Bjanczak\FilamentFlexFields\Support\Media\FlexMedia` entry point.
The component registry does not enumerate the methods of this service.

## Resolve the actual task

| Requirement | Installed entry point / guide |
| --- | --- |
| Store a path, Livewire upload, borrowed stream or bounded bytes | `FlexMedia::store()` with `MediaSource`, `MediaWrite`, `MediaPolicy` |
| Inspect, obtain an authorized URL, verify or delete an owned receipt | `FlexMedia::os()` |
| Original plus named synchronous variants | `FlexMedia::images()->store()` |
| Background image conversions and job status | `FlexMedia::queuedImages()`; read queue options, status and cancellation contracts |
| File arrives before durable record acceptance | `FlexMedia::staging()`; stage, commit and confirm are distinct |
| Attach to an existing Spatie model | `MediaWrite(adapter: 'spatie', record: $savedModel, spatie: $options)` |
| Custom browser capture interface | Separate public browser helpers and per-instance asset ownership |

Read the installed public classes and these matching guides before writing the
implementation. Do not infer a method, default or callback signature from a name.

- https://flex-fields.bjanczak.com/docs/media-os-api
- https://flex-fields.bjanczak.com/docs/media-os-configuration
- https://flex-fields.bjanczak.com/docs/media-os-images
- https://flex-fields.bjanczak.com/docs/media-os-lifecycle
- https://flex-fields.bjanczak.com/docs/media-os-spatie
- https://flex-fields.bjanczak.com/docs/media-os-extensions
- https://flex-fields.bjanczak.com/docs/media-os-browser

## Preserve the service contracts

- Derive paths, targets, lease owners and record scope from trusted application
  context. A receipt or lease ID is not authorization. Request validation and
  application policies remain required; inspect actual bytes with `MediaPolicy`.
- `MediaSource::upload()` accepts a Livewire `TemporaryUploadedFile`. An ordinary
  Laravel request `UploadedFile` uses its validated local path through
  `MediaSource::path()`. Check that the local path exists.
- A borrowed stream starts at its current position. The caller closes it in
  `finally`. An inspection result must also be closed by its caller.
- Store the application's required receipt identifiers and metadata explicitly.
  Direct storage does not create an application gallery row or a model column.
  A database rollback does not roll back a disk/S3 write.
- If staging is required, confirm only after the durable model reference commits.
  Reconcile ambiguous promotions server-side; do not rebuild receipts from
  browser input or compensate a file still referenced by a committed record.
- Native image bundles contain the original and named variants; private URLs may
  be unavailable. Pending queued variants need explicit fallback handling, an
  appropriate worker and supported encoders. Keep originals unless the documented
  application retention policy deliberately changes the workflow.
- Spatie needs its optional dependencies and a persisted `HasMedia` record, or
  staging until it exists. Use Spatie collections/conversions/queues instead of
  running native conversions over the same attachment. Do not finalize a Spatie
  FileAdder inside its configuration callback; the adapter owns that write.
- Server-only storage needs no browser asset initialization. A custom capture UI
  has a separate browser lifecycle and does not replace server validation.

Verify allowed/rejected content, the application's record mapping, private URL
failure, save failure, and the selected queue or Spatie path in an isolated test
environment. Include staging retry/confirmation where used. Report the exercised
configuration; do not claim every option or provider combination was verified.

Use `fff:media:doctor --json` for read-only configuration diagnosis. The separate
`fff:media:probe --write` creates synthetic data on explicit `--disk` and
`--lock-store` targets or performs bundled `--format` conversions in PHP temporary
files. It is an active backend operation and requires operator authorization;
do not run it merely because a diagnostic example includes it. A passed probe is
local and scoped, not proof of shared hosts, worker liveness or application save.
