<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BubbleChoiceField;

BubbleChoiceField::make('habits')
    ->label('My habits today')
    ->options([
        'water' => ['label' => 'Water', 'description' => 'H2O'],
        'yoga' => ['label' => 'Yoga', 'description' => 'Flow'],
        'journal' => ['label' => 'Journal', 'description' => 'Notes'],
        'workout' => ['label' => 'Workout', 'description' => 'Gym'],
    ])
    ->layoutOptions([
        'size' => 160,
        'minSize' => 25,
        'gutter' => 8,
        'numCols' => 6,
        'fringeWidth' => 160,
        'yRadius' => 130,
        'xRadius' => 220,
        'cornerRadius' => 50,
        'compact' => true,
        'gravitation' => 5,
    ])
    ->arenaHeight('400px')
    ->arenaColor('#16260a')
    ->bubbleColor('#8cb76c')
    ->selectedBubbleColor('#c8f560')
    ->selectedShape('scallop')
    ->minItems(1)
    ->maxItems(4);

// Rich option: image as background or center icon
BubbleChoiceField::make('mood')
    ->options([
        'calm' => [
            'label' => 'Calm',
            'description' => 'CLM',
            'color' => '#1e3a8a',
            'selectedColor' => '#93c5fd',
            'image' => asset('images/calm.jpg'),
            'imageMode' => 'icon', // or 'background'
        ],
        'focus' => [
            'label' => 'Focus',
            'image' => asset('images/focus.jpg'),
            'imageMode' => 'background',
        ],
    ])
    ->required();

// Exact selection with a circular selected shape.
BubbleChoiceField::make('destinations')
    ->options([
        'coast' => 'Coast',
        'forest' => 'Forest',
        'mountains' => 'Mountains',
    ])
    ->selectedShape('circle')
    ->exactItems(2)
    ->maxItems(2);

// Unavailable choices are also rejected by server validation.
BubbleChoiceField::make('activities')
    ->options([
        'walk' => 'Walk',
        'cycle' => 'Cycle',
        'swim' => 'Swim',
    ])
    ->disabledOptions(['swim'])
    ->variant('outline')
    ->required();

// Display a saved selection without allowing UI edits.
BubbleChoiceField::make('habits_summary')
    ->options([
        'water' => 'Drink water',
        'read' => 'Read',
    ])
    ->readOnly()
    ->arenaHeight('320px');
