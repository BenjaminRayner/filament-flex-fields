<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

it('uses headless runtime for eligible static select fields by default', function (): void {
    $field = SelectField::make('status')
        ->options(['draft' => 'Draft'])
        ->searchable();

    expect($field->shouldUseHeadlessEngine())->toBeTrue();
});

it('includes rich html select fields in headless migration', function (): void {
    $richField = SelectField::make('plan')
        ->options([
            'pro' => [
                'label' => 'Pro',
                'description' => 'Advanced analytics',
            ],
        ])
        ->richOptions()
        ->searchable();

    expect($richField->shouldUseHeadlessEngine())->toBeTrue();
});

it('excludes native select fields from headless migration', function (): void {
    $nativeField = SelectField::make('status')
        ->options(['draft' => 'Draft'])
        ->native();

    $richField = SelectField::make('plan')
        ->options([
            'pro' => [
                'label' => 'Pro',
                'description' => 'Advanced analytics',
            ],
        ])
        ->richOptions()
        ->searchable();

    $userSelect = UserSelect::make('assignee')
        ->options([
            'jane' => [
                'label' => 'Jane Cooper',
                'description' => 'jane@example.com',
            ],
        ])
        ->searchable();

    expect($nativeField->shouldUseHeadlessEngine())->toBeFalse()
        ->and($userSelect->shouldUseHeadlessEngine())->toBeTrue()
        ->and($richField->shouldUseHeadlessEngine())->toBeTrue();
});

it('includes relationship style async search selects in headless migration', function (): void {
    $field = SelectField::make('status')
        ->searchable()
        ->getSearchResultsUsing(fn (): array => ['draft' => 'Draft'])
        ->preload();

    expect($field->hasDynamicSearchResults())->toBeTrue()
        ->and($field->shouldUseHeadlessEngine())->toBeTrue();
});

it('uses headless runtime for async search fields', function (): void {
    $field = SelectField::make('status')
        ->searchable()
        ->getSearchResultsUsing(fn (): array => ['draft' => 'Draft'])
        ->preload();

    expect($field->shouldUseHeadlessEngine())->toBeTrue();
});

it('uses enhanced renderer for native selects with search multiple or HTML regardless of call order', function () {
    foreach (['searchable', 'multiple', 'allowHtml'] as $method) {
        $first = SelectField::make('status')->{$method}()->native(true);
        $second = SelectField::make('status')->native(true)->{$method}();
        expect($first->shouldUseHeadlessEngine())->toBeTrue()
            ->and($second->shouldUseHeadlessEngine())->toBeTrue();
    }
});

it('allows native(true) for plain single selects', function (): void {
    $field = SelectField::make('status')
        ->options(['draft' => 'Draft'])
        ->native(true);

    expect($field->isNative())->toBeTrue()
        ->and($field->shouldUseHeadlessEngine())->toBeFalse();
});
