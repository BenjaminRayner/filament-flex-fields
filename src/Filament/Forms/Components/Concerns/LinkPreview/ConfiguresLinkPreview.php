<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\LinkPreview;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresLinkPreview
{
    protected string|Closure $variant = 'primary';

    protected bool|Closure $previewEnabled = true;

    protected int|Closure $previewDebounce = 500;

    protected int|Closure $previewMinUrlLength = 10;

    protected int|Closure $previewMinSkeletonMs = 500;

    /** @var 'horizontal'|'vertical'|'card'|Closure */
    protected string|Closure $previewLayout = 'horizontal';

    protected bool|Closure $resolveInitialPreviewOnServer = true;

    protected bool|Closure $showVisitLink = true;

    protected string|BackedEnum|Htmlable|Closure|null $visitIcon = null;

    protected string|Closure|null $visitLabel = null;

    protected string|Closure|null $prefix = null;

    protected string|Closure|null $suffix = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        if (! in_array($variant, ['primary', 'secondary', 'soft', 'flat', 'ghost'], true)) {
            throw new InvalidArgumentException("Invalid LinkPreviewField variant [{$variant}].");
        }

        return $variant;
    }

    public function preview(bool|Closure $condition = true): static
    {
        $this->previewEnabled = $condition;

        return $this;
    }

    public function isPreviewEnabled(): bool
    {
        return (bool) $this->evaluate($this->previewEnabled);
    }

    public function previewDebounce(int|Closure $milliseconds): static
    {
        $this->previewDebounce = $milliseconds;

        return $this;
    }

    public function getPreviewDebounce(): int
    {
        return $this->boundedInteger($this->evaluate($this->previewDebounce), 0, 60000);
    }

    public function previewMinUrlLength(int|Closure $length): static
    {
        $this->previewMinUrlLength = $length;

        return $this;
    }

    public function getPreviewMinUrlLength(): int
    {
        return $this->boundedInteger($this->evaluate($this->previewMinUrlLength), 4, 2048);
    }

    public function previewMinSkeletonMs(int|Closure $milliseconds): static
    {
        $this->previewMinSkeletonMs = $milliseconds;

        return $this;
    }

    public function getPreviewMinSkeletonMs(): int
    {
        return $this->boundedInteger($this->evaluate($this->previewMinSkeletonMs), 0, 5000);
    }

    /**
     * @param  'horizontal'|'vertical'|'card'|Closure  $layout
     */
    public function previewLayout(string|Closure $layout): static
    {
        $this->previewLayout = $layout;

        return $this;
    }

    /**
     * @return 'horizontal'|'vertical'|'card'
     */
    public function getPreviewLayout(): string
    {
        $layout = $this->evaluate($this->previewLayout);

        if (! in_array($layout, ['horizontal', 'vertical', 'card'], true)) {
            throw new InvalidArgumentException("Invalid LinkPreviewField preview layout [{$layout}].");
        }

        return $layout;
    }

    public function resolveInitialPreviewOnServer(bool|Closure $condition = true): static
    {
        $this->resolveInitialPreviewOnServer = $condition;

        return $this;
    }

    public function shouldResolveInitialPreviewOnServer(): bool
    {
        return (bool) $this->evaluate($this->resolveInitialPreviewOnServer);
    }

    public function showVisitLink(bool|Closure $condition = true): static
    {
        $this->showVisitLink = $condition;

        return $this;
    }

    public function shouldShowVisitLink(): bool
    {
        return (bool) $this->evaluate($this->showVisitLink);
    }

    public function visitIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->visitIcon = $icon;

        return $this;
    }

    public function getVisitIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->visitIcon);
    }

    public function visitLabel(string|Closure $label): static
    {
        $this->visitLabel = $label;

        return $this;
    }

    public function getVisitLabel(): string
    {
        return $this->evaluate($this->visitLabel) ?? Translations::get('filament-flex-fields::default.link_preview.visit');
    }

    public function prefix(string|Closure|null $label): static
    {
        $this->prefix = $label;

        return $this;
    }

    public function getPrefix(): ?string
    {
        $prefix = $this->evaluate($this->prefix);

        return is_string($prefix) && $prefix !== '' ? $prefix : null;
    }

    public function suffix(string|Closure|null $label): static
    {
        $this->suffix = $label;

        return $this;
    }

    public function getSuffix(): ?string
    {
        $suffix = $this->evaluate($this->suffix);

        return is_string($suffix) && $suffix !== '' ? $suffix : null;
    }

    private function boundedInteger(mixed $value, int $minimum, int $maximum): int
    {
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('Link preview integer setting is outside its supported range.');
        }

        return $value;
    }
}
