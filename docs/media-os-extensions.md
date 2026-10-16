---
title: "Media OS extensions and events"
description: "Connect your antivirus, processing, authorization, storage adapters and application listeners."
icon: "cable"
---

Register named extension factories in your application service provider's `boot()` method. Examples with `App\...` services refer to services supplied by your own application. Factories resolve within the current Laravel request/job scope.

## Antivirus scanner

Media OS has an integration contract, not a built-in antivirus engine. Your scanner implements `MediaScanner` and returns normally only after its provider certifies clean content.

```php
namespace App\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaScanner;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;

final class AntivirusScanner implements MediaScanner
{
    public function __construct(private AntivirusClient $client) {}

    public function scan(PreparedMedia $file, MediaWrite $intent, string $stage): void
    {
        if (! $this->client->isCertifiedClean($file->path)) {
            throw new MediaFailure('scan_rejected', 'The file could not be accepted.');
        }
    }
}
```

`AntivirusClient` is your provider client, for example a ClamAV connector or an external scanning service. Its timeout/unavailable/unknown results must not be treated as clean.

```php
use App\Media\AntivirusScanner;
use Bjanczak\FilamentFlexFields\Support\Media\MediaExtensions;

MediaExtensions::scanner('antivirus', fn () => app(AntivirusScanner::class));
```

Require scanning for a write:

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;

$policy = new MediaPolicy(requireScanner: true, quarantineDisk: 'quarantine');
```

Every registered scanner must succeed at enabled boundaries. Required scanning without a provider rejects the operation. Keep quarantine private.

## Processor that transforms content

Processors run in the configured order before persistence. Return the same `PreparedMedia` or a new owned snapshot. This example uses an application-provided PDF sanitizer:

```php
namespace App\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;

final class PdfSanitizer implements MediaProcessor
{
    public function __construct(private PdfSanitizerClient $client) {}

    public function process(PreparedMedia $file, MediaWrite $intent): PreparedMedia
    {
        $output = $this->client->sanitizeToTemporaryFile($file->path);
        try {
            return PreparedMedia::prepare(
                MediaSource::path($output, $file->file->originalName),
                $intent->policy ?? new MediaPolicy(),
            );
        } finally {
            @unlink($output);
        }
    }
}
```

Your sanitizer client must return its own temporary file. The returned prepared snapshot is independent of that temporary output. Do not close or mutate the borrowed input.

Register and select the processor:

```php
use App\Media\PdfSanitizer;
use Bjanczak\FilamentFlexFields\Support\Media\MediaExtensions;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

MediaExtensions::processor('sanitize-pdf', fn () => app(PdfSanitizer::class));
$intent = new MediaWrite(directory: 'documents', processors: ['sanitize-pdf']);
```

Processors are also suitable for normalizing a source into a smaller master before storage. Their output is remeasured and must still satisfy policy. Conversions alone retain the original.

## Custom access policy

```php
namespace App\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAccessPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Illuminate\Support\Facades\Gate;

final class AttachmentAccess implements MediaAccessPolicy
{
    public function authorize(string $action, MediaWrite $intent, ?MediaReceipt $receipt): void
    {
        Gate::authorize('manage-attachment', [$intent->record, $action, $receipt]);
    }
}
```

Define `manage-attachment` in your application's authorization layer. It must handle create uploads where `record` is null and enforce ownership of any supplied receipt.

```php
MediaExtensions::accessPolicy('attachments', fn () => app(\App\Media\AttachmentAccess::class));
```

Policies cover operations such as inspect, store, verify, URL access, delete, convert, recovery and metadata where invoked by the integration. They supplement your form/record authorization. Background workers must have the tenant context required by these policies.

## Custom storage adapter

```php
MediaExtensions::adapter('archive', fn () => app(\App\Media\ArchiveAdapter::class));
```

```php
$receipt = FlexMedia::store($source, new MediaWrite(adapter: 'archive', kind: 'document'));
```

Implement `Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter`:

| Method | Your adapter supplies |
| --- | --- |
| `write(PreparedMedia $file, MediaWrite $intent): MediaReceipt` | New stored object and its measured receipt |
| `read(MediaReceipt $receipt)` | Fresh readable stream; caller closes it |
| `commit(MediaReceipt $receipt, MediaWrite $intent): void` | Final publication/visibility |
| `url(MediaReceipt $receipt, DateTimeInterface $expiresAt, string $conversion = ''): ?string` | Safe public/signed URL or null |
| `delete(MediaReceipt $receipt): void` | Removal of that exact owned object |

A failed write must clean up partial objects before a receipt exists. Implement `MediaReceiptResolver` if native adapter objects must be reconstructed from durable receipts. Disk FLEX fields need path-compatible references; an adapter with arbitrary IDs needs a matching field integration.

## Replace a registration

```php
MediaExtensions::scanner('antivirus', fn () => app(\App\Media\AlternativeScanner::class), replace: true);
```

Use explicit replacement when overriding an already registered name. Adapter, processor and policy registration support the same `replace` flag. Names are lowercase identifiers; each group supports up to 64 registered extensions.

## Custom image encoder

Bind your own `Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder` implementation when you need Imagick, libvips, ICC preservation or an external image service:

```php
$this->app->scoped(
    \Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder::class,
    fn () => app(\App\Media\VipsImageEncoder::class),
);
```

The encoder must honor the typed conversion, input/output policy and limits, and return owned `PreparedMedia`. Storage, scanning and verification remain with Media OS.

## Stored event

```php
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaStored;
use Illuminate\Support\Facades\Event;

Event::listen(MediaStored::class, function (MediaStored $event): void {
    logger()->info('Media stored', [
        'operation' => $event->receipt->operationId,
        'bytes' => $event->receipt->file->bytes,
    ]);
});
```

## Rejected event

```php
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaRejected;

Event::listen(MediaRejected::class, function (MediaRejected $event): void {
    logger()->warning('Media rejected', [
        'reason' => $event->reason,
        'cleanup_failed' => $event->cleanupFailed,
    ]);
});
```

## Deleted event

```php
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaDeleted;

Event::listen(MediaDeleted::class, function (MediaDeleted $event): void {
    logger()->info('Media deleted', ['operation' => $event->receipt->operationId]);
});
```

## Complete image bundle event

```php
use Bjanczak\FilamentFlexFields\Support\Media\Events\ImageBundleStored;

Event::listen(ImageBundleStored::class, function (ImageBundleStored $event): void {
    logger()->info('Image variants ready', ['count' => count($event->bundle->variants)]);
});
```

This synchronous-bundle event means all requested variants are ready. Individual `MediaStored` events apply to each stored object. For queued conversions use their processing status; do not treat original storage as variant completion.

## Acceptance checks and notifications

Use `store(..., finalize: ...)` for a pre-publication veto. Use events for notifications after the operation. Listener failure is reported and does not reverse committed media. If you need guaranteed delivery to another system, use your application's transactional outbox and deduplicate by operation ID.

For upload-specific stored/accepted events and browser progress observations, see [FLEX events](/docs/file-upload-events-and-recovery).
