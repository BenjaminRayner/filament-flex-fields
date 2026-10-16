<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/** Best-effort notifications; application listeners belong to Laravel's dispatcher. */
final class ActivityDispatcher
{
    /** @var array<class-string<ComponentActivity>, true> */
    private array $emitting = [];

    public function __construct(private readonly Dispatcher $events) {}

    public function dispatch(ComponentActivity $event): void
    {
        $name = $event::class;
        if (isset($this->emitting[$name])) {
            return;
        }

        $this->emitting[$name] = true;
        try {
            $this->events->dispatch($event);
        } catch (Throwable $exception) {
            try {
                report($exception);
            } catch (Throwable) {
                // A reporting failure cannot replace the component's operation result.
            }
        } finally {
            unset($this->emitting[$name]);
        }
    }
}
