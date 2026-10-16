<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;

it('limits svg preview batches to the configured maximum', function () {
    $field = IconPickerField::make('icon')->sets(['heroicons']);
    $icons = array_map(
        fn (string $path): string => 'heroicon-'.basename($path, '.svg'),
        array_slice(glob(__DIR__.'/../../vendor/blade-ui-kit/blade-heroicons/resources/svg/*.svg'), 0, 80),
    );
    expect($icons)->toHaveCount(80);
    $rendered = $field->getIconPickerSvgPreviews($icons);
    expect($rendered)->toHaveCount(IconPickerField::MAX_SVG_PREVIEW_BATCH)
        ->and(array_diff(array_column($rendered, 'icon'), $icons))->toBe([]);
    expect($field->getIconPickerSvgPreviews(array_fill(0, 80, 'heroicon-o-star')))->toHaveCount(1);
});
