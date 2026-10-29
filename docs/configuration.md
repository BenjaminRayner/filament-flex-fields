---
title: "Configure FLEX Fields appearance and behavior"
sidebarTitle: "Configuration & styling"
description: "Configure FLEX Fields v4 defaults, panel density, theme tokens, control sizes, optional features and language editing."
icon: "sliders-horizontal"
---

Configure a field where you use it. Use panel settings for shared presentation and application configuration for reusable defaults. A small form and a detailed workflow can use the same components with different settings.

## Choose the right scope

| Scope | Where you configure it | Use it for |
| --- | --- | --- |
| Application | `config/filament-flex-fields.php` | Defaults and service settings used across your application |
| Panel | `FilamentFlexFieldsPlugin::make()` | FLEX density, theme tokens and navigation settings |
| Field | The field's fluent PHP methods | Its size, variant, data source, limits and optional features |

Not every setting exists at all three levels. For example, a text input reads its configured size default when it is created; a later `size('sm')` on that field replaces that default. An upload's editor is enabled on the upload instance. Check the component reference for the setting you are changing.

## Set application defaults

[Publish configuration](/docs/installation#optional-defaults-and-translations) if you need overrides. Update the existing `ui` section rather than replacing the entire file:

```php config/filament-flex-fields.php
'ui' => [
    // Keep the other UI settings already present.
    'flex_text_input_size' => 'md',
    'flex_text_input_variant' => 'secondary',
],
```

A field can then choose a different presentation:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('reference')
    ->size('sm')
    ->variant('primary');
```

Settings maps inherit newly added nested defaults. Explicit application values
(including `false` and `null`) take precedence. Lists replace the default list;
they are never appended. An explicitly empty array replaces that entire branch,
so omit a branch when you want its defaults. For example, setting
`'ui' => ['flex_text_input_size' => 'sm']` keeps the other UI defaults, while
`'ui' => []` explicitly clears that branch.

The file lists the settings the package actually reads, including icon defaults,
FormBuilder size/variant defaults, map assets, route prefixes and URL upload
limits. FormBuilder defaults apply when it builds fields from schemas; they do
not implicitly configure every direct `Field::make()` instance. Use each field's
fluent methods for explicit per-instance configuration. `ui.cell_height` was
unused and has been removed. `ui.video_auto_hide_controls` now supplies the video
control default; `autoHideControls(...)` on a field still takes precedence.

Schema defaults also inherit the configured variants and default country. An
explicit field `config` value wins over these application defaults, including
`auto_hide_controls => false`. Schema-generated NPS labels resolve in the current
language; custom labels stay unchanged, and an explicit empty or null endpoint
label suppresses that label.

After changing published configuration, rebuild the application configuration cache if your deployment uses it. See [configuration troubleshooting](/docs/troubleshooting#a-setting-change-has-no-effect).

## Control the component catalog

The catalog remains enabled by default. Disable its pages, routes and catalog-only
styles across the application without disabling any FLEX fields:

```dotenv .env
FLEX_FIELDS_PLAYGROUND=false
```

Alternatively set `'playground' => ['enabled' => false]` in the published configuration.
For a single panel, keep the global setting enabled and configure its plugin:

```php Panel provider
FilamentFlexFieldsPlugin::make()
    ->playground(false);
```

The global switch takes precedence. An enabled panel cannot restore a catalog
that the application has disabled. Theme settings, standalone components and
separately enabled custom field management remain available. When using cached
Filament components or application configuration, rebuild those caches after
changing registration settings during deployment.

### Restrict access to the catalog

Catalog pages require a user authenticated with the **current panel's guard**.
To restrict them further, define a Laravel gate and reference its ability in the
published configuration:

```php AuthServiceProvider::boot()
use Illuminate\Support\Facades\Gate;

Gate::define('view-flex-catalog', fn ($user): bool => $user->can('manage-developer-tools'));
```

```php config/filament-flex-fields.php
'playground' => [
    'enabled' => env('FLEX_FIELDS_PLAYGROUND', true),
    'gate' => 'view-flex-catalog',
],
```

Use an ability your application actually defines. You may also add a panel-specific
rule in its provider:

```php Panel provider
FilamentFlexFieldsPlugin::make()
    ->playgroundAuthorizeUsing(fn ($user, \Filament\Panel $panel): bool =>
        $user->can('manage-developer-tools')
    );
```

When both are configured, both rules must allow access. The same checks protect
the catalog index, individual examples, plugin detail and license guide, including
direct URLs. They do not grant access to other application pages or bypass your
application's existing authorization.

## Configure panel density and theme

Add these options to your existing panel provider:

```php Panel provider
use Bjanczak\FilamentFlexFields\Enums\Density;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // Keep your existing panel configuration.
        ->plugin(
            FilamentFlexFieldsPlugin::make()
                ->density(Density::Comfortable)
                ->theme([
                    'radius' => '12px',
                    'menu_radius' => '24px',
                ]),
        );
}
```

| Density | Intended use |
| --- | --- |
| `Density::Compact` / `'compact'` | Denser presentation |
| `Density::Comfortable` / `'comfortable'` | Default presentation |
| `Density::Spacious` / `'spacious'` | More spacious presentation |

Density and a field's `size()` are different settings. Density supplies the shared presentation setting; size chooses the supported dimensions of an individual control. A component may also have its own spacing and layout rules.

### Theme tokens

The `theme()` method accepts literal CSS values for FLEX tokens. These aliases are available:

| Token | What it sets |
| --- | --- |
| `primary` or `primary_color` | Field focus-border color |
| `radius` or `field_radius` | Shared field radius |
| `menu_radius` | Shared menu radius |
| `field_bg` | Field background |
| `field_border` | Field border |
| `field_focus_ring` | Focus-ring value |
| `field_focus_ring_width` | Focus-ring width |

Use units where CSS needs them, such as `'12px'`. Theme values are values, not stylesheets: do not pass declarations, markup or URLs. For more specific supported tokens, see [extension appearance](/docs/extensions/appearance).

<Note>
  Setting `primary` here overrides a FLEX focus-border token. It does not change every Filament accent or recolor every control state. Keep your panel's normal Filament color and dark-mode configuration for the wider application.
</Note>

### Defaults and explicit overrides

Application `ui.density` supplies the initial density; an explicit plugin `density()` replaces it. Application `ui.theme` supplies theme defaults; plugin `theme()` merges the supplied keys over those defaults. A field's explicit size or variant controls that field rather than changing every control on the page.

For service options such as disks, providers and queues, use the relevant [Media OS configuration](/docs/media-os-configuration) or component guide. Their precedence is specific to that feature.

### Navigation settings

Add these methods to the same plugin instance when you want to adjust its navigation:

```php
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;

