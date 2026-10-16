---
title: "Hold confirm & confirm morph"
description: "Deliberate Filament actions with press-and-hold, inline confirmation, countdown, server progress, retry and optional undo."
icon: "mouse-pointer-click"
"canonical": "https://flex-fields.bjanczak.com/docs/hold-confirm-action"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/hold-confirm.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Hold confirm & confirm morph in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/hold-confirm-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/hold-confirm-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/hold-confirm.webp"
"twitter:image:alt": "Hold confirm & confirm morph in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/hold-confirm-action#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/hold-confirm-action",
      "name": "Hold confirm & confirm morph",
      "description": "Deliberate Filament actions with press-and-hold, inline confirmation, countdown, server progress, retry and optional undo.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/hold-confirm-action#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/hold-confirm-action#article",
      "url": "https://flex-fields.bjanczak.com/docs/hold-confirm-action",
      "headline": "Hold confirm & confirm morph",
      "description": "Deliberate Filament actions with press-and-hold, inline confirmation, countdown, server progress, retry and optional undo.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/hold-confirm-action#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/hold-confirm-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/hold-confirm-motion.mp4#video",
      "name": "Hold confirm & confirm morph — FLEX Fields demonstration",
      "description": "A recorded demonstration of Hold confirm & confirm morph in the FLEX Fields playground for Filament. Deliberate Filament actions with press-and-hold, inline confirmation, countdown, server progress, retry and optional undo.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/hold-confirm.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.467S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/hold-confirm-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/hold-confirm-action#article"
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
  src="/art/previews/hold-confirm-motion.mp4"
  poster="/art/previews/hold-confirm.webp"
  width="1920"
  height="1080"
  aria-label="Hold confirm & confirm morph — FLEX Fields demonstration"
>
  <a href="/art/previews/hold-confirm-motion.mp4">Watch the Hold confirm & confirm morph demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Use **Hold Confirm** when a tap should not execute an action. Choose **Confirm Morph** when you want the question, progress and result to stay inside one compact button.

<CardGroup cols={2}>
  <Card title="Hold Confirm" icon="hand-pointer">Keep pressing until the fill completes. The button then shows the actual server result.</Card>
  <Card title="Confirm Morph" icon="arrows-left-right">Expand into a question, confirm by click, hold or countdown, then show success or Retry.</Card>
</CardGroup>

Import the FLEX action class:

```php
use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
```

## Start with a hold

```php
Action::make('apply')
    ->label('Hold to apply')
    ->color('primary')
    ->holdConfirm(1200)
    ->action(fn () => $this->applySettings());
```

Press with a mouse or touch, or focus the button and hold **Space** or **Enter**. Releasing early cancels the gesture. Moving outside the button, scrolling or resizing during a pointer hold also cancels it. **Escape** cancels confirmation before server execution.

After the gesture completes, the button shows loading until its PHP callback and after-hook finish. A successful callback shows Done; an explicit `$action->failure()` or rejected request shows an error and Retry. Only the executing confirmation instance becomes busy. An idle button runs no animation loop.

## Inline confirmation

<Tabs>
<Tab title="Click">
```php
Action::make('archive')
    ->label('Archive project')
    ->color('danger')
    ->confirmMorph('Archive this project?')
    ->confirmMorphLabels([
        'confirm' => 'Archive',
        'pending' => 'Archiving…',
        'done' => 'Archived',
    ])
    ->action(fn () => $this->archiveProject());
```
</Tab>
<Tab title="Hold">
```php
Action::make('removeAccess')
    ->label('Remove access')
    ->color('danger')
    ->holdConfirm(1000)
    ->confirmMorph('Remove this access?', 'hold')
    ->confirmMorphLabels(['confirm' => 'Hold to remove'])
    ->action(fn () => $this->removeAccess());
```

Calling `holdConfirm()` before `confirmMorph()` sets the hold duration while retaining the morph presentation. Calling `holdConfirm()` last switches back to the ordinary hold presentation.
</Tab>
<Tab title="Countdown">
```php
Action::make('publish')
    ->label('Publish changes')
    ->color('primary')
    ->confirmMorph('Publish these changes?', 'countdown')
    ->confirmMorphCountdown(3)
    ->confirmMorphLabels([
        'confirm' => 'Publish',
        'countdown' => 'Publishing in',
        'pending' => 'Publishing…',
        'done' => 'Published',
    ])
    ->action(fn () => $this->publishChanges());
```

After confirmation, the ring drains and seconds count down. **Cancel** or **Escape** stops the countdown. **Enter** executes immediately. Countdown time pauses while the document is hidden or the owning scope is inactive.
</Tab>
</Tabs>

