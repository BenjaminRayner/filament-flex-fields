---
title: "Performance choices"
description: "Bounded prefetch, large option lists, virtualization, pagination and smaller image previews in FLEX Fields."
icon: "gauge"
---

## Interaction comes before speculative loading

FLEX distinguishes work needed for an interaction from lower-priority preparation. Loading concurrency and optional prefetch are bounded, rather than creating an unlimited set of background requests.

Resolved resource plans use bounded caches. Lifecycle handling focuses on changed page regions where possible, avoiding repeated work over every unchanged field after a local update. Factory registration tracks implementation identities rather than silently wrapping the same factory again.

These optimizations concern resource and initialization work. They do not make an unbounded SQL query or a full-resolution preview cheap.

## Configure large datasets deliberately

**Virtualization limits rendered rows. Pagination limits fetched results.** Use both when your dataset calls for them:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

$departmentOptions = [];

for ($number = 1; $number <= 500; $number++) {
    $departmentOptions['department-'.$number] = 'Department '.$number;
}

SelectField::make('department')
    ->options($departmentOptions)
    ->virtualizeThreshold(40)
    ->virtualRowHeight(40)
    ->virtualWindowSize(30);
```

This demonstration crosses the threshold and uses string identifiers suitable for a text attribute. It still supplies all 500 options. For large database or remote catalogs, use [Select's paginated provider](/docs/selectfield) with scoped search instead of fetching everything first.

## Use the right image for a preview

Configure a named image conversion, then select it for previews. Selecting the preview alone does not create the conversion:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;

FlexImageUpload::make('photo')
    ->imagesOnly()
    ->previewConversion('thumb');
```

The `thumb` conversion must exist in your [native image configuration](/docs/media-os-images) or [Spatie model configuration](/docs/media-os-spatie). Local preview generation and persisted preview selection are separate settings; see [image previews](/docs/file-upload-images-and-sources).

If processing is queued, run the configured worker. Native and Spatie conversions retain the original, and a missing or pending conversion follows the chosen preview/fallback behavior.

## Continue reading

[Asset Manager overview](/docs/asset-loading) · [Resource sharing](/docs/assets/sharing) · [Activation & cleanup](/docs/assets/lifecycle) · [Performance](/docs/assets/performance) · [Publish & diagnose](/docs/assets/deployment)
