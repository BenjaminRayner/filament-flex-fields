---
title: "FlexTextareaField"
description: Auto-sizing textarea with shared emoji, dictation, toolbar selects and native Filament validation.
icon: "text-align-start"
"canonical": "https://flex-fields.bjanczak.com/docs/flextextareafield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/flex-textarea.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexTextareaField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/flex-textarea-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/flex-textarea-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/flex-textarea.webp"
"twitter:image:alt": "FlexTextareaField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flextextareafield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flextextareafield",
      "name": "FlexTextareaField",
      "description": "Auto-sizing textarea with shared emoji, dictation, toolbar selects and native Filament validation.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flextextareafield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flextextareafield#article",
      "url": "https://flex-fields.bjanczak.com/docs/flextextareafield",
      "headline": "FlexTextareaField",
      "description": "Auto-sizing textarea with shared emoji, dictation, toolbar selects and native Filament validation.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flextextareafield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/flex-textarea-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/flex-textarea-motion.mp4#video",
      "name": "FlexTextareaField — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexTextareaField in the FLEX Fields playground for Filament. Auto-sizing textarea with shared emoji, dictation, toolbar selects and native Filament validation.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/flex-textarea.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT5.883S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/flex-textarea-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flextextareafield#article"
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
  src="/art/previews/flex-textarea-motion.mp4"
  poster="/art/previews/flex-textarea.webp"
  width="1920"
  height="1080"
  aria-label="FlexTextareaField — FLEX Fields demonstration"
>
  <a href="/art/previews/flex-textarea-motion.mp4">Watch the FlexTextareaField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

`FlexTextareaField` extends Filament's `Textarea`. It stores ordinary text and preserves the native form lifecycle, while adding an animated height, a live counter and optional toolbar controls.

| Property | Value |
|---|---|
| Class | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField` |
| State | `string\|null` |
| Model cast | `'message' => 'string'` |
| Component ID / playground slug | `flex-textarea` |

## Basic usage

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;

FlexTextareaField::make('message')
    ->label('Message')
    ->placeholder('Write your message…')
    ->required()
    ->minLength(3)
    ->maxLength(500)
    ->characterCounter()
    ->maxHeight('12rem');
```

Autosizing is enabled by default. Above the maximum height, the text area scrolls internally. Set `maxHeight(null)` to remove the cap, or use `autosize(false)->rows(5)` for a fixed-height field. `animatedAutosize(false)` keeps automatic resizing without its height transition. Reduced-motion preferences also disable that transition.

## Emoji and dictation

```php
FlexTextareaField::make('message')
    ->maxLength(1000)
    ->characterCounter()
    ->emojiPicker()
    ->emojiPickerLocale('pl')
    ->emojiPickerLabel('Insert emoji')
    ->speechDictation()
    ->speechDictationLanguage('pl-PL')
    ->speechDictationLabel('Dictate')
    ->footer('Write or dictate your message.');
```

Emoji uses the same picker as [Flex Text Input](/docs/flextextinput). Its dropdown anchors to the whole textarea shell, respects RTL and adapts to narrow screens. Emoji is inserted at the current text selection through a native input event. Failed picker loading retains a retry path.

Dictation uses browser speech recognition and appends the transcript to the existing text. The microphone button is present from the first server render, so initialization does not insert it later or shift the toolbar. Where speech recognition is unavailable, it remains disabled with an explanatory label. Recognition uses only the native `SpeechRecognition` / `webkitSpeechRecognition` API, without downloading a model through FLEX. Recognition starts only after clicking the button; clicking it again stops the session. Browser permission, microphone and recognition errors appear as status messages. A `network` error means the browser could not reach its recognition service; it does not establish that the device is offline. Read-only and disabled fields cannot be modified by either tool.

Custom icons accept a Blade icon name, backed enum, `Htmlable` value or closure:

```php
FlexTextareaField::make('message')
    ->emojiPicker()
    ->emojiIcon('hugeicons-smile')
    ->speechDictation()
    ->microphoneIcon('hugeicons-mic-01');
```

## Toolbar selects

`toolbarSelect()` uses the shared FLEX Select engine with the textarea's pill trigger. It supports keyboard navigation, RTL, adaptive dropdown positioning and instance-owned cleanup. The target path is relative to the textarea's form container.

Declare a companion field to persist and validate the selected value. The dropdown alone does not create a database column or register a second form field.

```php
use Filament\Forms\Components\Hidden;

[
    Hidden::make('model')
        ->default('fast')
        ->required()
        ->in(['fast', 'balanced']),

    FlexTextareaField::make('message')
        ->toolbarSelect(
            'model',
            ['fast' => 'Fast', 'balanced' => 'Balanced'],
            icon: 'hugeicons-ai-brain-01',
            placeholder: 'Model',
        ),
];
```

Multiple calls add multiple selects. Options and placeholder may be closures. Application actions that use this state must validate the form and enforce their own authorization.

## Actions

`toolbarAction()` and `toolbarActions()` accept native Filament actions; the latter also accepts action groups. `submitAction()` places an action on the right side of the toolbar. It is disabled for empty or whitespace-only text, read-only/disabled fields, or an explicitly disabled action. Its loading target belongs to that field's action.

