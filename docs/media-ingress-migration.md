---
title: "Updating existing media integrations"
description: "Move existing application storage configuration to the typed Media OS API."
icon: "arrow-right-left"
---

Existing capture helpers remain available as compatibility entry points. For new application storage code, prefer `FlexMedia::store()` with `MediaSource`, `MediaWrite` and typed failure handling. This guide concerns your application API usage; it does not require you to move existing files automatically.

## Replace capture-style persistence

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;

$receipt = FlexMedia::store(
    MediaSource::path($filePath, 'document.pdf'),
    new MediaWrite(disk: 'local', directory: 'documents'),
);
```

A typed storage operation throws `MediaFailure` on rejection rather than returning an unqualified null result. [Failure handling](/docs/media-os-api#handle-rejection).

## Replace scanner callbacks

Implement and register `MediaScanner` through `MediaExtensions::scanner()`. The old boolean callback must return exactly true to certify a clean file; the new contract returns normally or throws. [Scanner example](/docs/media-os-extensions#antivirus-scanner).

## Existing paths and historical thumbnails

Existing path-only attachments remain readable. New named conversion bundles retain owned receipts for their original and derivatives. Historical thumbnails are not inferred from a guessed filename: inventory and migrate your old derivative references explicitly before expecting bundle deletion to remove them.

## Existing form fields

Disk state remains paths; Spatie state remains Media UUIDs. Add per-operation policies or callbacks without replacing the native field's storage callback:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;

FlexFileUpload::make('documents')->multiple()
    ->mediaMetadata(['purpose' => 'customer-documents']);
```

If your application replaces Filament's save lifecycle, call `completeMediaUploadsAfterSave($record)` on the mounted field after persisting its reference. [Save integration](/docs/file-upload-events-and-recovery#create-and-edit-forms).

## Queue changes

Synchronous disk conversions remain the default. Enable background conversion explicitly only after configuring shared storage and a durable worker. Spatie continues using its native model-defined conversion jobs. [Native queue setup](/docs/media-os-images#background-conversions) · [Spatie queues](/docs/media-os-spatie#queued-conversions).

## Originals

Named conversions and optimization preserve originals. An optimized preview/path is not evidence that the source has been deleted. Choose your source-normalization policy before permanent storage if you want a smaller master. [Original retention](/docs/media-os-images#original-retention).
