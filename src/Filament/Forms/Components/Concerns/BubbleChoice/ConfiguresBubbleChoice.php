<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BubbleChoice;

use Bjanczak\FilamentFlexFields\Support\BubbleChoice\LayoutConfiguration;
use Bjanczak\FilamentFlexFields\Support\BubbleChoice\OptionCatalog;
use Closure;
use InvalidArgumentException;

trait ConfiguresBubbleChoice
{
    /** @var array<string, mixed>|Closure */
    protected array|Closure $layoutOverrides = [];

    /** @var array<string, true> */
    protected array $layoutExclusions = [];

    /**
     * @var array<string | int, string | array<string, mixed>> | Closure
     */
    protected array|Closure $options = [];

    /**
     * @var array<string | int> | Closure
     */
    protected array|Closure $disabledOptions = [];

    protected string|Closure $variant = 'soft';

    protected string|Closure $arenaHeight = '500px';

    protected string|Closure $selectedShape = 'scallop';

    protected string|Closure|null $bubbleColor = null;

    protected string|Closure|null $selectedBubbleColor = null;

    protected string|Closure|null $arenaColor = null;

    protected int|float|Closure $bubbleSize = 160;

    protected int|float|Closure $minSize = 25;

    protected int|float|Closure $gutter = 8;

    protected int|Closure $numCols = 6;

    protected int|float|Closure $fringeWidth = 160;

    protected int|float|Closure $yRadius = 130;

    protected int|float|Closure $xRadius = 220;

    protected int|float|Closure $cornerRadius = 50;

    protected bool|Closure $compact = true;

    protected int|float|Closure $gravitation = 5;

    protected bool|Closure $provideProps = true;

    protected int|Closure|null $minItems = null;

    protected int|Closure|null $maxItems = null;

    protected int|Closure|null $exactItems = null;

