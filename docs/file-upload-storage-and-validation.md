---
title: "Upload storage and validation"
description: "Choose accepted formats, limits, disks, filenames, technical metadata and cleanup behavior."
icon: "file-check"
---

These examples use `FlexFileUpload` and `FlexImageUpload` from `Bjanczak\FilamentFlexFields\Filament\Forms\Components`. Limits apply independently to each rendered field.

## Recommended presets

```php
FlexFileUpload::make('documents')->withRecommendedDefaults();
```

```php
FlexImageUpload::make('photos')->withRecommendedDefaults();
```

The recommended preset enables a 5 MiB max file size, create-form path authorization, removal/replacement cleanup, open/download actions and focus outline. It does not install antivirus or automatically enable conversions.

Select a type preset without the other recommended settings:

```php
FlexFileUpload::make('documents')->documentsOnly();
FlexFileUpload::make('spreadsheets')->spreadsheetsOnly();
FlexImageUpload::make('photos')->imagesOnly();
```

## Exact MIME allow-list

```php
FlexFileUpload::make('attachment')
    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']);
```

Content is inspected rather than trusting the browser's MIME declaration. Add extension restrictions when filenames must also match a format policy:

```php
FlexFileUpload::make('document')
    ->acceptedFileTypes(['application/pdf'])
    ->allowedExtensions(['pdf'])
    ->rejectExecutableFiles();
```

Executable rejection is an additional filename/type guard, not antivirus. Active formats such as HTML or SVG need an appropriate policy/sanitizer if allowed.

## Per-file size

```php
FlexFileUpload::make('attachment')->minSize(10)->maxSize(8192);
```

Sizes are KiB: this permits files from 10 KiB to 8 MiB. Global PHP, web-server and Livewire temporary-upload limits must also allow your chosen maximum.

## File count and aggregate size

```php
FlexFileUpload::make('attachments')
    ->multiple()
    ->minFiles(1)->maxFiles(5)
    ->maxSize(5120)
    ->maxTotalSizeKb(15360)
    ->required();
```

This requires at least one file, allows five files, limits each to 5 MiB and the field total to 15 MiB. Aggregate limits do not increase the per-file limit.

## Image dimensions

```php
FlexImageUpload::make('hero')
    ->imagesOnly()
    ->minImageDimensions(1200, 600)
    ->maxImageDimensions(6000, 4000)
    ->maxSize(10240);
```

These validate the input. `maxImageWidth()`/`maxImageHeight()` instead configure output optimization dimensions; they are not a substitute for dimension validation.

## Application rules

```php
FlexFileUpload::make('proof')
    ->required()
    ->rule('file')
    ->validationMessages(['required' => 'Please attach your proof of purchase.']);
```

Use native Laravel rule objects or closures for business validation. The built-in requirements description can summarize declared standard limits but cannot infer arbitrary custom rules; describe those with `emptyStateHint()`.

URL and webcam files follow the same per-file admission checks, including size, type, dimensions and custom rules. URL import also validates before returning its staged preview. Aggregate/count rules and final Media OS scanning remain at their appropriate field/save boundaries.

## Private local storage

```php
FlexFileUpload::make('contract')
    ->disk('local')->directory('contracts')->visibility('private');
```

## Public images

```php
FlexImageUpload::make('photo')
    ->imagesOnly()->disk('public')->directory('photos')->visibility('public');
```

Configure Laravel's public storage link. Visibility should reflect whether users may access a file without authorization.

## S3

```php
FlexFileUpload::make('attachments')
    ->multiple()->disk('s3')->directory('customer-documents')->visibility('private');
```

Configure Laravel disk credentials, permissions, signing and browser delivery. Private signing failure does not fall back to public links.

## Scoped directories

```php
FlexFileUpload::make('attachments')->scopedDirectory('uploads');
```

For a directory determined by a saved record:

```php
FlexFileUpload::make('documents')
    ->directory(fn ($record): string => 'customers/'.($record?->getKey() ?? 'pending'));
```

