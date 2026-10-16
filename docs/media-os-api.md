---
title: "Media OS storage API"
description: "Practical examples for sources, storage, receipts, URLs, verification and deletion."
icon: "code-xml"
---

Use `FlexMedia` in application services, controllers, commands and jobs. Examples use an application-owned `$source`, `$intent` or `$receipt` when introduced in an earlier example; disk names must exist in `config/filesystems.php`.

## Common imports

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
```

## Local file

```php
$source = MediaSource::path(storage_path('app/imports/report.pdf'), 'report.pdf');
$intent = new MediaWrite(disk: 'local', directory: 'reports');
$receipt = FlexMedia::store($source, $intent);
```

The source path is read, not moved or deleted. Choose a safe destination directory; filenames cannot contain path traversal or multiple path segments.

## HTTP request upload

`MediaSource::upload()` accepts a Livewire `TemporaryUploadedFile`. For Laravel's ordinary request `UploadedFile`, use its local path:

```php
$file = $request->file('document');
$source = MediaSource::path($file->getRealPath(), $file->getClientOriginalName());
$receipt = FlexMedia::store($source, new MediaWrite(
    disk: 'local', directory: 'documents',
    policy: new MediaPolicy(mimeTypes: ['application/pdf']),
));
```

Validate presence and the request's rules before this example. Media OS independently validates the inspected bytes at permanent storage.

## Livewire temporary upload

```php
$source = MediaSource::upload($temporaryUploadedFile);
$receipt = FlexMedia::store($source, new MediaWrite(directory: 'attachments'));
```

## Borrowed stream

```php
$stream = fopen($localFile, 'rb');
try {
    $receipt = FlexMedia::store(
        MediaSource::stream($stream, 'archive.zip'),
        new MediaWrite(disk: 's3', directory: 'archives'),
    );
} finally {
    fclose($stream);
}
```

Media OS reads from the stream's current position. It does not rewind or close a borrowed stream. Close it in your own `finally` block.

## Copy between configured disks

```php
use Illuminate\Support\Facades\Storage;

