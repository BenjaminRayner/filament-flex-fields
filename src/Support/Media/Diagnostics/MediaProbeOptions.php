<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use InvalidArgumentException;

/** Active work requires an explicit target and acknowledgement, including direct PHP callers. */
final readonly class MediaProbeOptions
{
    /** @var list<string> */
    public array $formats;

    /** @param list<string> $formats */
    public function __construct(
        public bool $allowWrites = false,
        public ?string $disk = null,
        public ?string $lockStore = null,
        array $formats = [],
    ) {
        if (! $allowWrites || ($disk === null && $lockStore === null && $formats === [])) {
            throw new InvalidArgumentException('Select a disk, lock store or image format and explicitly allow diagnostic writes with --write.');
        }
        foreach ([$disk, $lockStore] as $name) {
            if ($name !== null && ($name === '' || strlen($name) > 128 || preg_match('/[\x00-\x1f\x7f]/', $name))) {
                throw new InvalidArgumentException('Select a bounded disk or cache store name.');
            }
        }
        if (! array_is_list($formats) || count($formats) > 4) {
            throw new InvalidArgumentException('Select at most four distinct output formats.');
        }
        foreach ($formats as $format) {
            if (! in_array($format, ['jpg', 'png', 'webp', 'avif'], true)) {
                throw new InvalidArgumentException('Supported probe formats are jpg, png, webp and avif.');
            }
        }
        if (count(array_unique($formats)) !== count($formats)) {
            throw new InvalidArgumentException('Select each output format once.');
        }
        $this->formats = array_map(static fn (string $format): string => $format, $formats);
    }
}
