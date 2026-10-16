---
title: "FlexMatrixTable"
description: High-performance matrix input for bulk data entry in a structured grid.
icon: "table-2"
"canonical": "https://flex-fields.bjanczak.com/docs/flex-matrix-table"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/flex-matrix-table.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexMatrixTable in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/flex-matrix-table-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/flex-matrix-table-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/flex-matrix-table.webp"
"twitter:image:alt": "FlexMatrixTable in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flex-matrix-table#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flex-matrix-table",
      "name": "FlexMatrixTable",
      "description": "High-performance matrix input for bulk data entry in a structured grid.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flex-matrix-table#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flex-matrix-table#article",
      "url": "https://flex-fields.bjanczak.com/docs/flex-matrix-table",
      "headline": "FlexMatrixTable",
      "description": "High-performance matrix input for bulk data entry in a structured grid.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flex-matrix-table#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/flex-matrix-table-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/flex-matrix-table-motion.mp4#video",
      "name": "FlexMatrixTable — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexMatrixTable in the FLEX Fields playground for Filament. High-performance matrix input for bulk data entry in a structured grid.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/flex-matrix-table.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.8S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/flex-matrix-table-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flex-matrix-table#article"
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
  src="/art/previews/flex-matrix-table-motion.mp4"
  poster="/art/previews/flex-matrix-table.webp"
  width="1920"
  height="1080"
  aria-label="FlexMatrixTable — FLEX Fields demonstration"
>
  <a href="/art/previews/flex-matrix-table-motion.mp4">Watch the FlexMatrixTable demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

A specialized grid input based on Filament's Repeater, optimized for bulk data entry across fixed rows and columns. Unlike a standard repeater, `FlexMatrixTable` is non-addable and non-deletable, providing a stable spreadsheet-like interface for complex configurations.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMatrixTable` |
| **State type** | `array<string, array<string, mixed>>` |
| **Model cast** | `'matrix_data' => 'array'` |
| **FieldType** | `flex-matrix-table` |
| **Playground** | `flex-matrix-table` slug in Flex Fields playground |
| **Extends** | `Filament\Forms\Components\Repeater` |

---

### Basic usage

#### Permission Matrix
```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMatrixTable;
use Filament\Forms\Components\Toggle;

FlexMatrixTable::make('permissions')
    ->rows([
        'users' => 'User Management',
        'posts' => 'Blog Posts',
        'settings' => 'System Settings',
    ])
    ->schema([
        Toggle::make('view')->label('View'),
        Toggle::make('create')->label('Create'),
        Toggle::make('edit')->label('Edit'),
        Toggle::make('delete')->label('Delete'),
    ]);
```

#### Custom Column Widths
```php
FlexMatrixTable::make('pricing')
    ->rows(['basic' => 'Basic', 'pro' => 'Pro'])
    ->schema([
        TextInput::make('price')->numeric()->prefix('$'),
        TextInput::make('limit')->numeric(),
    ])
    ->columnWidths([
        'price' => '150px',
        'limit' => '100px',
    ]);
```

---

### State & validation

For a compact Select Field column, keep the dropdown the same width as the field
with `wrapOptionLabels(false)`. Longer option labels are truncated:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

SelectField::make('priority')
    ->options(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'])
    ->wrapOptionLabels(false)
    ->size('sm')
    ->hiddenLabel();
```

#### Stored value
The state is an associative array where keys are row IDs and values are arrays of field data from the `schema()`.

```php
[
    'users' => [
        'view' => true,
        'create' => false,
        'edit' => true,
    ],
    'posts' => [
        'view' => true,
        'create' => true,
        'edit' => true,
    ],
]
```

#### Fixed Structure
By default, `FlexMatrixTable` disables `addable()`, `deletable()`, and `reorderable()`. This ensures the matrix always matches your defined `rows()`.

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `rows(array\|Closure $rows)` | Setup | `[]` | Row keys => labels |
| `columnWidths(array\|Closure $w)` | Setup | `[]` | Field name => CSS width |
| `size(string\|Closure $size)` | Setup | `'md'` | `sm`, `md`, `lg` |
| `rounding(string\|Closure $round)` | Setup | config | Border radius token |
| `schema(array\|Closure $schema)` | Setup | — | Fields to show in each row |

---

### Real-world examples

#### Feature Availability Matrix
```php
FlexMatrixTable::make('features')
    ->label('Plan Features')
    ->rows([
        'api' => ['label' => 'API Access', 'desc' => 'REST & GraphQL'],
        'sso' => ['label' => 'Single Sign-On', 'desc' => 'SAML & OAuth'],
        'support' => ['label' => 'Priority Support', 'desc' => '24/7 coverage'],
    ])
    ->schema([
        Checkbox::make('starter')->label('Starter'),
        Checkbox::make('business')->label('Business'),
        Checkbox::make('enterprise')->label('Enterprise'),
    ])
    ->columnWidths([
        'starter' => '100px',
        'business' => '100px',
        'enterprise' => '100px',
    ]);
```

---

### Playground

`/admin/flex-fields/flex-matrix-table`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [MatrixChoiceField](/docs/matrixchoicefield) | Simple radio/checkbox grids without custom schemas |
| `Repeater` | When users need to add/remove rows dynamically |
| [ItemCardGroup](/docs/itemcardgroup) | Visual card-based bulk selection |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-matrix-choice` | Root wrapper (shared with MatrixChoice) |
| `fff-matrix-choice--{sm\|md\|lg}` | Size variant |
| `fff-matrix-choice__header` | Table header row |
| `fff-matrix-choice__row` | Individual data row |
| `fff-matrix-choice__cell` | Data cell |
| `fff-matrix-choice__label` | Row label wrapper |
| `fff-matrix-choice__description` | Row description text |

### Child validation and row identity

Each cell renders the actual field from `schema()`, including its validation,
configuration and independently loaded resources. Required, numeric, range and
custom rules belong to those child fields. The table also rejects unknown row
keys and row values that are not arrays. Disabled rows disable their child schema;
values from those disabled child fields are excluded from saving.

Row keys identify nested form paths: use nonempty keys without dots. Descriptions
and custom row metadata remain available through `getNormalizedRows()`.
The row and column layout uses the shared matrix stylesheet; the table adds no
second JavaScript controller over the child fields.
