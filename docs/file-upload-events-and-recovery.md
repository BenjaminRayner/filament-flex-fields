---
title: "Upload events and recovery"
description: "Connect PHP callbacks, temporary transfer observations, custom save flows and retry recovery."
icon: "rotate-ccw"
---

Use storage events for authoritative file operations. Browser transfer events are optional observations. Neither event family replaces your application's authorization.

## Stored file callback

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;

FlexFileUpload::make('contract')
    ->onUploadEvent(UploadEventType::Stored, function (UploadEvent $event): void {
        logger()->info('Attachment stored', ['operation' => $event->receipt?->operationId]);
    });
```

`Stored` confirms permanent storage. It does not prove that the database record has committed its reference.

## Record acceptance callback

```php
FlexFileUpload::make('contract')
    ->onUploadEvent(UploadEventType::Accepted, function (UploadEvent $event): void {
        logger()->info('Attachment accepted', ['operation' => $event->receipt?->operationId]);
    });
```

Use `Accepted` for work that requires the saved model reference. For guaranteed external notification, write an application outbox and deduplicate by operation ID.

## Rejection and deletion

```php
FlexFileUpload::make('contract')
    ->onUploadEvent(UploadEventType::Rejected, function (UploadEvent $event): void {
        logger()->warning('Attachment rejected', [
            'reason' => $event->reason, 'cleanup_failed' => $event->cleanupFailed,
        ]);
    })
    ->onUploadEvent(UploadEventType::Deleted, function (UploadEvent $event): void {
        logger()->info('Attachment removed', ['bytes_freed' => $event->bytesFreed]);
    });
```

## Recovery notification

```php
FlexFileUpload::make('contract')
    ->onUploadEvent(UploadEventType::Recovered, function (UploadEvent $event): void {
        logger()->info('Attachment recovered', ['operation' => $event->receipt?->operationId]);
    });
```

| Event | Meaning |
| --- | --- |
| `Stored` | Permanent file/bundle stored |
| `Accepted` | Saved record reference confirmed after commit |
| `Recovered` | Explicit recovery completed |
| `Rejected` | Storage/admission rejected |
| `Deleted` | Owned file removed |

`UploadEvent` exposes `type`, `intent`, optional `receipt`, optional `reason`, `bytesFreed` and `cleanupFailed`. Events also dispatch through Laravel. Callback failures are reported without reversing a completed storage operation.

## Application-wide listener

```php
use Illuminate\Support\Facades\Event;

Event::listen(UploadEvent::class, function (UploadEvent $event): void {
    logger()->info('Upload operation', ['type' => $event->type->value]);
});
```

## Pre-publication acceptance check

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

FlexFileUpload::make('contract')
    ->mediaFinalizeUsing(function (MediaReceipt $receipt, MediaWrite $intent): void {
        if ($receipt->file->bytes === 0) {
            throw new MediaFailure('empty_contract', 'The contract is empty.');
        }
    });
```

Throw to reject before publication. Use a processor for byte transformations and an event for after-storage side effects.

## Existing storage callbacks

```php
use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

FlexFileUpload::make('document')
    ->afterPersistUploadedFileUsing(
        function (BaseFileUpload $component, string $identifier, TemporaryUploadedFile $file): void {
            logger()->info('Document stored', ['identifier' => $identifier]);
        },
    )
    ->deletedStoredFileUsing(
        function (BaseFileUpload $component, string $identifier, int $bytesFreed): void {
            logger()->info('Document deleted', ['bytes' => $bytesFreed]);
        },
    );
```

These callbacks are available on disk and Spatie variants with confirmed-operation semantics. Prefer typed `onUploadEvent()` when you need the receipt and write context.

## Observe transfer failures

```php
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadTransportEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadTransportEventType;

FlexFileUpload::make('attachments')->multiple()
    ->onUploadTransportEvent(UploadTransportEventType::Failed,
        function (UploadTransportEvent $event): void {
            logger()->warning('Transfer observation', ['field' => $event->field]);
        },
    );
```

