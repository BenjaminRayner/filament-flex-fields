<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\BarcodeCaptured;
use Bjanczak\FilamentFlexFields\Support\Events\ComponentActivity;
use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;
use Bjanczak\FilamentFlexFields\Support\Events\RetentionPruned;
use Bjanczak\FilamentFlexFields\Support\Events\SelectSearched;
use Bjanczak\FilamentFlexFields\Support\Events\UploadFailed;
use Bjanczak\FilamentFlexFields\Support\Events\UploadSucceeded;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

final class QueuedActivityProbe implements ShouldQueue
{
    public function handle(SelectSearched $event): void {}
}

it('dispatches typed field events through the normal Laravel dispatcher', function (): void {
    Event::fake([FieldMounted::class]);
    SelectField::make('country')->options(['PL' => 'Poland'])->callAfterStateHydrated();
    Event::assertDispatched(FieldMounted::class, fn (FieldMounted $event): bool => $event->field === 'country' && $event->type === 'select');
});

it('emits private search metadata once per cache miss', function (): void {
    $events = [];
    Event::listen(SelectSearched::class, function (SelectSearched $event) use (&$events): void {
        $events[] = $event;
    });
    $field = SelectField::make('country')->searchable()->getSearchResultsUsing(fn (string $search): array => ['PL' => 'Poland']);
    expect($field->getSearchResults('pol'))->toBe(['PL' => 'Poland']);
    expect($field->getSearchResults('pol'))->toBe(['PL' => 'Poland']);
    expect($events)->toHaveCount(1)
        ->and($events[0]->queryHash)->toBe(hash('sha256', 'pol'))
        ->and($events[0]->queryLength)->toBe(3)
        ->and($events[0]->source)->toBe('options')
        ->and($events[0]->toArray())->not->toHaveKey('query');
});

it('does not bypass virus scanning when activity listeners fail', function (): void {
    Event::listen(UploadFailed::class, fn () => throw new RuntimeException('monitor unavailable'));
    MediaCaptureOs::registerVirusScanCallback(fn (string $path): bool => false);
    expect(FlexFileUpload::make('file')->disk('public')->passesMediaCaptureVirusScan('bad.exe'))->toBeFalse();
    MediaCaptureOs::registerVirusScanCallback(null);
});

it('reports listener errors without breaking search and can deliver the next event', function (): void {
    $reported = [];
    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->andReturnUsing(function (Throwable $error) use (&$reported): void {
        $reported[] = $error;
    });
    app()->instance(ExceptionHandler::class, $handler);
    Event::listen(SelectSearched::class, fn () => throw new RuntimeException('monitor unavailable'));
    $field = SelectField::make('country')->searchable()->getSearchResultsUsing(fn (): array => ['PL' => 'Poland']);
    expect($field->getSearchResults('pol'))->toBe(['PL' => 'Poland']);
    expect($field->getSearchResults('pola'))->toBe(['PL' => 'Poland']);
    expect($reported)->toHaveCount(2);
});

it('preserves component results even when error reporting fails', function (): void {
    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->andThrow(new RuntimeException('reporter unavailable'));
    app()->instance(ExceptionHandler::class, $handler);
    Event::listen(FieldMounted::class, fn () => throw new RuntimeException('listener failed'));
    app(ActivityDispatcher::class)->dispatch(new FieldMounted('country', 'select'));
    expect(true)->toBeTrue();
});

it('bounds recursive delivery and clears the guard after each notification', function (): void {
    $calls = 0;
    Event::listen(FieldMounted::class, function (FieldMounted $event) use (&$calls): void {
        $calls++;
        app(ActivityDispatcher::class)->dispatch($event);
    });
    $dispatcher = app(ActivityDispatcher::class);
    $dispatcher->dispatch(new FieldMounted('country', 'select'));
    $dispatcher->dispatch(new FieldMounted('country', 'select'));
    expect($calls)->toBe(2);
});

it('supports interface listeners without a package listener registry', function (): void {
    $received = [];
    Event::listen(ComponentActivity::class, function (ComponentActivity $event) use (&$received): void {
        $received[] = $event::class;
    });
    app(ActivityDispatcher::class)->dispatch(new UploadSucceeded('photo', 'disk', 'upload', 'op-1'));
    expect($received)->toBe([UploadSucceeded::class]);
});

it('supports normal queued Laravel listeners with scalar event snapshots', function (): void {
    Queue::fake();
    Event::listen(SelectSearched::class, QueuedActivityProbe::class);
    app(ActivityDispatcher::class)->dispatch(new SelectSearched('country', hash('sha256', 'pol'), 3, 'options'));
    Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job): bool {
        $restored = unserialize(serialize($job));

        return $restored->class === QueuedActivityProbe::class
            && $restored->data[0] instanceof SelectSearched
            && $restored->data[0]->queryLength === 3;
    });
});

it('keeps activity snapshots immutable and preserves optional source metadata', function (): void {
    $events = [
        new FieldMounted('country', 'select'),
        new SelectSearched('country', hash('sha256', 'pol'), 3, 'page', true),
        new UploadSucceeded(null, 'disk', 'upload', null),
        new UploadFailed(null, 'disk', 'virus_scan', stage: 'post_persist', path: 'bad.exe'),
        new RetentionPruned('temp_captures', 100, ['one.tmp'], true),
        new BarcodeCaptured('sku', '123', null, ['field' => 'spoofed', 'custom' => 'value']),
    ];
    foreach ($events as $event) {
        expect(unserialize(serialize($event))->toArray())->toBe($event->toArray());
    }
    expect($events[1]->toArray()['has_cursor'])->toBeTrue()
        ->and($events[5]->toArray())->toMatchArray(['field' => 'sku', 'custom' => 'value', 'format' => null]);
    expect(fn () => $events[0]->field = 'changed')->toThrow(Error::class);
});

it('ships no package logging driver or enterprise configuration', function (): void {
    $config = require __DIR__.'/../../config/filament-flex-fields.php';
    expect($config)->not->toHaveKey('enterprise')
        ->not->toHaveKey('observability');
});
