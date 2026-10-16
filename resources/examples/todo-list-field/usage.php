<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;

TodoListField::make('tasks')
    ->options([
        'water' => ['label' => 'Water', 'description' => 'Stay hydrated'],
        'inbox' => 'Clear inbox',
        'party' => [
            'label' => 'Launch party',
            'celebration' => 'sparkles', // per-item override
        ],
    ])
    ->allowCreate()
    ->allowDelete()
    ->deleteConfirmation()
    ->deletable('created')
    ->allowEdit()
    ->editable('created')
    ->editWithDescription()
    ->searchable()
    ->virtualizing()
    ->infiniteScroll(20) // page size; remoteLoader('loadTodoPage') for AJAX
    ->sounds()
    ->celebration('fireworks')
    ->celebrationFullscreen() // all-done viewport-covering canvas
    ->celebrationDurationMs(5500)
    ->strikethroughStyle('hand') // or 'straight'
    ->doneSettleMs(500)
    ->minDone(1)
    ->color('primary');

// Reorder + lock example
TodoListField::make('checklist')
    ->options([
        'a' => 'Pack',
        'b' => ['label' => 'Required waiver', 'locked' => true],
    ])
    ->reorderable()
    ->reorderUsing(function (array $items): bool {
        // e.g. foreach ($items as $position => $row) {
        //     Task::whereKey($row['id'])->update(['position' => $position]);
        // }
        return true; // false reverts
    })
    ->allowDelete()
    ->deletable('all');

// Sub-stack + undo toast (default on) + optional date
TodoListField::make('trip')
    ->options([
        'pack' => [
            'label' => 'Pack bag',
            'date' => '12 Sep', // optional meta
            'children' => [
                'passport' => 'Passport',
                'charger' => 'Charger',
            ],
        ],
    ])
    ->undoCompletionNotifications() // false to disable
    ->createUsing(function (array $item): array {
        // e.g. $task = Task::create(['title' => $item['label']]);
        // return [...$item, 'id' => (string) $task->id];
        return $item;
    })
    ->deleteUsing(function (array $item): bool {
        // e.g. Task::whereIn('id', [$item['id'], ...collect($item['children'] ?? [])->pluck('id')])->delete();
        return true; // false cancels
    })
    ->reorderUsing(function (array $items): bool {
        // e.g. foreach ($items as $i => $row) { Task::whereKey($row['id'])->update(['position' => $i]); }
        return true;
    })
    ->afterToggled(function (array $changed, array $items): void {
        // e.g. foreach ($changed as $row) { Task::whereKey($row['id'])->update(['done' => $row['done']]); }
    });

// Or sync the full list with Livewire: ->live()->afterStateUpdated(fn ($state) => ...)

// // Custom celebration (browser) — playground demo key: playground-burst
// window.FilamentFlexFieldsTodoList.registerCelebration('playground-burst', {
//   durationMs: 3200,
//   start({ canvas, durationMs, playSound, fullscreen, reducedMotion }) {
//     // rAF canvas loop… return { stop() {} }
//   },
// });

// Numbered pagination: the full state survives page changes.
TodoListField::make('paged_tasks')
    ->options($tasks)
    ->searchable()
    ->searchPrompt('Find a task…')
    ->paginated(8)
    ->sounds(false)
    ->celebration(false);

// Measured natural heights: virtualItemHeight is an estimate, not a fixed row size.
TodoListField::make('large_checklist')
    ->options($tasks)
    ->virtualizing()
    ->virtualItemHeight(64)
    ->infiniteScroll(20)
    ->maxItems(1000);

// Remote loader on the containing Livewire component:
// loadTodoPage(int $page, string $search): array
// Return ['items' => [...], 'hasMore' => true, 'total' => 120].
TodoListField::make('remote_tasks')
    ->options($initialTasks)
    ->searchable()
    ->virtualizing()
    ->infiniteScroll(20)
    ->remoteLoader('loadTodoPage');
