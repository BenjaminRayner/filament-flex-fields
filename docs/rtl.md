---
title: "RTL support"
sidebarTitle: "RTL support"
description: "Use FLEX Fields with Arabic, Hebrew and mixed-direction content. Explore real controls, locale-aware direction, dropdown alignment and Laravel translations."
icon: "arrow-left-right"
mode: "wide"
canonical: "https://flex-fields.bjanczak.com/docs/rtl"
---

import { RtlPreview } from "/snippets/rtl-preview.jsx";

Build forms that read naturally in either direction. FLEX controls use logical spacing and read the direction of their rendered field. Labels, translated content and stored values remain separate choices.

## Component examples

Switch between Arabic, Hebrew and English, then type into the inputs, reveal a password, change the selected period or toggle notifications. These examples use the actual FLEX Blade renderers and browser controllers with local preview values; they do not submit a form or contact a server.

<RtlPreview />

The email and password values keep their LTR order inside the RTL layout. The surrounding labels, action placement, segments and switches follow the selected direction.

## Set the direction where it belongs

In a Filament application, configure your application locale and panel direction through Filament. For a particular FLEX field, a wrapper direction applies to the whole control:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('city')
    ->label('المدينة')
    ->options([
        'riyadh' => 'الرياض',
        'jeddah' => 'جدة',
        'dammam' => 'الدمام',
    ])
    ->searchable()
    ->extraAttributes(['dir' => 'rtl', 'lang' => 'ar']);
```

Direction changes presentation and navigation; the saved value is still the option key, such as `riyadh`. It does not translate your labels, change validation or rewrite your database values.

For text content, configure the input independently:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('display_name')
    ->label('الاسم الكامل')
    ->textDirection('rtl');

FlexTextInput::make('email')
    ->label('البريد الإلكتروني')
    ->email()
    ->textDirection('ltr');
```

Use `textDirection('auto')` when a text value can begin in either script. Keep identifiers, email addresses and URLs in their natural order. Use `<bdi>` when displaying a mixed-direction value inside a sentence.

## Translated content in the same form

[TranslatableFields](/docs/translatablefields) can derive the editable control's direction from the selected content locale:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

TranslatableFields::make('Product title')
    ->locales([
        'en' => 'English',
        'ar' => 'العربية',
        'he' => 'עברית',
    ])
    ->directionByLocale()
    ->schema([
        FlexTextInput::make('title'),
    ]);
```

By default, `directionByLocale()` sets the editable input's direction where the field supports it. The surrounding panel labels retain the panel direction. Use `TranslatableDirectionScope::Field` when the entire field wrapper should follow the content locale. Configure additional RTL locales through `filament-flex-fields.translatable.rtl_locales`.

## Menus, calendars and keyboard navigation

| Control | What to configure |
| --- | --- |
| [Select](/docs/selectfield), [Country](/docs/countryfield), [Timezone](/docs/timezonefield), [Font Select](/docs/font-select) | Set the direction on the field wrapper. The shared dropdown keeps its direction when rendered outside the field. |
| [Text input](/docs/flextextinput) and [Textarea](/docs/flextextareafield) | Configure content direction independently of labels and surrounding layout. |
| [Segment Control](/docs/segmentcontrol) | Arrow navigation follows the rendered track's direction. Home and End reach the first and last available option. |
| [Date Picker](/docs/date-picker), [Date Range](/docs/date-range-picker), [Time Picker](/docs/time-picker) | Use the field's `locale()` for calendar language and formatting. Configure timezone and calendar system separately. |
| [Slider](/docs/flexslider) | Use its `rtl()` option when the value axis should run right to left. |

When composing your own layouts, prefer logical spacing such as `padding-inline-start` and `margin-inline-end`. A custom portal should carry the field's direction onto its own root. Do not mirror photos, signatures, maps or every icon simply because the surrounding layout is RTL.

## UI translations and fonts

Direction support and translated copy are separate. The Arabic and Hebrew labels above are application-provided sample content. The package currently includes UI catalogs for English, Polish, German, French, Spanish, Italian, Dutch and Brazilian Portuguese; it does not include complete Arabic or Hebrew UI catalogs.

Keep your application labels in Laravel language files. Publish the package translations to provide or override the UI messages your application needs:

```bash
php artisan vendor:publish --tag=filament-flex-fields-translations
```

See [Languages, dates and direction](/docs/configuration#languages-dates-and-direction) for translation paths and locale behavior. Choose an application font with Arabic or Hebrew coverage, and check shaping, line height and mixed text with your actual content.

Before publishing a multilingual form, exercise typing, clearing, search, keyboard navigation and opening menus inside your actual panel, modal and mobile layouts. Direction settings do not replace application-level validation or accessibility checks.
