---
title: "FLEX Upload configuration reference"
description: "Field options, defaults, inherited settings, Spatie options and data-driven configuration."
icon: "braces"
---

This reference lists the customer configuration surface. Use [the usage guide](/docs/flexfileupload-and-fleximageupload) for complete examples. Boolean methods enable their option when called without an argument; pass `false` to disable it. Most FLEX presentation/storage switches also accept evaluated closures where their signature permits them.

## Presets and validation

| Method | Purpose |
| --- | --- |
| `withRecommendedDefaults()` | 5 MiB max, create-form path handling, remove/replace cleanup, open/download, focus outline; document/image preset by class |
| `applyRecommendedSecurityDefaults()` | Same common settings without selecting a format preset |
| `documentsOnly()` | Document MIME preset |
| `imagesOnly()` | Image MIME preset |
| `spreadsheetsOnly()` | Spreadsheet MIME preset |
| `allowedExtensions(array $extensions)` | Additional filename-extension allow-list |
| `rejectExecutableFiles(bool\|Closure $condition = true)` | Executable filename/type guard; default false |
| `minImageDimensions(int $width, int $height)` | Minimum input image dimensions |
| `maxImageDimensions(int $width, int $height)` | Maximum input image dimensions |
| `maxTotalSizeKb(int\|Closure $kilobytes)` | Total field byte allowance expressed in KiB |

[Validation examples](/docs/file-upload-storage-and-validation).

## Layout and presentation

| Method | Options / default |
| --- | --- |
| `uploadLayout(UploadLayout\|string\|Closure $layout)` | `list`, `grid`, `compact`, `avatar`; default list |
| `variant(string\|Closure $variant)` | `primary` (default), `secondary`, `flat` |
| `gridColumns(int\|Closure $columns)` | 1–8; default 3 |
| `gridTileSize(int\|Closure $pixels)` | 80–320; default 116 |
| `avatarSize(AvatarSize\|int\|string\|Closure $pixels)` | `sm` 96, `md` 128 (default), `lg` 160, or integer 64–512 |
| `compactList(bool\|Closure $condition = true)` | Compact file list |
| `compactHeader(bool\|Closure $condition = true)` | Condensed file picker header without artwork; independent of list/grid layout; default false |
| `showFileIcon(bool\|Closure $condition = true)` | File-type artwork; default true |
| `dropzoneLabel(string\|Closure $label)` | Replace the main instruction |
| `emptyStateHint(string\|Closure $hint)` | Extra guidance after standard constraints |
| `remainingSlotsLabel(bool\|Closure $condition = true)` | Remaining file-count caption; default false |
| `uploadSummary(bool\|Closure $condition = true)` | File/size summary; default false |
| `requireReplaceConfirmation(bool\|Closure $condition = true)` | Replacement confirmation; default false |
| `instantUpload(bool\|Closure $condition = true)` | Immediate selection transfer; default true |
| `pausable(bool\|Closure $condition = true)` | Individual/queue pause for list/grid; default false |

Common inherited FLEX appearance APIs include `size()` and `focusOutline()`; see [shared concepts](/docs/configuration) for field configuration. Reorder uses inherited `reorderable()` with the FLEX drag presentation.

Typed layout example:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadLayout;

