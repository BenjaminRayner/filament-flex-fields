<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Video;

use Bjanczak\FilamentFlexFields\Support\Video\VideoPreviewOptions;
use Closure;

trait ConfiguresVideoPreviews
{
    protected string|Closure|null $previewSource = null;

    /** @var list<array{start: float, end: float, src: string, x: int, y: int, width: ?int, height: ?int}>|Closure */
    protected array|Closure $previewThumbnails = [];

    public function previewSource(string|Closure|null $source): static
    {
        if (! $source instanceof Closure) {
            VideoPreviewOptions::source($source);
        }
        $this->previewSource = $source;

        return $this;
    }

    /** @param array<array-key, mixed>|Closure $frames */
    public function previewThumbnails(array|Closure $frames): static
    {
        $this->previewThumbnails = is_array($frames) ? VideoPreviewOptions::thumbnails($frames) : $frames;

        return $this;
    }

    public function getPreviewSource(): ?string
    {
        return VideoPreviewOptions::source($this->evaluate($this->previewSource));
    }

    /** @return list<array{start: float, end: float, src: string, x: int, y: int, width: ?int, height: ?int}> */
    public function getPreviewThumbnails(): array
    {
        return is_array($this->previewThumbnails) ? $this->previewThumbnails : VideoPreviewOptions::thumbnails($this->evaluate($this->previewThumbnails));
    }
}
