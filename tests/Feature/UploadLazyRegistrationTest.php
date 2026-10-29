<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadModelSavedRegistration;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher as NativeDispatcher;
use Illuminate\Support\Facades\Schema;

class LazyUploadFieldProbe extends FlexFileUpload
{
    public int $recordReads = 0;

    public function getRecord(bool $withContainerRecord = true): Model|array|null
    {
        $this->recordReads++;

        return null;
    }
}

it('does not register or instantiate upload lifecycle on ordinary model saved events', function (): void {
    $events = app(Dispatcher::class);
    expect(app()->resolved(UploadRecordLifecycle::class))->toBeFalse()
        ->and($events->getListeners('eloquent.saved: ExampleModel'))->toBe([]);
    $events->dispatch('eloquent.saved: ExampleModel', [new class extends Model {}]);
    expect(app()->resolved(UploadRecordLifecycle::class))->toBeFalse();
});

it('registers once on demand and preserves unrelated listeners', function (): void {
    $events = app(Dispatcher::class);
    $externalCalls = 0;
    $events->listen('eloquent.saved: *', function () use (&$externalCalls): void {
        $externalCalls++;
    });
    $before = count($events->getListeners('eloquent.saved: ExampleModel'));
    $field = LazyUploadFieldProbe::make('files');
    $lifecycle = app(UploadRecordLifecycle::class);
    for ($i = 0; $i < 10; $i++) {
        $lifecycle->track($field);
    }
    expect(count($events->getListeners('eloquent.saved: ExampleModel')))->toBe($before + 1);
    $events->dispatch('eloquent.saved: ExampleModel', [new class extends Model {}]);
    expect($field->recordReads)->toBe(1)->and($externalCalls)->toBe(1);
});

it('resolves the active scope after a reset without capturing previous fields or duplicating registration', function (): void {
    $events = app(Dispatcher::class);
    $first = LazyUploadFieldProbe::make('first');
    $old = app(UploadRecordLifecycle::class);
    $old->track($first);
    $reference = WeakReference::create($old);
    unset($old);
    app()->forgetScopedInstances();
    gc_collect_cycles();
    expect($reference->get())->toBeNull();
    $events->dispatch('eloquent.saved: ExampleModel', [new class extends Model {}]);
    expect($first->recordReads)->toBe(0);
    $second = LazyUploadFieldProbe::make('second');
    app(UploadRecordLifecycle::class)->track($second);
    $events->dispatch('eloquent.saved: ExampleModel', [new class extends Model {}]);
    expect($second->recordReads)->toBe(1)->and($first->recordReads)->toBe(0)
        ->and(count($events->getListeners('eloquent.saved: ExampleModel')))->toBe(1);
});

it('registers independently on a replacement dispatcher without retaining the old dispatcher', function (): void {
    $registration = new UploadModelSavedRegistration;
    $first = new NativeDispatcher(app());
    $registration->ensureRegistered($first);
    $reference = WeakReference::create($first);
    unset($first);
    gc_collect_cycles();
    expect($reference->get())->toBeNull();
    $second = new NativeDispatcher(app());
    $registration->ensureRegistered($second);
    $registration->ensureRegistered($second);
    expect($second->getListeners('eloquent.saved: ExampleModel'))->toHaveCount(1);
});

class LazyUploadSavedRecord extends Model
{
    protected $table = 'lazy_upload_saved_records';

    protected $guarded = [];

    public $timestamps = false;
}

class LazyUploadAcceptanceProbe extends FlexFileUpload
{
    public int $accepted = 0;

    public int $cleaned = 0;

    public function getRawState(): mixed
    {
        return [];
    }

    public function syncFileEditorialMetadata(): void
    {
        $this->accepted++;
    }

    public function cleanupReplacedFilesAfterSave(): void
    {
        $this->cleaned++;
    }
}

it('accepts real Eloquent saves after commit and never after rollback', function (): void {
    Schema::create('lazy_upload_saved_records', function ($table): void {
        $table->id();
        $table->string('name');
    });
    $record = LazyUploadSavedRecord::create(['name' => 'Original']);
    $field = LazyUploadAcceptanceProbe::make('files')->model($record);
    app(UploadRecordLifecycle::class)->track($field);
    $connection = $record->getConnection();
    $connection->beginTransaction();
    $record->update(['name' => 'Rolled back']);
    expect($field->accepted)->toBe(0)->and($field->cleaned)->toBe(0);
    $connection->rollBack();
    expect($field->accepted)->toBe(0)->and($record->fresh()->name)->toBe('Original');
    $connection->beginTransaction();
    $record->update(['name' => 'Committed']);
    expect($field->accepted)->toBe(0);
    $connection->commit();
    expect($field->accepted)->toBe(1)->and($field->cleaned)->toBe(1)
        ->and($record->fresh()->name)->toBe('Committed');
});

it('does not retain fields across repeated worker scopes or multiply listeners', function (): void {
    $events = app(Dispatcher::class);
    $references = [];
    for ($i = 0; $i < 200; $i++) {
        $field = LazyUploadFieldProbe::make('files');
        $references[] = WeakReference::create($field);
        app(UploadRecordLifecycle::class)->track($field);
        $events->dispatch('eloquent.saved: ExampleModel', [new class extends Model {}]);
        expect($field->recordReads)->toBe(1);
        unset($field);
        app()->forgetScopedInstances();
    }
    gc_collect_cycles();
    expect(array_filter($references, fn ($reference) => $reference->get() !== null))->toBe([])
        ->and($events->getListeners('eloquent.saved: ExampleModel'))->toHaveCount(1);
});
