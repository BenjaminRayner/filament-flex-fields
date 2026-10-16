---
title: "Media OS"
description: "Store, validate, secure and process media in your Laravel application."
icon: "folder-open"
---

Media OS gives your application one API for storing files on Laravel disks, generating image variants, connecting Spatie Media Library, scanning files and managing temporary uploads. Use it directly in your application services or through [FLEX File Upload](/docs/flexfileupload-and-fleximageupload).

<Badge color="purple" shape="pill">Laravel filesystems</Badge> <Badge color="gray" shape="pill">Optional Spatie</Badge>

## Choose your integration

<CardGroup cols={2}>
  <Card title="Storage API" icon="hard-drive" href="/docs/media-os-api">Store uploads, local files, streams and bytes using a typed write policy.</Card>
  <Card title="Configuration" icon="sliders-horizontal" href="/docs/media-os-configuration">Choose disks, limits, visibility, worker settings and cleanup policies.</Card>
  <Card title="Image conversions" icon="images" href="/docs/media-os-images">Generate thumbnails and WebP/AVIF variants, synchronously or in a worker.</Card>
  <Card title="Spatie integration" icon="database" href="/docs/media-os-spatie">Use collections, native conversions, responsive images and model relationships.</Card>
  <Card title="Temporary uploads" icon="clock" href="/docs/media-os-lifecycle">Handle staging, record acceptance, recovery and abandoned files.</Card>
  <Card title="Extensions and events" icon="plug" href="/docs/media-os-extensions">Connect your antivirus scanner, processors and access policy.</Card>
  <Card title="Capture and retention" icon="mic" href="/docs/media-os-capture">Configure transcription, signature evidence and retention services.</Card>
  <Card title="Browser integrations" icon="camera" href="/docs/media-os-browser">Build a custom capture interface with the public browser helpers.</Card>
</CardGroup>

## Understand the upload lifecycle

```mermaid
flowchart LR
    A[Select or capture] --> B[Temporary transfer]
    B --> C[Inspect and validate]
    C --> D[Permanent storage]
    D --> E[Record accepted]
    E --> F[Optional queued conversions]
```

<Tooltip headline="Record acceptance" tip="Confirmation that your application's saved record references the permanent media." href="/docs/media-os-lifecycle" cta="Read the lifecycle guide">Record acceptance</Tooltip> is separate from transfer completion. Queued conversion files can become available after the record has been saved.

## Your first stored file

The following example stores a PDF on the Laravel `local` disk. `$path` is a readable local path supplied by your application. Import the classes shown in each example into the class where you use them.

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;

$intent = new MediaWrite(
    disk: 'local',
    directory: 'contracts',
    policy: new MediaPolicy(
        maxBytes: 10 * 1024 * 1024,
        mimeTypes: ['application/pdf'],
    ),
);

$receipt = FlexMedia::store(MediaSource::path($path, 'contract.pdf'), $intent);
```

The result is a `MediaReceipt`. Save the attachment reference in your own model or use a FLEX upload field to manage form persistence for you.

```php
$attachment->disk = $receipt->disk;
$attachment->path = $receipt->path;
$attachment->mime_type = $receipt->file->mimeType;
$attachment->size = $receipt->file->bytes;
$attachment->save();
```

A successful browser transfer and a saved record are different milestones. With a FLEX field, choosing a file transfers it to temporary storage; the form's save flow creates and accepts the permanent attachment. Background thumbnail generation is a third, independent step.

## Files and images in forms

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;

FlexFileUpload::make('contract')
    ->withRecommendedDefaults()
    ->acceptedFileTypes(['application/pdf'])
    ->disk('local')
    ->directory('contracts');
```

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;

FlexImageUpload::make('photos')
    ->withRecommendedDefaults()
    ->multiple()
    ->uploadLayout('grid')
    ->reorderable()
    ->maxFiles(10);
```

## What is included

- Inspected MIME types, byte limits, SHA-256 integrity checks and private/public visibility.
- Local disks and configured cloud disks, including S3, through Laravel's filesystem.
- Named native image conversions, separate conversion disks, optimized previews and optional background jobs.
- Optional Spatie collections, conversions, responsive images and custom properties.
- Configurable antivirus integrations, processing, access policies and Laravel events.
- Temporary staging, explicit recovery and scheduled cleanup.
- Configurable capture transcription, signature evidence and retention services.

<Info>Media OS does not include an antivirus engine or a cloud-storage account. Configure those services in your application when you enable them. Disk storage does not require Spatie. Native raster conversions require PHP GD; the available output codecs depend on your GD installation.</Info>

## Originals and converted files

<Warning>Image conversions **retain the original** and create additional files. This applies to native synchronous conversions, native queued conversions and Spatie conversions. An optimized preview does not mean the original has been deleted.</Warning>

If your storage policy permits only a reduced master image and a thumbnail, normalize the source before permanent storage, then create the thumbnail from that normalized master. There is no built-in conversion option that deletes the stored original while keeping its derivative records. [Learn about originals and previews](/docs/media-os-images#original-retention).

## Configuration and next steps

The configuration namespace is `filament-flex-fields.media_capture`. Per-operation `MediaWrite` and `MediaPolicy` objects let you override storage and validation for a particular upload.

```php
$intent = new MediaWrite(
    disk: 's3',
    directory: 'products/photos',
    policy: new MediaPolicy(
        maxBytes: 5 * 1024 * 1024,
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        visibility: 'public',
    ),
);
```

Continue with [configuration](/docs/media-os-configuration), [storage examples](/docs/media-os-api) or [FLEX upload fields](/docs/flexfileupload-and-fleximageupload).
