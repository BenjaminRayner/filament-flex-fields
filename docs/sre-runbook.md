---
title: "Monitoring & operations"
icon: "activity"
---

# Monitoring & operations

Short operational checklist for Flex Fields asset loading, injector health, and select regressions. Intended for maintainers and advanced self-hosted deployments.

---

## Asset publish

After every package update or dist rebuild:

```bash
composer update janczakb/filament-flex-fields
php artisan filament:assets
```

Automate in `composer.json` post-autoload when possible so CI and local installs stay in sync. If styles look stale, confirm `public/` (or your Filament asset path) contains fresh `janczakb/filament-flex-fields` files and that config cache was cleared.

---

## Injector debug

Lazy CSS and Alpine chunks load through `flex-field-asset-injector.js`. When assets fail after SPA navigation or nested modals:

1. Open DevTools → Network and filter `filament-flex-fields`.
2. Confirm `data-fff-asset-batch` markers appear on morph payloads.
3. Check for duplicate `<link rel="stylesheet">` tags with the same href.
4. Verify protected links (`data-fff-stylesheet`, `data-fff-alpine-chunk`, `data-fff-playground-bundle`) are not removed during dedupe.
5. On playground pages, ensure per-slug bundles load once via `data-fff-playground-bundle`.

Common fixes: hard refresh, re-run `filament:assets`, disable aggressive CDN HTML minifiers that strip data attributes.

---

## Asset Inspector

Use the dev-time Asset Inspector (`flex-field-asset-inspector.js`) to list loaded Flex Fields URLs and detect duplicate hrefs / Alpine chunks after SPA navigation:

```javascript
const inspector = FffAssetInspector?.create?.({ getLoadedUrls: () => [...] })
inspector?.inspect() // { urls, duplicates }
```

**Pass criteria:** zero duplicates after full page load, modal open/close, and `wire:navigate` between two resource pages that use different flex fields. Shared chunks (`flex-fields-select-menu-*`, `flex-fields-search-normalize-*`) and `teleported-menu` CSS must appear once even when select / phone / country / timezone / currency / tags share a page.

For loading diagnostics, use the dedicated asset lifecycle audit page. The former upload-page skeleton demonstration and its stored preference are removed.

---

## Headless Select attach

Production SelectField uses the headless combobox Alpine entry (`fffHeadlessSelectField`). Successful init sets `data-fff-select-attached="true"` on `.fff-select-field__shell`.

E2E / SRE checks:

1. Wait for `.fff-select-field__shell[data-fff-select-attached="true"]` before opening menus.
2. Attached shell count should equal total `.fff-select-field__shell` count on the page.
3. Missing attribute after load ⇒ Alpine chunk failed to register or headless init aborted (check Network for `select-field` / select-menu chunks).

Helper: `waitForSelectCoordinatorAttached()` in `tests/e2e/helpers/console-errors.mjs`.

---

## Application events and monitoring

FLEX dispatches immutable, typed events through Laravel's standard event
dispatcher. No monitoring driver, external connection, automatic log stream or
`enterprise` configuration is required. With no application listeners, these
notifications perform no monitoring I/O.

All event classes below live in `Bjanczak\FilamentFlexFields\Support\Events`:

| Event class | When it is dispatched | Useful properties |
|-------------|-----------------------|-------------------|
| `FieldMounted` | Select field state hydration | `field`, `type` |
| `SelectSearched` | Server-side select search cache miss | `field`, `queryHash`, `queryLength`, `source`, `hasCursor` |
| `UploadSucceeded` | Successful Media OS persistence | `field`, `adapter`, `kind`, `correlationId` |
| `UploadFailed` | Media OS persistence failure or post-persist virus-scan rejection | `field`, `adapter`, `reason`, optional `kind`, `correlationId`, `stage`, `path` |
| `RetentionPruned` | Retention command notification, including dry runs | `category`, `count`, `items`, `dryRun` |
| `BarcodeCaptured` | Barcode capture notification | `field`, `value`, `format`, `context` |

`field` can be `null` for direct Media OS calls outside a field. `hasCursor` is
`null` for non-paginated searches. Retention `items` contains at most 25 sample
paths; `count` is the full operation count. Dry runs report their intent without
claiming that files were deleted. `FieldMounted` describes hydration, not a count
of unique visitors or unique DOM mounts. Upload notifications describe the media
operation; they do not guarantee that a surrounding application database
transaction has committed. After-commit listeners can follow that transaction.

