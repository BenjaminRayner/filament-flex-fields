---
title: "Todo List"
description: "Build Filament task lists with animated checkmarks, subtasks, undo, creation, editing, TanStack virtualization, pagination and configurable sounds."
icon: "list-check"
"canonical": "https://flex-fields.bjanczak.com/docs/todolistfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/todo-list-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Todo List in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/todo-list-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/todo-list-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/todo-list-field.webp"
"twitter:image:alt": "Todo List in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/todolistfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/todolistfield",
      "name": "Todo List",
      "description": "Build Filament task lists with animated checkmarks, subtasks, undo, creation, editing, TanStack virtualization, pagination and configurable sounds.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/todolistfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/todolistfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/todolistfield",
      "headline": "Todo List",
      "description": "Build Filament task lists with animated checkmarks, subtasks, undo, creation, editing, TanStack virtualization, pagination and configurable sounds.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/todolistfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/todo-list-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/todo-list-field-motion.mp4#video",
      "name": "Todo List — FLEX Fields demonstration",
      "description": "A recorded demonstration of Todo List in the FLEX Fields playground for Filament. Build Filament task lists with animated checkmarks, subtasks, undo, creation, editing, TanStack virtualization, pagination and configurable sounds.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/todo-list-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT6.817S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/todo-list-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/todolistfield#article"
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
  src="/art/previews/todo-list-field-motion.mp4"
  poster="/art/previews/todo-list-field.webp"
  width="1920"
  height="1080"
  aria-label="Todo List — FLEX Fields demonstration"
>
  <a href="/art/previews/todo-list-field-motion.mp4">Watch the Todo List demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Todo List combines a task checklist with animated completion, optional subtasks and configurable editing. Start with a few fixed tasks, or add search and measured virtualization for a longer list.

<CardGroup cols={2}>
  <Card title="Try the component" icon="play" href="https://demo.bjanczak.com/admin/flex-fields/todo-list-field">Check tasks, try celebrations and compare pagination with infinite scrolling.</Card>
  <Card title="Saving form data" icon="database" href="/docs/data-and-validation">Store the task list in a JSON column with an Eloquent array cast.</Card>
</CardGroup>

## First checklist

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;

TodoListField::make('tasks')
    ->label('Before departure')
    ->options([
        'documents' => 'Check travel documents',
        'supplies' => [
            'label' => 'Prepare supplies',
            'description' => 'Water, food and first-aid kit',
        ],
    ]);
```

The default includes animated checkmarks, hand-drawn strikethroughs, completion sounds, an undo notification and a fireworks celebration when all available tasks are complete. To start with a quiet checklist:

```php
TodoListField::make('tasks')
    ->options(['review' => 'Review the document'])
    ->sounds(false)
    ->celebration(false)
    ->undoCompletionNotifications(false);
```

## Task options

`options()` accepts an array or a closure. Array keys become stable task identifiers unless the row provides its own `id`. Use identifiers that remain consistent when the form is reopened.

| Option | Default | Purpose |
| --- | --- | --- |
| `id` | Array key | Unique string or integer identifier, normalized to a string. Maximum 255 bytes. |
| `label` | Array key | Task title. Required, nonblank, up to 500 characters. |
| `description` | `null` | Optional description, up to 2,000 characters. `desc` is also accepted in option definitions. |
| `done` | `false` | Initial completion state. |
| `disabled` / `locked` | `false` | Prevents toggling, editing, deletion and reordering. |
| `created` | `false` | Marks a task as user-created for `created` edit/delete modes. |
| `children` | `[]` | One level of child tasks. Labels and structured rows are accepted. |
| `date` | `null` | Display-only date text on a parent task, up to 128 characters. It is not a date constraint. |
| `sound` | `null` | Optional completion sound URL for this task. |
| `celebration` | `null` | Per-task effect name. `false` suppresses the effect for that task. |
| `celebrationFullscreen` | `false` | Displays that task's effect over the viewport. `celebration_fullscreen` is also accepted. |

```php
TodoListField::make('departure')
    ->options([
        'briefing' => [
            'label' => 'Safety briefing completed',
            'done' => true,
            'locked' => true,
        ],
        'packing' => [
            'label' => 'Pack the bag',
            'date' => 'Friday, 12 September',
            'children' => [
                'passport' => 'Passport',
                'charger' => 'Charger',
            ],
        ],
    ]);
