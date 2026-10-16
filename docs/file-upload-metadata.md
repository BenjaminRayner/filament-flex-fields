---
title: "Upload information editor"
description: "Choose editable fields, alternative text, captions, shared tags, languages and validation."
icon: "file-pen-line"
---

The per-file information editor is **off by default**. Enable only the fields and languages your application needs. It uses FLEX input components and the shared TranslatableTabs component.

## Enable the editor

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;

FlexImageUpload::make('photos')
    ->imagesOnly()->multiple()
    ->fileMetadataEditor()
    ->fileMetadataStatePath('photos_editorial');
```

Default fields are `title`, `alt`, `caption`, `copyright` and `tags`. The action is available in list, grid and avatar layouts. Cancelling it leaves values unchanged.

For disk fields, add the companion JSON model column and normal fillable policy. Cast both multiple-file and editorial attributes:

```php
protected function casts(): array
{
    return ['photos' => 'array', 'photos_editorial' => 'array'];
}
```

The companion path is relative to the field's form container, including repeaters. Use a path different from file state and `storeMetadataIn()` technical metadata.

## Alternative text only

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataSchema([
        FlexTextInput::make('alt')->label('Alternative text')->maxLength(250),
    ]);
```

Omitted fields are neither shown nor accepted. No other information fields have to be enabled.

## Choose your own FLEX fields

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;

FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataSchema([
        FlexTextInput::make('alt')->label('Alternative text')->maxLength(250),
        FlexTextareaField::make('caption')->label('Caption')->maxLength(1000),
        FlexTextInput::make('copyright')->label('Copyright')->required(),
        TagsField::make('tags')->label('Tags')->maxTags(20),
    ]);
```

Supply 1–32 uniquely named FLEX field templates with simple relative names. Native Filament field renderers are not accepted. The editor stores a bounded plain-text/tag document rather than arbitrary HTML or EXIF modifications.

## Select exactly three languages

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataSchema([FlexTextInput::make('alt')->maxLength(250)])
    ->fileMetadataTranslations(
        ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch'],
        fields: ['alt'],
    );
```

Up to 32 locales are supported. A list such as `['pl', 'en', 'de']` is also accepted. Translations are opt-in and independent of enabling the editor.

## Translate selected fields only

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataTranslations(['en', 'pl'], fields: ['title', 'alt']);
```

Only title and alt become locale maps. Caption, copyright and tags remain shared. Tags always appear once outside translation tabs: they are shared identities and cannot be configured as translated fields.

## Primary language

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataTranslations(['en', 'pl', 'de'], fields: ['alt'])
    ->fileMetadataDefaultLocale('pl');
```

The default must belong to the configured language list. Otherwise the first configured locale is used. The primary locale selects the initial tab and the optional fallback source.

## Require alt only in the primary language

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataSchema([FlexTextInput::make('alt')->maxLength(250)])
    ->fileMetadataTranslations(['pl', 'en', 'de'], fields: ['alt'])
    ->fileMetadataDefaultLocale('pl')
    ->fileMetadataRequiredInDefaultLocale(['alt']);
```

Avoid adding `required()` to the translated field template if other languages should remain optional; the template's own required rule applies to every language clone.

## Display fallback

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataTranslations(['pl', 'en'], fields: ['alt'])
    ->fileMetadataDefaultLocale('pl')
    ->fileMetadataFallback();
```

Fallback is off by default. It resolves missing/null/blank display text from the primary locale without copying text into saved translations or satisfying required validation.

For application display code using the configured field instance:

```php
$text = $field->getFileEditorialText($fileKey, 'alt', 'en');
```

For an already obtained metadata document:

```php
$text = $field->resolveFileEditorialText($metadata, 'alt', 'en');
```

`readFileEditorialMetadata($fileKey)` returns raw values. File reads still enforce attachment ownership.

## Additional validation rules

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataTranslations(['pl', 'en'], fields: ['alt'])
    ->fileMetadataValidationRules([
        'alt.pl' => ['required', 'max:250'],
        'alt.en' => ['nullable', 'max:250'],
        'tags' => ['array', 'max:20'],
        'tags.*' => ['string', 'max:40'],
    ]);
```

Rules are relative to the metadata document. Laravel rule objects and wildcard rules are supported. They are checked in the editor and again before permanent persistence.

Structural limits remain: plain strings up to 4,000 characters, 50 distinct tags up to 80 characters each, 64 KiB JSON, and only declared fields/locales. Custom rules may tighten, but cannot remove, those limits.

## Shared tags and Spatie Tags

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataSchema([TagsField::make('tags')->maxTags(20)]);
```

These are per-file editorial strings. They do **not** implicitly synchronize the parent model's Spatie Tags relation. For a model tag relation, use the optional `FlexSpatieTagsField` separately; see [TagsField integration](/docs/tags-field). Spatie Media Library custom properties and Spatie Tags relationships are different storage features.

