<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrafficSplit;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTrafficSplitForm;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableTrafficSplitForm::$formSchema = [];
});

it('renders independent owners with first paint geometry and accessible splitters', function (): void {
    TestableTrafficSplitForm::$formSchema = [TrafficSplit::make('split')->labels(['A', 'B', 'C'])->lockedSegments([0]), TrafficSplit::make('other')->segmentCount(2)];
    $html = Livewire::test(TestableTrafficSplitForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($html)->toContain('data-fff-asset-consumer="traffic-split"')->not->toContain('x-init="init()"')
        ->and($xpath->query('//*[@role="separator"]')->length)->toBe(3)
        ->and($xpath->query('//*[@role="separator"][@tabindex="0"]')->length)->toBe(2)
        ->and($xpath->query('//*[@role="separator"][@aria-valuenow="34"][@aria-disabled="true"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-traffic-segment="0"]')->item(0)->getAttribute('style'))->toBe('width: 34%');
});

it('rejects forged allocations at authoritative form validation', function (mixed $value): void {
    TestableTrafficSplitForm::$formSchema = [TrafficSplit::make('split')->required()];
    $component = Livewire::test(TestableTrafficSplitForm::class)->set('data.split', $value)->call('save');
    $component->assertHasErrors(['data.split']);
    expect($component->get('saved'))->toBeNull();
})->with([
    'wrong total' => [[40, 30, 20]], 'under minimum' => [[90, 5, 5]], 'extra item' => [[25, 25, 25, 25]],
    'negative' => [[-1, 50, 51]], 'decimal' => [[33.5, 33.5, 33]], 'boolean' => [[true, 49, 50]],
    'nested' => [[[34], 33, 33]], 'mapping' => [['a' => 34, 'b' => 33, 'c' => 33]],
    'numeric junk' => [['34x', 33, 33]], 'infinite' => [['INF', 33, 33]], 'empty' => [[]], 'null' => [null],
]);

it('stores canonical integer arrays while leaving malformed state available for validation', function (): void {
    TestableTrafficSplitForm::$formSchema = [TrafficSplit::make('split')->required()];
    $component = Livewire::test(TestableTrafficSplitForm::class)->set('data.split', ['40', '30', '30'])->call('save')->assertHasNoErrors();
    expect($component->get('saved.split'))->toBe([40, 30, 30]);
    $component->set('data.split', [40, '30bad', 30])->call('save')->assertHasErrors(['data.split']);
});

it('keeps the owner for value changes and replaces it when policy changes', function (): void {
    TestableTrafficSplitForm::$formSchema = [TrafficSplit::make('split')->lockedSegments(fn (Get $get): array => $get('lock') ? [1] : [])];
    $component = Livewire::test(TestableTrafficSplitForm::class);
    $key = static function (string $html): string {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return (new DOMXPath($document))->query('//*[@role="group"]')->item(0)->getAttribute('wire:key');
    };
    $original = $key($component->html(false));
    $component->set('data.split', [40, 30, 30]);
    expect($key($component->html(false)))->toBe($original);
    $component->set('data.lock', true);
    expect($key($component->html(false)))->not->toBe($original);
});

it('preserves allocations for URL edits and rebalances only changed repeater row counts', function (): void {
    $split = TrafficSplit::make('split')->linkedToRepeater('urls');
    TestableTrafficSplitForm::$formSchema = [Repeater::make('urls')->schema([])->afterStateUpdated($split->repeaterSyncCallback()), $split];
    $component = Livewire::test(TestableTrafficSplitForm::class, ['initial' => ['urls' => ['a' => [], 'b' => []], 'split' => [60, 40]]]);
    $component->set('data.urls', ['a' => ['url' => 'changed'], 'b' => []]);
    expect($component->get('data.split'))->toBe([60, 40]);
    $component->set('data.urls', ['a' => [], 'b' => [], 'c' => []]);
    expect($component->get('data.split'))->toBe([34, 33, 33]);
    $component->set('data.urls', ['a' => [], 'b' => []]);
    expect($component->get('data.split'))->toBe([50, 50]);
});
