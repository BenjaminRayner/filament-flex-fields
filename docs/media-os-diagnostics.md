---
title: "Check your Media OS setup"
sidebarTitle: "Diagnostics"
description: "Inspect Media OS configuration, then explicitly test storage, locks and image conversion with synthetic diagnostic data and actionable reports."
icon: "activity"
---

Use the media doctor after configuring storage or preparing a deployment. It
inspects the installed application's defaults and explains the next step for each
detected problem.

```bash
php artisan fff:media:doctor
```

The configuration doctor does not read or write media, connect to storage, acquire locks,
dispatch jobs, run cleanup or invoke extension factories. It runs after normal
Laravel application boot; it cannot diagnose an application that cannot boot.

## Choose the workflow you use

<CodeGroup>
```bash Uploads and recovery
php artisan fff:media:doctor --json
```
```bash Bundled image encoder
php artisan fff:media:doctor --images --format=webp --format=jpg
```
```bash Background image conversions
php artisan fff:media:doctor --queued \
  --connection=database --queue=media-conversions --timeout=60 --format=webp
```
```bash Separate web and worker hosts
php artisan fff:media:doctor --queued --distributed \
  --connection=database --queue=media-conversions --timeout=60 --json
```
</CodeGroup>

Use the connection, queue and timeout from your actual `ConversionQueueOptions`.
The command does not change those options or discover every field's PHP callbacks.
Without `--connection`, it inspects Laravel's default queue connection. Without
`--format`, the image profile checks WebP output support. Image flags do not add
an image requirement to the basic upload profile.

### Your application uses a custom encoder

```bash
php artisan fff:media:doctor --queued --custom-encoder --json
```

`--images` and `--queued` select the **bundled GD profile** unless you specify
`--custom-encoder`. This is your choice of diagnostic profile, not automatic
detection of a container binding. A custom encoder is reported as unverified;
the command does not instantiate it or assume that it needs GD.

The GD checks inspect functions and format-support flags in the current PHP process. They do not convert
a sample image or prove that every accepted source format, image size or memory
budget will work. JPEG auto-orientation additionally uses EXIF. Run representative
conversion checks on the PHP processes that perform your conversions.

Spatie conversions retain their own Media Library configuration and workers.
The queued profile above checks native FLEX `ConversionQueueOptions`, rather
than certifying a Spatie queue setup. See [Spatie media](/docs/media-os-spatie).

## Understand the result

| Status | Meaning |
| --- | --- |
| `passed` | The stated local configuration or capability check passed. |
| `failed` | A known configuration problem was detected. Follow its next step. |
| `unverified` | This inspection cannot establish the result, or an external workflow needs confirmation. |
| `skipped` | The check was intentionally omitted. |

JSON includes the selected profile, local PHP/Laravel/Filament/Livewire versions,
counts, individual effects and repair advice. Unknown development-version values
are `null`. Versions describe this process, not a separate worker or an open
browser tab.

`configurationValid: true` means **no known configuration errors were found in
the selected profile**. `operationallyVerified` remains `false`: configuration
alone cannot establish worker liveness, storage access or a successful conversion.

Exit codes are `0` for no known configuration errors, `1` for detected errors and
`2` for invalid command options. An exit code of `0` can include `unverified`
checks; do not use it as a complete production-readiness certificate.

The structured report omits raw configuration values, credentials, application
keys, private disk and queue names, local paths, private URLs and extension identities.
Names selected explicitly appear as `explicit` in the profile. Both terminal and
JSON output use the same findings and leave repairs to the operator.

## What gets checked

| Area | Checks |
| --- | --- |
| Default media policy | Byte limits, MIME rules, visibility and required scanning boundaries. |
| Original and recovery disks | Existing declarations, local root or S3 bucket, writable mode, private metadata disks and bounded scoped-disk references. |
| Recovery | Laravel encryption key/cipher, safe relative metadata directories, staging expiry and lock bounds. |
| Coordination | Persistent cache declaration suitable for inter-process locking; custom drivers remain unverified. |
| Images, when selected | Image budgets and local GD output functions, or an unverified custom-encoder profile. |
| Queued conversions, when selected | Native queue options, durable connection requirement, retry window and private journal configuration. |
| Cleanup | Enabled native Artisan tasks actually registered in Laravel's scheduler, missing/duplicate registration and supported retention frequency. |

Disk roles correspond to these `media_capture` keys:

