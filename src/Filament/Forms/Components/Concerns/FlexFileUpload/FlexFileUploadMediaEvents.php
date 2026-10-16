<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Contracts\Events\Dispatcher;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/** @mixin BaseFileUpload */
trait FlexFileUploadMediaEvents
{
    /** @var array<string, list<Closure>> */
    protected array $flexMediaListeners = [];

    public function onUploadEvent(UploadEventType $type, Closure $callback): static
    {
        $this->flexMediaListeners[$type->value][] = $callback;

        return $this;
    }

    public function notifyUploadEvent(UploadEvent $event): void
    {
        try {
            app(Dispatcher::class)->dispatch($event);
        } catch (Throwable $error) {
            UploadNotifications::report($error);
        }
        foreach ($this->flexMediaListeners[$event->type->value] ?? [] as $callback) {
            try {
                $this->evaluate($callback, ['event' => $event, 'receipt' => $event->receipt, 'intent' => $event->intent,
                    'path' => $event->receipt?->identifier, 'bytes' => $event->bytesFreed, 'bytesFreed' => $event->bytesFreed]);
            } catch (Throwable $error) {
                try {
                    UploadNotifications::report($error);
                } catch (Throwable) {
                    // A notification cannot turn a committed operation into an upload retry.
                }
            }
        }
    }

    public function notifyStoredUpload(MediaReceipt $receipt, MediaWrite $intent, TemporaryUploadedFile $file): void
    {
        $this->notifyUploadEvent(new UploadEvent(UploadEventType::Stored, $intent, $receipt));
        if ($this->flexAfterPersistUploadedFileUsing instanceof Closure) {
            try {
                $this->evaluate($this->flexAfterPersistUploadedFileUsing, ['path' => $receipt->identifier, 'file' => $file, 'receipt' => $receipt]);
            } catch (Throwable $error) {
                UploadNotifications::report($error);
            }
        }
    }

    public function notifyDeletedUpload(MediaReceipt $receipt, MediaWrite $intent, int $bytes): void
    {
        $this->notifyUploadEvent(new UploadEvent(UploadEventType::Deleted, $intent, $receipt, bytesFreed: $bytes));
        if ($this->flexDeletedStoredFileUsing instanceof Closure) {
            try {
                $this->evaluate($this->flexDeletedStoredFileUsing, ['path' => $receipt->identifier, 'bytes' => $bytes, 'bytesFreed' => $bytes]);
            } catch (Throwable $error) {
                UploadNotifications::report($error);
            }
        }
    }
}
