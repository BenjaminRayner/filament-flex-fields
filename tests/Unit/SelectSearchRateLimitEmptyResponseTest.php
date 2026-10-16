<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

it('rejects select transport with HTTP 429 when rate limited', function (): void {
    config()->set('filament-flex-fields.select.search_rate_limit_per_minute', 1);

    $limiter = app(SelectSearchRateLimiter::class);
    RateLimiter::clear($limiter->key('country'));
    expect($limiter->attempt('country'))->toBeTrue();

    $field = SelectField::make('country')
        ->options(['pl' => 'Poland', 'de' => 'Germany'])
        ->searchable()
        ->getSearchResultsUsing(fn (): array => ['pl' => 'Poland']);

    expect(fn () => $field->getSearchResultsForJs('pol'))
        ->toThrow(TooManyRequestsHttpException::class);
    expect(fn () => $field->getSearchResultsPageForJs('pol'))
        ->toThrow(TooManyRequestsHttpException::class);

    RateLimiter::clear($limiter->key('country'));
});

it('rejects tag transport with HTTP 429 when rate limited', function (): void {
    config()->set('filament-flex-fields.select.search_rate_limit_per_minute', 1);

    $limiter = app(SelectSearchRateLimiter::class);
    RateLimiter::clear($limiter->key('tags'));
    expect($limiter->attempt('tags'))->toBeTrue();

    $field = TagsField::make('tags')
        ->suggestions(['laravel', 'php'])
        ->getSearchResultsUsing(fn (): array => ['laravel']);

    expect(fn () => $field->getTagSearchResults('la'))
        ->toThrow(TooManyRequestsHttpException::class);

    RateLimiter::clear($limiter->key('tags'));
});

it('rejects icon transport with HTTP 429 when rate limited', function (): void {
    config()->set('filament-flex-fields.select.search_rate_limit_per_minute', 1);

    $limiter = app(SelectSearchRateLimiter::class);
    RateLimiter::clear($limiter->key('icon'));
    expect($limiter->attempt('icon'))->toBeTrue();

    $field = IconPickerField::make('icon');

    expect(fn () => $field->getIconPickerSearchResults('star', 'heroicons', 2))
        ->toThrow(TooManyRequestsHttpException::class);
    expect(fn () => $field->getIconPickerSvgPreviews(['heroicon-o-star']))
        ->toThrow(TooManyRequestsHttpException::class);

    RateLimiter::clear($limiter->key('icon'));
});
