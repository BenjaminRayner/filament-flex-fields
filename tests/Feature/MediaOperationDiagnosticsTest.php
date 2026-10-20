<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Events\MediaDiagnosticsReported;
use Bjanczak\FilamentFlexFields\Support\Events\MediaOperationObserved;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDiagnosticReport;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\GenerateImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Tests\Support\MediaDoctorEnvironment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('exports diagnostic codes only when explicitly requested and keeps monitor failures out of command results', function (): void {
    MediaDoctorEnvironment::configure();
    Event::fake([MediaDiagnosticsReported::class]);
    expect(Artisan::call('fff:media:doctor', ['--json' => true]))->toBe(0);
    Event::assertNotDispatched(MediaDiagnosticsReported::class);
    expect(Artisan::call('fff:media:doctor', ['--json' => true, '--emit' => true]))->toBe(0);
    Event::assertDispatchedTimes(MediaDiagnosticsReported::class, 1);
    Event::assertDispatched(MediaDiagnosticsReported::class, function ($event): bool {
        $data = $event->toArray();
        expect($data['operationally_verified'])->toBeFalse()->and($data['read_only'])->toBeTrue();
        foreach ($data['checks'] as $check) {
            expect(array_keys($check))->toBe(['code', 'status']);
        }
        expect(json_encode($data))->not->toContain('doctor-private', 'doctor-worker', 'diagnostic-private-storage');

        return true;
    });
});

it('omits arbitrary report prose and masks untrusted operation identities and reasons', function (): void {
    $report = new MediaDiagnosticReport(new MediaDoctorOptions);
    $report->add('known_failure', 'failed', 'PRIVATE CUSTOMER VALUE', 'https://private.test/?token=SECRET');
    $event = new MediaDiagnosticsReported($report);
    expect(json_encode($event->toArray()))->not->toContain('PRIVATE', 'SECRET', 'private.test');
    $id = (string) Str::uuid();
    $observed = new MediaOperationObserved('rejected', $id, 'customer@example.test', 'https://private.test', 'SECRET');
    expect($observed->toArray())->toMatchArray(['original_operation_id' => $id, 'operation_id' => null, 'journal_id' => null, 'reason' => 'other'])
        ->and(fn () => new MediaOperationObserved('SECRET', $id))->toThrow(InvalidArgumentException::class);
});

it('correlates original storage, a real queued job and its encoded conversion without exporting filenames', function (): void {
    Storage::fake('diagnostic-originals');
    Storage::fake('diagnostic-journal');
    config()->set('filament-flex-fields.media_capture.images.queue.journal_disk', 'diagnostic-journal');
    config()->set('queue.default', 'diagnostic-worker');
    config()->set('queue.connections.diagnostic-worker', ['driver' => 'database', 'retry_after' => 120]);
    config()->set('filament-flex-fields.media_capture.require_virus_scan', false);
    Bus::fake([GenerateImageConversions::class]);
    $observations = [];
    Event::listen(MediaOperationObserved::class, function (MediaOperationObserved $event) use (&$observations): void {
        $observations[] = $event->toArray();
    });
    $image = imagecreatetruecolor(16, 8);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    $queued = app(QueuedImageConversions::class)->store(MediaSource::bytes($bytes, 'PRIVATE CUSTOMER.png'),
        new MediaWrite(disk: 'diagnostic-originals', policy: new MediaPolicy), [new ImageConversion('thumb', width: 8)]);
    Bus::assertDispatchedTimes(GenerateImageConversions::class, 1);
    $job = Bus::dispatched(GenerateImageConversions::class)->first();
    $job->handle(app(QueuedImageConversions::class));
    $stages = array_column($observations, 'stage');
    expect($stages)->toContain('stored', 'conversion_queued', 'conversion_processing', 'conversion_ready');
    foreach ($observations as $item) {
        expect($item['original_operation_id'])->toBe($queued->original->operationId);
        if (str_starts_with($item['stage'], 'conversion_')) {
            expect($item['journal_id'])->toBe($queued->id);
        }
    }
    expect(json_encode($observations))->not->toContain('PRIVATE', 'diagnostic-originals', '.png', 'token');
});

it('does not turn a throwing monitoring listener into an operation failure or an invalid JSON report', function (): void {
    MediaDoctorEnvironment::configure();
    $calls = 0;
    Event::listen(MediaDiagnosticsReported::class, function () use (&$calls): void {
        $calls++;
        throw new RuntimeException('Controlled monitoring transport failure');
    });
    expect(Artisan::call('fff:media:doctor', ['--json' => true, '--emit' => true]))->toBe(0)
        ->and(json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR)['configurationValid'])->toBeTrue()
        ->and($calls)->toBe(1);
});