    /**
     * @param  array<string | int, string | array<string, mixed>> | Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  array<string | int> | Closure  $keys
     */
    public function disabledOptions(array|Closure $keys): static
    {
        $this->disabledOptions = $keys;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function arenaHeight(string|Closure $height): static
    {
        $this->arenaHeight = $height;

        return $this;
    }

    public function selectedShape(string|Closure $shape): static
    {
        $this->selectedShape = $shape;

        return $this;
    }

    public function bubbleColor(string|Closure|null $color): static
    {
        $this->bubbleColor = $color;

        return $this;
    }

    public function selectedBubbleColor(string|Closure|null $color): static
    {
        $this->selectedBubbleColor = $color;

        return $this;
    }

    public function arenaColor(string|Closure|null $color): static
    {
        $this->arenaColor = $color;

        return $this;
    }

    public function bubbleSize(int|float|Closure $size): static
    {
        $this->bubbleSize = $size;

        $this->layoutExclusions['size'] = true;

        return $this;
    }

    public function minSize(int|float|Closure $size): static
    {
        $this->minSize = $size;

        $this->layoutExclusions['minSize'] = true;

        return $this;
    }

    public function gutter(int|float|Closure $gutter): static
    {
        $this->gutter = $gutter;

        $this->layoutExclusions['gutter'] = true;

        return $this;
    }

    public function numCols(int|Closure $cols): static
    {
        $this->numCols = $cols;

        $this->layoutExclusions['numCols'] = true;

        return $this;
    }

    public function fringeWidth(int|float|Closure $width): static
    {
        $this->fringeWidth = $width;

        $this->layoutExclusions['fringeWidth'] = true;

        return $this;
    }

    public function yRadius(int|float|Closure $radius): static
    {
        $this->yRadius = $radius;

        $this->layoutExclusions['yRadius'] = true;

        return $this;
    }

    public function xRadius(int|float|Closure $radius): static
    {
        $this->xRadius = $radius;

        $this->layoutExclusions['xRadius'] = true;

        return $this;
    }

    public function cornerRadius(int|float|Closure $radius): static
    {
        $this->cornerRadius = $radius;

        $this->layoutExclusions['cornerRadius'] = true;

        return $this;
    }

    public function compact(bool|Closure $condition = true): static
    {
        $this->compact = $condition;

        $this->layoutExclusions['compact'] = true;

        return $this;
    }

    public function gravitation(int|float|Closure $strength): static
    {
        $this->gravitation = $strength;

        $this->layoutExclusions['gravitation'] = true;

        return $this;
    }

    public function provideProps(bool|Closure $condition = true): static
    {
        $this->provideProps = $condition;

        $this->layoutExclusions['provideProps'] = true;

        return $this;
    }

    /**
     * @param  array{
     *     size?: int|float,
     *     minSize?: int|float,
     *     gutter?: int|float,
     *     numCols?: int,
     *     fringeWidth?: int|float,
     *     yRadius?: int|float,
     *     xRadius?: int|float,
     *     cornerRadius?: int|float,
     *     compact?: bool,
     *     gravitation?: int|float,
     *     provideProps?: bool
     * }|Closure  $options
     */
    public function layoutOptions(array|Closure $options): static
    {
        $this->layoutOverrides = $options;
        $this->layoutExclusions = [];

        return $this;
    }

    public function minItems(int|Closure|null $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function maxItems(int|Closure|null $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function exactItems(int|Closure|null $count): static
    {
        $this->exactItems = $count;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['soft', 'solid', 'outline'], true)) {
            throw new InvalidArgumentException("Bubble choice variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function getArenaHeight(): string
    {
        return (string) $this->evaluate($this->arenaHeight);
    }

    public function getSelectedShape(): string
    {
        $shape = (string) $this->evaluate($this->selectedShape);

        if (! in_array($shape, ['scallop', 'circle', 'grow'], true)) {
            throw new InvalidArgumentException("Bubble choice selected shape [{$shape}] is not supported.");
        }

        return $shape;
    }

    public function getBubbleColor(): ?string
    {
        $color = $this->evaluate($this->bubbleColor);

        return filled($color) ? (string) $color : null;
    }

    public function getSelectedBubbleColor(): ?string
    {
        $color = $this->evaluate($this->selectedBubbleColor);

        return filled($color) ? (string) $color : null;
    }

    public function getArenaColor(): ?string
    {
        $color = $this->evaluate($this->arenaColor);

        return filled($color) ? (string) $color : null;
    }

    /**
     * @return array{
     *     size: float,
     *     minSize: float,
     *     gutter: float,
     *     provideProps: bool,
     *     numCols: int,
     *     fringeWidth: float,
     *     yRadius: float,
     *     xRadius: float,
     *     cornerRadius: float,
     *     compact: bool,
     *     gravitation: float
     * }
     */
    public function getLayoutOptionsForJs(): array
    {
        $base = [
            'size' => (float) $this->evaluate($this->bubbleSize),
            'minSize' => (float) $this->evaluate($this->minSize),
            'gutter' => (float) $this->evaluate($this->gutter),
            'provideProps' => (bool) $this->evaluate($this->provideProps),
            'numCols' => (int) $this->evaluate($this->numCols),
            'fringeWidth' => (float) $this->evaluate($this->fringeWidth),
            'yRadius' => (float) $this->evaluate($this->yRadius),
            'xRadius' => (float) $this->evaluate($this->xRadius),
            'cornerRadius' => (float) $this->evaluate($this->cornerRadius),
            'compact' => (bool) $this->evaluate($this->compact),
            'gravitation' => (float) $this->evaluate($this->gravitation),
        ];

        $overrides = $this->evaluate($this->layoutOverrides);
        if (! is_array($overrides)) {
            throw new InvalidArgumentException('Bubble layoutOptions must resolve to an array.');
        }

        return LayoutConfiguration::resolve($base, array_diff_key($overrides, $this->layoutExclusions));
    }

    public function getMinItems(): ?int
    {
        $count = $this->evaluate($this->minItems);

        if ($count !== null && (! is_int($count) || $count < 0 || $count > OptionCatalog::LIMIT)) {
            throw new InvalidArgumentException('Bubble selection limits must be between 0 and '.OptionCatalog::LIMIT.'.');
        }

        return $count;
    }

    public function getMaxItems(): ?int
    {
        $count = $this->evaluate($this->maxItems);

        if ($count !== null && (! is_int($count) || $count < 0 || $count > OptionCatalog::LIMIT)) {
            throw new InvalidArgumentException('Bubble selection limits must be between 0 and '.OptionCatalog::LIMIT.'.');
        }

        return $count;
    }

    public function getExactItems(): ?int
    {
        $count = $this->evaluate($this->exactItems);

        if ($count !== null && (! is_int($count) || $count < 0 || $count > OptionCatalog::LIMIT)) {
            throw new InvalidArgumentException('Bubble selection limits must be between 0 and '.OptionCatalog::LIMIT.'.');
        }

        return $count;
    }
}