## Spatie media storage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;

FlexSpatieMediaLibraryFileUpload::make('photos')
    ->collection('photos')->imagesOnly()->multiple()
    ->fileMetadataEditor()
    ->fileMetadataTranslations(['en', 'pl'], fields: ['alt', 'caption']);
```

No companion model column is needed for the Spatie field. After record acceptance, editorial values are saved to the owned media's `custom_properties.editorial`, preserving other custom properties.

## Action presentation

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()
    ->fileMetadataStatePath('photo_editorial')
    ->configureFileMetadataActionUsing(fn ($action) => $action->modalWidth('lg'));
```

Standalone Livewire forms must provide Filament's `HasActions`, `InteractsWithActions` and render `<x-filament-actions::modals />`. Standard Filament pages already provide that action infrastructure. Editor inputs remain FLEX components.

## Disable or simplify

```php
FlexImageUpload::make('photo')->imagesOnly()->fileMetadataEditor(false);
```

```php
FlexImageUpload::make('photo')->imagesOnly()
    ->fileMetadataEditor()->fileMetadataStatePath('photo_editorial')
    ->fileMetadataTranslations(null);
```

The second example keeps the editor but removes translation tabs. Automatic translation, concurrent-editor revision management and embedded EXIF editing are not included.

## Image viewer and information sidebar

The image viewer is optional. It opens images from this field in a gallery with previous/next navigation, keyboard arrows, wheel zoom, touch pinch and swipe. By default it displays the original image without re-encoding it. Use `fileViewerConversion('large')` for a separate stored conversion, or `fileViewerUrlUsing()` for an authorized legacy preview URL; see [viewer source configuration](/docs/file-upload-api#choose-the-viewer-image-source). The fullscreen gallery opens from the selected thumbnail and returns to that image when closed. If the thumbnail is outside the viewport, the gallery uses a fade. Reduced-motion preferences disable these transitions. The optional information panel sits beside the image on desktop and below it on mobile.

```php
FlexImageUpload::make('photos')
    ->multiple()
    ->fileViewer();
```

When the information editor is enabled, its configured fields and languages are reused in the sidebar. The sidebar is read only by default. Enable editing explicitly:

```php
FlexImageUpload::make('photos')
    ->multiple()
    ->fileViewer()
    ->fileMetadataEditor()
    ->fileMetadataStatePath('photos_editorial')
    ->fileMetadataSchema([
        FlexTextInput::make('alt')->label('Alternative text')->maxLength(250),
    ])
    ->fileMetadataTranslations(['en' => 'English', 'pl' => 'Polski'], fields: ['alt'])
    ->fileViewerEditableMetadata();
```

Switching images starts the visual transition immediately while the current information is validated and saved. The information panel is temporarily locked. If validation fails, the gallery returns to the previous image and keeps the entered information. The Save information button displays a spinner and Saving status, prevents duplicate submission, and locks the gallery controls until the request finishes. A failed validation keeps the form open. Closing the viewer without saving discards changes to the current image; information already saved while navigating remains in the form.

Hide the sidebar with `fileViewerMetadata(false)`. Customize the FLEX action with `configureFileViewerActionUsing()`. You can add your own FLEX inputs to `fileMetadataSchema()` or add a schema view through the action's schema configuration. The viewer only accepts images owned by the current field; SVG is excluded from this gallery.


### Fullscreen gallery without information

```php
FlexImageUpload::make('photos')->imagesOnly()->multiple()
    ->uploadLayout('grid')
    ->fileViewer()
    ->fileViewerMetadata(false);
```

The gallery uses the full available width. Thumbnail entry/return animations use the image's actual destination bounds; they do not require a sidebar. List and avatar previews use the same viewer. With information enabled, the panel appears beside the image on desktop and below it on narrow screens.

### A custom editable sidebar

```php
FlexImageUpload::make('photos')->imagesOnly()->multiple()
    ->fileMetadataEditor()->fileMetadataStatePath('photos_editorial')
    ->fileMetadataSchema([
        FlexTextInput::make('alt')->label('Alternative text')->maxLength(250),
        FlexTextInput::make('credit')->label('Photo credit')->maxLength(120),
    ])
    ->fileMetadataTranslations(['en', 'pl', 'de'], fields: ['alt'])
    ->fileMetadataDefaultLocale('en')
    ->fileViewer()->fileViewerEditableMetadata();
```

These field and language choices are shared with the information editor. Shared credit stays outside the translation tabs. Save information runs configured validation and exposes a saving state; failed saves keep the draft available for correction. For a custom read-only view, use the viewer action schema configuration rather than declaring a non-field component in `fileMetadataSchema()`.
