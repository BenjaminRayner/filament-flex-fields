<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Storage;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class DiskAdapter implements MediaAdapter
{
    public function __construct(private readonly MediaDestinations $destinations, private readonly MediaDefaults $defaults) {}

    public function write(PreparedMedia $file, MediaWrite $intent): MediaReceipt
    {
        $directory = MediaAddress::path($intent->directory, allowEmpty: true);
        $extension = pathinfo($file->file->originalName, PATHINFO_EXTENSION);
        $extension = preg_match('/^[a-zA-Z0-9]{1,12}$/D', $extension) ? '.'.strtolower($extension) : '';
        $name = MediaAddress::filename($intent->filename ?? (string) Str::uuid().$extension);
        $path = ($directory === '' ? '' : $directory.'/').$name;
        $diskName = $this->destinations->disk($intent->disk, context: ['field' => $intent->field, 'record' => $intent->record, 'kind' => $intent->kind]);
        $disk = Storage::disk($diskName);

        return Cache::lock('fff:media:destination:'.hash('sha256', $diskName.'\0'.$path), 60)->block(10, function () use ($disk, $diskName, $path, $file, $intent): MediaReceipt {
            if ($disk->exists($path)) {
                throw new MediaFailure('destination_exists', 'Media destination already exists.');
            }
            $stream = $file->open();
            try {
                if ($disk->writeStream($path, $stream, ['visibility' => 'private']) === false) {
                    throw new MediaFailure('write_failed', 'Media storage rejected the write.');
                }
            } catch (Throwable $error) {
                $failure = $error instanceof MediaFailure ? $error : new MediaFailure('write_failed', 'Unable to persist media.', $error);
                try {
                    if (! $disk->delete($path)) {
                        $failure->cleanupFailed = true;
                    }
                } catch (Throwable $cleanup) {
                    $failure->cleanupFailed = true;
                    try {
                        report($cleanup);
                    } catch (Throwable) {
                        // Keep the original storage failure.
                    }
                }
                throw $failure;
            } finally {
                fclose($stream);
            }

            return new MediaReceipt('disk', $path, $diskName, $path, $file->file, (string) $intent->operationId);
        });
    }

    public function read(MediaReceipt $receipt): mixed
    {
        MediaAddress::path($receipt->path);
        $stream = Storage::disk($receipt->disk)->readStream($receipt->path);
        if (! is_resource($stream)) {
            throw new MediaFailure('read_failed', 'Unable to read stored media.');
        }

        return $stream;
    }

    public function delete(MediaReceipt $receipt): void
    {
        MediaAddress::path($receipt->path);
        if (! Storage::disk($receipt->disk)->delete($receipt->path)) {
            throw new MediaFailure('cleanup_failed', 'Unable to remove media object.');
        }
    }

    public function commit(MediaReceipt $receipt, MediaWrite $intent): void
    {
        $visibility = ($intent->policy ?? $this->defaults->policy())->visibility;
        if (Storage::disk($receipt->disk)->setVisibility($receipt->path, $visibility) === false) {
            throw new MediaFailure('publication_failed', 'Unable to apply media visibility.');
        }
    }

    public function url(MediaReceipt $receipt, DateTimeInterface $expiresAt, string $conversion = ''): ?string
    {
        MediaAddress::path($receipt->path);
        // A conversion is not a disk path suffix; only the native Spatie adapter interprets it.
        if ($conversion !== '') {
            return null;
        }
        try {
            $disk = Storage::disk($receipt->disk);

            return method_exists($disk, 'temporaryUrl') ? $disk->temporaryUrl($receipt->path, $expiresAt) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
