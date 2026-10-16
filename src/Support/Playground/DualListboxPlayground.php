<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class DualListboxPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'dual_listbox__basic' => ['read', 'write'],
            'dual_listbox__permissions' => ['users.view', 'posts.edit'],
            'dual_listbox__flat' => ['tailwind'],
            'dual_listbox__sm' => ['draft'],
            'dual_listbox__lg' => ['published'],
            'dual_listbox__disabled' => ['draft'],
            'dual_listbox__large_list' => ['item-3', 'item-21'],
            'dual_listbox__limited' => ['draft'],
            'dual_listbox__readonly' => ['published'],
            'dual_listbox__locked' => ['users.view', 'posts.edit'],
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        $statusOptions = [
            'draft' => 'Draft',
            'reviewing' => 'Reviewing',
            'published' => 'Published',
            'archived' => 'Archived',
        ];

        $techOptions = [
            'tailwind' => 'Tailwind CSS',
            'alpine' => 'Alpine.js',
            'laravel' => 'Laravel',
            'livewire' => 'Livewire',
            'react' => 'React',
            'vue' => 'Vue',
        ];

        $permissionOptions = [
            'users.view' => [
                'label' => 'View users',
                'description' => 'Browse the user directory',
            ],
            'users.create' => [
                'label' => 'Create users',
                'description' => 'Invite and register accounts',
            ],
            'users.delete' => [
                'label' => 'Delete users',
                'description' => 'Remove accounts permanently',
            ],
            'posts.view' => [
                'label' => 'View posts',
                'description' => 'Read published content',
            ],
            'posts.edit' => [
                'label' => 'Edit posts',
                'description' => 'Update existing articles',
            ],
            'posts.publish' => [
                'label' => 'Publish posts',
                'description' => 'Make content public',
            ],
            'billing.view' => [
                'label' => 'View billing',
                'description' => 'See invoices and plans',
            ],
            'billing.manage' => [
                'label' => 'Manage billing',
                'description' => 'Change subscription settings',
            ],
        ];

        return [
            Section::make('Dual Listbox')
                ->description('Search, mark and transfer multiple items. Reorder with touch, mouse or keyboard.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    DualListboxField::make('dual_listbox__permissions')
                        ->label('Permissions')
                        ->helperText('Assign capabilities. Drag selected items to reorder. On touch, hold one item, tap others with a second finger, then drag the group onto the other pane — drop on a Selected row to insert before or after it.')
                        ->options($permissionOptions)
                        ->default(['users.view', 'posts.edit'])
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            DualListboxField::make('dual_listbox__basic')
                                ->label('Access levels')
                                ->options([
                                    'read' => 'Read access',
                                    'write' => 'Write access',
                                    'delete' => 'Delete access',
                                    'admin' => 'Administration',
                                ])
                                ->default(['read', 'write']),
                            DualListboxField::make('dual_listbox__flat')
                                ->label('Flat variant')
                                ->variant('flat')
                                ->options($techOptions),
                        ]),
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            DualListboxField::make('dual_listbox__sm')
                                ->label('Small')
                                ->size('sm')
                                ->options($statusOptions),
                            DualListboxField::make('dual_listbox__lg')
                                ->label('Large')
                                ->size('lg')
                                ->options($statusOptions),
                            DualListboxField::make('dual_listbox__disabled')
                                ->label('Disabled')
                                ->disabled()
                                ->options($statusOptions),
                            DualListboxField::make('dual_listbox__locked')
                                ->label('Locked options')
                                ->helperText('Locked rows cannot move between panes in the interface. View users can still be reordered; Delete users cannot be added.')
                                ->options($permissionOptions)
                                ->disabledOptions(['users.view', 'users.delete'])
                                ->default(['users.view', 'posts.edit']),
                        ]),
                    DualListboxField::make('dual_listbox__large_list')
                        ->label('1,000 options · virtual scrolling')
                        ->options(array_combine(
                            array_map(fn (int $index): string => 'item-'.$index, range(1, 1000)),
                            array_map(fn (int $index): string => 'Catalog item '.$index, range(1, 1000)),
                        ))
                        ->virtualThreshold(100)
                        ->helperText('Only the visible rows and a small buffer are rendered. Search both panes and use End to reach the last row.')
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        DualListboxField::make('dual_listbox__limited')
                            ->label('Choose one to three')
                            ->options($statusOptions)
                            ->required()
                            ->minItems(1)
                            ->maxItems(3),
                        DualListboxField::make('dual_listbox__readonly')
                            ->label('Read only')
                            ->options($statusOptions)
                            ->readOnly(),
                    ]),
                ]),
        ];
    }
}
