<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images\Queue;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\ReceiptCodec;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/** Disk conversions share Media OS inspection, encoder, scanner, access and integrity boundaries. */
final class QueuedImageConversions
{
    public function __construct(
        private readonly MediaOs $media,
        private readonly MediaRegistry $registry,
        private readonly MediaDefaults $defaults,
        private readonly ImageEncoder $encoder,
        private readonly ConversionJournal $journal,
        private readonly Dispatcher $bus,
    ) {}

    /** Server API for media outside Filament. Returns the stable original and journal identity.
     * @param  list<ImageConversion>  $conversions
     */
    public function store(MediaSource $source, MediaWrite $intent, array $conversions, ?ConversionQueueOptions $queue = null, ?ImageLimits $limits = null): QueuedImageBundle
    {
        $id = null;
        $original = $this->media->store($source, $intent, function (MediaReceipt $original, MediaWrite $write) use ($conversions, $queue, $limits, &$id): void {
            $id = $this->prepare($original, $write, $conversions, $queue, $limits);
        });
        if ($id === null) {
            throw new MediaFailure('conversion_journal_missing', 'Image conversion finalizer did not run.');
        }
        $this->dispatch($id, $intent->record);

        return new QueuedImageBundle($id, $original);
    }

    /** Prepare only; FLEX dispatches after proving the saved record reference.
     * @param  list<ImageConversion>  $conversions
     */
    public function prepare(MediaReceipt $original, MediaWrite $intent, array $conversions, ?ConversionQueueOptions $queue = null, ?ImageLimits $limits = null): string
    {
        $queue ??= new ConversionQueueOptions;
        $queue = new ConversionQueueOptions($queue->connectionName(), $queue->queue, $queue->tries, $queue->timeout);
        $limits ??= ImageLimits::fromConfig();
        if ($original->adapter !== 'disk' || $intent->adapter !== 'disk') {
            throw new InvalidArgumentException('Queued disk conversions require the disk adapter; Spatie uses its native queued conversions.');
        }
        if (! str_starts_with($original->file->mimeType, 'image/') || $conversions === [] || count($conversions) > $limits->variants) {
            throw new InvalidArgumentException('Queued conversions require an image and a bounded non-empty conversion plan.');
        }
        $names = [];
        $plan = [];
        foreach ($conversions as $conversion) {
            if (! $conversion instanceof ImageConversion || isset($names[$conversion->name])) {
                throw new InvalidArgumentException('Queued conversions require unique typed names.');
            }
            $names[$conversion->name] = true;
            $plan[] = [...get_object_vars($conversion), 'fit' => $conversion->fit->value, 'format' => $conversion->format->value];
        }
        $id = (string) Str::uuid();
        $this->media->authorize('convert', $intent, $original);
        $this->journal->update($id, function (?array $existing) use ($id, $original, $intent, $plan, $limits, $queue): array {
            if ($existing !== null) {
                throw new MediaFailure('conversion_journal_exists', 'Conversion journal already exists.');
            }

            return ['version' => 1, 'id' => $id, 'original' => ReceiptCodec::encode($original),
                'intent' => ['kind' => $intent->kind, 'field' => $intent->field, 'directory' => $intent->directory,
                    'metadata' => $intent->metadata, 'policy' => get_object_vars($intent->policy ?? $this->defaults->policy())],
                'record' => $this->recordIdentity($intent->record), 'plan' => $plan, 'limits' => get_object_vars($limits),
                'queue' => get_object_vars($queue), 'status' => 'awaiting_save', 'variants' => [], 'attempts' => [], 'error' => null];
        });

        return $id;
    }

    public function dispatch(string $id, ?Model $record = null): void
    {
        $connection = $record?->getConnection() ?? app('db')->connection();
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->dispatch($id, $record));