FlexFileUpload::make('attachments')->multiple()->uploadLayout(UploadLayout::Grid);
```

## Sources

| Method | Purpose |
| --- | --- |
| `allowUrlUpload(bool\|Closure $condition = true)` | URL tab; default false |
| `allowWebcamUpload(bool\|Closure $condition = true)` | Camera tab for compatible image fields; default false |
| `uploadSourceTabsVariant(string\|Closure $variant)` | `default` or `ghost` |
| `uploadSourceTabsColor(string\|Closure\|null $color)` | Shared source-tab color |
| `uploadSourceTabLabels(array\|Closure $labels)` | Labels keyed by `file`, `url`, `webcam` |

[Source examples](/docs/file-upload-images-and-sources#url-import).

## Storage and cleanup

| Method | Purpose |
| --- | --- |
| `scopedDirectory(string\|Closure $prefix = 'uploads')` | Context-aware directory |
| `createFormStrategy(bool\|Closure $condition = true)` | Create-form path authorization strategy |
| `storeMetadataIn(string\|Closure $statePath)` | Technical metadata companion |
| `deleteFileOnRemove(bool\|Closure $condition = true)` | Owned file/bundle removal |
| `deleteReplacedFiles(bool\|Closure $condition = true)` | Accepted replacement cleanup |
| `pruneOrphanedOnSave(bool\|Closure $condition = true)` | Known-baseline reconciliation after save |
| `afterPersistUploadedFileUsing(?Closure $callback)` | Confirmed storage callback |
| `deletedStoredFileUsing(?Closure $callback)` | Confirmed deletion callback, including bytes freed |

[Storage examples](/docs/file-upload-storage-and-validation).

## Media OS

| Method | Purpose |
| --- | --- |
| `mediaPolicy(MediaPolicy\|Closure\|null $policy)` | Engine limits, visibility, integrity and scans |
| `mediaAdapter(string\|Closure $adapter)` | Registered path-compatible adapter; default disk |
| `mediaProcessors(array\|Closure $processors)` | Ordered registered processor names |
| `mediaMetadata(array\|Closure $metadata)` | Application context |
| `mediaWriteUsing(?Closure $callback)` | Complete typed write; preserve field/record/fixed adapter |
| `mediaFinalizeUsing(?Closure $callback)` | Acceptance check before publication |
| `onUploadEvent(UploadEventType $type, Closure $callback)` | Confirmed PHP operation event |
| `onUploadTransportEvent(?UploadTransportEventType $type, Closure $callback)` | Optional browser observation; null selects all |
| `uploadTransportProgressInterval(int $milliseconds)` | 500–10,000 ms |

[Events and lifecycle examples](/docs/file-upload-events-and-recovery).

## Disk image processing

| Method | Purpose |
| --- | --- |
| `imageConversions(array\|Closure $conversions)` | Unique typed `ImageConversion` definitions |
| `imageLimits(ImageLimits\|Closure\|null $limits)` | Pixel/variant/memory budgets |
| `previewConversion(string\|Closure\|null $conversion)` | Named server preview variant for avatar/list/grid; original fallback while pending |
| `previewUrlUsing(?Closure $callback)` | Authorized legacy thumbnail resolver; null falls back to the native preview; originals remain unchanged |
| `previewThumbnailSize(int\|Closure\|null $pixels)` | Local static-image thumbnail longest edge, default null/off; opt in with 64–2048 px |
| `queueImageConversions(bool\|Closure $condition = true)` | Background native conversions; default false |
| `imageConversionQueue(ConversionQueueOptions\|Closure\|null $options)` | Connection, queue, tries and timeout |
| `optimizeImages(bool\|Closure $condition = true)` | `optimized` derivative; default false |
| `optimizeImagesToWebp(bool\|Closure $condition = true)` | WebP output shortcut |
| `optimizeImagesToAvif(bool\|Closure $condition = true)` | AVIF output shortcut |
| `maxImageWidth(int\|Closure $width)`, `maxImageHeight(int\|Closure $height)` | Optimization output bounds |
| `stripExif(bool\|Closure $condition = true)` | Optimization setting; native re-encoding strips metadata |

[Image examples](/docs/file-upload-images-and-sources). Spatie uses model conversions instead of these native conversion APIs.

## Default avatar

| Method | Purpose |
| --- | --- |
| `defaultAvatar(AvatarPlaceholder\|string\|Closure\|null $provider)` | Display-only placeholder; default null |
| `defaultAvatarUsing(?Closure $provider)` | Application provider resolver |
| `defaultAvatarEnabled(bool\|Closure $condition = true)` | Conditional placeholder |
| `defaultAvatarFallbackUsing(?Closure $provider)` | Provider-error fallback |

[Avatar examples](/docs/file-upload-images-and-sources#any-avatar-provider).

## Information editor

| Method | Purpose |
| --- | --- |
| `fileMetadataEditor(bool\|Closure $condition = true)` | Enable editor; default false |
| `fileMetadataStatePath(string\|Closure $path)` | Disk companion path |
| `fileMetadataSchema(array\|Closure $schema)` | 1–32 FLEX field templates |
| `fileMetadataValidationRules(array $rules)` | Additive document-relative validation |
| `configureFileMetadataActionUsing(?Closure $callback)` | Action presentation |
| `fileMetadataTranslations(array\|Closure\|null $locales, array $fields = ['title', 'alt', 'caption', 'copyright'])` | Selected translated text fields; default no locales |
| `fileMetadataDefaultLocale(string\|Closure\|null $locale)` | Primary language, default first configured |
| `fileMetadataRequiredInDefaultLocale(array $fields)` | Required only in primary language |
| `fileMetadataFallback(bool\|Closure $condition = true)` | Display-only fallback; default false |

For application reads: `readFileEditorialMetadata($fileKey)`, `getFileEditorialText($fileKey, $field, $locale)` and `resolveFileEditorialText($metadata, $field, $locale)`. [Metadata examples](/docs/file-upload-metadata).

## Staging and custom saves

| Method | Purpose |
| --- | --- |
| `mediaStaging(bool\|Closure $condition = true)` | Global default true |
| `mediaAutoRecovery(bool\|Closure $condition = true)` | Global default true; separate from staging |
| `mediaStagingOwnerUsing(?Closure $callback)` | Trusted custom owner |
| `mediaReferenceUsing(?Closure $callback)` | Custom saved-reference proof |
| `completeMediaUploadsAfterSave(?Model $record = null)` | Custom-flow acceptance after persistence |
| `abandonMediaUpload(string $leaseKey)` | Server-owned pending abandonment |
| `recoverMediaUpload(string $leaseKey, MediaWrite $intent, UploadManifest $reconciled)` | Server reconciliation; returns identifier |

[Save/recovery examples](/docs/file-upload-events-and-recovery).

## Inherited Filament options

The field extends Filament FileUpload and retains its normal form/validation APIs. Common choices include:

| Area | Methods |
| --- | --- |
| Validation | `required`, `rules`, `rule`, `validationMessages`, `acceptedFileTypes`, `minSize`, `maxSize`, `minFiles`, `maxFiles` |
| Transfer | `multiple`, `maxParallelUploads`, `appendFiles` |
| Storage | `disk`, `directory`, `visibility`, `getUploadedFileNameForStorageUsing`, `storeFileNamesIn`, `preserveFilenames` |
| Actions | `deletable`, `downloadable`, `openable`, `previewable`, `pasteable`, `reorderable` |
| Images | `avatar`, `imageEditor`, `circleCropper`, `imageEditorAspectRatios`, `imageCropAspectRatio`, `imageResizeMode`, `imageResizeTargetWidth`, `imageResizeTargetHeight` |
| Form | `label`, `helperText`, `disabled`, `hidden`, `visible`, `columnSpan` |

Replacing inherited low-level storage callbacks such as `saveUploadedFileUsing()` means your callback owns persistence and may bypass the Media OS lifecycle. Prefer `mediaWriteUsing()`, processors and finalizers when retaining Media OS behavior. Native FilePond panel/button-position options chiefly apply to its avatar/editor UI; FLEX lists/grids own their presentation layout.

## Optional Spatie field

The wrapper retains native collection options: `collection`, `conversion`, `conversionsDisk`, `responsiveImages`, `customProperties`, `mediaName`, `manipulations`, `properties`, `customHeaders`, `filterMediaUsing`. It also offers `spatieOptionsUsing()` for typed native adder options. [Full Spatie examples](/docs/media-os-spatie).

## Data-driven schemas

For schema-builder fields, put supported options inside `config`:

```json
{
  "type": "file",
  "slug": "attachments",
  "label": "Attachments",
  "config": {
    "multiple": true,
    "disk": "local",
    "directory": "attachments",
    "visibility": "private",
    "accepted_types": ["application/pdf"],
    "max_size_kb": 5120,
    "max_files": 5,
    "max_total_size_kb": 15360,
    "upload_layout": "grid",
    "grid_columns": 3,
    "allow_url_upload": true
  }
}
```

The configurator maps: `disk`, `directory`, `visibility`, `multiple`, `max_size_kb` (or `max_size`), `accepted_types`, `min_files`, `max_files`, `max_total_size_kb`, `documents_only`, `images_only`, `variant`, `size`, `instant_upload`, `upload_layout`, `compact_header`, `grid_columns`, `avatar_size`, `store_metadata_in`, `scoped_directory`, `optimize_images`, `max_image_width`, `max_image_height`, `allow_url_upload`, `allow_webcam_upload`, `queue_image_conversions`, `image_conversion_queue`.

Queued optimization configuration:

```json
{
  "images_only": true,
  "optimize_images": true,
  "queue_image_conversions": true,
  "image_conversion_queue": {
    "connection": "database",
    "queue": "media-conversions",
    "tries": 3,
    "timeout": 60
  }
}
```

Not every PHP method has a data-driven key. Configure custom callbacks, typed policies, metadata schemas and other unmapped options in PHP rather than inventing JSON keys.


## Click an uploaded file

Configure either `fileClickAction(?Closure $callback)` or `fileClickUrl(?Closure $callback)`; the last configured handler replaces the other. Pass `null` to clear it. These are trusted PHP callbacks, not executable JSON options.

```php
FlexFileUpload::make('documents')
    ->withRecommendedDefaults()
    ->multiple()->maxFiles(5)->uploadLayout('grid')->compactHeader()
    ->fileClickUrl(fn (string $fileKey, string $mimeType): ?string =>
        $mimeType === 'application/pdf'
            ? route('documents.preview', ['upload' => $fileKey])
            : null);
