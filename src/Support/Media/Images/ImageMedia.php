<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Events\ImageBundleStored;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/** Shared synchronous disk/custom-adapter image pipeline; Spatie owns its own native conversions. */
final class ImageMedia
{
    public function __construct(
        private readonly MediaOs $media,
        private readonly ImageEncoder $encoder,
        private readonly MediaDefaults $defaults,
        private readonly Dispatcher $events,
    ) {}

    /** @param list<ImageConversion> $conversions */
    public function store(MediaSource $source, MediaWrite $intent, array $conversions, ?ImageLimits $limits = null, ?Closure $finalize = null): ImageBundle
    {
        if ($intent->adapter === 'spatie') {
            throw new InvalidArgumentException('Configure Spatie conversions on the model; do not run a second conversion engine.');
        }
        $limits ??= ImageLimits::fromConfig();
        if (count($conversions) > $limits->variants) {
            throw new MediaFailure('image_variant_limit', 'Image conversion count exceeds its budget.');
        }
        $names = [];
        foreach ($conversions as $conversion) {
            if (! $conversion instanceof ImageConversion || isset($names[$conversion->name])) {
                throw new InvalidArgumentException('Image conversions need unique typed names.');
            }
            $names[$conversion->name] = true;
        }
        $policy = $intent->policy ?? $this->defaults->policy();
        $intent = $intent->withPolicy($policy)->identified($intent->operationId ?? (string) Str::uuid());
        $prepared = $this->media->inspect($source, $intent);
        $receipts = [];
        $intents = [];
        try {
            if (! str_starts_with($prepared->file->mimeType, 'image/')) {
                throw new MediaFailure('image_type_unsupported', 'Image conversions require inspected image bytes.');
            }
            $storedIntent = $intent->withoutProcessors();
            $original = $this->media->store(MediaSource::path($prepared->path, $prepared->file->originalName), $storedIntent,
                function (MediaReceipt $original) use ($prepared, $intent, $storedIntent, $policy, $conversions, $limits, $finalize, &$receipts, &$intents): void {
                    foreach ($conversions as $conversion) {
                        // Source allow-list controls upload eligibility; each encoder has an exact output MIME.
                        $derivedPolicy = new MediaPolicy($policy->maxBytes, 0, [$conversion->format->mimeType()],
                            $policy->requireScanner, $policy->scanBeforeWrite, $policy->scanAfterWrite, $policy->verifyIntegrity,
                            $policy->visibility, $policy->quarantineDisk);
                        $derivedIntent = new MediaWrite(adapter: $intent->adapter, kind: 'image-conversion',
                            disk: $conversion->disk ?? $original->disk,
                            directory: $conversion->directory ?? $intent->directory,
                            filename: (string) Str::uuid().'-'.$conversion->name.'.'.$conversion->format->value,
                            field: $intent->field, record: $intent->record, policy: $derivedPolicy,
                            metadata: [...$intent->metadata, 'original_operation' => $intent->operationId, 'conversion' => $conversion->name],
                            operationId: (string) Str::uuid());
                        $this->media->authorize('convert', $derivedIntent, $original);
                        $encoded = $this->encoder->encode($prepared, $conversion, $limits, $derivedPolicy);
                        try {
                            $receipts[$conversion->name] = $this->media->store(MediaSource::path($encoded->path, $encoded->file->originalName), $derivedIntent);
                            $intents[$conversion->name] = $derivedIntent;
                        } finally {
                            $encoded->close();
                        }
                    }
                    if ($finalize !== null) {
                        $finalize(new ImageBundle($original, $storedIntent, $receipts, $intents));
                    }
                });
        } catch (Throwable $error) {
            $cleanupFailed = false;
            foreach (array_reverse($receipts, true) as $name => $receipt) {
                $cleanupFailed = ! $this->media->compensate($receipt, $intents[$name]) || $cleanupFailed;
            }
            $failure = $error instanceof MediaFailure ? $error : new MediaFailure('image_conversion_failed', 'Image conversion failed.', $error);
            $failure->cleanupFailed = $failure->cleanupFailed || $cleanupFailed;
            throw $failure;
        } finally {
            $prepared->close();
        }
        $bundle = new ImageBundle($original, $storedIntent, $receipts, $intents);
        try {
            $this->events->dispatch(new ImageBundleStored($bundle));
        } catch (Throwable $error) {
            try {
                report($error);
            } catch (Throwable) { /* Reporting cannot undo committed media. */
            }
        }

        return $bundle;
    }

    public function delete(ImageBundle $bundle): void
    {
        // Reject inaccessible members before deleting any object.
        $this->media->authorize('delete', $bundle->intent, $bundle->original);
        foreach ($bundle->variants as $name => $receipt) {
            $this->media->authorize('delete', $bundle->intents[$name], $receipt);
        }
        foreach ($bundle->variants as $name => $receipt) {
            $this->media->delete($receipt, $bundle->intents[$name]);
        }
        $this->media->delete($bundle->original, $bundle->intent);
    }
}