FilamentFlexFieldsPlugin::make()
    ->navigationGroup('Tools')
    ->navigationLabel('FLEX')
    ->navigationIcon('hugeicons-dashboard-square-01')
    ->navigationSort(91);
```

These settings organize FLEX's panel navigation. They do not change the resource models or field values. The application-wide `navigation.sort` config key supplies the default sort position.

## Compare sizes without changing the style

Text Input supports `sm`, `md` and `lg`. Its package defaults are `md` and `primary`, unless your application changes the defaults.

<CodeGroup>
```php Small
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('reference')
    ->size('sm')
    ->variant('primary');
```
```php Medium
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('reference')
    ->size('md')
    ->variant('primary');
```
```php Large
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('reference')
    ->size('lg')
    ->variant('primary');
```
</CodeGroup>

## Choose a supported variant

Variant names belong to each component family:

| Component | Supported variants |
| --- | --- |
| [Text Input](/docs/flextextinput) | `primary`, `secondary`, `soft`, `flat` |
| [Select](/docs/selectfield) | `bordered`, `secondary`, `flat`, `faded`, `soft`, `underlined`, `item-card` |
| [File Upload](/docs/flexfileupload-and-fleximageupload) | `primary`, `secondary`, `flat` |
| [Date and time](/docs/date-time-configuration) | `primary`, `secondary`, `flat` |

For example, these fields use different presentation settings on the same form:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

$fields = [
    FlexTextInput::make('reference')
        ->size('sm')
        ->variant('primary'),

    SelectField::make('department')
        ->options([
            'design' => 'Design',
            'engineering' => 'Engineering',
            'operations' => 'Operations',
        ])
        ->size('md')
        ->variant('secondary'),
];
```

