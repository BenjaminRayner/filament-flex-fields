<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;
use InvalidArgumentException;

final readonly class MediaDoctorOptions
{
    /** @var list<string> */
    public array $formats;

    /** @param list<string> $formats */
    public function __construct(
        public bool $images = false,
        public bool $queued = false,
        public bool $distributed = false,
        public bool $customEncoder = false,
        public ?string $connection = null,
        public int $timeout = 60,
        array $formats = [],
        public string $queue = 'media-conversions',
    ) {
        if ($timeout < 1 || $timeout > 3600 || (! $queued && ($connection !== null || $timeout !== 60 || $queue !== 'media-conversions'))
            || ($connection !== null && ($connection === '' || strlen($connection) > 128 || preg_match('/[\x00-\x1f\x7f]/', $connection)))) {
            throw new InvalidArgumentException('Use a queued profile for connection/timeout options and a timeout between 1 and 3600 seconds.');
        }
        new ConversionQueueOptions($connection, $queue, timeout: $timeout);
        if ((! $images && ! $queued && ($customEncoder || $formats !== [])) || count($formats) > 4 || ! array_is_list($formats)) {
            throw new InvalidArgumentException('Image options require --images or --queued; request each output format once.');
        }
        foreach ($formats as $format) {
            if (! in_array($format, ['jpg', 'png', 'webp', 'avif'], true)) {
                throw new InvalidArgumentException('Supported diagnostic formats are jpg, png, webp and avif.');
            }
        }
        if (count(array_unique($formats)) !== count($formats)) {
            throw new InvalidArgumentException('Request each output format once.');
        }
        $this->formats = array_map(static fn (string $format): string => $format, $formats);
    }

    public function checksImages(): bool
    {
        return $this->images || $this->queued;
    }
}
