---
title: "Upload images, avatars and sources"
description: "Configure image editing, conversion previews, default avatars, URL import and camera capture."
icon: "camera"
"canonical": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/photo-editor.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Upload images, avatars and sources in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/photo-editor-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/photo-editor-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/photo-editor.webp"
"twitter:image:alt": "Upload images, avatars and sources in the FLEX Fields playground"
---

{/* flex-component-media:start */}
<script type="application/ld+json">
{JSON.stringify({
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://flex-fields.bjanczak.com/#organization",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "logo": "https://flex-fields.bjanczak.com/art/docs-logo-light.svg",
      "sameAs": [
        "https://github.com/janczakb/filament-flex-fields"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://flex-fields.bjanczak.com/#website",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "inLanguage": "en"
    },
    {
      "@type": "WebPage",
      "@id": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources",
      "name": "Upload images, avatars and sources",
      "description": "Configure image editing, conversion previews, default avatars, URL import and camera capture.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources#article",
      "url": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources",
      "headline": "Upload images, avatars and sources",
      "description": "Configure image editing, conversion previews, default avatars, URL import and camera capture.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/photo-editor-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/photo-editor-motion.mp4#video",
      "name": "Upload images, avatars and sources — FLEX Fields demonstration",
      "description": "A recorded demonstration of Upload images, avatars and sources in the FLEX Fields playground for Filament. Configure image editing, conversion previews, default avatars, URL import and camera capture.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/photo-editor.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT12.417S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/photo-editor-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/file-upload-images-and-sources#article"
      }
    }
  ]
}).replace(/</g, '\u003c')}
</script>

<video
  autoPlay
  muted
  loop
  playsInline
  controls
  preload="metadata"
  className="w-full aspect-video rounded-xl"
  src="/art/previews/photo-editor-motion.mp4"
  poster="/art/previews/photo-editor.webp"
  width="1920"
  height="1080"
  aria-label="Upload images, avatars and sources — FLEX Fields demonstration"
>
  <a href="/art/previews/photo-editor-motion.mp4">Watch the Upload images, avatars and sources demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Examples use `FlexImageUpload` from `Bjanczak\FilamentFlexFields\Filament\Forms\Components`.

## Named image conversions

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;

FlexImageUpload::make('photos')
    ->imagesOnly()->multiple()->uploadLayout('grid')
    ->disk('s3')->directory('photos')
    ->imageConversions([
        new ImageConversion('thumb', 160, 160, fit: ImageFit::Cover,
            format: ImageFormat::Webp, disk: 's3-thumbnails'),
        new ImageConversion('large', width: 1600, format: ImageFormat::Webp),
    ])
    ->previewConversion('thumb');
```

The preview uses `thumb` in avatar, list and grid layouts. The viewer defaults to the original; choose its own conversion with `fileViewerConversion()`. The image editor, Open and Download retain the original. The selected conversion must exist. While queued conversion work is unfinished, the original remains the fallback.

The Spatie wrapper supports the same `previewConversion('thumb')` option, or its inherited `conversion('thumb')` option. Explicit `previewConversion()` takes precedence. Define Spatie conversions on your media model; native `imageConversions()` belong to the disk uploader.

### Thumbnails for newly selected files

Browser-local thumbnail generation is **off by default**. Enable it explicitly with `previewThumbnailSize(512)` (or another supported size) for newly selected static JPEG, PNG and WebP images. Generation runs after admission, independently of the upload. Until it completes, the original local preview remains visible. The thumbnail encoder does not change upload or image-editor bytes. Any explicitly configured upload resize/crop or server conversion still applies through its own lifecycle.

```php
FlexImageUpload::make('photos')
    ->imagesOnly()->multiple()->uploadLayout('grid')
    ->previewConversion('thumb') // Saved server conversion.
    ->previewThumbnailSize(768); // Newly selected browser-local files.
```

The default `previewThumbnailSize(null)` disables local resizing. Accepted sizes are 64–2048 px, including a closure returning a size or null. Images already smaller than the configured size remain unchanged. Animated GIF/APNG/WebP, AVIF, unsupported decoders, conversion failures and images exceeding the field's pixel budget keep their original local preview. Local thumbnails are temporary display resources, not persisted conversions. Removing/replacing the file or destroying the field releases their resources.

See [all conversion options](/docs/media-os-images#conversion-options-reference) for crop, focus, padding, format, quality, orientation and animation handling.

## Background image processing

```php
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;

FlexImageUpload::make('photo')
    ->imagesOnly()
    ->imageConversions([new ImageConversion('thumb', 200, 200, fit: ImageFit::Cover)])
    ->previewConversion('thumb')
    ->queueImageConversions()
    ->imageConversionQueue(new ConversionQueueOptions(
        connection: 'database', queue: 'media-conversions', tries: 3, timeout: 60,
    ));