$stream = Storage::disk('imports')->readStream('incoming/manual.pdf');
try {
    $receipt = FlexMedia::store(
        MediaSource::stream($stream, 'manual.pdf'),
        new MediaWrite(disk: 's3', directory: 'manuals'),
    );
} finally {
    if (is_resource($stream)) {
        fclose($stream);
    }
}
```

## Small inline file

```php
$receipt = FlexMedia::store(
    MediaSource::bytes("Item,Quantity\nBook,2\n", 'order.csv'),
    new MediaWrite(disk: 'local', directory: 'exports'),
);
```

Inline sources have a separate 4 MiB ceiling. Use a stream or path for larger files.

## Exact filename

```php
$intent = new MediaWrite(
    disk: 'local', directory: 'invoices',
    filename: 'invoice-2026-001.pdf',
);
$receipt = FlexMedia::store($source, $intent);
```

Existing destinations are rejected rather than overwritten. Omit `filename` for a generated destination. A chosen filename does not bypass MIME validation.

## File policy

```php
$policy = new MediaPolicy(
    minBytes: 1024,
    maxBytes: 8 * 1024 * 1024,
    mimeTypes: ['application/pdf', 'image/*'],
    visibility: 'private',
    verifyIntegrity: true,
);
$receipt = FlexMedia::store($source, new MediaWrite(policy: $policy));
```

MIME types come from content inspection. An empty MIME list allows any inspected type within the byte policy; use an allow-list for a restricted workflow.

## Attach application context

```php
$intent = new MediaWrite(
    disk: 's3', directory: 'contracts', kind: 'document',
    field: 'contract', record: $savedRecord,
    metadata: ['department' => 'legal', 'category' => 'supplier'],
);
$receipt = FlexMedia::store($source, $intent);
```

`metadata` is context for your policies, processors and listeners. It is not automatically a new database column. Its JSON representation is limited to 64 KiB.

## Inspect without storing

```php
$prepared = FlexMedia::os()->inspect($source, $intent);
try {
    $mime = $prepared->file->mimeType;
    $bytes = $prepared->file->bytes;
    $sha256 = $prepared->file->sha256;
    $width = $prepared->file->width;
    $height = $prepared->file->height;
} finally {
    $prepared->close();
}
```

Inspection applies policy, configured processors and the pre-write scan. The returned snapshot belongs to your caller; close it after use.

## Read the storage result

| Receipt property | Meaning |
| --- | --- |
| `adapter` | Storage integration, normally `disk` or `spatie` |
| `identifier` | Adapter-specific file reference |
| `disk`, `path` | Actual storage disk and relative path |
| `file->bytes`, `file->mimeType` | Measured stored content |
| `file->sha256`, `file->originalName` | Content checksum and source name |
| `file->width`, `file->height` | Image dimensions when readable |
| `operationId` | Correlation value for your logs and events |
| `native` | Native adapter object, such as a Spatie `Media` model |

A receipt describes an object; possession of one does not grant access to it. Use receipts retained by your application, with the same record and policy context used at storage.

## Private temporary URL

```php
$url = FlexMedia::os()->url($receipt, $intent, now()->addMinutes(15));
```

Returns `null` when the adapter cannot provide the requested URL. A failed private signing operation does not become a public link. Configure temporary URL support on your disk or [provide your own signing resolver](/docs/media-os-configuration#custom-signed-urls).

## Verify a saved file

```php
FlexMedia::os()->verify($receipt, $intent);
```

Rechecks persisted bytes and policy, including the post-write scan. Integrity verification defaults to true; the optional third argument controls checksum comparison for that explicit verification call.

## Delete an owned file

```php
FlexMedia::os()->delete($receipt, $intent);
```

For an image bundle, use `FlexMedia::images()->delete($bundle)` so all owned variants are included. For queued variants, [cancel their plan before deleting the original](/docs/media-os-images#cancel-background-conversions).

## Reject before publication

```php
$receipt = FlexMedia::store($source, $intent,
    finalize: function (MediaReceipt $receipt, MediaWrite $intent): void {
        if ($receipt->file->bytes === 0) {
            throw new MediaFailure('empty_document', 'The document is empty.');
        }
    },
);
```

Throwing rejects the new write and attempts to remove files created by that operation. A finalizer is for acceptance checks. Transform content with a processor, not by mutating an already measured file.

## Handle rejection

```php
try {
    $receipt = FlexMedia::store($source, $intent);
} catch (MediaFailure $failure) {
    report($failure);
    $reason = $failure->reason;
    $needsCleanupReview = $failure->cleanupFailed;
}
```

Use stable reason codes such as `size_rejected` and `type_rejected` for your application's messages. Keep exception chains and storage credentials out of end-user messages.

## API reference

| Method | Result / purpose |
| --- | --- |
| `FlexMedia::store($source, $intent, $finalize = null)` | Store and return a `MediaReceipt` |
| `FlexMedia::os()` | Resolve the storage service |
| `os()->inspect($source, $intent)` | Owned `PreparedMedia` snapshot |
| `os()->verify($receipt, $intent, $integrity = true)` | Revalidate a persisted object |
| `os()->authorize($action, $intent, $receipt = null)` | Run registered access policies |
| `os()->url($receipt, $intent, $expiresAt, $conversion = '')` | Authorized URL or `null` |
| `os()->delete($receipt, $intent)` | Delete an authorized object |
| `os()->compensate($receipt, $intent, $policy = null)` | Cleanup a rejected operation; returns success boolean |
| `FlexMedia::images()` | [Synchronous image bundles](/docs/media-os-images) |
| `FlexMedia::queuedImages()` | [Background conversion plans](/docs/media-os-images#background-conversions) |
| `FlexMedia::staging()` | [Temporary leases and recovery](/docs/media-os-lifecycle) |

`compensate()` is intended for rejection handling, including configured quarantine. Use ordinary `delete()` for a customer's deliberate removal.

## Write options reference

| `MediaWrite` option | Default / purpose |
| --- | --- |
| `adapter` | `disk`; registered adapter name |
| `kind` | `upload`; application media category |
| `disk` | null, resolved from defaults |
| `directory` | `uploads` |
| `filename` | null, generated filename |
| `field`, `record` | Optional field and Eloquent ownership context |
| `spatie` | Optional typed `SpatieOptions` |
| `policy` | Optional `MediaPolicy`; otherwise global defaults |
| `metadata` | JSON-compatible application context, up to 64 KiB |
| `processors` | Ordered registered names, up to 32 |
| `operationId` | Optional bounded correlation identifier; generated when omitted |

`operationId` correlates operations; it does not make a direct store call an idempotent retry. Use staging receipts for retry recovery.

## Policy options reference

| `MediaPolicy` option | Default / purpose |
| --- | --- |
| `maxBytes` | 268,435,456 bytes |
| `minBytes` | 0 |
| `mimeTypes` | Empty allow-list; exact inspected MIME or wildcard family |
| `requireScanner` | false |
| `scanBeforeWrite`, `scanAfterWrite` | true |
| `verifyIntegrity` | true |
| `visibility` | `private` |
| `quarantineDisk` | null |

A policy must have a positive maximum and a minimum between zero and that maximum. Required scanning cannot disable either scan boundary.

## Copy a write with a different policy

```php
$restricted = $intent->withPolicy(new MediaPolicy(
    maxBytes: 2 * 1024 * 1024, mimeTypes: ['application/pdf'],
));
$receipt = FlexMedia::store($source, $restricted);
```

## Correlate application operations

```php
$identified = $intent->identified((string) Str::uuid());
```

Import `Illuminate\Support\Str`. `withoutProcessors()` produces a write with the same other options but an empty processor list; only use it when deliberately operating on an already processed source.

```php
$preparedWrite = $intent->withoutProcessors();
```
