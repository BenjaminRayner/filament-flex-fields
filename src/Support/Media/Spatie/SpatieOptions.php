<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Spatie;

use Closure;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

/** Native model collections/conversions remain authoritative. */
final readonly class SpatieOptions
{
    /**
     * @param  array<string, mixed>  $customProperties
     * @param  array<string, mixed>  $manipulations
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $headers
     * @param  (Closure(FileAdder): void)|null  $configure
     * @param  (Closure(string): string)|null  $sanitizeFilename
     */
    public function __construct(
        public string $collection = 'default',
        public ?string $name = null,
        public ?string $conversionsDisk = null,
        public array $customProperties = [],
        public array $manipulations = [],
        public array $attributes = [],
        public array $headers = [],
        public bool $responsiveImages = false,
        public ?int $order = null,
        public ?string $queue = null,
        public ?Closure $sanitizeFilename = null,
        public ?Closure $configure = null,
    ) {
        if ($collection === '' || strlen($collection) > 255 || ($order !== null && $order < 0)) {
            throw new InvalidArgumentException('Invalid Spatie media collection or ordering.');
        }
        // These belong to the adapter/subject and must not be replaced through fill().
        foreach (['id', 'uuid', 'model_id', 'model_type', 'disk', 'conversions_disk', 'file_name', 'size', 'mime_type', 'collection_name', 'custom_properties'] as $reserved) {
            if (array_key_exists($reserved, $attributes)) {
                throw new InvalidArgumentException('Reserved Spatie media attribute: '.$reserved);
            }
        }
    }
}
