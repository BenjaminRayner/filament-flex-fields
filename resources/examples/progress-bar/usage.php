<?php

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressBar;

ProgressBar::make()->label('Upload progress')->value(65)->color('success');

ProgressBar::make()->label('Preparing files')->indeterminate();

ProgressBar::make()->label('Checklist')->variant('pills')->value(45)
    ->gradientFrom('#6366f1')->gradientTo('#ec4899');

ProgressBar::make()->label('Milestones')->variant('pills')->pillCount(12)->value(70);

ProgressBar::make()->label('Delivery')->segments([
    ['label' => 'Ordered', 'description' => 'Completed', 'icon' => 'hugeicons-checkmark-circle-02'],
    ['label' => 'Shipped', 'description' => 'In transit', 'icon' => 'hugeicons-truck'],
    ['label' => 'Delivered', 'description' => 'Pending', 'icon' => 'hugeicons-home-01'],
])->activeSegment(1)->activeSegmentProgress(0.5)->color('success');

ProgressBar::make()->label('Route')->value(45)->startMarker('hugeicons-location-01')
    ->currentMarker('hugeicons-truck')->endMarker('hugeicons-home-01')->remainingTrackStyle('dashed');

ProgressBar::make()->label('Storage')->value(18)->max(20)->displayValue('18 GB / 20 GB')
    ->shell()->valueBadge()->description('Current usage')->footer('Updated today');
