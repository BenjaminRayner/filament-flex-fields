<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Spatie;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaReceiptResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaDestinations;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class SpatieAdapter implements MediaAdapter, MediaReceiptResolver
{
    public function __construct(private readonly MediaDestinations $destinations) {}

    public function restore(MediaReceipt $receipt, MediaWrite $intent): MediaReceipt
    {
        $record = $intent->record;
        $collection = $intent->spatie !== null ? $intent->spatie->collection : 'default';
        $media = $record === null ? null : app(OwnedMedia::class)->find($record, $receipt->identifier, $collection);
        if ($media === null) {
            throw new MediaFailure('denied', 'Stored Spatie receipt does not belong to this record and collection.');
        }

        return new MediaReceipt($receipt->adapter, $receipt->identifier, $receipt->disk, $receipt->path,
            $receipt->file, $receipt->operationId, $media);
    }

    public function write(PreparedMedia $file, MediaWrite $intent): MediaReceipt
    {
        $record = $intent->record;
        if (! interface_exists(HasMedia::class) || ! $record instanceof HasMedia || ! $record->exists || $record->getKey() === null) {
            throw new MediaFailure('spatie_record_missing', 'Spatie Media OS requires a persisted HasMedia Eloquent record.');
        }
        $options = $intent->spatie ?? new SpatieOptions;
        $collection = $record->getMediaCollection($options->collection);
        $disk = $this->destinations->spatieDisk($intent->disk, $collection?->diskName ?: null, ['field' => $intent->field, 'record' => $record, 'kind' => $intent->kind]);
        $name = MediaAddress::filename($intent->filename ?? basename($file->file->originalName));
        $uuid = (string) Str::uuid();
        $adder = app(OwnedFileAdder::class);
        $adder->setSubject($record)->setFile($file->path)->preservingOriginal()->usingFileName($name)
            ->usingName($options->name ?? pathinfo($name, PATHINFO_FILENAME))
            ->withCustomProperties(array_replace_recursive($options->customProperties, ['flex_capture' => [
                'field' => $intent->field, 'kind' => $intent->kind, 'collection' => $options->collection, 'correlation_id' => $intent->operationId,
                'sha256' => $file->file->sha256, 'width' => $file->file->width, 'height' => $file->file->height,
            ]]))
            ->withManipulations($options->manipulations)
            ->withProperties($options->attributes)
            ->addCustomHeaders($options->headers)
            ->withResponsiveImagesIf($options->responsiveImages);
        if ($options->conversionsDisk !== null) {
            $adder->storingConversionsOnDisk($options->conversionsDisk);
        }
        if ($options->order !== null) {
            $adder->setOrder($options->order);
        }
        if ($options->queue !== null) {
            $adder->onQueue($options->queue);
        }
        if ($options->sanitizeFilename !== null) {
            $adder->sanitizingFileName($options->sanitizeFilename);
        }
        if ($options->configure !== null) {
            ($options->configure)($adder);
        }
        $adder->seal($file, $intent, $uuid);
        try {
            $media = $adder->toMediaCollection($options->collection, $disk);
        } catch (Throwable $error) {
            $failure = $error instanceof MediaFailure ? $error : new MediaFailure('write_failed', 'Native Spatie media persistence failed.', $error);
            try {
                // Native synchronous conversions/events can throw after the row and file exist.
                // Only this operation's UUID on this owner/collection may be compensated.
                $partial = app(OwnedMedia::class)->find($record, $uuid, $options->collection);
                if ($partial !== null && $partial->delete() === false) {
                    $failure->cleanupFailed = true;
                }
            } catch (Throwable $cleanup) {
                $failure->cleanupFailed = true;
                try {
                    report($cleanup);
                } catch (Throwable) {
                    // Preserve the original operation failure even if reporting is unavailable.
                }
            }
            throw $failure;
        }

        return new MediaReceipt('spatie', (string) $media->uuid, (string) $media->disk, $media->getPathRelativeToRoot(), $file->file, (string) $intent->operationId, $media);
    }

    public function read(MediaReceipt $receipt): mixed
    {
        $media = $this->native($receipt);
        $stream = Storage::disk((string) $media->disk)->readStream($media->getPathRelativeToRoot());
        if (! is_resource($stream)) {
            throw new MediaFailure('read_failed', 'Unable to read stored Spatie media.');
        }

        return $stream;
    }

    public function delete(MediaReceipt $receipt): void
    {
        if ($this->native($receipt)->delete() === false) {
            throw new MediaFailure('cleanup_failed', 'Unable to remove Spatie media.');
        }
    }

    public function commit(MediaReceipt $receipt, MediaWrite $intent): void
    {
        // Collection events, conversion dispatch and file publication belong to native Spatie.
    }

    public function url(MediaReceipt $receipt, DateTimeInterface $expiresAt, string $conversion = ''): ?string
    {
        $media = $this->native($receipt);
        if ($conversion !== '' && ! $media->hasGeneratedConversion($conversion)) {
            return null;
        }
        try {
            return $media->getTemporaryUrl($expiresAt, $conversion);
        } catch (Throwable) {
            return null;
        }
    }

    private function native(MediaReceipt $receipt): Media
    {
        if (! $receipt->native instanceof Media || (string) $receipt->native->uuid !== $receipt->identifier) {
            throw new MediaFailure('invalid_receipt', 'Spatie receipt has no matching native media.');
        }

        return $receipt->native;
    }
}
