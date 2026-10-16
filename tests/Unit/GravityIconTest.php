<?php

declare(strict_types=1);

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\GravityIcon;

it('maps legacy semantic names to Hugeicons for filament', function () {
    expect(GravityIcon::make('arrow-chevron-down'))->toBe('hugeicons-arrow-down-01')
        ->and(GravityIcon::make('Magnifier'))->toBe('hugeicons-search-01')
        ->and(GravityIcon::make('trash_bin'))->toBe('hugeicons-delete-02')
        ->and(GravityIcon::CirclePlusFill)->toBe('hugeicons-add-circle');
});

it('uses the installed Hugeicons provider without a Gravity dependency', function () {
    expect(class_exists(BladeHugeiconsServiceProvider::class))->toBeTrue();
});