            return;
        }
        $batch = $this->journal->update($id, function (?array $state) use ($record): ?array {
            if ($state === null || in_array($state['status'], ['ready', 'cancelled', 'processing'], true) || ($state['dispatched'] ?? false)) {
                return null;
            }
            if ($record !== null) {
                $state['record'] = $this->recordIdentity($record);
            }
            $state['status'] = 'queued';

            return $state;
        });
        if ($batch === null) {
            return;
        }
        try {
            $this->bus->dispatch(new GenerateImageConversions($id, new ConversionQueueOptions(...$batch['queue'])));
            $this->journal->update($id, static function (?array $state): ?array {
                if ($state !== null) {
                    $state['dispatched'] = true;
                }

                return $state;
            });
        } catch (Throwable $error) {
            $this->failed($id, $error);
            throw $error;
        }
    }

    /** @return array{status: string, variants: array<string, MediaReceipt>, error: ?string} */
    public function snapshot(string $id, MediaReceipt $original, MediaWrite $authority): array
    {
        $state = $this->journal->read($id) ?? throw new MediaFailure('conversion_journal_missing', 'Conversion journal is unavailable.');
        if (ReceiptCodec::encode($original) !== $state['original']) {
            throw new MediaFailure('denied', 'Conversion journal belongs to a different original.');
        }
        $this->media->authorize('inspect', $authority, $original);
        $variants = [];
        foreach ($state['variants'] as $name => $receipt) {
            $variants[$name] = ReceiptCodec::decode($receipt);
        }

        return ['status' => $state['status'], 'variants' => $variants, 'error' => $state['error']];
    }

    /** Explicit administrative reconciliation of a failed/lost dispatch; never browser-provided plans. */
    public function retry(string $id, MediaReceipt $original, MediaWrite $authority): void
    {
        $this->snapshot($id, $original, $authority);
        $this->media->authorize('convert', $authority, $original);
        Cache::lock('fff:images:worker:'.$id, 30)->block(10, function () use ($id): void {
            $state = $this->journal->update($id, static function (?array $state): ?array {
                if ($state === null || ! in_array($state['status'], ['queued', 'retrying', 'failed'], true)) {
                    return null;
                }
                $state['dispatched'] = false;

                return $state;
            });
            if ($state !== null) {
                $this->dispatch($id);
            }
        });
    }

    public function process(string $id, int $timeout = 60): void
    {
        try {
            Cache::lock('fff:images:worker:'.$id, $timeout + 60)->block(10, function () use ($id): void {
                $batch = $this->journal->read($id);
                if ($batch === null || $batch['status'] === 'ready' || $batch['status'] === 'awaiting_save') {
                    return;
                }
                if ($batch['status'] === 'cancelled') {
                    $this->cancel($id);

                    return;
                }
                $original = ReceiptCodec::decode($batch['original']);
                $intent = $this->intent($batch, $original);
                $this->journal->update($id, static function (?array $state): ?array {
                    if ($state !== null && $state['status'] !== 'cancelled') {
                        $state['status'] = 'processing';
                        $state['error'] = null;
                    }

                    return $state;
                });
                $this->media->verify($original, $intent);
                $stream = $this->registry->adapter('disk')->read($original);
                try {
                    $prepared = $this->media->inspect(MediaSource::stream($stream, $original->file->originalName), $intent);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                try {
                    foreach ($batch['plan'] as $definition) {
                        $definition['fit'] = ImageFit::from($definition['fit']);
                        $definition['format'] = ImageFormat::from($definition['format']);
                        $conversion = new ImageConversion(...$definition);
                        $state = $this->journal->read($id);
                        if ($state === null || $state['status'] === 'cancelled') {
                            return;
                        }
                        $policy = $intent->policy ?? $this->defaults->policy();
                        $derivedPolicy = new MediaPolicy($policy->maxBytes, 0, [$conversion->format->mimeType()], $policy->requireScanner,
                            $policy->scanBeforeWrite, $policy->scanAfterWrite, $policy->verifyIntegrity, $policy->visibility, $policy->quarantineDisk);
                        $write = new MediaWrite(kind: 'image-conversion', disk: $conversion->disk ?? $original->disk,
                            directory: $conversion->directory ?? $intent->directory, field: $intent->field, record: $intent->record,
                            policy: $derivedPolicy, metadata: [...$intent->metadata, 'original_operation' => $original->operationId, 'conversion' => $conversion->name]);
                        $this->media->authorize('convert', $write, $original);
                        if (isset($state['variants'][$conversion->name])) {
                            $saved = ReceiptCodec::decode($state['variants'][$conversion->name]);
                            if (Storage::disk($saved->disk)->exists($saved->path)) {
                                $this->media->verify($saved, $write);
                                $this->registry->adapter('disk')->commit($saved, $write);

                                continue;
                            }
                            // Publication may have compensated a checkpoint after a failed adapter commit.
                            $this->journal->update($id, static function (?array $state) use ($conversion): ?array {
                                if ($state !== null) {
                                    unset($state['variants'][$conversion->name]);
                                }

                                return $state;
                            });
                        }
                        // A persisted reservation recovers the write/checkpoint crash window without a second object.
                        $attempt = $state['attempts'][$conversion->name] ?? null;
                        if ($attempt !== null && Storage::disk($attempt['disk'])->exists($attempt['path'])) {
                            $receipt = ReceiptCodec::decode($attempt);
                            $write = $write->identified($receipt->operationId);
                            $this->media->verify($receipt, $write);
                            $this->checkpoint($id, $conversion->name, $receipt);
                            $this->registry->adapter('disk')->commit($receipt, $write);

                            continue;
                        }
                        $encoded = $this->encoder->encode($prepared, $conversion, new ImageLimits(...$batch['limits']), $derivedPolicy);
                        try {
                            $operation = (string) Str::uuid();
                            $filename = $operation.'-'.$conversion->name.'.'.$conversion->format->value;
                            $write = new MediaWrite(kind: $write->kind, disk: $write->disk, directory: $write->directory,
                                filename: $filename, field: $write->field, record: $write->record, policy: $derivedPolicy,
                                metadata: $write->metadata, operationId: $operation);
                            $path = ($write->directory === '' ? '' : $write->directory.'/').$filename;
                            $reservation = new MediaReceipt('disk', $path, (string) $write->disk, $path, $encoded->file, $operation);
                            $this->journal->update($id, static function (?array $state) use ($conversion, $reservation): array {
                                if ($state === null || $state['status'] === 'cancelled') {
                                    throw new MediaFailure('conversion_cancelled', 'Image conversion was cancelled.');
                                }
                                $state['attempts'][$conversion->name] = ReceiptCodec::encode($reservation);

                                return $state;
                            });
                            $this->media->store(MediaSource::path($encoded->path, $encoded->file->originalName), $write,
                                fn (MediaReceipt $receipt) => $this->checkpoint($id, $conversion->name, $receipt));
                        } finally {
                            $encoded->close();
                        }
                    }
                    $this->journal->update($id, static function (?array $state): ?array {
                        if ($state !== null && $state['status'] !== 'cancelled') {
                            $state['status'] = 'ready';
                        }

                        return $state;
                    });
                } finally {
                    $prepared->close();
                }
            });
        } catch (LockTimeoutException $error) {
            throw $error;
        } catch (Throwable $error) {
            $this->failed($id, $error, false);
            throw $error;
        }
    }

    private function checkpoint(string $id, string $name, MediaReceipt $receipt): void
    {
        $this->journal->update($id, static function (?array $state) use ($name, $receipt): array {
            if ($state === null || $state['status'] === 'cancelled') {
                throw new MediaFailure('conversion_cancelled', 'Image conversion was cancelled.');
            }
            $state['variants'][$name] = ReceiptCodec::encode($receipt);

            return $state;
        });
    }

    public function failed(string $id, ?Throwable $error, bool $terminal = true): void
    {
        $this->journal->update($id, static function (?array $state) use ($error, $terminal): ?array {
            if ($state !== null && ! in_array($state['status'], ['cancelled', 'ready'], true)) {
                $state['status'] = $terminal ? 'failed' : 'retrying';
                $state['dispatched'] = false;
                $state['error'] = $error instanceof MediaFailure ? $error->reason : 'image_conversion_failed';
            }

            return $state;
        });
    }

    /** Tombstone first; writers checkpoint under the same state lock and compensate if cancelled. */
    public function cancel(string $id, ?MediaWrite $authority = null): int
    {
        $batch = $this->journal->read($id);
        if ($batch === null) {
            return 0;
        }
        $original = ReceiptCodec::decode($batch['original']);
        $intent = $authority ?? $this->intent($batch, $original);
        $this->media->authorize('delete', $intent, $original);
        $batch = $this->journal->update($id, static function (?array $state): ?array {
            if ($state !== null) {
                $state['status'] = 'cancelled';
            }

            return $state;
        });
        $members = [];
        foreach ($batch === null ? [] : [...$batch['attempts'], ...$batch['variants']] as $data) {
            $receipt = ReceiptCodec::decode($data);
            if (! Storage::disk($receipt->disk)->exists($receipt->path)) {
                continue;
            }
            $policy = $intent->policy ?? $this->defaults->policy();
            $write = new MediaWrite(kind: 'image-conversion', disk: $receipt->disk, record: $intent->record, field: $intent->field,
                policy: new MediaPolicy($policy->maxBytes, 0, [$receipt->file->mimeType], $policy->requireScanner,
                    $policy->scanBeforeWrite, $policy->scanAfterWrite, $policy->verifyIntegrity, $policy->visibility, $policy->quarantineDisk),
                operationId: $receipt->operationId);
            $this->media->authorize('delete', $write, $receipt);
            $members[] = [$receipt, $write];
        }
        $freed = 0;
        foreach ($members as [$receipt, $write]) {
            $this->media->verify($receipt, $write);
            $this->media->delete($receipt, $write);
            $freed += $receipt->file->bytes;
        }

        return $freed;
    }

    /** @return array{class: class-string<Model>, key: int|string, connection: ?string}|null */
    private function recordIdentity(?Model $record): ?array
    {
        if ($record === null || ! $record->exists) {
            return null;
        }
        $key = $record->getKey();
        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException('Queued conversions require a stable record key.');
        }

        return ['class' => $record::class, 'key' => $key, 'connection' => $record->getConnectionName()];
    }

    /** @param array<string, mixed> $batch */
    private function intent(array $batch, MediaReceipt $original): MediaWrite
    {
        $record = null;
        if ($batch['record'] !== null) {
            $class = $batch['record']['class'];
            if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
                throw new MediaFailure('conversion_record_invalid', 'Conversion record class is unavailable.');
            }
            $model = (new $class)->setConnection($batch['record']['connection']);
            $record = $model->newQuery()->find($batch['record']['key']);
            if (! $record instanceof Model) {
                throw new MediaFailure('conversion_record_missing', 'Conversion record no longer exists.');
            }
        }

        return new MediaWrite(kind: $batch['intent']['kind'], disk: $original->disk, directory: $batch['intent']['directory'],
            field: $batch['intent']['field'], record: $record, policy: new MediaPolicy(...$batch['intent']['policy']),
            metadata: $batch['intent']['metadata'], operationId: $original->operationId);
    }
}
