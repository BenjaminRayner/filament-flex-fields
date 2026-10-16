# Package-local Select compatibility checks

Run these CLI tools from the package checkout after installing its development dependencies:

```sh
php scripts/compatibility/native-select-api.php
php scripts/compatibility/select-query-contract.php
php scripts/compatibility/picker-state-contract.php
php scripts/compatibility/select-runtime-policy-contract.php
php scripts/compatibility/schema-render-contract.php
```

Each command runs in a separate PHP process and uses the package's `vendor`. No host application bootstrap, database or existing test files are loaded. The query fixture uses SQLite `:memory:`. The schema fixture uses a separate Testbench application with explicit providers and writes compiled views under ignored `build/filament-v590-views`.

The checks cover inherited native API/endpoint attributes and renderer ordering; scoped selected records/search, alternate relationship keys, custom callback authority, clone isolation and page bounds; catalog/state/currency/cache integrity; and real schema hydration, Blade rendering, validation and exposed label dispatch across ten picker families. The runtime-policy probe additionally covers lazy provider binding, rich rendering scope restoration, clone callbacks, address configuration/state boundaries, icon limit/exclusion/pagination policy, bounded localized cache lifetimes and ICU separators. The schema probe checks metadata isolation across container scopes and SVG cache batches. The printed Filament version is the version actually loaded.

These checks do not certify browser motion, accessibility, real devices, every native configuration combination, or all relationship writes/pivot actions. They complement static analysis and targeted integration checks. See `.internal/v4-select-architecture-decision.md` for the framework boundary and upgrade policy.

Catalog promotion checks: `php scripts/compatibility/catalog-promotions-v4.php` validates cache-only first paint, conditional refresh, bounded stale retention, mandatory English metadata, public config boundaries and lazy example highlighting. `node scripts/compatibility/promotion-deck-v4.mjs` uses a virtual clock to exercise timer suspension, dynamic bullets and disposal. `node scripts/assets/audit-css-browser-compatibility.mjs` verifies optimized shared CSS preserves standard backdrop filters.

`php scripts/compatibility/catalog-lazy-v4.php` checks all 65 English/Polish catalog entries, their resolved character limits and production asset isolation, and confirms no demo/example classes are loaded during discovery.


Select composition continuation:

```sh
node scripts/compatibility/country-map-composition.mjs
php scripts/compatibility/country-map-policy-contract.php
node scripts/compatibility/schedule-composition.mjs
php scripts/compatibility/schedule-policy-contract.php
node scripts/compatibility/location-select-contract.mjs
php scripts/compatibility/location-select-render.php
```

The PHP render probes use real Laravel/Livewire/Filament schemas. The client
provider probe exercises the actual Select request/page loader. These targeted
checks do not certify the full existing suite or all browser first-paint frames.

Icon Select composition:
```sh
node scripts/compatibility/icon-select-contract.mjs
php scripts/compatibility/icon-select-page.php
php scripts/compatibility/icon-select-render.php
```
These cover the real shared Select adapter, provider disposal/bounds, filter
commands, virtual page demand, query-bound PHP cursors and real Filament SSR.


Media OS File Upload: `php scripts/compatibility/media-upload-contract.php` uses actual Filament/Livewire forms, disposable disks and optional real Spatie with SQLite `:memory:`. It verifies policies, conversions, encrypted manifests, rollback/retry, ownership and the post-save cleanup boundary. It does not bootstrap or mutate the host application.

The Media Upload probe additionally exercises stable staging identities, once-only processors, automatic DB reference acceptance, nested rollback/source restoration, native Spatie `singleFile()` deferred replacement, interrupted post-storage checkpoints, uncertain binding retention, administrative full-receipt recovery, independent database connections, rolled-back creates and post-publication metadata failures. The expanded run passes 86 checks. Journal faults are injected/simulated; this does not certify physical process death, all host application saves, AV vendors or live S3 credentials. Native byte-offset transport is outside this integration.

The optional configuration extension brings the upload probe to 116 checks: native
metadata Action mounting/save, real TranslatableTabs composition, disk JSON
create/edit, Spatie editorial properties/ownership, two-file rollback/retry
association, native custom field validation, default-avatar provider/fallback,
transport validation/deduplication/disabled state and independent recovery opt-out
with explicit reconciliation. No tests/** files are changed.

Image editor continuation:

```sh
php scripts/compatibility/media-upload-contract.php
node scripts/compatibility/image-editor-pixels.mjs
node scripts/compatibility/image-editor-transport.mjs
node scripts/compatibility/image-editor-scheduling.mjs
```

The current upload contract passes 217 checks with optional Spatie Tags available.
It now includes stored original resolution, request-to-request edited source
persistence, inherited editorial metadata/order, completion reconciliation and
real disk/Spatie edited-image worker jobs. The pixel probe passes 66 checks; the
transport probe covers owner/entanglement boundaries and concurrent/error paths.
See `.internal/file-upload-editor-audit.md` for evidence and explicit limits. These probes
do not certify proprietary filter equivalence, physical mobile devices or live S3.

Core build preflight: `npm run audit:core:build` runs ten groups using disposable
artifacts and actual native framework events. It verifies bounded strict metadata,
format/hash/root/PHP–JS parity, independent scoped snapshots, external packages,
read-only corruption rejection before native publication, explicit local fallback
and production denial. These probes live outside tests/** because existing asset
suites do not exercise this new release/preflight contract. The standalone validator
is `php scripts/assets/validate-build.php`; installed packages additionally support
`php artisan fff:assets:doctor --json`. See the runtime guide for deployment limits.