## Observe every transfer event

```php
FlexFileUpload::make('attachments')->multiple()
    ->onUploadTransportEvent(null, function (UploadTransportEvent $event): void {
        logger()->debug('Transfer', [
            'type' => $event->type->value,
            'progress' => $event->progress,
            'source' => $event->source,
        ]);
    })
    ->uploadTransportProgressInterval(1500);
```

Progress reporting interval is 500–10,000 ms. Without subscriptions the field sends no transport-observation requests.

Supported types: `Added`, `Started`, `Progress`, `Completed`, `Failed`, `Cancelled`, `Paused`, `Resumed`, `Removed`, `Retried`, `Reordered`, `QueuePaused`, `QueueResumed`.

The typed observation includes field path, session, sequence, optional file ID, percentage and source (`file`, `url`, `webcam`). Browser observations are best-effort and can be unavailable. Do not use them as proof of persistence, access rights or billing. `Completed` means temporary transfer finished; use `Stored`/`Accepted` for permanent storage.

## Staging and automatic recovery

Both are enabled by default through `media_capture.uploads`:

```php
FlexFileUpload::make('document')->mediaStaging()->mediaAutoRecovery();
```

Disable automatic retry recovery while retaining staging:

```php
FlexFileUpload::make('document')->mediaStaging()->mediaAutoRecovery(false);
```

Disable staging for a field:

```php
FlexFileUpload::make('document')->mediaStaging(false);
```

Recovery reuses recognized operation receipts; it does not restore an entire unsaved form after closing the browser. Ambiguous storage outcomes require server-side reconciliation.

## Create and edit forms

Standard Filament record/relationship lifecycle integrates acceptance for both create and edit. On connections supporting Laravel rollback callbacks, failed transactions compensate newly created media and retain previous accepted files for retry. Connections without that capability require explicit reconciliation of interrupted writes.

If you replace the save lifecycle, persist the file state and editorial companion values, then complete acceptance using the actual field instance from your form:

```php
// $uploadField is the configured, mounted field used by this form.
$record->save();
$uploadField->completeMediaUploadsAfterSave($record);
```

This is transaction-aware and waits for commit. Do not call it with a different newly constructed field. The saved record must actually reference the uploaded identifier.

## Custom reference proof

```php
use Illuminate\Database\Eloquent\Model;

FlexFileUpload::make('attachment')
    ->mediaReferenceUsing(fn (Model $record, string $identifier): bool =>
        $record->attachments()->where('path', $identifier)->exists(),
    );
```

Use this when your save code maps field state to a custom relation rather than a directly saved attribute.

## Custom staging owner

```php
FlexFileUpload::make('attachment')
    ->mediaStagingOwnerUsing(fn (): string =>
        'user:'.auth()->id().':form:'.session()->getId().':attachment',
    );
```

Choose a trusted, stable owner that uniquely isolates the intended form/file workflow. For multiple simultaneously open forms, include a server-issued form identity. Never accept an arbitrary owner value from browser input.

## Explicit abandonment and recovery

```php
$uploadField->abandonMediaUpload($serverOwnedLeaseKey);
```

```php
$identifier = $uploadField->recoverMediaUpload(
    $serverOwnedLeaseKey, $originalIntent, $reconciledUploadManifest,
);
```

Recovery takes a complete independently located `UploadManifest` and matching `MediaWrite`, verifies ownership/content, and returns the primary identifier. Your application applies and saves the recovered reference, then confirms acceptance. These are authorized server APIs, not unrestricted client actions.

## Cleanup

```bash
php artisan flex-fields:prune-media-staging --dry-run
```

Run the scheduler and configure [private staging/receipt storage](/docs/media-os-configuration#temporary-staging). The cleanup process preserves uncertain permanent writes instead of deleting them by age. Explicit retention is a separate policy.
