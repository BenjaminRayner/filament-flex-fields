<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAccessPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Events\MediaStored;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaExtensions;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    MediaCaptureOs::resetRuntimeState();
    Storage::fake('media-os');
    config()->set('filament-flex-fields.media_capture.require_virus_scan', false);
});

it('stores inspected bytes and publishes one committed native event', function (): void {
    Event::fake([MediaStored::class]);
    $receipt = app(MediaOs::class)->store(MediaSource::bytes('actual content', 'letter.txt'),
        new MediaWrite(disk: 'media-os', filename: 'letter.txt'));

    expect(Storage::disk('media-os')->get($receipt->path))->toBe('actual content')
        ->and($receipt->file->bytes)->toBe(14)
        ->and($receipt->file->mimeType)->toBe('text/plain')
        ->and($receipt->file->sha256)->toBe(hash('sha256', 'actual content'));
    Event::assertDispatchedTimes(MediaStored::class, 1);
});

it('applies configured publication defaults and explicit field policies independently', function (): void {
    config()->set('filament-flex-fields.media_capture.visibility', 'public');
    $public = app(MediaOs::class)->store(MediaSource::bytes('public'), new MediaWrite(disk: 'media-os'));
    $private = app(MediaOs::class)->store(MediaSource::bytes('private'), new MediaWrite(disk: 'media-os', policy: new MediaPolicy(visibility: 'private')));
    expect(Storage::disk('media-os')->getVisibility($public->path))->toBe('public')
        ->and(Storage::disk('media-os')->getVisibility($private->path))->toBe('private');
});

it('snapshots a borrowed stream from its cursor without closing the caller resource', function (): void {
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, 'prefix-content');
    fseek($stream, 7);
    $file = PreparedMedia::prepare(MediaSource::stream($stream), new MediaPolicy);
    $path = $file->path;
    try {
        expect(is_resource($stream))->toBeTrue()
            ->and(file_get_contents($path))->toBe('content');
        rewind($stream);
        fwrite($stream, 'mutated');
        expect(file_get_contents($path))->toBe('content');
        $first = $file->open();
        $second = $file->open();
        expect(fread($first, 2))->toBe('co')->and(stream_get_contents($second))->toBe('content');
        fclose($first);
        fclose($second);
    } finally {
        $file->close();
        fclose($stream);
    }
    expect(is_file($path))->toBeFalse()
        ->and(fn () => $file->open())->toThrow(MediaFailure::class);
});

it('enforces the actual byte and inspected MIME limits before creating objects', function (): void {
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('five!'),
        new MediaWrite(disk: 'media-os', policy: new MediaPolicy(maxBytes: 4))))->toThrow(MediaFailure::class)
        ->and(fn () => app(MediaOs::class)->store(MediaSource::bytes('plain text', 'spoofed.png'),
            new MediaWrite(disk: 'media-os', policy: new MediaPolicy(mimeTypes: ['image/*']))))->toThrow(MediaFailure::class)
        ->and(Storage::disk('media-os')->allFiles())->toBe([]);
});

it('rejects unsafe destinations before writing', function (string $directory, string $filename): void {
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('data'),
        new MediaWrite(disk: 'media-os', directory: $directory, filename: $filename)))->toThrow(MediaFailure::class)
        ->and(Storage::disk('media-os')->allFiles())->toBe([]);
})->with([
    ['../outside', 'file.txt'], ['/absolute', 'file.txt'], ['safe', '../escape.txt'],
    ['safe', 'nested/file.txt'], ['safe//double', 'file.txt'], ['safe', "bad\0name"],
]);

it('refuses a collision and leaves the existing file unchanged', function (): void {
    Storage::disk('media-os')->put('uploads/same.txt', 'prior owner');
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('replacement'),
        new MediaWrite(disk: 'media-os', filename: 'same.txt')))->toThrow(MediaFailure::class)
        ->and(Storage::disk('media-os')->get('uploads/same.txt'))->toBe('prior owner');
});

it('fail closes unavailable and indeterminate scanners', function (): void {
    $intent = new MediaWrite(disk: 'media-os', policy: new MediaPolicy(requireScanner: true));
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('content'), $intent))->toThrow(MediaFailure::class);
    MediaCaptureOs::registerVirusScanCallback(fn (): null => null);
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('content'), $intent))->toThrow(MediaFailure::class)
        ->and(Storage::disk('media-os')->allFiles())->toBe([]);
});

