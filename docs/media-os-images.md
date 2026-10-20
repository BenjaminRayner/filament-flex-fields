---
title: "Media OS image conversions"
description: "Create thumbnails and optimized image variants synchronously or in background workers."
icon: "image"
---

Native image conversions work on Laravel disks without Spatie. Each named conversion is generated from the same stored original. For Spatie uploads, define conversions on your model instead; [see the Spatie guide](/docs/media-os-spatie).

## Original with thumbnail and large image

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;

$source = MediaSource::path($photoPath, 'product.jpg');
$intent = new MediaWrite(
    disk: 'public', directory: 'products',
    policy: new MediaPolicy(
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        visibility: 'public',
    ),
);
$conversions = [
    new ImageConversion('thumb', 160, 160, fit: ImageFit::Cover),
    new ImageConversion('large', width: 1600, format: ImageFormat::Webp, quality: 85),
];
$bundle = FlexMedia::images()->store($source, $intent, $conversions);
```

The bundle contains `original`, `variants` and the matching `intents`. Retain the bundle's owned receipts in your application if you need to manage all its files later. FLEX upload fields manage their own receipts automatically.

## Use a variant URL

```php
$thumb = $bundle->variant('thumb');
$url = FlexMedia::os()->url($thumb, $bundle->intents['thumb'], now()->addMinutes(15));
```

`variant()` throws for an unknown name. `url()` may return `null` if private signing is unavailable.

## Resize without cropping

```php
$conversion = new ImageConversion('display', width: 1200, fit: ImageFit::Contain);
```

Omit height to retain the aspect ratio. With both dimensions, `Contain` fits the image inside the bounds.

## Square crop

```php
$conversion = new ImageConversion('square', 300, 300, fit: ImageFit::Cover);
```

## Crop around a focus point

```php
$conversion = new ImageConversion(
    'portrait', 400, 600, fit: ImageFit::Cover, focusX: 0.35, focusY: 0.25,
);
```

Focus coordinates range from 0 to 1; `0.5, 0.5` is the center.

## Pad with a background

```php
$conversion = new ImageConversion(
    'catalog', 800, 800, fit: ImageFit::Pad, background: '#f5f5f5',
);
```

## Stretch to exact dimensions

```php
$conversion = new ImageConversion('badge', 200, 100, fit: ImageFit::Stretch);
```

Stretch can distort the source. Use it only when your design requires it.

## Output formats

```php
$conversions = [
    new ImageConversion('web', width: 1200, format: ImageFormat::Webp, quality: 82),
    new ImageConversion('avif', width: 1200, format: ImageFormat::Avif, quality: 65),
    new ImageConversion('print', width: 1600, format: ImageFormat::Jpeg, quality: 92),
    new ImageConversion('transparent', width: 800, format: ImageFormat::Png),
];
```

An unavailable codec causes an explicit failure. There is no automatic format substitution. PNG remains lossless; its quality option controls compression rather than visual loss.

## Separate derivative disk

```php
$conversion = new ImageConversion(
    'thumb', 160, 160, fit: ImageFit::Cover,
    disk: 's3-thumbnails', directory: 'catalog/thumbs',
);
```

Disk and directory otherwise inherit the original's storage location and write directory.

## Orientation, transparency and upscaling

```php
$conversion = new ImageConversion(
    'hero', 1920, 1080, fit: ImageFit::Cover,
    format: ImageFormat::Jpeg, background: '#ffffff',
    upscale: false, autoOrient: true, progressive: true,
);
```

By default, small images are not enlarged. JPEG flattens transparency onto the background. EXIF auto-orientation requires the EXIF extension.

## Animated input

```php
$conversion = new ImageConversion('poster', width: 800, firstFrame: true);
```

This explicitly permits flattening animation to one frame. The native encoder does not preserve animation. GIF and AVIF inputs conservatively require this option; animated PNG/WebP are detected. Native conversions do not render SVG, PDF or video.

## Resource limits

```php
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;

$limits = new ImageLimits(
    sourcePixels: 8_000_000, outputPixels: 2_000_000,
    variants: 6, workingBytes: 128 * 1024 * 1024,
);
$bundle = FlexMedia::images()->store($source, $intent, $conversions, $limits);
```

Defaults: 16 million source pixels, 16 million output pixels, 16 variants and 256 MiB working memory. Available PHP memory may impose a lower limit. Dimensions must be positive and at most 32,768 per side. Non-contain fits require both dimensions. Conversion names are unique lowercase identifiers; `original` is reserved.

The default encoder uses GD. Re-encoding removes EXIF and does not preserve ICC profiles. For color-managed workflows, bind your own `ImageEncoder` implementation; [see extensions](/docs/media-os-extensions#custom-image-encoder).

## Background conversions

Use a worker when image generation should not delay a form's permanent save. The original is stored and remains the stable attachment reference.

```php
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;

