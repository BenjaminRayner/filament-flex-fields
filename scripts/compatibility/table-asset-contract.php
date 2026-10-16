<?php

declare(strict_types=1);

// Real Laravel/Filament formatting pipeline; no application DB, no test suite edits.
require __DIR__.'/shared-overlay-v4-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ProgressColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\RatingColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\StatusChipColumn;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;

FlexFieldStylesheetQueue::reset();
$columns = [
    [StatusChipColumn::make('status')->criticalStyles(), '<script>alert(1)</script>', 'status-chip-column'],
    [ProgressColumn::make('progress')->criticalStyles(), 60, 'progress-column'],
    [RatingColumn::make('rating')->ratingIcon('hugeicons-star')->criticalStyles(), 4, 'rating-column'],
];
foreach ($columns as [$column, $value, $component]) {
    // Rating's table wrapper owns cell assets; its public preview helper owns standalone output.
    $html = $column instanceof RatingColumn ? $column->formatRatingDisplay($value) : (string) $column->formatState($value);
    if (! str_contains($html, 'data-fff-asset-consumer="'.$component.'"') || ! str_contains($html, 'data-fff-asset-batch')) {
        throw new RuntimeException('Filament stripped the ownership marker for '.$component);
    }
    if (str_contains($html, '<script>alert(1)</script>')) {
        throw new RuntimeException('Unescaped record label');
    }
}
$first = FlexFieldStylesheetQueue::criticalStylesheets();
foreach ($columns as [$column, $value]) {
    if ($column instanceof RatingColumn) {
        $column->formatRatingDisplay($value);
    } else {
        $column->formatState($value);
    }
}
if ($first !== FlexFieldStylesheetQueue::criticalStylesheets() || count($first) !== count(array_unique($first))) {
    throw new RuntimeException('Repeated cells duplicate critical CSS');
}
echo "PASS: column formatting and standalone previews preserve consumer boundaries, escape record labels and deduplicate initial critical styles.\n";