Here `department` is a text attribute storing one of the three string identifiers. Use valid record identifiers and a configured relationship if the selection represents a related model.

## Enable features on the field that needs them

<CodeGroup>
```php Emoji input
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('message')
    ->emojiPicker();
```
```php Image editor
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;

FlexImageUpload::make('photo')
    ->imagesOnly()
    ->imageEditor()
    ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1']);
```
</CodeGroup>

Enabling the image editor on one upload does not enable it on every upload. File information editing is a separate, opt-in feature: choose its fields and languages in the [metadata guide](/docs/file-upload-metadata).

[Assets & performance](/docs/asset-loading) explains how these choices affect loading. Browser-dependent features also have [requirements](/docs/requirements#browser-and-device-features).

## Languages, dates and direction

These choices solve different problems:

| Choice | Meaning |
| --- | --- |
| Application translation language | Labels and messages from Laravel language files |
| Field locale | Formatting or calendar labels where that field supports it |
| Content locales | The languages your users can edit for a translated attribute |
| Default/fallback locale | Which language a configured workflow uses when text is missing |
| RTL direction | Reading and layout direction |
| Timezone | The meaning or presentation of time in the relevant workflow |

Bundled UI catalogs are available for English (`en`), Polish (`pl`), German
(`de`), French (`fr`), Spanish (`es`), Italian (`it`), Dutch (`nl`) and Brazilian
Portuguese (`pt_BR`). They live in `resources/lang/` inside the package. Publish
application overrides to `lang/vendor/filament-flex-fields/{locale}/` with
`php artisan vendor:publish --tag=filament-flex-fields-translations`.
Translation keys and Laravel replacement tokens are checked across every bundled
catalog. Application labels and option content remain application-owned.

Default labels, placeholders, validation presets and action text resolve when
read, using the current application language. This includes retained rich editor
tools and action schemas, upload metadata, Todo actions, schedule switches and
verification/card footer actions. The Phone and Currency menus refresh their
localized catalogs when their language changes while keeping stored codes intact.
Explicit custom labels remain unchanged; use a callback such as
`->label(fn () => __('app.customer_name'))` for an application label that should
also follow later locale changes. For the empty translation badge, `null` in
`translatable.empty_badge_label` uses the translated default; a custom string
keeps that exact text. Browser-owned widgets need their normal render/lifecycle
update after changing application language; changing a PHP locale does not
mutate an already loaded browser session by itself.

Select content languages explicitly with FLEX translation tabs:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

TranslatableFields::make('Product title')
    ->locales([
        'en' => 'English',
        'pl' => 'Polski',
        'de' => 'Deutsch',
    ])
    ->directionByLocale()
    ->schema([
        FlexTextInput::make('title')->maxLength(150),
    ]);
```

JSON mode needs a matching `title` JSON column and array cast. [TranslatableFields](/docs/translatablefields) describes locale configuration and optional Spatie storage. [Upload metadata](/docs/file-upload-metadata) separately describes selected translated fields, default-language requirements and display fallback. A fallback does not create missing saved translations.

For dates, `locale()` and `timeZone()` have specific meanings; see [date/time configuration](/docs/date-time-configuration). Do not assume that changing language converts stored timestamps.

## Reuse layouts and controls

Use [Item Card](/docs/itemcard) to group related fields, [Segment Tabs](/docs/segmenttabs) to separate tasks and [TranslatableFields](/docs/translatablefields) for language editing. Keep each child field's data and configuration on the child.

For a reusable custom component, [compose real child controls](/docs/extensions/child-php-api). Share their renderer and declared assets rather than copying their HTML, CSS and JavaScript.

<CardGroup cols={2}>
  <Card title="Save the right value" icon="database-zap" href="/docs/data-and-validation">Match columns, casts and validation to your fields.</Card>
  <Card title="Understand resource loading" icon="network" href="/docs/asset-loading">See how instances and optional features share assets.</Card>
</CardGroup>


## Monitoring events

FLEX uses [standard Laravel activity events](/docs/sre-runbook#application-events-and-monitoring)
for search, uploads, retention and barcode notifications. Register application
listeners to collect metrics or write logs; there is no FLEX monitoring driver or
`enterprise` switch. Use queued listeners for slow external integrations.