Use trusted server values. `createFormStrategy()` enables the field's create-form path authorization strategy:

```php
FlexFileUpload::make('document')->createFormStrategy();
```

Explicit record paths still require your application's record/tenant authorization.

## Filename generation

Random filenames avoid collisions by default. Supply your own generator when needed:

```php
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

FlexFileUpload::make('document')
    ->getUploadedFileNameForStorageUsing(
        fn (TemporaryUploadedFile $file): string =>
            'document-'.Str::uuid().'.'.$file->extension(),
    );
```

Do not include directory segments. Media OS refuses to overwrite existing destinations. Keep client filenames for display instead of trusting them as executable storage paths:

```php
FlexFileUpload::make('documents')
    ->multiple()->storeFileNamesIn('document_names');
```

`preserveFilenames()` is inherited and available when your workflow explicitly needs it, subject to destination collision/path protections.

## Technical metadata sidecar

```php
FlexImageUpload::make('photo')
    ->imagesOnly()->storeMetadataIn('photo_metadata');
```

The companion state records technical information such as original name, MIME, size and image dimensions. Use a model JSON column and array cast if you want to persist that state. For user-editable alt/caption/tags use the separate [information editor](/docs/file-upload-metadata), with a different state path.

## Open, download and read-only

```php
FlexFileUpload::make('contract')->openable()->downloadable()->deletable(false);
```

```php
FlexFileUpload::make('contract')->disabled();
```

Action visibility follows these settings and file state. An optimized preview may show a thumbnail, while open/download links refer to the original.

## Removal and replacement

```php
FlexFileUpload::make('contract')
    ->deleteFileOnRemove()
    ->deleteReplacedFiles()
    ->requireReplaceConfirmation();
```

Removal cleanup deletes the field's owned file/bundle. Replacement cleanup runs after the new record reference is accepted so a failed save can retain the old attachment. If another part of your application shares references to the same file, use your own ownership policy before enabling destructive cleanup.

Reconcile removed baseline attachments after save:

```php
FlexFileUpload::make('attachments')->multiple()->pruneOrphanedOnSave();
```

This operates on the field's known baseline; it does not scan a shared directory and guess which objects are orphaned.

Disable these behaviors individually when your application owns retention:

```php
FlexFileUpload::make('attachments')
    ->deleteFileOnRemove(false)
    ->deleteReplacedFiles(false)
    ->pruneOrphanedOnSave(false);
```

## Media OS policy

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;

FlexFileUpload::make('documents')
    ->multiple()->maxSize(8192)
    ->mediaPolicy(new MediaPolicy(
        maxBytes: 8 * 1024 * 1024,
        mimeTypes: ['application/pdf'],
        requireScanner: true,
        visibility: 'private',
        quarantineDisk: 'quarantine',
    ));
```

Register the required scanner in your application. Native field restrictions still apply alongside the engine policy.

## Processors and application context

```php
FlexFileUpload::make('contract')
    ->mediaProcessors(['sanitize-pdf'])
    ->mediaMetadata(['purpose' => 'supplier-contract']);
```

Register named processors through `MediaExtensions`. [Processor example](/docs/media-os-extensions#processor-that-transforms-content).

For advanced write configuration, return a `MediaWrite` preserving the field/record ownership:

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

FlexFileUpload::make('contract')
    ->mediaWriteUsing(fn (MediaWrite $intent): MediaWrite => new MediaWrite(
        adapter: $intent->adapter, kind: $intent->kind,
        disk: $intent->disk, directory: $intent->directory,
        filename: $intent->filename, field: $intent->field, record: $intent->record,
        spatie: $intent->spatie, policy: $intent->policy,
        metadata: [...$intent->metadata, 'department' => 'legal'],
        processors: $intent->processors, operationId: $intent->operationId,
    ));
```

A custom `mediaAdapter()` must supply references compatible with this field. Use the dedicated Spatie field for UUID state instead of switching a disk field's adapter to Spatie.