### Subscribe using Laravel

Register a listener once in your application's provider `boot()` method, or use
Laravel event discovery for a typed listener class:

```php
use Bjanczak\FilamentFlexFields\Support\Events\UploadFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

public function boot(): void
{
    Event::listen(UploadFailed::class, static function (UploadFailed $event): void {
        Log::channel('stack')->warning('Upload failed', [
            'field' => $event->field,
            'adapter' => $event->adapter,
            'reason' => $event->reason,
            'correlation_id' => $event->correlationId,
        ]);
    });
}
```

Every event implements `ComponentActivity` and exposes `toArray()`. Listen to
`ComponentActivity::class` to receive the whole group. Existing richer Media OS
notifications (`MediaStored`, `MediaRejected`, `MediaDeleted`,
`ImageBundleStored`) remain available; choose the relevant event stream rather
than counting the same operation in both.

### Queue slow monitoring

Use a normal Laravel queued listener for external monitoring. For example,
register `Event::listen(UploadFailed::class, LogUploadFailure::class)` with this
application listener:

```php
namespace App\Listeners;

use Bjanczak\FilamentFlexFields\Support\Events\UploadFailed;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

final class LogUploadFailure implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $timeout = 10;

    public function handle(UploadFailed $event): void
    {
        Log::channel('stack')->warning('Upload failed', [
            'field' => $event->field,
            'reason' => $event->reason,
            'correlation_id' => $event->correlationId,
        ]);
    }
}
```

Configure a non-`sync` Laravel queue connection and run its worker to move that
work outside the request. Built-in event properties contain snapshots, not field instances, uploaded
file handles or model objects. Keep any caller-supplied barcode `context` small
and serializable (scalar values and arrays), without service/model instances. Normal Laravel queue retry and failed-job handling
apply. After-commit listeners are discarded if the surrounding transaction rolls
back; use `ShouldQueue` instead when failed/rolled-back operations must also be
reported. Neither stream is a durable audit outbox or a guaranteed delivery system.

Notifications are best effort: a synchronous listener or queue submission failure
is reported through Laravel's exception handler without replacing the component's
result. Laravel stops the current synchronous listener chain when a listener
throws; keep listeners short and independently reliable. Recursive delivery of the
same activity class is suppressed until that delivery ends. The guard is scoped
to the current request/job; FLEX retains no listener registry.

In Octane and queue workers, register listeners once. Never register per-request
closures capturing a user or tenant. Keep listener classes stateless and resolve
current tenant/request services inside `handle()` using `app(...)`; do not retain
request-specific services in process-lived listener constructors. Queued consumers
must obtain tenant identity from an application-defined trusted context rather
than relying on the worker's HTTP authentication state.

### Data handling and migration

PHP search events contain a hash and length, never the raw query. A hash is not a
promise of anonymization. Barcode values, custom context and file paths can be
sensitive: explicitly select permitted properties for logs and apply your
application's access and retention policies.

The former `ObservabilityHooks`, `SiemBridge` and their listener state have been
removed. Replace `ObservabilityHooks::on(...)` registrations with `Event::listen`
and typed event properties (or `toArray()`). Remove the `enterprise` block and
`FLEX_FIELDS_ENTERPRISE_ENABLED` / `FLEX_FIELDS_SIEM_*` variables from published
application configuration, then rebuild your configuration cache if used. There
is no replacement switch or driver: the application controls which listeners are
registered and where they send events.

### Media Capture retention

Schedule (when `media_capture.retention.schedule_enabled=true`):

```bash
php artisan flex-fields:prune-capture-media
php artisan flex-fields:prune-capture-media --category=temp_captures --dry-run
```

Key env vars: `FLEX_FIELDS_REQUIRE_VIRUS_SCAN`, `FLEX_FIELDS_QUARANTINE_DISK`, `FLEX_FIELDS_RETENTION_*`, `FLEX_FIELDS_MEDIA_TENANT_*`.

See [Media Ingress](/docs/media-capture-os) and [Media Ingress migration](/docs/media-ingress-migration).

Overlay exclusive open cannot reach PHP; the runtime dispatches a browser CustomEvent instead:

```javascript
window.addEventListener('fff:observability', (e) => {
  // e.detail.event — 'overlay.open' | 'select.search' (client Livewire fetch)
  // e.detail.id for overlays; e.detail.queryLength for client search
})
```

