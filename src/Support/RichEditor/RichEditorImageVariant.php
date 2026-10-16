<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\RichEditor;

use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadImageProcessor;
use InvalidArgumentException;

final class RichEditorImageVariant
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $maxLongEdge = null,
        public readonly ?int $maxWidth = null,
        public readonly ?int $maxHeight = null,
        public readonly bool $webp = false,
        public readonly bool $master = false,
        public readonly bool $optimize = true,
    ) {
        if (! preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]{0,63}\z/', $name)) {
            throw new InvalidArgumentException('Image variant names must contain only letters, numbers, underscores and hyphens.');
        }

        foreach ([$maxLongEdge, $maxWidth, $maxHeight] as $dimension) {
            if ($dimension !== null && ($dimension < 1 || $dimension > 32768)) {
                throw new InvalidArgumentException('Image variant dimensions must be between 1 and 32768 pixels.');
            }
        }
    }

    public static function make(string $name): self
    {
        return new self(name: $name);
    }

    public function maxLongEdge(int $pixels): self
    {
        return new self(
            name: $this->name,
            maxLongEdge: $pixels,
            maxWidth: $this->maxWidth,
            maxHeight: $this->maxHeight,
            webp: $this->webp,
            master: $this->master,
            optimize: $this->optimize,
        );
    }

    public function maxWidth(int $pixels): self
    {
        return new self(
            name: $this->name,
            maxLongEdge: $this->maxLongEdge,
            maxWidth: $pixels,
            maxHeight: $this->maxHeight,
            webp: $this->webp,
            master: $this->master,
            optimize: $this->optimize,
        );
    }

    public function maxHeight(int $pixels): self
    {
        return new self(
            name: $this->name,
            maxLongEdge: $this->maxLongEdge,
            maxWidth: $this->maxWidth,
            maxHeight: $pixels,
            webp: $this->webp,
            master: $this->master,
            optimize: $this->optimize,
        );
    }

    public function webp(bool $condition = true): self
    {
        return new self(
            name: $this->name,
            maxLongEdge: $this->maxLongEdge,
            maxWidth: $this->maxWidth,
            maxHeight: $this->maxHeight,
            webp: $condition,
            master: $this->master,
            optimize: $this->optimize,
        );
    }

    public function master(bool $condition = true): self
    {
        return new self(
            name: $this->name,
            maxLongEdge: $this->maxLongEdge,
            maxWidth: $this->maxWidth,
            maxHeight: $this->maxHeight,
            webp: $this->webp,
            master: $condition,
            optimize: $this->optimize,
        );
    }

    public function optimize(bool $condition = true): self
    {
        return new self(
            name: $this->name,
            maxLongEdge: $this->maxLongEdge,
            maxWidth: $this->maxWidth,
            maxHeight: $this->maxHeight,
            webp: $this->webp,
            master: $this->master,
            optimize: $condition,
        );
    }

    /**
     * @param  array<string, array<string, mixed>|RichEditorImageVariant>  $variants
     * @return list<RichEditorImageVariant>
     */
    public static function normalizeCollection(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $name => $variant) {
            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException('Rich editor image variant names must be non-empty strings.');
            }

            $normalized[] = $variant instanceof self
                ? $variant
                : self::fromArray($name, is_array($variant) ? $variant : []);
        }

        return self::ensureMasterVariant($normalized);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            maxLongEdge: isset($config['max_long_edge']) ? (int) $config['max_long_edge'] : (isset($config['maxLongEdge']) ? (int) $config['maxLongEdge'] : null),
            maxWidth: isset($config['max_width']) ? (int) $config['max_width'] : (isset($config['maxWidth']) ? (int) $config['maxWidth'] : null),
            maxHeight: isset($config['max_height']) ? (int) $config['max_height'] : (isset($config['maxHeight']) ? (int) $config['maxHeight'] : null),
            webp: (bool) ($config['webp'] ?? $config['optimize_to_webp'] ?? false),
            master: (bool) ($config['master'] ?? false),
            optimize: (bool) ($config['optimize'] ?? $config['optimize_images'] ?? true),
        );
    }

    /**
     * @param  list<RichEditorImageVariant>  $variants
     * @return list<RichEditorImageVariant>
     */
    public static function ensureMasterVariant(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        if (count($variants) > 12 || count(array_unique(array_map(fn (self $variant): string => strtolower($variant->name), $variants))) !== count($variants)) {
            throw new InvalidArgumentException('Configure at most 12 image variants with unique names.');
        }

        if (count(array_filter($variants, fn (self $variant): bool => $variant->master)) > 1) {
            throw new InvalidArgumentException('Only one image variant may be the master.');
        }

        $hasMaster = collect($variants)->contains(fn (self $variant): bool => $variant->master);

        if ($hasMaster) {
            return $variants;
        }

        $largest = collect($variants)
            ->sortByDesc(fn (self $variant): int => $variant->maxLongEdge ?? $variant->maxWidth ?? $variant->maxHeight ?? 0)
            ->first();

        return array_map(
            fn (self $variant): self => $variant->name === $largest?->name
                ? $variant->master()
                : $variant,
            $variants,
        );
    }

    public function masterVariant(): ?self
    {
        return $this->master ? $this : null;
    }

    public function toImageProcessor(): FileUploadImageProcessor
    {
        return new FileUploadImageProcessor(
            optimizeImages: $this->optimize,
            optimizeImagesToWebp: $this->webp,
            maxImageWidth: $this->maxWidth,
            maxImageHeight: $this->maxHeight,
            maxImageLongEdge: $this->maxLongEdge,
        );
    }
}
