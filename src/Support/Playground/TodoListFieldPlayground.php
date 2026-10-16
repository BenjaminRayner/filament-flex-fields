<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

class TodoListFieldPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        $seed = [
            'water' => ['label' => 'Water', 'description' => 'Stay hydrated'],
            'inbox' => ['label' => 'Clear inbox', 'description' => 'Zero unread'],
            'walk' => ['label' => 'Walk', 'description' => '20 minutes'],
            'ship' => ['label' => 'Ship release', 'description' => 'Tag + notes'],
        ];

        $long = [];

        for ($i = 1; $i <= 60; $i++) {
            $long['task-'.$i] = [
                'label' => 'Task '.$i,
                'description' => 'Item #'.$i,
            ];
        }

        $variableHeights = [];

        for ($i = 1; $i <= 40; $i++) {
            if ($i % 5 === 0) {
                $variableHeights['vh-'.$i] = [
                    'label' => 'Finalize the quarterly charter-ops playbook covering marina berth rotation, skipper briefings, guest handover checklists, and post-trip damage notes for fleet item '.$i,
                    'description' => 'Include provisioning lead times, fuel bunkering windows, insurance claim photo requirements, Wi‑Fi voucher inventory, and multilingual disclaimer review before Friday standup #'.$i.'.',
                ];
            } elseif ($i % 3 === 0) {
                $variableHeights['vh-'.$i] = [
                    'label' => 'Medium task '.$i.' with a wrapping title for two-line height',
                    'description' => 'One extra line of context so the row is taller than a short control.',
                ];
            } else {
                $variableHeights['vh-'.$i] = [
                    'label' => 'Short '.$i,
                    'description' => 'Item #'.$i,
                ];
            }
        }

        return [
            Section::make('Todo list field')
                ->description('Animated checklist with settle grey-out, celebrations, create/delete, search, virtualize, and pagination.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    TodoListField::make('todo_list__complete_all')
                        ->label('Complete-all fireworks (fullscreen)')
                        ->helperText('Finish every task → fullscreen fireworks · delayed settle mute')
                        ->options($seed)
                        ->celebration('fireworks')
                        ->celebrationFullscreen()
                        ->sounds()
                        ->strikethroughStyle('hand')
                        ->doneSettleMs(500),

                    Section::make('Celebration gallery')
                        ->compact()
                        ->schema([
                            TodoListField::make('todo_list__celebration_gallery')
                                ->label('Per-item effects')
                                ->helperText('One list · each row has its own in-box celebration (standard check first)')
                                ->celebration(false)
                                ->sounds()
                                ->options([
                                    'standard' => [
                                        'label' => 'Standard check',
                                        'description' => 'Check + strike only · no celebration effect',
                                    ],
                                    'fw' => [
                                        'label' => 'Launch fireworks',
                                        'description' => 'In-box fireworks',
                                        'celebration' => 'fireworks',
                                    ],
                                    'confetti' => [
                                        'label' => 'Throw confetti',
                                        'celebration' => 'confetti',
                                    ],
                                    'sparkles' => [
                                        'label' => 'Sparkle',
                                        'celebration' => 'sparkles',
                                    ],
                                    'streamers' => [
                                        'label' => 'Launch meteors',
                                        'celebration' => 'streamers',
                                    ],
                                    'bloom' => [
                                        'label' => 'Check cascade',
                                        'celebration' => 'bloom',
                                    ],
                                ]),

                            Section::make('Fullscreen & custom')
                                ->compact()
                                ->schema([
                                    View::make('filament-flex-fields::partials.playground.todo-list-custom-celebration'),
                                    Grid::make(['default' => 1, 'lg' => 2])
                                        ->extraAttributes(['class' => 'fff-playground-variants'])
                                        ->schema([
                                            TodoListField::make('todo_list__celebration_fullscreen')
                                                ->label('Fullscreen fireworks')
                                                ->helperText('celebrationFullscreen on this row · viewport canvas')
                                                ->celebration(false)
                                                ->sounds()
                                                ->options([
                                                    'fw_fs' => [
                                                        'label' => 'Launch fullscreen fireworks',
                                                        'description' => 'Check to fire · ~6s fullscreen',
                                                        'celebration' => 'fireworks',
                                                        'celebrationFullscreen' => true,
                                                    ],
                                                ]),
                                            TodoListField::make('todo_list__celebration_custom')
                                                ->label('Custom silk aurora')
                                                ->helperText('registerCelebration(playground-burst) · fullscreen')
                                                ->celebration(false)
                                                ->sounds()
                                                ->options([
                                                    'custom_fs' => [
                                                        'label' => 'Launch silk aurora',
                                                        'description' => 'Custom celebration · fullscreen',
                                                        'celebration' => 'playground-burst',
                                                        'celebrationFullscreen' => true,
                                                    ],
                                                ]),
                                        ]),
                                ]),
                        ]),

                    Section::make('Sounds · strike styles')
                        ->compact()
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])
                                ->extraAttributes(['class' => 'fff-playground-variants'])
                                ->schema([
                                    TodoListField::make('todo_list__sounds_off')
                                        ->label('Sounds off')
                                        ->options($seed)
                                        ->sounds(false)
                                        ->celebration(false),
                                    TodoListField::make('todo_list__strike_straight')
                                        ->label('Straight strike')
                                        ->options($seed)
                                        ->strikethroughStyle('straight')
                                        ->celebration(false),
                                ]),
                        ]),

                    TodoListField::make('todo_list__mutable')
                        ->label('Create · edit · delete created')
                        ->helperText('Edit opens Filament modal (FlexTextInput title + description) · reserved action gutter · X only on created')
                        ->options($seed)
                        ->allowCreate()
                        ->createWithDescription()
                        ->allowEdit()
                        ->editable('created')
                        ->editWithDescription()
                        ->editIcon(FlexIcon::Pencil)
                        ->allowDelete()
                        ->deletable('created')
                        ->maxItems(8)
                        ->sounds()
                        ->celebration('confetti'),

                    TodoListField::make('todo_list__edit_all')
                        ->label('Edit any item')
                        ->helperText('allowEdit + editable(all) · modal title + description')
                        ->options($seed)
                        ->allowEdit()
                        ->editable('all')
                        ->editWithDescription()
                        ->allowDelete()
                        ->deletable('all')
                        ->celebration(false)
                        ->sounds(false),

                    TodoListField::make('todo_list__deletable_all')
                        ->label('Delete any item')
                        ->helperText('Delete opens a confirmation modal · cancel keeps the task · includes subtasks')
                        ->options($seed)
                        ->allowCreate()
                        ->allowDelete()
                        ->deleteConfirmation()
                        ->deletable('all')
                        ->deleteUsing(fn (array $item): bool => true)
                        ->maxItems(8)
                        ->celebration(false)
                        ->sounds(),

                    TodoListField::make('todo_list__reorder_locked')
                        ->label('Reorder · locked options')
                        ->helperText('Drag handle reorders parent groups · locked/disabled rows stay put · reorderUsing echo (demo)')
                        ->options([
                            'water' => ['label' => 'Water', 'description' => 'Stay hydrated'],
                            'policy' => [
                                'label' => 'Safety briefing (locked)',
                                'description' => 'Required — cannot toggle, delete, or drag',
                                'locked' => true,
                                'done' => true,
                            ],
                            'inbox' => ['label' => 'Clear inbox', 'description' => 'Zero unread'],
                            'walk' => ['label' => 'Walk', 'description' => '20 minutes', 'disabled' => true],
                            'ship' => ['label' => 'Ship release', 'description' => 'Tag + notes'],
                        ])
                        ->reorderable()
                        ->reorderUsing(fn (array $items): bool => true)
                        ->allowDelete()
                        ->deletable('all')
                        ->celebration(false)
                        ->sounds(false),

                    TodoListField::make('todo_list__substack')
                        ->label('Sub-stack · undo toast')
                        ->helperText('Parent cascades to children · Undo restores only that completion (later edits stay) · optional date meta')
                        ->options([
                            'test' => [
                                'label' => 'test',
                                'description' => 'dddddd',
                                'date' => '12 Sep',
                                'children' => [
                                    'c1' => ['label' => 'fsdf'],
                                    'c2' => ['label' => 'dsfdfs'],
                                    'c3' => ['label' => 'dsfdsfds'],
                                    'c4' => ['label' => 'sdfsd'],
                                    'c5' => ['label' => 'fsdfds', 'done' => true],
                                ],
                            ],
                            'solo' => [
                                'label' => 'Standalone task',
                                'description' => 'No children',
                            ],
                        ])
                        ->celebration(false)
                        ->sounds(false),

                    TodoListField::make('todo_list__substack_no_undo')
                        ->label('Sub-stack · undo off')
                        ->helperText('Same cascade behavior without the completion toast')
                        ->options([
                            'pack' => [
                                'label' => 'Pack bag',
                                'children' => [
                                    'p1' => 'Passport',
                                    'p2' => 'Charger',
                                    'p3' => 'Snacks',
                                ],
                            ],
                        ])
                        ->undoCompletionNotifications(false)
                        ->celebration(false)
                        ->sounds(false),

                    TodoListField::make('todo_list__long_copy')
                        ->label('Long title + description')
                        ->helperText('Wrap / overflow stress — multi-line title, long description, strike width (no virtualize)')
                        ->options([
                            'long-wrap' => [
                                'label' => 'Finalize the quarterly charter-ops playbook covering marina berth rotation, skipper briefings, guest handover checklists, and post-trip damage notes for every yacht in the Mediterranean fleet',
                                'description' => 'Include provisioning lead times, fuel bunkering windows, insurance claim photo requirements, Wi‑Fi voucher inventory, and the exact copy that appears on the guest welcome tablet — then confirm legal review signed off the multilingual disclaimers before Friday standup.',
                            ],
                            'long-token' => [
                                'label' => 'supercalifragilisticexpialidocious-charter-ops-handover-checklist-v2-final-FINAL',
                                'description' => 'Unbroken-token stress for overflow-wrap / strike measurement on a single dense label.',
                            ],
                            'short' => [
                                'label' => 'Short control',
                                'description' => 'Baseline row next to the long ones.',
                            ],
                        ])
                        ->allowCreate()
                        ->allowDelete()
                        ->deletable('created')
                        ->celebration(false)
                        ->sounds(),

                    Section::make('Search · virtualize · pagination')
                        ->compact()
                        ->schema([
                            TodoListField::make('todo_list__variable_virtual')
                                ->label('Variable-height virtualizing')
                                ->helperText('Mixed short / medium / long rows — each row keeps its natural height (auto-measured)')
                                ->options($variableHeights)
                                ->searchable()
                                ->virtualizing()
                                ->allowCreate()
                                ->createWithDescription()
                                ->celebration(false)
                                ->sounds(),
                            TodoListField::make('todo_list__virtual')
                                ->label('Searchable + virtualizing')
                                ->helperText('Uniform short rows — same auto-height engine')
                                ->options($long)
                                ->searchable()
                                ->virtualizing()
                                ->allowCreate()
                                ->celebration(false)
                                ->sounds(),
                            TodoListField::make('todo_list__paginated')
                                ->label('Numbered pagination · 8 tasks per page')
                                ->helperText('Previous/next navigation keeps completion and edits across pages. Search returns to the first page.')
                                ->options($long)
                                ->searchable()
                                ->paginated(8)
                                ->celebration(false)
                                ->sounds(false),
                            TodoListField::make('todo_list__infinite')
                                ->label('Infinite scroll (page size 8)')
                                ->helperText('virtualizing + infiniteScroll — load more on scroll; use remoteLoader() for AJAX pages')
                                ->options($long)
                                ->searchable()
                                ->virtualizing()
                                ->infiniteScroll(8)
                                ->celebration(false)
                                ->sounds(false),
                        ]),

                    TodoListField::make('todo_list__validation')
                        ->label('Validation · minDone(1)')
                        ->helperText('required + minDone(1)')
                        ->options($seed)
                        ->minDone(1)
                        ->required()
                        ->celebration(false)
                        ->sounds(false),
                ]),
        ];
    }
}