Browser events remain independent of PHP listeners. They are local browser notifications; FLEX does not automatically send them to the server or an external monitoring endpoint.

---

## Select race checklist

Run after any SelectField or teleported-menu change:

- [ ] Headless shells expose `data-fff-select-attached="true"` after first paint.
- [ ] Open dropdown → select option → close → reopen: menu opens cleanly (no stuck overlay).
- [ ] Multi-select checklist: selected rows stay visible with checkmarks; menu does not flicker on scroll.
- [ ] Grid layout: selected badge keeps solid circular background in portaled panel.
- [ ] Phone / country / timezone / currency pickers: scrolling inside menu does not replay open animation.
- [ ] Modal action with select: assets retained while modal open; no FOUC on first open.
- [ ] Console: no errors from select coordinator attach/detach.

Playwright: `tests/e2e/playground-select.spec.mjs` and `field-smoke.spec.mjs` when `FLEX_FIELDS_PLAYGROUND_URL` is set.

---

## FFART asset runtime checklist

Run after any change to `flex-field-asset-injector.js`, `flex-field-consumer-graph.js`, `emit-assets`, or modal/slide-over asset hooks:

- [ ] `npm run test:consumer-graph` — logic + property + chaos + inspector (80+ unit tests).
- [ ] No duplicate `link[href*="filament-flex-fields"]` in `<head>` for the same normalized URL (Inspector **I1** / **I33**).
- [ ] `FffAssetInspector.inspect().legacySymbols` is empty (**I34** — no `pageRetainedUrls` / `modalOwnedUrls`).
- [ ] Page field + modal field same `livewireKey` → shared URL `refCount ≥ 1` after modal close (**REQ-3** / **I5**).
- [ ] Modal-only CSS/chunks uninstalled after modal close (**REQ-4** / **I6**).
- [ ] Nested modals: LIFO stack pop only — parent assets survive child open/close (**I7**).
- [ ] `wire:navigate` / Livewire tab swap: page batches resync, modal leftovers gone (**I8**).
- [ ] Slide-over (`.fi-modal-slide-over`) uses the same modal stack as centered modals (**REQ-5** / **I9**).

### Browser inspector (dev)

With playground or admin panel open:

```javascript
FffAssetInspector.inspect()
```

Check `failingInvariants` is `[]`, `duplicates` is `[]`, and `performanceMarks` includes `fff:load` after opening a heavy field.

### E2E (requires `FLEX_FIELDS_PLAYGROUND_URL`)

| Spec | Requirement |
|------|-------------|
| `asset-page-modal-select.spec.mjs` | REQ-3 |
| `asset-modal-only-uninstall.spec.mjs` | REQ-4 |
| `asset-network-dedup.spec.mjs` | REQ-1 |
| `asset-action-select-slideover.spec.mjs` | REQ-5 |
| `asset-action-fillform-select.spec.mjs` | fillForm retain |

---

## Component audit WARN policy

`npm run audit:components` reports three statuses per component:

| Status | Meaning | Release gate |
|--------|---------|--------------|
| **OK** | Within PHP/JS line budgets and asset wiring complete | Required |
| **WARN** | Asset wiring OK but PHP or JS entry exceeds monolith line budget (500 PHP / 400 JS) | Allowed for v3 heavy fields (SelectField, FlexRichEditor, SignatureField) — track in 3.2 split backlog |
| **FAIL** | Missing dist CSS/JS, broken Alpine manifest, or critical asset gap | Blocks release |

WARN rows do **not** fail the component-audit command. Treat them as refactor signals: prefer splitting Alpine mixins and PHP concerns before adding features to monolith entries. Re-run the audit after JS/CSS splits to confirm status moves to OK.

---

## Build manifests

The package build generates `resources/dist/asset-registry.json` and the bundled
icon catalog together with the browser assets. Run `npm run build` in the package
checkout and ship its complete output. Do not regenerate manifests inside an
installed application or modify files under `vendor/` after deployment.

Publish the shipped assets with `php artisan filament:assets`, then verify them
with `php artisan fff:assets:doctor --published`. Custom icon sets are resolved
through Blade Icons without rewriting the bundled catalog.

---

## Escalation

File reproducible steps at [GitHub Issues](https://github.com/janczakb/filament-flex-fields/issues). Include Filament version, browser, whether SPA navigation is enabled, and a HAR or screenshot of duplicate stylesheet loads when asset-related.
