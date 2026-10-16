<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Only a server-owned journal identity travels through the broker. */
final class GenerateImageConversions implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $id, ConversionQueueOptions $options)
    {
        $this->tries = $options->tries;
        $this->timeout = $options->timeout;
        $this->onConnection($options->connectionName())->onQueue($options->queue)->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 60];
    }

    public function handle(QueuedImageConversions $conversions): void
    {
        try {
            $conversions->process($this->id, $this->timeout);
        } catch (LockTimeoutException) {
            // A duplicate or interrupted predecessor still owns its lease; wait beyond it.
            $this->release($this->timeout + 60);
        }
    }

    public function failed(?Throwable $error): void
    {
        app(QueuedImageConversions::class)->failed($this->id, $error);
    }
}
