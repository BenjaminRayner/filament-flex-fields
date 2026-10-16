<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation;

use Filament\Forms\Components\BaseFileUpload;
use InvalidArgumentException;

/** Server/browser presentation snapshot. Native field authorization precedes every URL. */
final readonly class UploadPresentation
{
    public function __construct(
        public UploadLayout $layout = UploadLayout::List,
        public int $gridColumns = 3,
        public int $avatarSize = 128,
        public int $gridTileSize = 116,
    ) {
        if ($gridColumns < 1 || $gridColumns > 8 || $avatarSize < 64 || $avatarSize > 512) {
            throw new InvalidArgumentException('Upload layout requires 1–8 columns and a 64–512px avatar.');
        }
        if ($gridTileSize < 80 || $gridTileSize > 320) {
            throw new InvalidArgumentException('Upload grid tiles require a size between 80 and 320px.');
        }
    }

    /** @param array<array-key, array<string, mixed>|null> $index
     * @return list<array<string, mixed>>
     */
    public function files(array $index, bool $previewable = true, bool $append = true): array
    {
        $files = [];
        foreach ($index as $key => $file) {
            if ($file === null || ! is_string($file['name'] ?? null)) {
                continue;
            }
            $type = is_string($file['type'] ?? null) ? $file['type'] : '';
            $name = $file['name'];
            $bytes = $file['size'] ?? 0;
            $files[] = [
                'id' => 'stored:'.$key, 'nativeKey' => (string) $key,
                'name' => $name, 'bytes' => is_int($bytes) && $bytes > 0 ? $bytes : 0, 'type' => $type,
                'extension' => strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                'preview' => $previewable && in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)
                    ? self::url($file['url'] ?? null) : null,
                'openUrl' => self::url($file['openableUrl'] ?? null),
                'downloadUrl' => self::url($file['downloadableUrl'] ?? null),
                'processingStatus' => $file['processingStatus'] ?? null,
                'processingError' => $file['processingError'] ?? null,
                'complete' => true, 'uploaded' => false, 'failed' => false, 'uploading' => false, 'loading' => false,
                'progress' => 100, 'remaining' => null,
            ];
        }

        return $append ? $files : array_reverse($files);
    }

    /** @return array<string, mixed> */
    public function forField(BaseFileUpload $field): array
    {
        return [
            'layout' => $this->layout->value,
            'gridColumns' => $this->gridColumns,
            'gridTileSize' => $this->gridTileSize,
            'avatarSize' => $this->avatarSize,
            'isDeletable' => $field->isDeletable(),
            'isDownloadable' => $field->isDownloadable(),
            'isOpenable' => $field->isOpenable(),
            'isReorderable' => $field->isReorderable(),
            'isPreviewable' => $field->isPreviewable(),
            'isDisabled' => $field->isDisabled(),
        ];
    }

    /** @param list<string>|null $types
     * @return list<array{kind: string, label: string}>
     */
    public function illustration(?array $types): array
    {
        $types = array_map(strtolower(...), $types ?? []);
        if ($types === [] || in_array('*/*', $types, true)) {
            return [['kind' => 'image', 'label' => 'JPG'], ['kind' => 'document', 'label' => 'DOC'], ['kind' => 'document', 'label' => 'PDF']];
        }
        $tiles = [];
        foreach ($types as $type) {
            $kind = str_starts_with($type, 'image/') ? 'image' : 'document';
            $labels = match ($type) {
                'image/*' => ['JPG', 'PNG', 'WEBP'], 'image/jpeg' => ['JPG'], 'image/png' => ['PNG'],
                'image/webp' => ['WEBP'], 'image/avif' => ['AVIF'], 'image/gif' => ['GIF'],
                'application/pdf' => ['PDF'], 'text/plain' => ['TXT'], 'text/csv' => ['CSV'],
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['DOC'],
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['XLS'],
                default => [$kind === 'image' ? 'IMG' : (str_starts_with($type, 'audio/') ? 'AUDIO' : (str_starts_with($type, 'video/') ? 'VIDEO' : 'FILE'))],
            };
            foreach ($labels as $label) {
                $tiles[$kind.':'.$label] = ['kind' => $kind, 'label' => $label];
            }
        }

        return array_slice(array_values($tiles), 0, 3);
    }

    private static function url(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }

        // Application-relative paths and signed HTTP(S) URLs; no active data/SVG URLs.
        return str_starts_with($value, '/') && ! str_starts_with($value, '//')
            || in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }
}
