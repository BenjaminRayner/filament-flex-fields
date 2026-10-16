<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Images\Queue;

use InvalidArgumentException;

final readonly class ConversionQueueOptions
{
    public function __construct(
        public ?string $connection = null,
        public string $queue = 'media-conversions',
        public int $tries = 3,
        public int $timeout = 60,
    ) {
        if ($queue === '' || strlen($queue) > 128 || preg_match('/[\x00-\x1f\x7f]/', $queue)
            || $tries < 1 || $tries > 20 || $timeout < 1 || $timeout > 3600) {
            throw new InvalidArgumentException('Invalid image conversion queue configuration.');
        }
    }

    public function connectionName(): string
    {
        $connection = $this->connection ?? config('queue.default');
        if (! is_string($connection) || ! is_array(config('queue.connections.'.$connection))) {
            throw new InvalidArgumentException('Configure an existing asynchronous queue connection.');
        }
        $driver = config('queue.connections.'.$connection.'.driver');
        if (! is_string($driver) || $driver === '' || in_array($driver, ['sync', 'null', 'deferred', 'background'], true)) {
            throw new InvalidArgumentException('Queued image conversions require a durable worker queue.');
        }
        $retry = config('queue.connections.'.$connection.'.retry_after');
        if (is_numeric($retry) && (int) $retry <= $this->timeout) {
            throw new InvalidArgumentException('Queue retry_after must exceed the conversion job timeout.');
        }

        return $connection;
    }
}