```

Generated child IDs use `parent-id:child-key`. Explicit child IDs must also be unique across the whole field. Checking a parent completes its unlocked children. Changing a child recalculates the parent; locked children are never changed by a cascade. Completion limits count parent tasks, not each child separately.

## Create and edit tasks

```php
TodoListField::make('tasks')
    ->options(['review' => 'Review the proposal'])
    ->allowCreate()
    ->createLabel('Add task')
    ->createPlaceholder('Task title')
    ->createWithDescription()
    ->createDescriptionPlaceholder('Optional notes')
    ->allowEdit()
    ->editable('all')
    ->editWithDescription()
    ->editIcon('hugeicons-pencil-edit-02')
    ->allowDelete()
    ->deleteConfirmation()
    ->deletable('created');
```

| Method | Default | Behavior |
| --- | --- | --- |
| `allowCreate(bool\|Closure)` | `false` | Enables inline task creation. Calling without arguments enables it. |
| `createWithDescription(bool\|Closure)` | `false` | Adds a description input while creating a task. |
| `createLabel(string\|Closure\|null)` | Translated “Create option” | Text of the creation row. |
| `createPlaceholder(string\|Closure\|null)` | Translated title prompt | Placeholder of the title input. |
| `createDescriptionPlaceholder(string\|Closure\|null)` | Translated description prompt | Placeholder of the description input. |
| `allowEdit(bool\|Closure)` | `false` | Enables the edit action and its modal. |
| `editable(string\|Closure)` | `'all'` | `'all'`, `'created'` or `'none'`. Locked rows remain protected. |
| `editWithDescription(bool\|Closure\|null)` | Follows `createWithDescription()` | Includes a description field in the edit modal. |
| `editIcon(string\|BackedEnum\|Htmlable\|Closure\|null)` | Default pencil icon | Changes the edit action icon. |
| `allowDelete(bool\|Closure)` | `false` | Enables deletion actions. |
| `deleteConfirmation(bool\|Closure)` | `false` | Opens a confirmation modal before deleting a task and its subtasks. Cancel keeps the task unchanged. |
| `deletable(string\|Closure)` | `'created'` | `'created'`, `'all'` or `'none'`. Deleting a parent removes its child tasks too. |

The edit modal uses FLEX Text Input for its fields. The edit button immediately shows a spinner while the modal is requested. Repeated clicks during that request do not open duplicate modals. With `deleteConfirmation()`, cancellation leaves the task unchanged and `deleteUsing()` runs only after confirmation. A rejected callback keeps the task in place; locked tasks cannot be removed. Client-submitted labels are displayed as text, never interpreted as HTML. Invalid or empty creation/edit input is not committed.

## Search and long lists

<Tabs>
  <Tab title="Search">

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->searchable()
    ->searchPrompt('Find a task…');
```

Search matches titles and descriptions, including child tasks. The search box shares the FLEX Text Input presentation. Local search returns to the first page and resets the list scroll position.

  </Tab>
  <Tab title="Virtualization">

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->searchable()
    ->virtualizing()
    ->virtualItemHeight(64);
```

TanStack Virtual keeps a measured window of parent groups for large lists. Small collections keep their rows mounted to avoid unnecessary work while scrolling; child tasks count toward that limit. Wrapping titles, descriptions and children retain their natural heights. `virtualItemHeight()` is the initial estimate, not a fixed row height; the minimum estimate is 32 pixels and the default is 52. Resizing the list remeasures its contents while preserving the visible position. The scroll surface keeps its full height as rows enter or leave the rendered window.

Virtualization reduces rendered nodes. It does not reduce the number of tasks held in form state; use a remote loader when loading every task at once is inappropriate.

  </Tab>
  <Tab title="Pages">

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->searchable()
    ->paginated(8);
```

Previous/next controls display eight parent groups per page. Completion and edits remain in the full field state when changing pages. `paginated()` defaults to ten; `paginated(false)` disables pagination. A page size must be an integer between 1 and 10,000.

  </Tab>
  <Tab title="Infinite scroll">

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->searchable()
    ->virtualizing()
    ->infiniteScroll(20);
```

More parent groups become available as the list approaches its bottom. `infiniteScroll()` without a size uses twenty unless a page size is already configured. `infiniteScroll(false)` disables incremental loading. Pagination and infinite scrolling are separate navigation modes; calling `paginated()` switches off infinite scrolling.

  </Tab>
</Tabs>

## Remote loading

`remoteLoader('loadTodoPage')` calls a public method on the containing Livewire component. The method receives a one-based page number and the search text. For browser loaders, the same name can refer to a function on `window`; that function also receives `{ signal }` for cancellation.

```php
TodoListField::make('tasks')
    ->options($initialTasks)
    ->searchable()
    ->virtualizing()
    ->infiniteScroll(20)
    ->remoteLoader('loadTodoPage');