| Role in report codes | Configuration |
| --- | --- |
| `original` | `disk` and the default media `visibility` |
| `staging` | `staging.disk` |
| `receipts` | `uploads.manifest_disk`; upload leases use the same disk |
| `journal` | `images.queue.journal_disk`, with `--queued` |
| `quarantine` | `quarantine_disk`, when configured |

These are application defaults. A field, operation, tenant resolver or Spatie
collection can select different storage and policy. Validate those choices in
their own save/reopen and authorization tests.

### Public images and private recovery metadata

Public originals are supported when the selected original disk and media policy
are intentionally public. Staging, upload receipts, conversion journals and
quarantine still need private storage. A public media disk is not a suitable
default for private originals or recovery metadata. See [storage
configuration](/docs/media-os-configuration#default-storage).

### Retry windows and SQS

For a queue that uses `retry_after`, set an explicit integer greater than the
conversion job's timeout. An omitted value is reported as unverified rather
than guessing the driver's effective default.

SQS visibility is configured at the provider. A Laravel `retry_after` value does
not prove its visibility timeout. Verify the provider setting and actual worker
termination limits. See [Laravel queue timeout
guidance](https://laravel.com/docs/13.x/queues#job-expiration).

### Scheduler registration and execution

The doctor checks the actual registered native commands; an unrelated shell
command containing a task's name does not count. It also detects duplicate
registrations. Disabled built-in tasks remain unverified because the application
may run its own cleanup workflow.

```bash
php artisan schedule:list
```

Check your scheduler service or cron execution separately. Registering a task
does not start Laravel's scheduler. See [Laravel
scheduling](https://laravel.com/docs/13.x/scheduling#running-the-scheduler).

## Before a deployment is accepted

1. Correct detected configuration failures and rerun the same profile.
2. Check that workers consume the selected connection and queue.
3. Verify storage permissions, private serving, shared recovery data and atomic
   locks across participating processes.
4. Verify consistent application keys, package releases and effective configuration
   without copying credentials into a support request.
5. Exercise the actual upload, conversion, record-save and reopen workflow with
   application authorization in place.

## Active probes

After inspecting configuration, use `fff:media:probe` to exercise the selected
infrastructure with synthetic diagnostic data. Each target is explicit: there is
no default disk, cache store or image format. `--write` is required for every run,
including image-only probes, because those create temporary snapshots.

<CodeGroup>
```bash Disk write, read and cleanup
php artisan fff:media:probe --write --disk=private-media
```
```bash Cache lock behavior
php artisan fff:media:probe --write --lock-store=redis
```
```bash Real bundled image conversion
php artisan fff:media:probe --write --format=webp --format=jpg
```
```bash Combined structured report
php artisan fff:media:probe --write \
  --disk=private-media --lock-store=redis --format=webp --json
```
</CodeGroup>

Replace `private-media` and `redis` with your application's configured disk and
cache store names. Run the selected probes from each relevant application or
worker host using that host's PHP runtime and credentials. This command invokes
the configured storage/cache adapters and their factories; unlike the
configuration doctor, it actively connects to the selected backends. It can incur
small storage/cache request charges. Its duration depends on your backend timeout
settings. It does not dispatch jobs, start workers or execute cleanup schedules.

### What a probe demonstrates

| Probe | Work performed |
| --- | --- |
| Disk | Writes fewer than 128 synthetic bytes to a random object with private visibility, reads and compares the exact bytes, checks adapter visibility, deletes that exact object and verifies its absence. |
| Lock | Uses two different owner tokens on one random key; checks acquisition, exclusion, refusal of a foreign release, acquisition after the original owner releases, and owner-aware cleanup. |
| Image | Prepares a fixed 16×8 synthetic PNG and calls the real bundled GD encoder for an 8×4 output in each requested format; verifies output dimensions and MIME, then closes owned snapshots. |

Only selected probes run. A failed disk check does not prevent an independent
lock or image check. The image probe uses a small fixed policy and pixel/memory
budget; it does not convert a customer file, run custom encoders or exercise
Spatie conversions. Check representative uploads and your application's actual
conversion settings separately.

The lock probe checks independent handles **within this process**. An `array`
cache can pass that local check and still provide no cross-process coordination.
Use a persistent shared store for deployment, and verify that participating hosts
actually use the same backend. These probes do not establish crash recovery,
distributed fencing or worker liveness. Private visibility is adapter metadata;
test that unauthorized HTTP clients cannot retrieve private files separately.

### Results and cleanup

Exit `0` means all checks executed for the selected targets passed; exit `1`
means a probe or its cleanup failed; exit `2` means invalid options. The report
has scope `media-active-probe`, `readOnly: false`, a random `runId`, individual
observations and repair advice. `operationallyVerified` stays `false`: passing a
small infrastructure probe does not certify an application's complete workflow.
The report omits disk/store names, roots, credentials, backend exception messages
and customer values. Application bootstrap and custom adapter logging remain
under the application's control.

The disk probe owns only `.fff-diagnostics/<runId>.probe` on the selected disk.
It never lists files or removes a directory. Cleanup is attempted even when a
write fails, because a backend may have written the object before returning an
error. `cleanupRequired: true` identifies a failed cleanup check. After fixing
permissions, remove only that run's diagnostic object on the selected disk.

The terminal prints the run ID before work starts. `--json` prints one complete
report after completion. If the process is forcibly terminated before that report,
the run ID may be unavailable; use your storage administration tools to inspect
the diagnostic namespace and remove only confirmed diagnostic objects. No
automatic sweep is performed. A local empty diagnostic directory may remain.

Diagnostic locks use `fff:diagnostic:<runId>` with a 60-second TTL and are released
by their owner. The command never flushes the cache or forcibly releases another
owner's lock. Interrupted runs depend on the backend's TTL behavior. PHP removes
normal owned temporary snapshots when they are closed; abrupt host/process failure
still needs the platform's temporary-file housekeeping.

Use [asset diagnostics](/docs/extensions/diagnostics) for browser build/publication
problems. Use [media lifecycle and recovery](/docs/media-os-lifecycle) when a stored
operation needs application-level recovery.

## Send results to your existing monitoring

By default the doctor prints its report without dispatching a monitoring event. Add `--emit` to send a compact summary through Laravel's existing event dispatcher:

```bash
php artisan fff:media:doctor --queued --json --emit
php artisan fff:media:probe --write --disk=media --format=webp --json --emit
```

Register an application listener for `MediaDiagnosticsReported` and send its `toArray()` result to the logger, metrics service or queue you already use:

```php
use Bjanczak\FilamentFlexFields\Support\Events\MediaDiagnosticsReported;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(MediaDiagnosticsReported::class, function (MediaDiagnosticsReported $event): void {
    Log::channel('stack')->info('FLEX media diagnostics', $event->toArray());
});
```

The event contains the scope, read-only flag and check codes/statuses. It omits free-form explanations, resource names, credentials, paths and URLs. Failed monitoring listeners cannot change the command's result. Sending events is optional and does not install another monitoring service. Choose an application listener implementing Laravel's `ShouldQueue` if delivery should use your existing queue.

## Follow an upload across the browser and worker

Authorized native disk File Upload responses include `correlationId` for the original operation. This UUID is a diagnostic identity, not an access token. Legacy uploads or application-supplied non-UUID operation names may have no public correlation ID.

Listen for `MediaOperationObserved` on the server:

```php
use Bjanczak\FilamentFlexFields\Support\Events\MediaOperationObserved;

Event::listen(MediaOperationObserved::class, function (MediaOperationObserved $event): void {
    Log::channel('stack')->info('FLEX media operation', $event->toArray());
});
```

Match the browser response's `correlationId` to `original_operation_id`. Original storage and each derivative's storage carry the same original ID; derivative storage also has its own `operation_id`. Queue stages include `journal_id`, allowing the worker's queued, processing, ready, failed or cancelled observations to be grouped with that original.

A `stored` event confirms Media OS storage, **not** a successful form save. Use `UploadEventType::Accepted` for record acceptance. A queued observation records dispatch intent; a ready observation records completion of the conversion journal. Monitoring remains best effort: missing observations are not evidence that an operation did not occur. Use the authorized journal/recovery APIs to inspect durable state.

Only UUID identities, fixed stage names and classified failure reasons are exported. Unknown failure reasons become `other`. File contents, names, signed URLs, model keys and application metadata are excluded. Existing richer application events retain their APIs; do not forward their entire payload when a redacted monitoring event is sufficient.

When a grouped configuration check fails, read the accompanying specific failed
checks. They identify settings such as `media_capture.max_bytes`,
`media_capture.images.variants`, the staging directory, the selected queue's
`driver`, or its `retry_after` window. Repair the named setting, rebuild your
application's configuration cache when applicable, and rerun the same profile on
both web and worker hosts. Reports identify the constraint without exporting its
private configured value. A passing configuration check still does not establish
that a worker is running or that a storage service is reachable.
