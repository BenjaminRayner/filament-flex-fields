<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Facades\FlexFields;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\TenantFieldPackState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\TenantFieldPacks;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Theme\FlexFieldsTheme;
use Illuminate\Support\Facades\Event;

it('resolves the facade from the current request scope', function (): void {
    $initial = FlexFields::theme();
    $first = app(FlexFieldsTheme::class);
    FlexFields::mergeTheme(['primary' => '#ff0000']);

    expect(FlexFields::theme()['primary'])->toBe('#ff0000')
        ->and(app(FlexFieldsTheme::class))->toBe($first);

    app()->forgetScopedInstances();

    expect(app(FlexFieldsTheme::class))->not->toBe($first)
        ->and(FlexFields::theme())->toBe($initial);
});

it('resolves activity delivery and media overrides from the current scope', function (): void {
    config()->set('filament-flex-fields.media_capture.require_virus_scan', false);
    $initial = app(ActivityDispatcher::class);
    $received = [];
    Event::listen(FieldMounted::class, function (FieldMounted $event) use (&$received): void {
        $received[] = $event->field;
    });
    MediaCaptureOs::registerVirusScanCallback(fn (string $path): bool => false);
    $initial->dispatch(new FieldMounted('one', 'select'));
    expect($received)->toBe(['one'])
        ->and(MediaCaptureOs::passesVirusScan('example.pdf'))->toBeFalse();

    app()->forgetScopedInstances();
    $next = app(ActivityDispatcher::class);
    $next->dispatch(new FieldMounted('two', 'select'));
    expect($next)->not->toBe($initial)
        ->and($received)->toBe(['one', 'two'])
        ->and(MediaCaptureOs::passesVirusScan('example.pdf'))->toBeTrue();
});

it('releases tenant field policy state across repeated worker scopes', function (): void {
    $references = [];
    for ($scope = 0; $scope < 200; $scope++) {
        expect(TenantFieldPacks::hasPack('tenant'))->toBeFalse()
            ->and(TenantFieldPacks::packFor('tenant'))->toBe([]);
        TenantFieldPacks::registerPack('tenant', ['field-'.$scope]);
        $references[] = WeakReference::create(app(TenantFieldPackState::class));
        expect(TenantFieldPacks::packFor('tenant'))->toBe(['field-'.$scope]);
        app()->forgetScopedInstances();
    }
    gc_collect_cycles();
    expect(array_filter($references, fn (WeakReference $reference): bool => $reference->get() !== null))->toBe([]);
});