```

Return a list of task rows, or explicitly describe whether another page exists:

```php
public function loadTodoPage(int $page, string $search): array
{
    $this->authorize('viewAny', Task::class);

    $tasks = Task::query()
        ->where('owner_id', auth()->id())
        ->when($search !== '', fn ($query) => $query
            ->where('title', 'like', '%'.$search.'%'))
        ->orderBy('id')
        ->paginate(20, ['*'], 'page', max(1, $page));

    return [
        'items' => $tasks->getCollection()->map(fn (Task $task): array => [
            'id' => (string) $task->getKey(),
            'label' => $task->title,
            'done' => (bool) $task->done,
        ])->all(),
        'hasMore' => $tasks->hasMorePages(),
        'total' => $tasks->total(),
    ];
}
```

With bare-array responses, a full page means more results may exist; a shorter page ends loading. Use `hasMore` when the server knows the answer. Duplicate root IDs already loaded are retained with their local edits; duplicate IDs within a response or collisions with another task's child ID are rejected. Failed requests display a retry action. Out-of-order responses and responses belonging to a disposed field cannot replace newer results.

<Note>
Remote loading retains visited task rows and their changes in field state. Submission contains loaded tasks, not every task in the database. Apply ownership checks, filtering and authorization in your loader and persistence callbacks. A field's UI policy is not a database authorization policy.
</Note>

## Reorder tasks

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->reorderable()
    ->reorderAnimationDuration(200)
    ->persistCompletedOrder(false);
```

Drag a parent group's handle using a pointer or touch. Focus the handle and use Up/Down to reorder with a keyboard. Child tasks move with their parent. A drag preview follows the pointer, and an insertion line marks the destination. Press Escape to cancel. Locked groups keep their positions; keyboard moves skip them. `persistCompletedOrder(true)` displays completed groups after unfinished ones when reordering is off.

Reordering is disabled during search and when virtualization, pagination or infinite scrolling is enabled. This avoids presenting a partial collection as though it were a complete ordering. The default movement duration is 200 milliseconds. A rejected `reorderUsing()` callback leaves the prior order intact.

## Completion feedback

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->color('primary')
    ->size('md')
    ->strikethroughStyle('hand')
    ->doneSettleMs(500)
    ->sounds()
    ->celebration('confetti')
    ->celebrationFullscreen(false)
    ->undoCompletionNotifications();
```

| Method | Default | Purpose |
| --- | --- | --- |
| `color(string\|Closure\|null)` | `'primary'` | Filament accent color. |
| `size('sm'\|'md'\|'lg')` | `'md'` | Control spacing, text and checkbox size. |
| `strikethroughStyle(string\|Closure)` | `'hand'` | `'hand'` or `'straight'`. |
| `doneSettleMs(int\|Closure)` | `500` | Delay before muting the row after its strike animation. Negative values resolve to zero. |
| `sounds(bool\|Closure)` | `true` | Enable/disable every sound for the field. Playback remains subject to browser media policy. |
| `checkSound(string\|Closure\|null)` | Bundled check sound | Override the completion sound URL. |
| `accentSound(string\|Closure\|null)` | Bundled accent sound | Override the deletion/accent sound. |
| `createSound(string\|Closure\|null)` | Bundled create sound | Override the new-task sound. |
| `celebration(string\|Closure\|false\|null)` | `'fireworks'` | Built-in or registered effect. `false`/`null` disables complete-all effects. |
| `celebrationFullscreen(bool\|Closure)` | `false` | Complete-all effect covers the viewport. |
| `celebrationDurationMs(int\|Closure)` | `5500` | Fallback duration for effects without their own duration; minimum 500 ms. Built-ins can define their own in-box/fullscreen duration. |
| `celebrationStartSound(string\|Closure\|null)` | Bundled fireworks start | Override the fireworks launch sound. |
| `celebrationSound(string\|Closure\|null)` | Bundled fireworks burst | Override the fireworks burst sound. |
| `undoCompletionNotifications(bool\|Closure)` | `true` | Completion notification with Undo. Undo patches the affected completion states while preserving later edits. |

Built-in effects are `fireworks`, `confetti`, `sparkles`, `meteors` and `bloom`. Per-task celebrations can differ within one field. Users requesting reduced motion receive completion state changes without motion effects.

Bundled audio is resolved through the component's published resource registry. Sound files are requested on playback, not preloaded for every task. Follow [installation](/docs/installation) to publish assets after installing or updating FLEX.

### Register a custom celebration

After the Todo List module is available, register an effect through its public browser API. Return a handle with `stop()` to release every frame, listener and canvas resource you create.

```js
window.FilamentFlexFieldsTodoList.registerCelebration('my-effect', {
    durationMs: 2500,
    start({ canvas, durationMs, playSound, fullscreen, reducedMotion }) {
        // Draw your effect on canvas and own its animation resources.
        return {
            stop() {
                // Cancel your frames and release your resources.
            },
        }
    },
})
```

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->celebration('my-effect');
```

