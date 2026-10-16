---
title: FLEX catalog
description: Localized component discovery, official categories, extensions and promotions.
---

# FLEX catalog

FLEX replaces the development-only Playground. It remains behind panel authentication and is enabled in every environment by default. The canonical route segment is `flex-fields`; generate links through Filament's page `getUrl()` methods. FLEX navigation is permanent; `navigation.sort` controls its position.

The index and detail pages use a full-width page shell with a centered content area: 960 px on the index and 760 px on detail pages. The index is a custom page with shared FLEX input styling, local keyword search, official category sections, Installed/Community collections and curated Popular/New & Noteworthy sections. Detail pages preserve interactive demos and separate Demo/Code Example using FLEX SegmentTabs. Entries may supply icon/image, hero image, description and author metadata. Built-in entries carry an original filled verification seal.

## Detail pages

The detail header places a 60 px icon above the component name, with the author
and verification seal immediately beneath the name. Compact 36 px status/repository
and ellipsis controls sit on the right. Demo actions live in the adjacent
ellipsis menu, using FLEX's shared overlay controller and styles. Validate uses
the normal Filament validation path; Dump JSON inspects raw form state without
requiring the demo to pass validation; Reset keeps native confirmation.

`ComponentPresentation::heroImage` and `RecommendedPlugin::heroImage` accept an
optional image URL. The detail page renders it between the header and demo/content,
reserving the 720 × 336 aspect ratio before decoding. The description follows the
hero, or the header when no hero is configured. Demo, Code Example and Information
use unmodified standard FLEX SegmentTabs. Information contains the developer,
category and applicable external links/version; it is not duplicated below the
demo. External entries use Code Example (when applicable) and Information.
The example URL below is illustrative:

```php
new ComponentPresentation(
    label: ['en' => 'Location', 'pl' => 'Lokalizacja'],
    description: ['en' => 'Choose a location.', 'pl' => 'Wybierz lokalizację.'],
    category: CatalogCategory::Location,
    icon: 'hugeicons-maps-location-02',
    author: 'ACME',
    heroImage: 'https://cdn.example.com/components/location/hero.webp',
);
```

## Component types

The index combines **All / Installed / Community**, text search and a separate
component-type filter: form fields, table columns, content and layouts, infolist
entries, and actions. Only types present in the catalog are offered. Filtering is
local; it does not send requests or mount component demos.

