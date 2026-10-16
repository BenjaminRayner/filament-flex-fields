<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaExtensions;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\ReceiptCodec;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageRepository;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    MediaCaptureOs::resetRuntimeState();
    Storage::fake('staged');
    Storage::fake('permanent');
    $this->repository = new StageRepository(new StageOptions(disk: 'staged', ttlSeconds: 60));
    $this->staging = new MediaStaging($this->repository, app(MediaOs::class), app(MediaRegistry::class));
    $this->intent = new MediaWrite(disk: 'permanent', field: 'recording');
});

it('keeps staged bytes private and commits only once across repeated submits', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('recorded audio'), $this->intent, 'tenant:42:user:7');
    expect(Storage::disk('permanent')->allFiles())->toBe([])
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeTrue();
    $first = $this->staging->commit($lease->id, 'tenant:42:user:7', $this->intent);
    $second = $this->staging->commit($lease->id, 'tenant:42:user:7', $this->intent);
    expect($first->identifier)->toBe($second->identifier)
        ->and(Storage::disk('permanent')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('permanent')->get($first->path))->toBe('recorded audio')
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeFalse();
});

it('rejects other owners and destinations without destroying a retryable file', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('pending'), $this->intent, 'owner');
    expect(fn () => $this->staging->commit($lease->id, 'stranger', $this->intent))->toThrow(MediaFailure::class)
        ->and(fn () => $this->staging->commit($lease->id, 'owner', new MediaWrite(disk: 'permanent', field: 'other')))->toThrow(MediaFailure::class)
        ->and(fn () => $this->staging->abandon($lease->id, 'stranger'))->toThrow(MediaFailure::class)
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeTrue()
        ->and(Storage::disk('permanent')->allFiles())->toBe([]);
});

it('abandon removes temporary bytes but cannot delete committed media', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('pending'), $this->intent, 'owner');
    $this->staging->abandon($lease->id, 'owner');
    expect(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeFalse()
        ->and(fn () => $this->staging->commit($lease->id, 'owner', $this->intent))->toThrow(MediaFailure::class);
    $committed = $this->staging->stage(MediaSource::bytes('permanent'), $this->intent, 'owner');
    $receipt = $this->staging->commit($committed->id, 'owner', $this->intent);
    $this->staging->abandon($committed->id, 'owner');
    expect(Storage::disk('permanent')->get($receipt->path))->toBe('permanent');
});

it('prunes expired leases and committed receipt metadata without permanent media deletion', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('expired'), $this->intent, 'owner');
    $data = $this->repository->load($lease->id);
    $data['expires'] = time() - 1;
    $this->repository->save($lease->id, $data);
    expect(fn () => $this->staging->commit($lease->id, 'owner', $this->intent))->toThrow(MediaFailure::class)
        ->and($this->staging->prune(dryRun: true)['removed'])->toBe(1)
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeTrue();
    expect($this->staging->prune()['removed'])->toBe(1)
        ->and(Storage::disk('staged')->allFiles())->toBe([]);
    $committed = $this->staging->stage(MediaSource::bytes('permanent'), $this->intent, 'owner');
    $receipt = $this->staging->commit($committed->id, 'owner', $this->intent);
    $data = $this->repository->load($committed->id);
    $data['expires'] = time() - 1;
    $this->repository->save($committed->id, $data);
    expect($this->staging->prune()['removed'])->toBe(1)
        ->and(Storage::disk('permanent')->get($receipt->path))->toBe('permanent');
});

it('retains an ambiguous interrupted promotion for reconciliation instead of duplicating it', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('pending'), $this->intent, 'owner');
    $data = $this->repository->load($lease->id);
    $data['state'] = 'promoting';
    $data['expires'] = time() - 1;
    $this->repository->save($lease->id, $data);
    expect($this->staging->prune()['uncertain'])->toBe(1)
        ->and(fn () => $this->staging->abandon($lease->id, 'owner'))->toThrow(MediaFailure::class)
        ->and(fn () => $this->staging->commit($lease->id, 'owner', $this->intent))->toThrow(MediaFailure::class)
        ->and(Storage::disk('permanent')->allFiles())->toBe([]);
});

it('runs non-idempotent processors once across staging and commit', function (): void {
    $calls = 0;
    MediaExtensions::processor('append', function () use (&$calls): MediaProcessor {
        return new class($calls) implements MediaProcessor
        {
            public function __construct(public int &$calls) {}

            public function process(PreparedMedia $file, MediaWrite $intent): PreparedMedia
            {
                $this->calls++;

                return PreparedMedia::prepare(MediaSource::bytes(file_get_contents($file->path).'!'), new MediaPolicy);
            }
        };
    });
    $intent = new MediaWrite(disk: 'permanent', processors: ['append']);
    $lease = $this->staging->stage(MediaSource::bytes('original'), $intent, 'owner');
    $receipt = $this->staging->commit($lease->id, 'owner', $intent);
    expect(Storage::disk('permanent')->get($receipt->path))->toBe('original!')->and($calls)->toBe(1);
});