## Persistence callbacks

Tasks normally save with the surrounding form. Callbacks can instead persist individual operations. Return `null` from create/edit or `false` from delete/reorder to cancel the requested operation.

| Callback | Available arguments |
| --- | --- |
| `createUsing(?Closure)` | `$item`, `$component` |
| `editUsing(?Closure)` | `$item`, `$data`, `$children`, `$component` |
| `deleteUsing(?Closure)` | `$item`, `$children`, `$deletedIds`, `$id`, `$component` |
| `reorderUsing(?Closure)` | `$items`, `$component` |
| `afterToggled(?Closure)` | `$changed`, `$items`, `$component` |

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->allowCreate()
    ->createUsing(function (array $item): array {
        $task = auth()->user()->tasks()->create(['title' => $item['label']]);

        return [...$item, 'id' => (string) $task->getKey()];
    })
    ->allowEdit()
    ->editUsing(function (array $item, array $data): ?array {
        $task = auth()->user()->tasks()->findOrFail($item['id']);
        Gate::authorize('update', $task);
        $task->update(['title' => $data['label']]);

        return [...$item, 'label' => $task->title];
    })
    ->afterToggled(function (array $changed): void {
        foreach ($changed as $item) {
            $task = auth()->user()->tasks()->findOrFail($item['id']);
            Gate::authorize('update', $task);
            $task->update(['done' => $item['done']]);
        }
    });
```

`deleteUsing()` receives all child IDs when deleting a parent. `reorderUsing()` receives current task contents in the requested order; supplied row contents do not override current server values. Scope database queries and authorize records inside callbacks. For atomic changes across multiple records, use a database transaction.

For whole-field synchronization, the standard `live()->afterStateUpdated()` integration is also available. Avoid persisting the same change twice by enabling both approaches unintentionally.

## Validation and database storage

```php
TodoListField::make('tasks')
    ->options($tasks)
    ->allowCreate()
    ->maxItems(30)
    ->minDone(1)
    ->maxDone(10)
    ->required();
```

| Constraint | Meaning |
| --- | --- |
| `maxItems(int\|Closure\|null)` | Maximum number of parent groups, including new tasks. Default: no configured limit. |
| `minDone(int\|Closure\|null)` | Minimum completed parent groups. Default: none; `required()` requires at least one unless `minDone()` explicitly sets another value. |
| `maxDone(int\|Closure\|null)` | Maximum completed parent groups. Default: none. |
| `exactDone(int\|Closure\|null)` | Exact number of completed parent groups. It must also satisfy other configured limits. |

Limits accept zero and must resolve to integers from 0 to 10,000, or `null` to remove the limit. The overall tree is bounded to 10,000 nodes, including children. Submitted data is checked on the server for valid structure, text lengths, supported boolean values, unique IDs, permitted task IDs, locked completion states and completion/item counts. Invalid data is reported rather than silently dropped.

Store tasks in a JSON column with an array cast:

```php
// Migration
$table->json('tasks')->nullable();

// Eloquent model
protected function casts(): array
{
    return ['tasks' => 'array'];
}
```

The saved value is a list of structured task rows with string IDs, boolean completion flags and children. A legacy list of configured IDs can be hydrated as completed tasks, but new saves use structured rows. Configured lock and feedback metadata comes from server options. Validate record access in your application before saving or running persistence callbacks.

## Keyboard, mobile and appearance

- Focus a task and press Space or Enter to toggle it. Up/Down and Home/End navigate visible parent tasks.
- Focus a reorder handle and use Up/Down to move a group where reordering is enabled.
- Press Enter in the new-task title to save, or move to its description when enabled. Escape cancels creation.
- Touch layouts expose task actions without requiring a mouse hover.
- RTL uses logical spacing and reverses pagination arrow presentation. Light and dark colors follow the field's styles and Filament accent.
- Changing search, resizing or returning from another tab reuses the field's keyed rows; inactive surfaces load through FLEX's normal activation lifecycle.

Use standard Filament options such as `label()`, `helperText()`, `disabled()`, `columnSpan()`, `default()` and additional `rules()` alongside this API.
