<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Staging;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaDescriptor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;

final class ReceiptCodec
{
    /** @return array<string, int|string|null> */
    public static function encode(MediaReceipt $receipt): array
    {
        return [
            'adapter' => $receipt->adapter, 'identifier' => $receipt->identifier, 'disk' => $receipt->disk,
            'path' => $receipt->path, 'operation' => $receipt->operationId,
            'bytes' => $receipt->file->bytes, 'mime' => $receipt->file->mimeType,
            'hash' => $receipt->file->sha256, 'name' => $receipt->file->originalName,
            'width' => $receipt->file->width, 'height' => $receipt->file->height,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function decode(array $data): MediaReceipt
    {
        foreach (['adapter', 'identifier', 'disk', 'path', 'operation', 'mime', 'hash', 'name'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                throw new MediaFailure('stage_corrupt', 'Invalid durable media receipt.');
            }
        }
        if (! isset($data['bytes']) || ! is_int($data['bytes']) || $data['bytes'] < 0) {
            throw new MediaFailure('stage_corrupt', 'Invalid durable media byte count.');
        }
        if (! preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $data['adapter'])
            || ! preg_match('/^[a-f0-9]{64}$/D', $data['hash'])
            || $data['identifier'] === '' || $data['operation'] === ''
            || strlen($data['identifier']) > 1024 || strlen($data['operation']) > 255) {
            throw new MediaFailure('stage_corrupt', 'Invalid durable media identity.');
        }

        return new MediaReceipt($data['adapter'], $data['identifier'], $data['disk'], $data['path'],
            new MediaDescriptor($data['bytes'], $data['mime'], $data['hash'], $data['name'],
                is_int($data['width'] ?? null) ? $data['width'] : null,
                is_int($data['height'] ?? null) ? $data['height'] : null), $data['operation']);
    }
}