```

Clicking the file content in list or the tile face in grid invokes the configured handler. Native buttons remain separate; drag sorting does not activate it. Keyboard Enter/Space activates the file button. Only completed, active files can be clicked; disabled fields cannot execute custom handlers. Each file has its own pending state and repeated clicks on that file are suppressed while its request is pending.

Available named callback arguments are `file` (a `TemporaryUploadedFile` or the saved disk path/Spatie UUID), `fileKey` (the current native field-state key), `fileName`, `mimeType`, `component`, `livewire` and `record`. The browser sends only the file key. PHP resolves the current field state and authorizes saved-file access through the appropriate Media OS bridge. The bridge does not generate preview URLs, conversion jobs or unnecessary download URLs for this click. For disk files, read activation always requires a native original record path or an explicitly configured `preventFilePathTampering(..., allowFilePathUsing: ...)` policy; `withRecommendedDefaults()` supplies the existing create-form directory policy. Disabling general path tampering protection does not bypass this click guard. Temporary uploads must still exist. A stale or foreign key rejects before invoking the callback.

```php
FlexFileUpload::make('attachments')
    ->fileClickAction(function (string $fileName): void {
        Notification::make()->title('Selected: '.$fileName)->success()->send();
    });
```

Import `Filament\Notifications\Notification` in the example above. A callback may perform your application action, mount an application-owned Livewire action, or return a Laravel redirect response. `fileClickUrl()` returns a same-window application-relative or HTTP(S) URL, or `null` for no navigation. Active schemes, protocol-relative URLs, credentials, whitespace and URLs over 2,048 bytes are rejected. A temporary `fileKey` is not a durable database record ID: your application route must resolve it through an authorized owner/session mapping, or use `record` and the stored `file` to build a route for a persisted record. Never treat a URL or callback as authorization to another record. Business policies, transactions and server-side idempotency of write callbacks belong to your application.

## Compact header and maximum files

`compactHeader()` removes file-header illustrations and puts the description and Select files control in a short row; narrow containers stack them. It works with both list and grid and does not change tile size or file-row density. URL/camera source controls retain their source-specific presentation. `compactList()` remains a separate option for smaller file rows. JSON configuration supports `compact_header: true`.

For multiple uploads, `maxFiles(5)` disables Select files and further browsing/drop additions once five active rows occupy the slots. Queued, uploading and failed files reserve slots until removed. Removing a row enables adding again. Native FilePond admission and server validation remain authoritative if client state is forged or several files are chosen at once. A single-file field retains replacement behavior; the limit lock applies to multiple fields.


## Grid sizes and actions

`gridSize('md')` uses the current 116px tile; `gridSize('sm')` uses 96px (about 17% smaller). `gridTileSize(100)` remains the exact-pixel option (80–320px). The last size call wins. All tile artwork, progress, corners and primary controls scale from the same tile geometry.

The default `gridActions('menu')` uses an ellipsis action menu only when the current file has actions beyond removal. A removal-only file keeps its × button. The menu follows the HeroUI Usage presentation, with 150 ms entry and 100 ms exit, and supports arrows, Home/End, typeahead and Escape. Reduced-motion preferences skip the animation. The menu includes configured preview, metadata, image editing and open/download actions, plus pause/resume, retry or manual upload when relevant. Reorder files by dragging their tiles; movement commands are not included in the menu. No hover toolbar is painted below the image. `gridActions('inside')` is a compatibility alias for `menu`; `gridActions('below')` explicitly opts into a separate secondary row. Queue pause/resume lives in the footer below the files, beside the optional summary, and does not shift the grid. Image upload percentages use a high-contrast dark badge with white text; document tiles retain the blue percentage.

```php
FlexImageUpload::make('photos')->multiple()->uploadLayout('grid')
    ->gridSize('sm')->gridActions('menu')->fileClickPreview()
    ->imageEditor()->reorderable();
```

`fileClickPreview()` enables the existing image viewer and makes a completed image tile (or list content) open it. Non-image files cannot invoke an image preview. It is mutually exclusive with `fileClickAction()` and `fileClickUrl()`; the last click configuration wins. Its boolean/closure condition controls that instance. `fileViewer(false)` independently disables preview. These options do not replace file ownership checks.

## Choose the viewer image source

`previewConversion('thumb')` selects the small list/grid/avatar preview. `fileViewerConversion('large')` independently chooses a stored named image conversion for the lightbox. The default `fileViewerConversion(null)` uses the original. Missing/unfinished conversions fall back to the authorized original. This supports native disk manifests and generated Spatie conversions. Newly selected temporary images still show their temporary original until storage/conversions exist.

`fileViewerUrlUsing(?Closure)` can resolve an existing large preview from your legacy system. Its arguments are `file` (saved path/Spatie UUID), `defaultUrl`, `record` and `component`. Original authorization runs first. A null callback result retains the normal original/conversion URL. URL validation matches `previewUrlUsing()`. This affects only the lightbox; editor, Open and Download retain the original. [Legacy files and galleries](/docs/file-upload-models-and-migration).