it('retains failed uploads for retry after a scanner outage', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('pending'), $this->intent, 'owner');
    MediaCaptureOs::registerVirusScanCallback(fn (): bool => false);
    expect(fn () => $this->staging->commit($lease->id, 'owner', $this->intent))->toThrow(MediaFailure::class)
        ->and($this->repository->load($lease->id)['state'])->toBe('pending');
    MediaCaptureOs::registerVirusScanCallback(fn (): bool => true);
    $receipt = $this->staging->commit($lease->id, 'owner', $this->intent);
    expect(Storage::disk('permanent')->get($receipt->path))->toBe('pending');
});

it('does not starve expired leases behind active entries when the deletion budget is small', function (): void {
    $repository = new StageRepository(new StageOptions(disk: 'staged', pruneBatchSize: 1));
    $staging = new MediaStaging($repository, app(MediaOs::class), app(MediaRegistry::class));
    for ($index = 0; $index < 3; $index++) {
        $staging->stage(MediaSource::bytes('active'), $this->intent, 'owner');
    }
    $expired = $staging->stage(MediaSource::bytes('expired'), $this->intent, 'owner');
    $data = $repository->load($expired->id);
    $data['expires'] = time() - 1;
    $repository->save($expired->id, $data);
    expect($staging->prune()['removed'])->toBe(1)
        ->and(Storage::disk('staged')->exists($repository->payloadPath($expired->id)))->toBeFalse()
        ->and(Storage::disk('staged')->allFiles())->toHaveCount(6);
});

it('recovers a reconciled interrupted write after lease expiry without writing a second object', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('reconciled'), $this->intent, 'owner');
    $receipt = app(MediaOs::class)->store(MediaSource::bytes('reconciled'), $this->intent->identified($lease->id));
    $data = $this->repository->load($lease->id);
    $data['state'] = 'promoting';
    $data['expires'] = time() - 1;
    $this->repository->save($lease->id, $data);
    expect(fn () => $this->staging->recover($lease->id, 'stranger', $this->intent, $receipt))->toThrow(MediaFailure::class);
    $recovered = $this->staging->recover($lease->id, 'owner', $this->intent, $receipt);
    expect($recovered->identifier)->toBe($receipt->identifier)
        ->and($this->staging->commit($lease->id, 'owner', $this->intent)->identifier)->toBe($receipt->identifier)
        ->and($this->repository->load($lease->id)['state'])->toBe('committed')
        ->and(Storage::disk('permanent')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeFalse();
});

it('rejects an unrelated recovery receipt and preserves both permanent and staged bytes', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('pending'), $this->intent, 'owner');
    $unrelated = app(MediaOs::class)->store(MediaSource::bytes('someone else'), $this->intent);
    $data = $this->repository->load($lease->id);
    $data['state'] = 'promoting';
    $this->repository->save($lease->id, $data);
    expect(fn () => $this->staging->recover($lease->id, 'owner', $this->intent, $unrelated))->toThrow(MediaFailure::class)
        ->and(Storage::disk('permanent')->get($unrelated->path))->toBe('someone else')
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeTrue()
        ->and($this->repository->load($lease->id)['state'])->toBe('promoting');
});

it('resumes a journalled promotion after expiry and rejects corrupt journal identities', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('journalled'), $this->intent, 'owner');
    $receipt = app(MediaOs::class)->store(MediaSource::bytes('journalled'), $this->intent->identified($lease->id));
    $data = $this->repository->load($lease->id);
    $data['state'] = 'promoting';
    $data['expires'] = time() - 1;
    $data['receipt'] = ReceiptCodec::encode($receipt);
    $data['receipt']['operation'] = 'unrelated';
    $this->repository->save($lease->id, $data);
    expect(fn () => $this->staging->commit($lease->id, 'owner', $this->intent))->toThrow(MediaFailure::class);
    $data['receipt']['operation'] = $lease->id;
    $this->repository->save($lease->id, $data);
    expect($this->staging->commit($lease->id, 'owner', $this->intent)->identifier)->toBe($receipt->identifier)
        ->and(Storage::disk('permanent')->allFiles())->toHaveCount(1);
});

it('reports corrupt manifests as typed failures and retains them for diagnosis', function (): void {
    $lease = $this->staging->stage(MediaSource::bytes('pending'), $this->intent, 'owner');
    Storage::disk('staged')->put($this->repository->manifestPath($lease->id), '{invalid');
    try {
        $this->staging->commit($lease->id, 'owner', $this->intent);
        test()->fail('Corrupt JSON must not be promoted.');
    } catch (MediaFailure $error) {
        expect($error->reason)->toBe('stage_corrupt');
    }
    expect($this->staging->prune()['uncertain'])->toBe(1)
        ->and(Storage::disk('staged')->exists($this->repository->payloadPath($lease->id)))->toBeTrue();
});
