<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation;

use Bjanczak\FilamentFlexFields\Support\Translations;
use Symfony\Component\Mime\MimeTypes;

/** Human-readable declared limits. Opaque application validation needs an explicit hint. */
final readonly class UploadRequirements
{
    /** @param list<string>|null $types
     * @param  list<string>  $extensions
     * @param  array<string, string>  $mimeTypeMap
     * @param  array{minWidth: ?int, minHeight: ?int, maxWidth: ?int, maxHeight: ?int}|null  $dimensions
     */
    public function __construct(
        public ?array $types = null,
        public array $extensions = [],
        public array $mimeTypeMap = [],
        public ?int $minSize = null,
        public ?int $maxSize = null,
        public ?int $minFiles = null,
        public ?int $maxFiles = null,
        public ?int $maxTotalSize = null,
        public ?array $dimensions = null,
        public bool $required = false,
    ) {}

    /** @return list<array{label: string, mime: string}> */
    public function formats(): array
    {
        $mimeTypes = MimeTypes::getDefault();
        $types = array_map(strtolower(...), $this->types ?? []);
        $formats = [];
        if ($this->extensions !== []) {
            foreach ($this->extensions as $extension) {
                $mimes = array_values($mimeTypes->getMimeTypes($extension));
                // The displayed list respects both configured allow-lists.
                if ($types !== [] && ! in_array('*/*', $types, true) && ! $this->matches($extension, $mimes, $types)) {
                    continue;
                }
                $formats[] = ['label' => strtoupper($extension), 'mime' => $mimes[0] ?? ''];
            }

            return $formats;
        }
        foreach ($types as $type) {
            if ($type === '*/*') {
                return [];
            }
            $label = match ($type) {
                'image/*' => self::label('images'), 'video/*' => self::label('videos'), 'audio/*' => self::label('audio'),
                'image/jpeg' => 'JPG', 'image/svg+xml' => 'SVG', 'text/plain' => 'TXT',
                default => strtoupper(ltrim($this->mimeTypeMap[$type] ?? ($mimeTypes->getExtensions($type)[0] ?? $type), '.')),
            };
            $formats[$label] = ['label' => $label, 'mime' => $type];
        }

        return array_values($formats);
    }

    public function description(): string
    {
        $limits = $this->limits();

        return $this->formatDescription().($limits === [] ? '' : ' ('.implode('; ', $limits).')');
    }

    public function formatDescription(): string
    {
        $formats = $this->formats();
        if (count($formats) === 1 && $formats[0]['mime'] === 'image/*') {
            return self::label('image_examples');
        }

        return implode(', ', array_column($formats, 'label')) ?: self::label($this->types === null && $this->extensions === [] ? 'all_files' : 'formats');
    }

    /** @return list<string> */
    public function limits(): array
    {
        $limits = [];
        foreach (['min_size' => $this->minSize, 'max_size' => $this->maxSize, 'total_size' => $this->maxTotalSize] as $key => $size) {
            if ($size !== null) {
                $limits[] = self::label($key, ['size' => self::size($size)]);
            }
        }
        foreach (['min_files' => $this->minFiles, 'max_files' => $this->maxFiles] as $key => $count) {
            if ($count !== null) {
                $limits[] = self::label($count === 1 ? substr($key, 0, -1) : $key, ['count' => $count]);
            }
        }
        foreach (['minWidth' => 'min_width', 'minHeight' => 'min_height', 'maxWidth' => 'max_width', 'maxHeight' => 'max_height'] as $dimension => $key) {
            if (($value = $this->dimensions[$dimension] ?? null) !== null) {
                $limits[] = self::label($key, ['pixels' => $value]);
            }
        }
        if ($this->required) {
            $limits[] = self::label('required');
        }

        return $limits;
    }

    /** @return list<string> */
    public function icons(string $source): array
    {
        if ($source === 'url') {
            return ['hugeicons-file-link', 'hugeicons-link-square-02', 'hugeicons-file-download'];
        }
        if ($source === 'camera') {
            return ['hugeicons-image-01', 'hugeicons-camera-01', 'hugeicons-image-done-01'];
        }

        return ['hugeicons-image-01', 'hugeicons-upload-01', 'hugeicons-music-note-03'];
    }

    private static function size(int $kilobytes): string
    {
        $value = $kilobytes >= 1024 ? $kilobytes / 1024 : $kilobytes;

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').($kilobytes >= 1024 ? ' MB' : ' KB');
    }

    /** @param list<string> $mimes
     * @param  list<string>  $types
     */
    private function matches(string $extension, array $mimes, array $types): bool
    {
        foreach ($types as $type) {
            if (strtolower(ltrim($this->mimeTypeMap[$type] ?? '', '.')) === $extension || $type === '.'.$extension) {
                return true;
            }
            foreach ($mimes as $mime) {
                if ($type === $mime || (str_ends_with($type, '/*') && str_starts_with($mime, substr($type, 0, -1)))) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, string|int> $replace */
    private static function label(string $key, array $replace = []): string
    {
        return Translations::get('filament-flex-fields::default.file_upload.requirements.'.$key, $replace);
    }
}