The morph surface keeps a fixed height and animates its width to fit each state. Text and controls enter separately; success draws a checkmark, and failure offers Retry. Reduced-motion preferences use short fades instead of spring, blur and drawing effects. Logical spacing supports RTL.

## Undo is a real second operation

```php
Action::make('archive')
    ->label('Archive project')
    ->confirmMorph('Archive this project?')
    ->confirmMorphLabels([
        'done' => 'Archived',
        'undoing' => 'Restoring…',
    ])
    ->action(fn () => $this->archiveProject())
    ->confirmMorphUndo(fn () => $this->restoreProject());
```

Undo appears only when you provide a callback. It runs through the same Filament action lifecycle and waits for its own server response. Successful undo returns to the original button; a failed undo offers Retry for the undo operation. It does not automatically reverse database changes: implement and authorize the restoration in your callback. URL actions do not offer Undo.

Your undo callback can contain any application PHP logic and receives Filament's usual injected utilities. For a record action, `$record` is the current record; `$action` is the executing FLEX action. Use `$action->failure()` to report an unsuccessful restoration:

```php
->confirmMorphUndo(function ($record, Action $action): void {
    if (! $record->update(['archived' => false])) {
        $action->failure();
    }
})
```

This example assumes your model has an `archived` attribute. Replace the update with your own restoration operation. Pass `null` to `confirmMorphUndo(null)` to remove the Undo callback and button.

## Handle a failure

```php
Action::make('publish')
    ->label('Publish')
    ->confirmMorph('Publish now?')
    ->confirmMorphLabels(['error' => 'Could not publish'])
    ->action(function (Action $action): void {
        if (! $this->publishChanges()) {
            $action->failure();
        }
    });
```

Use `failure()` when your callback catches an error or returns an unsuccessful application result. Returning `false` by itself does not change Filament's action status. Retry executes the callback again; make repeated operations safe in your application. Exceptions and transport failures do not produce a success animation.

## Duration, cancellation and result visibility

| Method | Accepted values | Default | Effect |
|---|---|---|---|
| `holdConfirm($duration, $sweep)` | Integer 200–60,000 ms; `right`, `left`, `up`, `down` | `2000`, `right` | Enables ordinary hold and sets continuous hold duration. |
| `holdConfirmSweep($sweep)` | Four directions above | `right` | Sets the fill direction for either presentation's hold gesture. |
| `holdConfirmReleaseDuration($duration)` | Integer 0–5,000 ms | `200` | Rewinds an unfinished hold; zero is immediate. A new hold starts from zero. |
| `holdConfirmThemed($condition)` | Boolean | `true` | Applies the ordinary danger hold palette when color is `danger`. |
| `confirmMorph($prompt, $mode)` | Plain-text prompt or null; `press`, `hold`, `countdown` | Action label plus `?`, `press` | Enables morph. Its unset hold duration defaults to 1,000 ms. |
| `confirmMorphMode($mode)` | Three modes above | `press` | Changes the morph confirmation interaction. |
| `confirmMorphCountdown($seconds)` | Integer 1–30 | `3` | Sets countdown duration in seconds. |
| `confirmMorphTimeout($milliseconds)` | Integer 0–60,000 | `6000` | Returns an unanswered morph question to idle. Zero disables this timeout. |
| `confirmMorphResultTimeout($milliseconds)` | Integer 0–60,000 | `5000` | Returns success/error to idle for either presentation. Zero leaves the result visible. |
| `confirmMorphCancelOnOutsidePress($condition)` | Boolean | `true` | Cancels a morph question/countdown on an outside press. |
| `confirmMorphLabels($labels)` | Known keys below, plain-text values up to 240 characters | Translations | Overrides individual labels. |
| `confirmMorphUndo($callback)` | Closure or null | `null` | Provides or removes the server-side undo operation. |
| `auditReasonRequired($condition)` | Boolean | `false` | Opens a required, validated reason form before executing. |

Configuration values other than the undo callback can also be evaluated closures, using Filament's usual utility injection. Invalid modes, time budgets and label keys throw `InvalidArgumentException` instead of silently changing your configuration.

Question/result timeouts pause on pointer hover, when the document is hidden or the owner is inactive. Starting a hold suspends the unanswered-question timeout. **These timeouts never end a pending PHP request or invent a success result.**

```php
Action::make('approve')
    ->confirmMorph('Approve this request?')
    ->confirmMorphTimeout(10000)
    ->confirmMorphResultTimeout(0)
    ->confirmMorphCancelOnOutsidePress(false)
    ->action(fn () => $this->approveRequest());
```

## Every label