it('scans a real local snapshot after persistence and quarantines rejected bytes', function (): void {
    Storage::fake('media-quarantine');
    $scans = 0;
    MediaCaptureOs::registerVirusScanCallback(function (string $path) use (&$scans): bool {
        expect(is_file($path))->toBeTrue()->and(file_get_contents($path))->toBe('danger');

        return ++$scans === 1;
    });
    $intent = new MediaWrite(disk: 'media-os', policy: new MediaPolicy(quarantineDisk: 'media-quarantine'));
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('danger'), $intent))->toThrow(MediaFailure::class)
        ->and($scans)->toBe(2)
        ->and(Storage::disk('media-os')->allFiles())->toBe([])
        ->and(Storage::disk('media-quarantine')->allFiles())->toHaveCount(1);
});

it('compensates failing finalizers without notifying a committed event', function (): void {
    Event::fake([MediaStored::class]);
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('content'), new MediaWrite(disk: 'media-os'),
        fn () => throw new RuntimeException('finalizer failed')))->toThrow(MediaFailure::class)
        ->and(Storage::disk('media-os')->allFiles())->toBe([]);
    Event::assertNotDispatched(MediaStored::class);
});

it('allows external adapters without adding an enum case or modifying core', function (): void {
    $adapter = new class implements MediaAdapter
    {
        public ?string $stored = null;

        public bool $committed = false;

        public function write(PreparedMedia $file, MediaWrite $intent): MediaReceipt
        {
            $this->stored = file_get_contents($file->path);

            return new MediaReceipt('custom', 'external-1', 'custom', 'external-1', $file->file, $intent->operationId);
        }

        public function read(MediaReceipt $receipt): mixed
        {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $this->stored);
            rewind($stream);

            return $stream;
        }

        public function delete(MediaReceipt $receipt): void
        {
            $this->stored = null;
        }

        public function commit(MediaReceipt $receipt, MediaWrite $intent): void
        {
            $this->committed = true;
        }

        public function url(MediaReceipt $receipt, DateTimeInterface $expiresAt, string $conversion = ''): ?string
        {
            return 'https://example.test/signed';
        }
    };
    $constructions = 0;
    MediaExtensions::adapter('custom', function () use ($adapter, &$constructions): MediaAdapter {
        $constructions++;

        return $adapter;
    });
    $intent = new MediaWrite(adapter: 'custom');
    $receipt = app(MediaOs::class)->store(MediaSource::bytes('custom content'), $intent);
    expect($adapter->committed)->toBeTrue()->and($adapter->stored)->toBe('custom content')
        ->and(app(MediaOs::class)->url($receipt, $intent, now()->addMinute()))->toBe('https://example.test/signed')
        ->and($constructions)->toBe(1);
});

it('resolves a replacement processor and measures its own output', function (): void {
    MediaExtensions::processor('uppercase', fn () => new class implements MediaProcessor
    {
        public function process(PreparedMedia $file, MediaWrite $intent): PreparedMedia
        {
            return PreparedMedia::prepare(MediaSource::bytes(strtoupper(file_get_contents($file->path))), new MediaPolicy);
        }
    });
    $receipt = app(MediaOs::class)->store(MediaSource::bytes('source'),
        new MediaWrite(disk: 'media-os', processors: ['uppercase']));
    expect(Storage::disk('media-os')->get($receipt->path))->toBe('SOURCE')
        ->and($receipt->file->sha256)->toBe(hash('sha256', 'SOURCE'));
});

it('enforces custom authorization before storage work', function (): void {
    MediaExtensions::accessPolicy('owner', fn () => new class implements MediaAccessPolicy
    {
        public function authorize(string $action, MediaWrite $intent, ?MediaReceipt $receipt): void
        {
            throw new MediaFailure('denied', 'Owner mismatch.');
        }
    });
    expect(fn () => app(MediaOs::class)->store(MediaSource::bytes('secret'), new MediaWrite(disk: 'media-os')))->toThrow(MediaFailure::class, 'Owner mismatch.')
        ->and(Storage::disk('media-os')->allFiles())->toBe([]);
});

it('does not cache a failed extension construction as successful initialization', function (): void {
    $attempts = 0;
    MediaExtensions::adapter('broken', function () use (&$attempts): MediaAdapter {
        $attempts++;
        throw new RuntimeException('construction failure');
    });
    for ($attempt = 0; $attempt < 2; $attempt++) {
        expect(fn () => app(MediaRegistry::class)->adapter('broken'))->toThrow(RuntimeException::class);
    }
    expect($attempts)->toBe(2);
});