```

Off by default. Permanent source validation/storage completes first. After the record references the source and commits, the worker creates derivatives. The model value stays the original path.

Before completion, existing files remain visible with their original preview. Lists display a processing caption; grid tiles retain their transfer/delete presentation with an accessible processing status. When ready, the selected preview changes without replacing the file or refreshing the whole page. Failed conversion jobs retain the original.

```bash
php artisan queue:work database --queue=media-conversions
```

Use [shared storage and queue configuration](/docs/media-os-configuration#conversion-worker). To restore synchronous conversions:

```php
FlexImageUpload::make('photo')->imagesOnly()->queueImageConversions(false);
```

For Spatie, use model `->queued()` conversions and field `->conversion('thumb')`. Do not enable the second native conversion engine. [Spatie setup](/docs/media-os-spatie#queued-conversions).

## Image resource budget

```php
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;

FlexImageUpload::make('photo')->imagesOnly()
    ->imageLimits(new ImageLimits(
        sourcePixels: 8_000_000, outputPixels: 2_000_000,
        variants: 4, workingBytes: 128 * 1024 * 1024,
    ));
```

## Optimization shortcuts

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->optimizeImages()
    ->optimizeImagesToWebp()
    ->maxImageWidth(1920)->maxImageHeight(1080)
    ->stripExif();
```

For AVIF-capable GD:

```php
FlexImageUpload::make('photo')->imagesOnly()->optimizeImages()->optimizeImagesToAvif();
```

Shortcuts create an `optimized` derivative. Do not also declare an explicit conversion named `optimized`. Named conversions give you more control for new configurations. Re-encoding strips source metadata; `stripExif(false)` is rejected when compatibility optimization is active, because this encoder produces metadata-free derivatives. Originals remain stored.

## Image editor

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->imageEditor()
    ->imageEditorAspectRatios(['16:9', '4:3', '1:1']);
```

Circular avatar editor:

```php
FlexImageUpload::make('avatar')->imagesOnly()->avatar()->imageEditor()->circleCropper();
```

Editor transformations affect the file selected for transfer. The optional information editor changes alt/title/caption metadata separately.

## Static default avatar

```php
FlexImageUpload::make('avatar')->imagesOnly()->avatar()
    ->defaultAvatar('/images/default-avatar.png');
```

The placeholder is display-only: it consumes no file slot and is never saved as an uploaded file.

## Any avatar provider

```php
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\AvatarPlaceholder;

FlexImageUpload::make('avatar')->imagesOnly()->avatar()
    ->defaultAvatarUsing(fn ($record): AvatarPlaceholder => new AvatarPlaceholder(
        url: app(AvatarUrlProvider::class)->forRecord($record),
        alt: 'Default profile picture', fit: 'cover', referrerPolicy: 'no-referrer',
    ))
    ->defaultAvatarFallbackUsing(fn (): string => '/images/default-avatar.png');
```

`AvatarUrlProvider` is your own service: DiceBear, a local generator or another provider can supply the URL. Cache expensive generation in that service. Use relative or HTTP(S) image URLs. On delivery failure the field returns to its centered icon; provider exceptions use the configured fallback.

Conditional/default-free avatar:

```php
FlexImageUpload::make('avatar')->imagesOnly()->avatar()
    ->defaultAvatar('/images/default-avatar.png')
    ->defaultAvatarEnabled(fn ($record): bool => $record !== null);
```

```php
FlexImageUpload::make('avatar')->imagesOnly()->avatar()->defaultAvatar(null);
```

## URL import

```php
FlexImageUpload::make('photo')
    ->imagesOnly()->allowUrlUpload()
    ->maxSize(5120)->minImageDimensions(800, 600);
```

Import uses the field's size, type, extension, dimension and custom per-file rules. Remote fetch protections additionally reject unsafe destinations. It does not bypass final scanning, access policies or permanent-save validation. Failed staged imports are removed.

## Camera capture

```php
FlexImageUpload::make('photo')->imagesOnly()->allowWebcamUpload();
```

The browser needs a secure context and camera permission. Camera capture is available only for image-compatible fields. Avatar mode prefers the user-facing camera; other image fields prefer the environment camera.

## All sources together

```php
FlexImageUpload::make('vehicle_photo')
    ->withRecommendedDefaults()
    ->allowUrlUpload()->allowWebcamUpload()
    ->uploadSourceTabsVariant('ghost')
    ->uploadSourceTabsColor('primary')
    ->uploadSourceTabLabels([
        'file' => 'Upload', 'url' => 'From link', 'webcam' => 'Take photo',
    ]);
```

Source-tab variants: `default` and `ghost`. Alternate sources retain their source identity in optional transport events and use the same information editor and Media OS persistence as local selection.


## Separate thumbnail and lightbox conversion

```php
FlexImageUpload::make('photos')->multiple()->uploadLayout('grid')
    ->imageConversions([
        new ImageConversion(name: 'thumb', width: 320, height: 320),
        new ImageConversion(name: 'large', width: 1600, height: 1600),
    ])
    ->previewConversion('thumb')->fileViewer()->fileViewerConversion('large');
```

A missing/pending conversion uses the original. For Spatie, register `thumb` and `large` on the media model and use the same two field selectors; do not declare native `imageConversions()` on that wrapper. Use [legacy preview URL resolvers](/docs/file-upload-models-and-migration#use-thumbnails-generated-by-an-older-system) when variants were generated outside either engine.