$batch = FlexMedia::queuedImages()->store(
    $source, $intent, $conversions,
    new ConversionQueueOptions(connection: 'database', queue: 'media-conversions'),
    $limits,
);
```

Run the configured worker:

```bash
php artisan queue:work database --queue=media-conversions
```

### Read processing status

```php
$status = FlexMedia::queuedImages()->snapshot($batch->id, $batch->original, $intent);
$state = $status['status'];
$variants = $status['variants'];
$error = $status['error'];
```

| Status | Meaning |
| --- | --- |
| `awaiting_save` | Conversion plan prepared; waiting for acceptance/dispatch |
| `queued` | Work submitted to the queue |
| `processing` | Worker generating variants |
| `retrying` | An attempt failed; retry is pending |
| `ready` | All requested variants are available |
| `failed` | Attempts exhausted; original remains available |
| `cancelled` | Further conversion work cancelled |

These states describe conversion work, not browser transfer progress.

### Prepare before a record is saved

When your application already owns a stored original but needs to delay dispatch until the model is saved:

```php
$id = FlexMedia::queuedImages()->prepare($receipt, $intent, $conversions, $options, $limits);
// Save and verify your model's attachment reference first.
FlexMedia::queuedImages()->dispatch($id, $savedRecord);
```

The service dispatches after the record connection commits. Your custom save flow must prove its reference before dispatch. FLEX fields perform this integration automatically.

### Retry failed conversions

```php
FlexMedia::queuedImages()->retry($batch->id, $batch->original, $intent);
```

Completed variants are preserved. Cancelled or already-ready plans are not revived. Invoke this through an authorized server action.

### Cancel background conversions

```php
$bytesFreed = FlexMedia::queuedImages()->cancel($batch->id, $intent);
FlexMedia::os()->delete($batch->original, $intent);
```

Cancellation prevents further work and removes the plan's owned derivatives. It deliberately retains the original until your storage lifecycle deletes it. Use your server-owned plan identity and receipt, never arbitrary client-supplied deletion data.

Queue jobs use durable checkpoints and locks to make retries safe. Use shared private journals, disks, encryption keys and locks across web/worker hosts. [Worker configuration](/docs/media-os-configuration#conversion-worker).

Inspect the selected native queue configuration with `php artisan fff:media:doctor
--queued --connection=database --timeout=60`, replacing those values with your
actual conversion options. This does not run a worker or perform a conversion.
See [Media OS diagnostics](/docs/media-os-diagnostics).

## Delete a synchronous bundle

```php
FlexMedia::images()->delete($bundle);
```

A synchronous conversion failure attempts to remove the whole newly created bundle. A successful bundle can be removed through this same ownership-aware API.

## Original retention

The native engine keeps the original plus all named variants. `optimizeImages()` in FLEX Upload also retains the original. In synchronous optimization, the form may use the optimized path as its primary value; in queued mode the original path remains the model value. Neither behavior deletes the original.

Spatie also preserves its stored original when generating conversions. Its [`preservingOriginal()` option](https://spatie.be/docs/laravel-medialibrary/v11/basic-usage/associating-files) controls copying versus moving the **input source file**, not deletion of the library's stored original. [Spatie optimization](https://spatie.be/docs/laravel-medialibrary/v11/converting-images/optimizing-converted-images) acts on conversions.

For a smaller master plus `thumb`, process the source before storing it as the master. Do not manually remove an original behind an active receipt/media record: original download, missing-preview fallback, verification and conversion regeneration depend on it.

## Conversion options reference

| Option | Default / purpose |
| --- | --- |
| `name` | Required unique name |
| `width`, `height` | Optional contain bounds; required for other fits |
| `fit` | `ImageFit::Contain`; also `Cover`, `Pad`, `Stretch` |
| `format` | `ImageFormat::Webp`; also `Jpeg`, `Png`, `Avif` |
| `quality` | 82; range 1–100 |
| `disk`, `directory` | Original destination defaults |
| `upscale` | false |
| `focusX`, `focusY` | 0.5 each |
| `background` | `#ffffff`; RGB/RGBA hex |
| `autoOrient`, `progressive` | true |
| `firstFrame` | false |

For field-level usage, see [FLEX image processing](/docs/file-upload-images-and-sources).
