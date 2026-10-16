<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Security;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class Quarantine
{
    public function copy(MediaAdapter $adapter, MediaReceipt $receipt, string $diskName): string
    {
        $stream = $adapter->read($receipt);
        try {
            return $this->write($stream, $diskName);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function copySource(MediaSource $source, string $diskName): string
    {
        $stream = $source->open();
        try {
            return $this->write($stream, $diskName);
        } finally {
            if ($source->ownsStream) {
                fclose($stream);
            }
        }
    }

    /** @param resource $stream */
    private function write(mixed $stream, string $diskName): string
    {
        $destination = 'quarantine/'.now()->format('Y/m/d').'/'.Str::uuid().'.bin';
        $target = Storage::disk($diskName);
        try {
            if ($target->writeStream($destination, $stream, ['visibility' => 'private']) === false) {
                throw new MediaFailure('quarantine_failed', 'Quarantine storage rejected the copy.');
            }

            return $destination;
        } catch (Throwable $error) {
            try {
                $target->delete($destination);
            } catch (Throwable $cleanup) {
                try {
                    report($cleanup);
                } catch (Throwable) {
                    // Preserve the original quarantine failure.
                }
            }
            throw $error;
        }
    }
}
