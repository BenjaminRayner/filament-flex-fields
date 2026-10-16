<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Bjanczak\FilamentFlexFields\Support\Media\SignatureLegalPack;
use Bjanczak\FilamentFlexFields\Support\SignatureStorage;
use Bjanczak\FilamentFlexFields\Support\SignatureSvg;
use Closure;
use Illuminate\Database\Eloquent\Model;

/** @mixin SignatureField */
trait SignaturePersistence
{
    protected function bootSignaturePersistence(): void
    {
        $this->default(null);

        $this->dehydrateStateUsing(function (SignatureField $component, mixed $state): ?string {
            if (! is_string($state) || trim($state) === '') {
                return null;
            }

            if ($component->isStoredFileReference($state)) {
                $component->maybeSinkSignatureToSpatie(
                    SignatureStorage::resolve($state, $component->getStorageDisk()) ?? $state,
                );

                return $state;
            }

            $normalized = $component->shouldStoreToDisk()
                ? SignatureStorage::store(
                    $state,
                    $component->getStorageDirectory(),
                    $component->getStorageDisk(),
                )
                : $component->normalizeState($state);

            if (is_string($normalized)) {
                $svg = str_contains($state, '<svg')
                    ? $state
                    : (SignatureStorage::resolve($normalized, $component->getStorageDisk()) ?? $state);
                $component->maybeSinkSignatureToSpatie($svg);
            }

            return $normalized;
        });

        $this->beforeStateDehydrated(function (SignatureField $component): void {
            if (! $component->shouldTimestampSeal()) {
                return;
            }

            $metadataPath = $component->getLegalMetadataInPath();

            if (blank($metadataPath)) {
                return;
            }

            if (! SignatureLegalPack::requiresInk($component->getState())) {
                return;
            }

            $set = $component->makeSetUtility();
            $set($metadataPath, SignatureLegalPack::legalAuditSeal($component->getState()));
        });

        $this->rule(function (SignatureField $component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if ($value === null || $value === '') {
                    if ($component->isRequired()) {
                        $fail(__('validation.required', ['attribute' => $component->getLabel()]));
                    }

                    return;
                }

                if (! is_string($value)) {
                    $fail(__('filament-flex-fields::default.validation.signature.invalid'));

                    return;
                }

                // Staged / permanent file refs (e.g. Flex Forms ffstage: after dehydrate) are valid.
                if ($component->isStoredFileReference($value)) {
                    return;
                }

                if ($component->requiresLegalInk() && ! SignatureLegalPack::requiresInk($value)) {
                    $fail(__('filament-flex-fields::default.validation.signature.too_few_strokes'));

                    return;
                }

                if (! SignatureSvg::isValid($value)) {
                    $fail(__('filament-flex-fields::default.validation.signature.invalid'));

                    return;
                }

                $maxBytes = max(1, $component->getMaxSizeKb()) * 1024;

                if (SignatureSvg::byteSize($value) > $maxBytes) {
                    $fail(__('filament-flex-fields::default.validation.signature.too_large', [
                        'max' => $component->getMaxSizeKb(),
                    ]));

                    return;
                }

                if (SignatureSvg::countPaths($value) < $component->getMinStrokes()) {
                    $fail(__('filament-flex-fields::default.validation.signature.too_few_strokes'));
                }
            };
        });
    }

    public function resolveStoredSvgUsing(?Closure $callback): static
    {
        $this->resolveStoredSvgUsing = $callback;

        return $this;
    }

    public function storeToDisk(
        string|Closure $directory = 'signatures',
        string|Closure|null $disk = null,
    ): static {
        $this->storeToDiskEnabled = true;
        $this->storageDirectory = $directory;

        if ($disk !== null) {
            $this->storageDisk = $disk;
        }

        return $this;
    }

    /**
     * Explicit disk storage (ffstage: / SVG path state). Alias of storeToDisk().
     */
    public function disk(
        string|Closure $directory = 'signatures',
        string|Closure|null $disk = null,
    ): static {
        return $this->storeToDisk($directory, $disk);
    }

    /**
     * Optional Spatie Media Library sink. Signature state stays SVG / ffstage: — UUID is optional metadata.
     */
    public function spatieSink(
        string|Closure $collection = 'signatures',
        string|Closure|null $disk = null,
        string|Closure|null $uuidStatePath = null,
    ): static {
        $this->spatieSinkEnabled = true;
        $this->spatieSinkCollection = $collection;

        if ($disk !== null) {
            $this->spatieSinkDisk = $disk;
        }

        if ($uuidStatePath !== null) {
            $this->spatieSinkUuidIn = $uuidStatePath;
        }

        return $this;
    }

    public function shouldStoreToDisk(): bool
    {
        return (bool) $this->evaluate($this->storeToDiskEnabled);
    }

    public function shouldSpatieSink(): bool
    {
        return (bool) $this->evaluate($this->spatieSinkEnabled);
    }

    public function getSpatieSinkCollection(): string
    {
        $collection = trim((string) $this->evaluate($this->spatieSinkCollection));

        return $collection !== '' ? $collection : 'signatures';
    }

    public function getSpatieSinkDisk(): ?string
    {
        $disk = $this->evaluate($this->spatieSinkDisk);

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    public function getSpatieSinkUuidInPath(): ?string
    {
        $path = $this->evaluate($this->spatieSinkUuidIn);

        return is_string($path) && $path !== '' ? $path : null;
    }

    protected function maybeSinkSignatureToSpatie(string $svg): void
    {
        if (! $this->shouldSpatieSink()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return;
        }

        $uuid = SignatureStorage::sinkToSpatie(
            $svg,
            $record,
            $this->getSpatieSinkCollection(),
            $this->getSpatieSinkDisk(),
            $this->getName(),
        );

        $uuidPath = $this->getSpatieSinkUuidInPath();

        if ($uuidPath !== null && is_string($uuid) && $uuid !== '') {
            $this->makeSetUtility()($uuidPath, $uuid);
        }
    }

    public function getStorageDirectory(): string
    {
        $directory = trim((string) $this->evaluate($this->storageDirectory));

        return $directory !== '' ? $directory : 'signatures';
    }

    public function getStorageDisk(): ?string
    {
        $disk = $this->evaluate($this->storageDisk);

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /**
     * SVG markup for durable file refs (ffstage:/path.svg) so the pad can redraw after remount.
     */
    public function getStoredSvgContent(): ?string
    {
        $state = $this->getState();

        if ($this->resolveStoredSvgUsing !== null) {
            $resolved = $this->evaluate($this->resolveStoredSvgUsing, [
                'state' => $state,
            ]);

            return is_string($resolved) && str_contains($resolved, '<svg') ? $resolved : null;
        }

        if ($this->shouldStoreToDisk() && is_string($state) && $state !== '') {
            return SignatureStorage::resolve($state, $this->getStorageDisk());
        }

        return null;
    }

    public function normalizeState(mixed $state): ?string
    {
        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        if ($this->isStoredFileReference($state)) {
            return $state;
        }

        return SignatureSvg::normalize($state);
    }

    /**
     * Durable disk tokens / paths after host apps stage the SVG (e.g. Flex Forms ffstage:).
     */
    public function isStoredFileReference(string $value): bool
    {
        return str_starts_with($value, 'ffstage:')
            || str_starts_with($value, 'ffmedia:')
            || str_contains($value, 'flex-forms/submissions/')
            || (str_ends_with(strtolower($value), '.svg') && ! str_contains($value, '<'));
    }
}
