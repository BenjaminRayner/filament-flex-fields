<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Staging;

use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use InvalidArgumentException;

final readonly class StageOptions
{
    public static function fromConfig(): self
    {
        $config = config('filament-flex-fields.media_capture.staging', []);
        if (! is_array($config)) {
            throw new InvalidArgumentException('Media staging configuration must be an array.');
        }

        return new self(
            disk: $config['disk'] ?? 'local', directory: $config['directory'] ?? 'media-os-staging',
            ttlSeconds: $config['ttl_seconds'] ?? 86400, receiptTtlSeconds: $config['receipt_ttl_seconds'] ?? 604800,
            lockSeconds: $config['lock_seconds'] ?? 300, lockWaitSeconds: $config['lock_wait_seconds'] ?? 10,
            pruneBatchSize: $config['prune_batch_size'] ?? 1000,
        );
    }

    public function __construct(
        public string $disk = 'local',
        public string $directory = 'media-os-staging',
        public int $ttlSeconds = 86400,
        public int $receiptTtlSeconds = 604800,
        public int $lockSeconds = 300,
        public int $lockWaitSeconds = 10,
        public int $pruneBatchSize = 1000,
    ) {
        MediaAddress::path($directory);
        if ($disk === '' || $ttlSeconds < 60 || $receiptTtlSeconds < 60 || $lockSeconds < 30
            || $lockWaitSeconds < 0 || $lockWaitSeconds >= $lockSeconds || $pruneBatchSize < 1 || $pruneBatchSize > 10000) {
            throw new InvalidArgumentException('Invalid Media OS staging options.');
        }
    }
}
