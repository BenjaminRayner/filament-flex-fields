<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SwitchField;

use Closure;
use InvalidArgumentException;

trait ConfiguresSwitch
{
    protected string|Closure $variant = 'default';

    protected string|Closure $layout = 'row';

    protected string|Closure $labelPosition = 'start';

    protected string|Closure|null $description = null;

    protected string|Closure|null $color = null;

    protected string|Closure|null $badge = null;

    protected string|Closure $badgeColor = 'primary';

    protected bool|Closure $isRippleEnabled = false;

    protected bool|Closure $isCompact = false;

    protected bool|Closure $isInlineToggle = false;

    protected bool|Closure $showsInlineFieldLabel = false;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function layout(string|Closure $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function labelPosition(string|Closure $position): static
    {
        $this->labelPosition = $position;

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function badge(string|Closure|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function badgeColor(string|Closure $badgeColor): static
    {
        $this->badgeColor = $badgeColor;

        return $this;
    }

    public function ripple(bool|Closure $condition = true): static
    {
        $this->isRippleEnabled = $condition;

        return $this;
    }

    public function compact(bool|Closure $condition = true): static
    {
        $this->isCompact = $condition;

        return $this;
    }

    public function inline(bool|Closure $condition = true): static
    {
        $this->isInlineToggle = $condition;
        $this->showsInlineFieldLabel = false;
        $this->hiddenLabel();
        $this->inlineLabel(false);

        return $this;
    }

    public function inlineWithLabel(bool|Closure $condition = true): static
    {
        $this->isInlineToggle = $condition;
        $this->showsInlineFieldLabel = $condition;
        $this->hiddenLabel(fn (): bool => ! $this->showsInlineFieldLabel());
        $this->inlineLabel($condition);

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        return in_array($variant, ['default', 'secondary'], true) ? $variant
            : throw new InvalidArgumentException("Switch variant [{$variant}] is not supported.");
    }

    public function getLayout(): string
    {
        $layout = (string) $this->evaluate($this->layout);

        if (! in_array($layout, ['row', 'card'], true)) {
            throw new InvalidArgumentException("Switch layout [{$layout}] is not supported.");
        }

        return $layout;
    }

    public function getLabelPosition(): string
    {
        $position = (string) $this->evaluate($this->labelPosition);

        if (! in_array($position, ['start', 'end'], true)) {
            throw new InvalidArgumentException("Switch label position [{$position}] is not supported.");
        }

        return $position;
    }

    public function getDescription(): ?string
    {
        $description = $this->evaluate($this->description);

        return filled($description) ? (string) $description : null;
    }

    public function getBadge(): ?string
    {
        $badge = $this->evaluate($this->badge);

        return filled($badge) ? (string) $badge : null;
    }

    public function getBadgeColor(): string
    {
        return (string) $this->evaluate($this->badgeColor);
    }

    public function isRippleEnabled(): bool
    {
        return (bool) $this->evaluate($this->isRippleEnabled);
    }

    public function isCompact(): bool
    {
        return (bool) $this->evaluate($this->isCompact);
    }

    public function isInlineToggle(): bool
    {
        return (bool) $this->evaluate($this->isInlineToggle);
    }

    public function showsInlineFieldLabel(): bool
    {
        return (bool) $this->evaluate($this->showsInlineFieldLabel);
    }

    public function getColor(): string
    {
        $color = $this->evaluate($this->color);

        if (filled($color)) {
            return (string) $color;
        }

        return 'primary';
    }

    public function getEffectiveOnColor(): string
    {
        return $this->getOnColor() ?? $this->getColor();
    }

    public function getEffectiveOffColor(): string
    {
        return $this->getOffColor() ?? 'gray';
    }
}