```php
use Filament\Actions\Action;
use Filament\Notifications\Notification;

FlexTextareaField::make('message')
    ->maxLength(500)
    ->submitAction(
        Action::make('send')
            ->label('Send message')
            ->icon('hugeicons-sent')
            ->action(function (FlexTextareaField $component): void {
                validator(['message' => $component->getState()], [
                    'message' => ['required', 'string', 'min:3', 'max:500'],
                ])->validate();

                // Replace this demo notification with your authorized application action.
                Notification::make()->title('Message accepted')->success()->send();
            }),
    );
```

Native prefix/suffix APIs remain available: `prefixAction()`, `prefixActions()`, `suffixAction()` and `suffixActions()`. Actions do not automatically submit or persist the surrounding form; configure that behavior in the action.

## Configuration

| Method | Default | Purpose |
|---|---|---|
| `variant(string\|Closure)` | `primary` | `primary`, `secondary`, `soft`, `flat` |
| `size(string\|Closure)` | `md` | `sm`, `md`, `lg` |
| `rounding(string\|Closure)` | package configuration | Shared field radius token |
| `characterCounter(bool\|Closure)` | `false` | Show the live count, plus the limit when configured |
| `animatedAutosize(bool\|Closure)` | `true` | Animate height changes |
| `maxHeight(string\|Closure\|null)` | `24rem` | CSS length such as `12rem` or `calc(50vh - 2rem)`; declaration delimiters are rejected |
| `footer(string\|Closure\|null)` | `null` | Text below the shell |
| `speechDictation(bool\|Closure)` | `false` | Enable the microphone control |
| `speechDictationLanguage(string\|Closure\|null)` | browser locale | Recognition language |
| `speechDictationLabel(string\|Closure)` | `Speak` | Microphone label |
| `microphoneIcon(...)` | configured microphone icon | Replace the microphone icon |
| `emojiPicker(bool\|Closure)` | `false` | Enable the shared emoji picker |
| `emojiPickerLocale(string\|Closure\|null)` | browser locale | Picker language |
| `emojiPickerLabel(string\|Closure)` | `Insert emoji` | Picker trigger label |
| `emojiIcon(...)` | configured emoji icon | Replace the emoji icon |
| `toolbarSelect(path, options, icon: ..., placeholder: ...)` | none | Add a select linked to a sibling state path |
| `toolbarAction(Action\|Closure)` | none | Add a prefix action |
| `toolbarActions(array)` | none | Add prefix actions or action groups |
| `submitAction(Action\|Closure\|null)` | none | Add a suffix action with the empty-text policy |

Inherited Filament configuration includes `autosize()`, `rows()`, `cols()`, `placeholder()`, `autocomplete()`, `required()`, `minLength()`, `maxLength()`, `rules()`, `readOnly()`, `disabled()`, `trim()` and state-binding modifiers such as `live()` or `live(onBlur: true)`.

## State and validation

The field enforces a server-side string rule in addition to configured native rules. Arrays, booleans and numeric forged state are rejected by form validation. Length, required rules and application authorization still run on the server; toolbar controls do not replace them.

Whitespace and line breaks are preserved by default. Use `trim()` to remove leading/trailing whitespace during dehydration. An optional empty value may be `null`. No JSON cast is needed for ordinary text.

The counter updates locally without waiting for a request and counts Unicode code points. Browser `maxlength` uses UTF-16 units, so emoji can consume more than one unit of the native input limit. The server remains authoritative for submitted text validation.

## Runtime and performance

The native `wire:model` binding owns text synchronization; the counter and tools do not send a duplicate state update. Autosizing reuses one measuring element per active instance and groups measurements into animation frames. Width changes and font loading trigger remeasurement; the field's own height animation does not repeatedly trigger it.

Emoji code loads on demand. Shared Select assets are requested only by instances with toolbar selects. Watchers, listeners, measurement nodes, animation frames and recognition sessions are disposed with the field. Inactive tabs and closed modals follow the package's normal activation lifecycle.

## Variants

```php
FlexTextareaField::make('notes')->variant('secondary')->size('sm');
FlexTextareaField::make('prompt')->variant('soft')->size('lg')->rounding('xl');
FlexTextareaField::make('description')->variant('flat')->autosize(false)->rows(4);
```

## Playground

Open the `flex-textarea` page in your panel's FLEX playground. Its **Code Example** tab contains counter, fixed-height, emoji/dictation, validated toolbar-select and action examples.

## CSS reference

| Class | Purpose |
|---|---|
| `fff-flex-textarea-field` / `fff-flex-textarea` | Instance wrapper |
| `fff-flex-textarea-field--{variant}` | Visual variant |
| `fff-flex-textarea__shell` | Shared outline and surface |
| `fff-flex-textarea__textarea` | Native text area |
| `fff-flex-textarea__toolbar` | Toolbar below the text |
| `fff-flex-textarea__footer` | Optional footer |
| `fff-flex-textarea__counter` | Character counter |
| `fff-flex-textarea__dictation-btn` | Microphone control |
| `fff-flex-textarea__emoji-trigger` | Emoji trigger |

Related: [Flex Text Input](/docs/flextextinput), [Voice Note Recorder](/docs/voicenoterecorderfield) and [Rich Editor](/docs/flex-rich-editor).
