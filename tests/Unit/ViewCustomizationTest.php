<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Rendering\ViewCustomization;
use Illuminate\Events\Dispatcher;

it('observes late listener registration and removal without running a callback', function (string $kind, bool $wildcard): void {
    $events = new Dispatcher(app());
    $view = 'filament-flex-fields::components.text-input.control';
    $other = 'filament-flex-fields::components.text-input.shell';
    $event = $kind.': '.($wildcard ? 'filament-flex-fields::*' : $view);
    $calls = 0;
    $events->listen('irrelevant:*', static function (): void {});
    expect(ViewCustomization::hasHooks($view, $events))->toBeFalse()
        ->and(ViewCustomization::hasHooks($other, $events))->toBeFalse();
    $events->listen($event, function () use (&$calls): void {
        $calls++;
    });
    expect(ViewCustomization::hasHooks($view, $events))->toBeTrue()
        ->and(ViewCustomization::hasHooks($other, $events))->toBe($wildcard)
        ->and($calls)->toBe(0);
    $events->forget($event);
    expect(ViewCustomization::hasHooks($view, $events))->toBeFalse()
        ->and(ViewCustomization::hasHooks($other, $events))->toBeFalse();
})->with([['creating', false], ['composing', false], ['creating', true], ['composing', true]]);

it('keeps custom dispatcher listener semantics', function (): void {
    $events = new class(app()) extends Dispatcher
    {
        public function hasListeners($eventName): bool
        {
            return str_starts_with($eventName, 'composing: ');
        }
    };
    expect($events->getListeners('composing: application::custom'))->toBe([])
        ->and(ViewCustomization::hasHooks('application::custom', $events))->toBeTrue();
});
