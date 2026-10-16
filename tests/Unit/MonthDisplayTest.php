<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\MonthDisplay;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMonthPicker;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeMonthLabels;
use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeSegmentHydrator;

it('formats and parses textual month labels for polish locale', function () {
    expect(DateTimeMonthLabels::format(6, 'pl_PL', MonthDisplay::Long))->toBe('czerwiec')
        ->and(DateTimeMonthLabels::format(6, 'pl_PL', MonthDisplay::Short))->toBe('cze')
        ->and(DateTimeMonthLabels::format(6, 'pl_PL', MonthDisplay::Numeric))->toBe('06')
        ->and(DateTimeMonthLabels::parse('czerwiec', 'pl_PL', MonthDisplay::Long))->toBe(6)
        ->and(DateTimeMonthLabels::parse('cze', 'pl_PL', MonthDisplay::Short))->toBe(6)
        ->and(DateTimeMonthLabels::parse('06', 'pl_PL', MonthDisplay::Numeric))->toBe(6);
});

it('hydrates month segments using month display mode', function () {
    $field = FlexMonthPicker::make('period')
        ->locale('pl_PL')
        ->monthDisplay(MonthDisplay::Long)
        ->default('2026-06');

    expect($field->getViewSegments()['single']['month'])->toBe('czerwiec');

    $dateField = FlexDateField::make('starts_on')
        ->locale('pl_PL')
        ->monthDisplay(MonthDisplay::Short)
        ->default('2026-06-15');

    expect($dateField->getViewSegments()['single']['month'])->toBe('cze')
        ->and(DateTimeSegmentHydrator::segmentMaxLength('month', MonthDisplay::Long, 'pl_PL'))->toBeGreaterThan(2);
});

it('uses the civil date display format and retains segmented month configuration', function () {
    $field = FlexDateField::make('starts_on')
        ->monthDisplay(MonthDisplay::Short)
        ->default('2026-06-15');

    expect($field->getMonthDisplay())->toBe(MonthDisplay::Short)
        ->and($field->getAlpineConfiguration()['displayFormat'])->toBe('M j, Y')
        ->and($field->getAlpineConfiguration()['initialDisplay'])->toBe('Jun 15, 2026');

    $field->monthDisplay(MonthDisplay::Long);

    expect($field->getAlpineConfiguration()['displayFormat'])->toBe('F j, Y')
        ->and($field->getAlpineConfiguration()['initialDisplay'])->toBe('June 15, 2026');

    $field->displayFormat('d/m/Y');
    expect($field->getAlpineConfiguration()['initialDisplay'])->toBe('15/06/2026');
});
