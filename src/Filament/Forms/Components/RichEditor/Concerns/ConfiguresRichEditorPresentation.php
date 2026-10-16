<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Concerns;

use Closure;

/** Presentation settings consumed by the FLEX view on every supported Filament minor. */
trait ConfiguresRichEditorPresentation
{
    protected bool|Closure $hasMinimalCustomBlockControls = false;

    protected bool|Closure $hasCustomBlocksGrid = false;

    protected bool|Closure $hasSearchableCustomBlocks = false;

    protected bool|Closure $hasStickyToolbar = false;

    protected bool|Closure $hasStickyPanels = false;

    protected string|Closure|null $stickyOffset = null;

    public function minimalCustomBlockControls(bool|Closure $condition = true): static
    {
        $this->hasMinimalCustomBlockControls = $condition;

        return $this;
    }

    public function hasMinimalCustomBlockControls(): bool
    {
        return (bool) $this->evaluate($this->hasMinimalCustomBlockControls);
    }

    public function customBlocksGrid(bool|Closure $condition = true): static
    {
        $this->hasCustomBlocksGrid = $condition;

        return $this;
    }

    public function hasCustomBlocksGrid(): bool
    {
        return (bool) $this->evaluate($this->hasCustomBlocksGrid);
    }

    public function searchableCustomBlocks(bool|Closure $condition = true): static
    {
        $this->hasSearchableCustomBlocks = $condition;

        return $this;
    }

    public function hasSearchableCustomBlocks(): bool
    {
        return (bool) $this->evaluate($this->hasSearchableCustomBlocks);
    }

    public function stickyToolbar(bool|Closure $condition = true): static
    {
        $this->hasStickyToolbar = $condition;

        return $this;
    }

    public function hasStickyToolbar(): bool
    {
        return (bool) $this->evaluate($this->hasStickyToolbar);
    }

    public function stickyPanels(bool|Closure $condition = true): static
    {
        $this->hasStickyPanels = $condition;

        return $this;
    }

    public function hasStickyPanels(): bool
    {
        return (bool) $this->evaluate($this->hasStickyPanels);
    }

    public function stickyOffset(string|Closure|null $offset): static
    {
        $this->stickyOffset = $offset;

        return $this;
    }

    public function getStickyOffset(): ?string
    {
        return $this->evaluate($this->stickyOffset);
    }
}
