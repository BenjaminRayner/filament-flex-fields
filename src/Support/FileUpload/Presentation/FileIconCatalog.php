<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation;

use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;

/** One file-kind mapping shared by server rendering and the browser presentation. */
final class FileIconCatalog
{
    private const EXTENSIONS = [
        'pdf' => 'document', 'doc' => 'document', 'docx' => 'document', 'odt' => 'document', 'rtf' => 'document', 'txt' => 'document',
        'xls' => 'sheet', 'xlsx' => 'sheet', 'ods' => 'sheet', 'csv' => 'sheet',
        'ppt' => 'presentation', 'pptx' => 'presentation', 'odp' => 'presentation',
        'fig' => 'figma', 'sketch' => 'sketch', 'html' => 'html', 'htm' => 'html',
        'json' => 'code', 'xml' => 'code', 'js' => 'code', 'ts' => 'code', 'css' => 'code', 'php' => 'code', 'py' => 'code',
        'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'webp' => 'image', 'avif' => 'image', 'gif' => 'image', 'svg' => 'image',
        'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'm4a' => 'audio', 'flac' => 'audio',
        'mp4' => 'video', 'webm' => 'video', 'mov' => 'video', 'avi' => 'video',
    ];

    private const MIME_TYPES = ['application/pdf' => 'document'];

    private const MIME_PREFIXES = ['image/' => 'image', 'audio/' => 'audio', 'video/' => 'video'];

    public static function kind(string $extension, string $mime = ''): string
    {
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));
        if (isset(self::MIME_TYPES[$mime])) {
            return self::MIME_TYPES[$mime];
        }
        foreach (self::MIME_PREFIXES as $prefix => $kind) {
            if (str_starts_with($mime, $prefix)) {
                return $kind;
            }
        }

        return self::EXTENSIONS[strtolower(ltrim($extension, '.'))] ?? 'attachment';
    }

    /** @return array{urls: array<string, string>, extensions: array<string, string>, mimeTypes: array<string, string>, mimePrefixes: array<string, string>} */
    public static function configuration(): array
    {
        $urls = [];
        foreach (['attachment', 'document', 'page', 'sheet', 'presentation', 'report', 'image', 'audio', 'video', 'code', 'html', 'figma', 'sketch'] as $kind) {
            $urls[$kind] = PublishedResourceUrl::for('flex-file-upload', 'file-icons', 'color-'.$kind.'.svg');
        }

        return ['urls' => $urls, 'extensions' => self::EXTENSIONS, 'mimeTypes' => self::MIME_TYPES, 'mimePrefixes' => self::MIME_PREFIXES];
    }
}
