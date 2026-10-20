<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Relations;

use InvalidArgumentException;

final readonly class MediaConversionColumns
{
    public function __construct(
        public string $name = 'name',
        public string $disk = 'disk',
        public string $path = 'path',
        public string $mimeType = 'mime_type',
        public string $bytes = 'bytes',
        public string $sha256 = 'sha256',
        public string $operationId = 'operation_id',
        public string $width = 'width',
        public string $height = 'height',
    ) {
        $columns = array_values(get_object_vars($this));
        foreach ($columns as $column) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/D', $column)) {
                throw new InvalidArgumentException('Conversion columns must be simple SQL identifiers.');
            }
        }
        if (count(array_unique(array_map(strtolower(...), $columns))) !== count($columns)) {
            throw new InvalidArgumentException('Conversion columns must be distinct.');
        }
    }
}