| Key | Default English text | Used for |
|---|---|---|
| `confirm` | Confirm | Confirmation button. |
| `cancel` | Cancel | Question/countdown cancellation. |
| `pending` | Working… | Server execution in both presentations. |
| `done` | Done | Successful server result. |
| `error` | Could not complete | Failure result. |
| `retry` | Retry | Repeats the failed confirm or undo operation. |
| `undo` | Undo | Optional restoration button. |
| `undoing` | Restoring… | Pending undo operation. |
| `undone` | Restored | Accessible announcement after successful undo. |
| `countdown` | Confirming in | Text before the animated seconds. |
| `hold` | Press and hold to confirm | Accessible label for morph hold confirmation. |
| `cancelled` | Confirmation cancelled | Accessible cancellation announcement. |

English and Polish translations are included. Use translated strings in `confirmMorphLabels()` for your own wording. Labels and prompts accept plain text, not HTML.

## Require a reason

```php
Action::make('archive')
    ->confirmMorph('Archive this project?')
    ->auditReasonRequired()
    ->action(function (array $data): void {
        $this->archiveProject($data['_fff_confirmation_reason']);
    });
```

The reason is required, must contain non-whitespace text and must be 3–2,000 characters. It is available as `_fff_confirmation_reason` in the action data. Existing action form fields remain in the form; reserve this key for the reason field. Disabling the action form with `disabledSchema()` while requiring a reason is rejected.

If an action has a native form or uses `requiresConfirmation()`, completing the inline gesture opens that native modal. The trigger returns to idle; it does not report success before the form is submitted. The modal then handles its own submission progress and validation.

<Note>
A hold or countdown prevents accidental input; it is not authorization. Keep policies, permissions, validation and transaction rules in your PHP operation. These also apply to direct or manipulated requests.
</Note>

## Appearance and integration

Use inherited `label()`, `icon()`, `color()`, `disabled()`, `authorize()` and `action()` methods. Ordinary hold preserves the Filament button size and supports `rounded('none'|'sm'|'md'|'lg'|'xl'|'full')` and `itemCard()`. Morph uses its own compact capsule geometry; its confirmation fill follows `color()`.

```php
Action::make('apply')
    ->label('Hold to apply')
    ->icon('hugeicons-tick-02')
    ->color('primary')
    ->size('lg')
    ->rounded('xl')
    ->holdConfirm(1500, 'up')
    ->holdConfirmReleaseDuration(150)
    ->action(fn () => $this->applySettings());
```

For table row actions, use the package action class in `recordActions()`. Filament injects the current record into the callback as usual. Header, schema and Item Card actions use the same confirmation API. Each rendered instance owns its assets, gestures and state; switching tabs or closing a modal disposes its transient resources.

### URL actions

```php
Action::make('continue')
    ->label('Hold to continue')
    ->holdConfirm(1000)
    ->url('/admin/projects');
```

Completing a URL action initiates navigation. It cannot verify that the destination loaded successfully and does not run a PHP action callback or Undo. Use `openUrlInNewTab()` when needed.

### Application-wide settings

```php
use Bjanczak\FilamentFlexFields\Support\Admin\HoldConfirmEnterprise;

HoldConfirmEnterprise::requiresAuditReason(true);
HoldConfirmEnterprise::bulkHoldMs(3500);
```

These helpers are request-scoped. Configure them in the request that builds your actions. `bulkHoldMs()` accepts 200–60,000 ms or null; obtain its value with `bulkHoldMs()` without an argument when defining your bulk action's duration. It does not change every action automatically.

## Events and inspection

Events bubble from the owning action element. `fff-hold-complete` means the gesture finished, **not** that PHP succeeded. `fff-confirm-state` carries `{ state, previous }`; states are `idle`, `confirming`, `countdown`, `pending`, `done`, `error`. `fff-confirm-result` carries `{ operation, success }` after confirm or undo completes. `fff-confirm-cancel` signals a cancelled confirmation.

PHP getters include `hasHoldConfirm()` (true for both presentations), `isConfirmMorph()`, `getHoldConfirmDuration()`, `getHoldConfirmSweep()`, `getHoldConfirmReleaseDuration()`, `isHoldConfirmThemed()`, `getHoldConfirmPalette()`, `getConfirmMorphMode()` and `getConfirmationConfiguration()`.

<CardGroup cols={2}>
  <Card title="Item Card" icon="rectangle-list" href="/docs/itemcard">Place confirmation actions in settings rows.</Card>
  <Card title="Asset loading" icon="layer-group" href="/docs/asset-loading">Understand per-instance lazy assets and shared dependencies.</Card>
</CardGroup>