For registered components, the type comes from `ComponentDefinition::kind`.
Each surface has one kind: `CoverCard` is a display, while `RatingField` and
`RatingColumn` are separate field and column surfaces. A `ComponentFamily` may
declare several surfaces with independent component identities. Shared CSS,
`uses` and optional capabilities do not give a field the kind of its dependencies.
An explicit `ComponentPresentation(groupedWith: 'parent-id', ...)` places a column
demo on the corresponding component's page. Its main catalog card then matches
both types, while the column remains an independently registered renderer. The
column has no duplicate card or separate playground route. See
[grouped catalog demos](/docs/extensions/catalog#group-a-field-and-its-table-column).

## Official categories

Every discoverable component and recommendation selects exactly one `CatalogCategory` enum case. Extensions cannot invent a category or register additional taxonomy records.

| Enum | Identity |
| --- | --- |
| `Guides` | `guides` |
| `Navigation` | `navigation` |
| `Buttons` | `buttons` |
| `Pickers` | `pickers` |
| `DateAndTime` | `date-and-time` |
| `Colors` | `colors` |
| `Controls` | `controls` |
| `Collections` | `collections` |
| `TextInput` | `text-input` |
| `DataDisplay` | `data-display` |
| `Media` | `media` |
| `Location` | `location` |

Presentation and category labels follow the application locale with language-region fallback, then English. Built-in catalog labels, descriptions and navigation/action text support Polish and English. Third-party authors supply their own localized metadata. The interactive examples retain their separate component/demo translation contracts.

## Recommended external plugins

```php
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\RecommendedPlugin;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

app(FlexCatalog::class)->registerPlugin(new RecommendedPlugin(
    id: 'acme-tools',
    label: ['en' => 'ACME Tools', 'pl' => 'Narzędzia ACME'],
    description: ['en' => 'Tools for your panel.', 'pl' => 'Narzędzia do panelu.'],
    author: 'ACME',
    repository: 'https://github.com/acme/tools',
    category: CatalogCategory::Controls,
    icon: 'hugeicons-settings-01',
    composerPackage: 'acme/tools',
    kinds: [ComponentKind::Field, ComponentKind::Column],
));
```

The optional `kinds` list declares the recommendation's component types. A recommendation can offer several types, as shown above. Use unique `ComponentKind` cases; `Primitive` is internal and cannot be advertised. Omitting `kinds` keeps the recommendation visible under All types without guessing its type from the category. This metadata does not register renderers or verify an external package's features.

The Installed status reads the local Composer package map and memoizes each package lookup. A missing/undeclared Composer identity is never treated as installed. Install opens the author's GitHub repository; FLEX does not execute installation commands. Registered custom components appear automatically even without a recommendation; see [Custom components](/docs/custom-components).

## Official promotions

Advertising content belongs to the publisher, outside public application configuration. The immutable `PromotionFeed` points to the publisher's `.github/ads.json` on GitHub Raw. Publish that file in the repository to make remote campaigns available; an unpushed local file is not a live feed. There is no host-editable URL, banner array, enable switch or `registerBanner()` API.

The first HTML response reads only the local Laravel cache. A Livewire refresh runs after first paint when due, protected by a single-flight cache lock. Successful documents are fresh for 15 minutes; the last valid document survives an outage for at most 24 hours. Failures retry after 5 minutes. Conditional ETag/Last-Modified requests support HTTP 304. Empty `banners: []` deliberately hides the promotion area. Without usable remote data, localized official FLEX content is displayed.

The remote document is versioned and bounded: 64 KiB, 12 banners, plain text and HTTPS images/destinations. No HTML, scripts or CSS are accepted. Every banner requires English `title`, `description` and `action`; optional `eyebrow` also requires English. Resolution tries the application locale, then its base language, then English.

```json
{
  "schemaVersion": 1,
  "slider": {"autoplay": true, "interval": 7000, "arrows": true, "dots": true},
  "banners": [{
    "id": "new-release",
    "title": {"en": "Discover the new release", "pl": "Poznaj nową wersję"},
    "description": {"en": "Explore what's new."},
    "action": {"en": "Explore"},
    "url": "https://github.com/janczakb/filament-flex-fields",
    "theme": "light",
    "backgroundImage": "https://example.com/banner.webp",
    "backgroundPosition": "center",
    "overlayOpacity": 0.35,
    "alignment": "start"
  }]
}
```

One banner renders a static card. Multiple banners use the slider, which supports timed playback (3–60 seconds), pause/resume, a circle timer, keyboard controls, touch swipes and a dynamic five-bullet window. Playback pauses on hover, keyboard focus and hidden tabs; reduced-motion preferences disable automatic movement. A whole-card skeleton waits for document resolution and images, including cached images; failed images release the loader and use the card's fallback background.

All slides reserve the height of the tallest content. Desktop, tablet and mobile have different minimum heights and expand for longer copy. Desktop arrows appear on hover/focus; phone controls remain available below the card. Animations use composited opacity/transforms rather than layout changes.

GitHub Raw is deliberately used for mutable advertising metadata: [jsDelivr caches branch URLs for 12 hours](https://github.com/jsdelivr/jsdelivr#caching), which can delay campaign updates. Versioned artwork may still use jsDelivr HTTPS URLs. No remote request blocks the initial page render.

## Official metadata limits

The schema and remote decoder enforce the same Unicode character limits through `CatalogValue`: title/label 120, description 600, author 80, action/eyebrow 48, keyword 48, at most 24 keywords. Identities use at most 96 ASCII characters; URLs at most 2048. Localized values accept at most 32 locales. Invalid metadata is rejected before registration or cache replacement.

## Code examples

The code reader is an isolated Livewire child. Opening Code Example updates only
that reader, without rerendering the page's form/header. Files are still lazy;
returning to the tab reuses its loaded contents. Demo actions retain the child,
and static header/Information markup is not morphed. Validation errors/reset still
update the interactive demo. The reader handles failed requests with retry.

Component schemas reference dedicated PHP files through lightweight `CodeExample` objects. Built-in examples live in `resources/examples/{component-id}/`; third-party packages use their own resources directory. Declaring a reference performs no file access, parsing, or demo/reader class autoload. The catalog index and production forms never resolve the reader or load preview CSS/JS. The Code Example tab makes an authorized Livewire request; only then are the selected component's files read as bounded UTF-8 text, never included or executed. PHP tokenization produces escaped, numbered lines. Highlighted output is cached for one hour by content identity, so edits cannot return stale code. Limits: 12 files per component, 128 KiB and 2,000 lines per file. Missing or oversized files show a translated unavailable message without disclosing local paths.

```php
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;

// Inside the custom component's ComponentPresentation:
examples: [
    CodeExample::php(__DIR__.'/../examples/basic.php', 'Basic usage'),
    CodeExample::php(__DIR__.'/../examples/advanced.php', 'Advanced setup'),
],
```

Example files begin with `<?php` and contain complete, syntactically valid PHP snippets. They do not belong in plugin configuration or the production field class. The same reader works for registered custom components with or without a live demo. Preview assets have their own component boundary and are removed by the shared asset lifecycle when their page consumer disappears.

## Performance and isolation

Definitions are loaded once per application registry. Catalog snapshots cache by component revision, recommendation revision and locale; a new custom registration invalidates the snapshot. A single snapshot bounds retained locale data in long-running workers. Search builds one normalized client index and memoizes the result set per filter combination; it needs no round-trip for typing. Images use lazy loading.

Catalog styles and JavaScript are demanded by FLEX views. Detail pages use the layout styles without importing the index's search runtime. Production field dependency plans do not include catalog assets. Discovery and installation status need neither HTTP calls nor database tables. Popular ordering is editorial metadata, not an invented usage statistic.

## Scope

The visual design is responsive and supports light/dark tokens and reduced motion. Hugeicons is the default control library. Deprecated `GravityIcon` PHP constants resolve to Hugeicons for API compatibility; they do not keep the old Composer or NPM dependency. Applications with previously published icon configuration should update old icon-name overrides when upgrading.

## Information and promotion presentation

Details show the localized category and developer. `ComponentPresentation` and `RecommendedPlugin` accept optional HTTPS `website`, `privacyPolicy` and `termsOfService` links. Missing links are omitted. Only external plugins show `version`: the local Composer version takes precedence when installed, otherwise the optional declared version is used. No remote lookup is made. Official components inherit the publisher website unless they declare their own website.

The shared page footer and catalog styles are demanded only by FLEX pages. Normal component asset plans do not include catalog presentation or snippet assets.


### Optional sponsors

The same publisher-owned `.github/ads.json` document accepts `sponsors`. No additional request, public config setting, database table or discovery scan is used. Omitted or empty lists render no strip and no support button. Up to 24 profiles are validated atomically with the banners and sorted gold → silver → bronze; equal tiers keep publisher order. Names are bounded to 80 characters, profile/avatar URLs must be HTTPS, and duplicate profiles are rejected. Avatar loading is lazy with an initials fallback. Supply a small avatar image (64–96 px) from a trusted CDN. The compact strip keeps the neon lime, borderless circular GitHub Sponsors link visible. Mobile profiles occupy a separate row beneath the title. Bronze, silver and gold gradient borders indicate tiers without dots or a count; overflow alone enables subtle edge fades and scroll controls. The join action reuses the existing translated tooltip directive, without a separate tooltip runtime or styles. Touch scrolling, arrow keys, Home/End and Tab keep every profile accessible. The local fixture currently renders the full 24-profile limit, using the same bounded publisher limit. The page-only controller coalesces scroll measurements, observes viewport resizing and disposes its observer/frame when removed.

```json
{
  "schemaVersion": 1,
  "banners": [],
  "sponsors": [
    {
      "name": "Sponsor name",
      "profile": "https://github.com/username",
      "avatar": "https://avatars.githubusercontent.com/u/123?s=64",
      "rank": "gold"
    }
  ]
}
```

Ranks are a closed set: `bronze`, `silver`, `gold`. Labels use normal English/Polish Laravel translations. The `+` link points to the publisher's GitHub Sponsors profile. The package's initial document intentionally has an empty sponsor list; never publish demonstration supporters as real ones. Mobile slides use swipe gestures and bullets; arrows are shown only on larger screens.

The compact code loading surface shares the editor's dark frame without imposing a fixed viewport height. Source content fades into that frame after the lazy request, with animation disabled for reduced-motion preferences. Preview styles and JavaScript remain isolated to requested example rendering.

During the current visual review, an empty feed shows DEMO initials automatically on local/testing installations (`SponsorPreview::SHOW_ON_LOCAL_INDEX`). Real feed profiles take precedence; production remains hidden for empty lists. Local development can also force the sponsor preview at `/admin/flex-fields/index?sponsors-preview=1`. Profiles are explicitly marked DEMO, kept out of the publisher document/cache and ignored outside local/testing environments. Disable the temporary preview constant after visual review to restore empty-list behavior locally. FLEX index and detail pages include a theme switch using the panel's existing theme store and event; no separate global theme runtime is introduced.


### Navigation and mobile presentation

Demo discovery shares one container-scoped, localized snapshot. A component-registry revision or language change invalidates it; a new request scope releases it. Discovery never reads example files. The Select dynamic-options demo uses a real deferred callback without an artificial server sleep.

On screens up to 700 px, promotions reach both viewport edges with square corners. The catalog header uses separate brand/count rows and an aligned theme switch. Search keeps the shared text-input styles with a 40 px height; categories, cards and footer use tighter spacing. Sponsor actions remain outside the scrollable profile rail.
