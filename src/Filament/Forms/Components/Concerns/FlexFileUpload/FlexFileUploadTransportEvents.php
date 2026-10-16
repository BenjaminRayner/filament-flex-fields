<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadTransportEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadTransportEventType;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Throwable;

/** @mixin BaseFileUpload */
trait FlexFileUploadTransportEvents
{
    /** @var array<string, list<Closure>> */
    protected array $flexTransportListeners = [];

    protected int $flexTransportInterval = 1000;

    /** Null subscribes to all transport observations. Opt-in: no listeners means no extra requests. */
    public function onUploadTransportEvent(?UploadTransportEventType $type, Closure $callback): static
    {
        $this->flexTransportListeners[$type === null ? '*' : $type->value][] = $callback;

        return $this;
    }

    public function uploadTransportProgressInterval(int $milliseconds): static
    {
        $this->flexTransportInterval = max(500, min(10000, $milliseconds));

        return $this;
    }

    /** @return array{events: list<string>, interval: int} */
    public function getUploadTransportConfiguration(): array
    {
        return ['events' => isset($this->flexTransportListeners['*'])
            ? array_map(static fn (UploadTransportEventType $event): string => $event->value, UploadTransportEventType::cases())
            : array_keys($this->flexTransportListeners), 'interval' => $this->flexTransportInterval];
    }

    /** @param list<array<string, mixed>> $events */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function reportUploadTransportEvents(string $session, array $events): void
    {
        if ($this->isDisabled() || $this->flexTransportListeners === []) {
            return;
        }
        Validator::make(['session' => $session, 'events' => $events], [
            'session' => ['required', 'uuid'], 'events' => ['array', 'max:32'],
            'events.*' => ['array:type,sequence,fileId,progress,source'],
            'events.*.type' => ['required', 'string', 'in:'.implode(',', $this->getUploadTransportConfiguration()['events'])],
            'events.*.sequence' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'events.*.fileId' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'events.*.progress' => ['nullable', 'numeric', 'between:0,100'],
            'events.*.source' => ['nullable', 'in:file,url,webcam'],
        ])->validate();
        $scope = hash('sha256', $this->getMediaStagingOwner());
        if (RateLimiter::tooManyAttempts('fff:upload:transport:'.$scope, 120)) {
            return;
        }
        RateLimiter::hit('fff:upload:transport:'.$scope, 60);
        $key = 'fff:upload:transport:'.$scope.':'.hash('sha256', $session);
        $accepted = Cache::lock($key.':lock', 10)->block(2, function () use ($key, $events): array {
            $last = (int) Cache::get($key, 0);
            $accepted = [];
            foreach ($events as $data) {
                if ($data['sequence'] <= $last) {
                    continue;
                }
                $last = $data['sequence'];
                $accepted[] = $data;
            }
            Cache::put($key, $last, 600);

            return $accepted;
        });
        foreach ($accepted as $data) {
            $field = $this->getStatePath() ?? $this->getName();
            $event = new UploadTransportEvent(UploadTransportEventType::from($data['type']), $field, $session,
                $data['sequence'], $data['fileId'] ?? null, isset($data['progress']) ? (float) $data['progress'] : null, $data['source'] ?? null);
            try {
                app(Dispatcher::class)->dispatch($event);
            } catch (Throwable $error) {
                UploadNotifications::report($error);
            }
            foreach ([...($this->flexTransportListeners[$event->type->value] ?? []), ...($this->flexTransportListeners['*'] ?? [])] as $callback) {
                try {
                    $this->evaluate($callback, ['event' => $event]);
                } catch (Throwable $error) {
                    UploadNotifications::report($error);
                }
            }
        }
    }
}
