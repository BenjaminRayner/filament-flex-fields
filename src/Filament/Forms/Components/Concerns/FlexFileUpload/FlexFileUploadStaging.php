<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadRecovery;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadStaging;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;

/** @mixin BaseFileUpload */
trait FlexFileUploadStaging
{
    protected bool|Closure|null $flexMediaStaging = null;

    protected bool|Closure|null $flexMediaAutoRecovery = null;

    public function mediaAutoRecovery(bool|Closure $condition = true): static
    {
        $this->flexMediaAutoRecovery = $condition;

        return $this;
    }

    public function shouldAutomaticallyRecoverMedia(): bool
    {
        return (bool) ($this->evaluate($this->flexMediaAutoRecovery) ?? config('filament-flex-fields.media_capture.uploads.auto_recovery', true));
    }

    protected ?Closure $flexMediaStagingOwner = null;

    protected ?Closure $flexMediaReference = null;

    public function mediaStaging(bool|Closure $condition = true): static
    {
        $this->flexMediaStaging = $condition;

        return $this;
    }

    /** A stable server-side owner for custom forms, actors or tenants. Never pass browser input. */
    public function mediaStagingOwnerUsing(?Closure $callback): static
    {
        $this->flexMediaStagingOwner = $callback;

        return $this;
    }

    /** Reference proof for applications that map field state to custom relations/storage. */
    public function mediaReferenceUsing(?Closure $callback): static
    {
        $this->flexMediaReference = $callback;

        return $this;
    }

    public function usesMediaStaging(): bool
    {
        return (bool) ($this->evaluate($this->flexMediaStaging) ?? config('filament-flex-fields.media_capture.uploads.staging', true));
    }

    public function getMediaStagingOwner(): string
    {
        if ($this->flexMediaStagingOwner !== null) {
            $owner = $this->evaluate($this->flexMediaStagingOwner);
        } else {
            try {
                $livewire = $this->getLivewire();
                $scope = [$livewire::class, $livewire->getId(), $this->getStatePath()];
            } catch (\TypeError) {
                // Server-side schema/CLI consumers have no Livewire owner; the temporary identity still isolates writes.
                $record = $this->getRecord();
                $scope = ['server', $record instanceof Model ? $record->getMorphClass() : null,
                    $record instanceof Model ? $record->getKey() : null, $this->getName()];
            }
            $owner = hash('sha256', json_encode([Auth::id(), $scope], JSON_THROW_ON_ERROR));
        }
        if (! is_string($owner) || $owner === '' || strlen($owner) > 1024) {
            throw new MediaFailure('invalid_owner', 'Upload staging requires a bounded server-side owner.');
        }

        return $owner;
    }

    public function isMediaUploadReferenced(Model $record, string $identifier): bool
    {
        if ($this->flexMediaReference !== null) {
            return $this->evaluate($this->flexMediaReference, ['record' => $record, 'identifier' => $identifier]) === true;
        }
        if ($this instanceof FlexSpatieMediaLibraryFileUpload && $record instanceof HasMedia) {
            return $record->media()->where('uuid', $identifier)->where('collection_name', $this->getCollection() ?? 'default')->exists();
        }
        // Covers plain attributes and JSON/repeater state even when dehydration reindexes item keys.
        $values = [$record->attributesToArray()];
        $visited = 0;
        while ($values !== []) {
            $value = array_pop($values);
            if (++$visited > 10000) {
                throw new MediaFailure('reference_budget', 'Record reference proof exceeds its budget.');
            }
            if ($value === $identifier) {
                return true;
            }
            if (is_array($value)) {
                array_push($values, ...array_values($value));
            }
        }

        return false;
    }

    /** Custom Livewire/API saves call this only after persisting their model; transaction-aware. */
    public function completeMediaUploadsAfterSave(?Model $record = null): void
    {
        $record ??= $this->getRecord();
        if (! $record instanceof Model || ! $record->exists) {
            throw new MediaFailure('record_missing', 'Media acceptance requires a persisted record.');
        }
        app(UploadRecordLifecycle::class)->acceptField($this, $record);
    }

    /** Pending lease operations are server APIs, deliberately not exposed as arbitrary Livewire methods. */
    public function abandonMediaUpload(string $leaseKey): void
    {
        app(UploadStaging::class)->abandon($this, $leaseKey);
    }

    /** Server-only recovery after independently locating an interrupted operation's complete receipts. */
    public function recoverMediaUpload(string $leaseKey, MediaWrite $intent, UploadManifest $reconciled): string
    {
        return app(UploadRecovery::class)->recover($this, $leaseKey, $intent, $reconciled)->primaryReceipt()->identifier;
    }
}
