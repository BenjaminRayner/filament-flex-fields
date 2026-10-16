---
title: "Media OS with Spatie Media Library"
description: "Connect model collections, conversions, responsive images and custom media properties."
icon: "database"
---

Spatie Media Library is optional. Use it when your attachments belong to Eloquent models and you want its collection and conversion APIs. Disk-only Media OS usage does not require these packages.

## Install optional dependencies

Follow Spatie's [installation instructions](https://spatie.be/docs/laravel-medialibrary/v11/installation-setup). For FLEX form fields, also install Filament's matching Spatie Media Library plugin version.

```bash
composer require spatie/laravel-medialibrary filament/spatie-laravel-media-library-plugin
```

Publish the Media Library configuration/migrations and migrate according to the versions installed in your application. Configure the `media` table, image driver and queue before using the integration.

## Model setup

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 160, 160)
            ->format('webp')
            ->performOnCollections('photos')
            ->nonQueued();
    }
}
```

## Store through Media OS

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;

$intent = new MediaWrite(
    adapter: 'spatie', record: $savedProduct,
    spatie: new SpatieOptions(collection: 'photos', name: 'Front view'),
);
$receipt = FlexMedia::store(MediaSource::path($photoPath, 'front.jpg'), $intent);
$media = $receipt->native;
```

Use a persisted `HasMedia` model. For an unsaved create record, [stage first](/docs/media-os-lifecycle#create-a-spatie-attachment) or let the FLEX field manage create-form timing.

## FLEX form field

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;

FlexSpatieMediaLibraryFileUpload::make('photos')
    ->collection('photos')
    ->imagesOnly()
    ->multiple()
    ->uploadLayout('grid')
    ->reorderable()
    ->conversion('thumb');
```

The field uses owned Media UUIDs while the disk field uses paths. You do not need a `photos` path column when using the Spatie relationship integration.

Factory shorthand:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;

FlexFileUpload::spatie('photos')->collection('photos')->imagesOnly()->multiple();
```

## Different disks for originals and conversions

```php
$options = new SpatieOptions(collection: 'photos', conversionsDisk: 's3-thumbnails');
$intent = new MediaWrite(adapter: 'spatie', disk: 's3', record: $savedProduct, spatie: $options);
```

An explicit disk takes precedence over the collection disk. Conversion storage is configured independently.

## Custom properties and display name

```php
$options = new SpatieOptions(
    collection: 'photos', name: 'Front view',
    customProperties: ['alt' => 'Blue sailing yacht', 'license' => 'owned'],
    order: 2,
);
```

Properties are application metadata; they do not edit image EXIF. The optional FLEX information editor uses `custom_properties.editorial` for its own document.

## Responsive images

```php
$options = new SpatieOptions(collection: 'photos', responsiveImages: true);
```

You can also define responsive images on native collections/conversions. For your frontend templates, use Spatie's `srcset`/rendering APIs. The FLEX preview selects its configured conversion; responsive-image generation does not automatically replace the field's preview with a full responsive `<img>` API.

## Native FileAdder configuration

```php
use Spatie\MediaLibrary\MediaCollections\FileAdder;

$options = new SpatieOptions(
    collection: 'photos',
    headers: ['CacheControl' => 'max-age=86400'],
    sanitizeFilename: fn (string $name): string => strtolower($name),
    configure: function (FileAdder $adder): void {
        $adder->usingName('Catalog photo');
    },
);
```

The callback configures the adder. Do not call `toMediaCollection()` inside it or change the source/record identity; Media OS owns that persistence step. Custom Media models, path generators, file namers and URL generators remain native Spatie configuration.

For a field callback, preserve its collection:

```php
FlexSpatieMediaLibraryFileUpload::make('photos')->collection('photos')
    ->spatieOptionsUsing(fn (SpatieOptions $options): SpatieOptions => new SpatieOptions(
        collection: $options->collection,
        name: 'Catalog photo',
        conversionsDisk: 's3-thumbnails',
        customProperties: ['purpose' => 'catalog'],
        responsiveImages: true,
    ));
```

This replaces the options object; explicitly retain any other defaults you need.

## Queued conversions

```php
public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('large')->width(1600)->format('webp')->queued();
    $this->addMediaConversion('thumb')->fit(Fit::Crop, 160, 160)->format('webp')->queued();
}
```

In `config/media-library.php`:

```php
'queue_connection_name' => 'database',
'queue_name' => 'media-conversions',
'queue_conversions_after_database_commit' => true,
```

```bash
php artisan queue:work database --queue=media-conversions
```

Spatie conversion jobs use the native Media Library queue configuration. `SpatieOptions::queue` configures the FileAdder's responsive-image job queue, not a replacement for the conversion queue configuration.

Before a selected conversion is generated, FLEX shows an available original preview. The list indicates processing; the field refreshes pending previews without refreshing the whole page. Native Spatie failed jobs are diagnosed/retried through Laravel's queue tools; a missing conversion alone is not treated as proof of a permanent failure.

Do not enable native `imageConversions()` or `queueImageConversions()` on this field; use Spatie's model conversion API.

## Retrieve and update native media

```php
$media = $savedProduct->getFirstMedia('photos');
$ready = $media?->hasGeneratedConversion('thumb');
```

```php
$media->setCustomProperty('alt', 'Updated description');
$media->save();
```

For private links, use Media OS's authorized URL API:

```php
$url = FlexMedia::os()->url($receipt, $intent, now()->addMinutes(15), conversion: 'thumb');
```

Missing conversions can fall back to the original. Private links require a signing-capable disk or configured URL provider.

## Move, copy and order

Native operations remain available on your owned model/media objects:

```php
$copy = $media->copy($otherProduct, 'photos');
```

```php
$media->move($otherProduct, 'photos');
```

```php
use Spatie\MediaLibrary\MediaCollections\Models\Media;

Media::setNewOrder($ownedMediaIdsInDesiredOrder);
```

Authorize both records and the supplied media IDs in your application before invoking native operations. In a FLEX field, `reorderable()` supplies the form's drag-sorting behavior.

## Removing originals

Spatie normally retains the stored original and creates conversions alongside it. It has no standard conversion setting for “delete the original after large and thumb complete”. `preservingOriginal()` controls whether importing a local file copies or moves that **input**; it does not remove the library's original. See [associating files](https://spatie.be/docs/laravel-medialibrary/v11/basic-usage/associating-files) and [conversion optimization](https://spatie.be/docs/laravel-medialibrary/v11/converting-images/optimizing-converted-images).

Store a normalized smaller master when you do not want to retain the full upload. Manually deleting the stored original would break original URLs, fallback and regeneration. The native Media OS engine also retains originals.

## Option reference

| `SpatieOptions` property | Purpose |
| --- | --- |
| `collection`, `name` | Collection and display name |
| `conversionsDisk` | Derivative disk |
| `customProperties` | Application metadata |
| `manipulations` | Native per-media image manipulation settings |
| `attributes` | Allowed custom Media attributes; ownership columns are reserved |
| `headers` | Storage headers |
| `responsiveImages` | Generate responsive images |
| `order` | Native media order |
| `queue` | FileAdder responsive-image job queue |
| `sanitizeFilename` | Filename sanitizer closure |
| `configure` | Native FileAdder configuration closure |

## Save behavior

Collection constraints and native Spatie events/conversions remain authoritative. FLEX staging defers destructive replacement until record acceptance. Object storage and database changes are separate operations; an interrupted save can require reconciliation. Use [field lifecycle hooks](/docs/file-upload-events-and-recovery) when replacing the usual Filament save flow.
