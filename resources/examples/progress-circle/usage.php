<?php

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle;

ProgressCircle::make()->value(69)->label('Completed')->color('primary');

ProgressCircle::make()->value(124)->max(223)->fraction('124 / 223')->label('Grade rating');

ProgressCircle::make()->variant('semicircle')->value(72)->gapAngle(24)
    ->gradientFrom('#6366f1')->gradientTo('#ec4899')->label('Upload progress');

ProgressCircle::make()->value(78)->displayValue('78%')->fraction('78 / 100')
    ->label('Completion rate')->contentLayout('left')->shell()->color('success');

ProgressCircle::make()->value(34)->heading('Bounce rate')->description('Visitors who leave after one page.')
    ->footer('Last 30 days')->shell()->size('lg')->color('danger');

ProgressCircle::make()->value(48)->paused()->pausedIcon('hugeicons-pause')->label('Paused upload');

ProgressCircle::make()->value(55)->size('xs');

ProgressCircle::make()->value(55)->size('xs')->showValue(false);

ProgressCircle::make()->value(0)->animated(false)->size('sm');

// A multi-channel gauge with local hover/focus preview, rounded arcs and a text legend.
ProgressCircle::make()->variant('breakdown')->shell()->heading('Workload by service')
    ->summaryValue('12,500 jobs')->changeLabel('+12%')
    ->segments([
        ['label' => 'API', 'value' => 6000, 'displayValue' => '6,000', 'color' => '#3b82f6'],
        ['label' => 'Uploads', 'value' => 3500, 'displayValue' => '3,500', 'color' => '#8b5cf6'],
        ['label' => 'Exports', 'value' => 2000, 'displayValue' => '2,000', 'color' => '#f59e0b'],
        ['label' => 'Webhooks', 'value' => 1000, 'displayValue' => '1,000', 'color' => '#14b8a6'],
    ]);

ProgressCircle::make()->variant('breakdown')->size('sm')->activeSegment(1)->showLegend(false)
    ->segments([['label' => 'Photos', 'value' => 32], ['label' => 'Video', 'value' => 40], ['label' => 'Documents', 'value' => 8]]);

ProgressCircle::make()->variant('breakdown')->interactive(false)
    ->segments([['label' => 'Used', 'value' => 60], ['label' => 'Free', 'value' => 40]]);
