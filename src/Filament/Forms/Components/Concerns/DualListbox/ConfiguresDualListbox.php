<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DualListbox;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresDualListbox
{
    /**
     * @var array<string | int, string | array<string, mixed>> | Closure
     */
    protected array|Closure $options = [];

    /**
     * @var array<string, array{label: string, description: ?string, disabled: bool}>|null
     */
    protected ?array $normalizedOptionsCache = null;

    /**
     * @var array<string | int> | Closure
     */
    protected array|Closure $disabledOptions = [];

    protected string|Closure $variant = 'bordered';

    protected string|Closure $listHeight = '16rem';

    protected bool|Closure $isSearchable = true;

    protected bool|Closure $isReorderable = true;

    protected bool|Closure $moveOnDoubleClick = true;

    protected bool|Closure $showTransferButtons = true;

    protected string|Closure|null $availableLabel = null;

    protected string|Closure|null $selectedLabel = null;

    protected int|Closure|null $minItems = null;

    protected int|Closure|null $maxItems = null;

    protected string|BackedEnum|Htmlable|Closure|null $searchIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $moveAllRightIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $moveRightIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $swapIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $moveLeftIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $moveAllLeftIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $moveUpIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $moveDownIcon = null;

    /**
     * @param  array<string | int, string | array<string, mixed>> | Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;
        $this->normalizedOptionsCache = null;

        return $this;
    }

    /**
     * @param  array<string | int> | Closure  $keys
     */
    public function disabledOptions(array|Closure $keys): static
    {
        $this->disabledOptions = $keys;
        $this->normalizedOptionsCache = null;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function listHeight(string|Closure $height): static
    {
        $this->listHeight = $height;

        return $this;
    }

    public function searchable(bool|Closure $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    public function reorderable(bool|Closure $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    public function moveOnDoubleClick(bool|Closure $condition = true): static
    {
        $this->moveOnDoubleClick = $condition;

        return $this;
    }

    public function showTransferButtons(bool|Closure $condition = true): static
    {
        $this->showTransferButtons = $condition;

        return $this;
    }

    public function availableLabel(string|Closure|null $label): static
    {
        $this->availableLabel = $label;

        return $this;
    }

    public function selectedLabel(string|Closure|null $label): static
    {
        $this->selectedLabel = $label;

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
        $this->minItems = $count;
        $this->maxItems = $count;

        return $this;
    }

    /**
     * @param  array{
     *     search?: string|BackedEnum|Htmlable,
     *     move_all_right?: string|BackedEnum|Htmlable,
     *     move_right?: string|BackedEnum|Htmlable,
     *     swap?: string|BackedEnum|Htmlable,
     *     move_left?: string|BackedEnum|Htmlable,
     *     move_all_left?: string|BackedEnum|Htmlable,
     *     move_up?: string|BackedEnum|Htmlable,
     *     move_down?: string|BackedEnum|Htmlable,
     * }  $icons
     */
    public function icons(array|Closure $icons): static
    {
        $icons = $this->evaluate($icons);

        if (isset($icons['search'])) {
            $this->searchIcon = $icons['search'];
        }

        if (isset($icons['move_all_right'])) {
            $this->moveAllRightIcon = $icons['move_all_right'];
        }

        if (isset($icons['move_right'])) {
            $this->moveRightIcon = $icons['move_right'];
        }

        if (isset($icons['swap'])) {
            $this->swapIcon = $icons['swap'];
        }

        if (isset($icons['move_left'])) {
            $this->moveLeftIcon = $icons['move_left'];
        }

        if (isset($icons['move_all_left'])) {
            $this->moveAllLeftIcon = $icons['move_all_left'];
        }

        if (isset($icons['move_up'])) {
            $this->moveUpIcon = $icons['move_up'];
        }

        if (isset($icons['move_down'])) {
            $this->moveDownIcon = $icons['move_down'];
        }

        return $this;
    }

    public function searchIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->searchIcon = $icon;

        return $this;
    }

    public function moveAllRightIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->moveAllRightIcon = $icon;

        return $this;
    }

    public function moveRightIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->moveRightIcon = $icon;

        return $this;
    }

    public function swapIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->swapIcon = $icon;

        return $this;
    }

    public function moveLeftIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->moveLeftIcon = $icon;

        return $this;
    }

    public function moveAllLeftIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->moveAllLeftIcon = $icon;

        return $this;
    }

    public function moveUpIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->moveUpIcon = $icon;

        return $this;
    }

    public function moveDownIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->moveDownIcon = $icon;

        return $this;
    }

    public function getDefaultSearchIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_search_icon', FlexIcon::Magnifier);
    }

    public function getDefaultMoveAllRightIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_move_all_right_icon', FlexIcon::ArrowChevronRight);
    }

    public function getDefaultMoveRightIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_move_right_icon', FlexIcon::ArrowRight);
    }

    public function getDefaultSwapIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_swap_icon', FlexIcon::ArrowRightArrowLeft);
    }

    public function getDefaultMoveLeftIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_move_left_icon', FlexIcon::ArrowLeft);
    }

    public function getDefaultMoveAllLeftIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_move_all_left_icon', FlexIcon::ArrowChevronLeft);
    }

    public function getDefaultMoveUpIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_move_up_icon', FlexIcon::ChevronUp);
    }

    public function getDefaultMoveDownIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveDefaultIcon('dual_listbox_move_down_icon', FlexIcon::ChevronDown);
    }

    public function getSearchIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->searchIcon) ?? $this->getDefaultSearchIcon();
    }

    public function getMoveAllRightIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->moveAllRightIcon) ?? $this->getDefaultMoveAllRightIcon();
    }

    public function getMoveRightIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->moveRightIcon) ?? $this->getDefaultMoveRightIcon();
    }

    public function getSwapIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->swapIcon) ?? $this->getDefaultSwapIcon();
    }

    public function getMoveLeftIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->moveLeftIcon) ?? $this->getDefaultMoveLeftIcon();
    }

    public function getMoveAllLeftIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->moveAllLeftIcon) ?? $this->getDefaultMoveAllLeftIcon();
    }

    public function getMoveUpIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->moveUpIcon) ?? $this->getDefaultMoveUpIcon();
    }

    public function getMoveDownIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->moveDownIcon) ?? $this->getDefaultMoveDownIcon();
    }

    protected function resolveDefaultIcon(string $configKey, string|BackedEnum|Htmlable $fallback): string|BackedEnum|Htmlable
    {
        $icon = config("filament-flex-fields.ui.{$configKey}");

        if (is_string($icon) && filled($icon)) {
            return $icon;
        }

        return $fallback;
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['bordered', 'flat', 'faded'], true)) {
            throw new InvalidArgumentException("Dual listbox variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function getListHeight(): string
    {
        $height = $this->evaluate($this->listHeight);
        if (! is_string($height) || ! preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vh|dvh)$/D', $height) || (float) $height <= 0) {
            throw new InvalidArgumentException('List height must be a positive CSS length (px, rem, em, vh or dvh).');
        }

        return $height;
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->isSearchable);
    }

    public function isReorderable(): bool
    {
        return (bool) $this->evaluate($this->isReorderable);
    }

    public function isMoveOnDoubleClick(): bool
    {
        return (bool) $this->evaluate($this->moveOnDoubleClick);
    }

    public function showsTransferButtons(): bool
    {
        return (bool) $this->evaluate($this->showTransferButtons);
    }

    public function getAvailableLabel(): string
    {
        $label = $this->evaluate($this->availableLabel);

        return filled($label)
            ? (string) $label
            : Translations::get('filament-flex-fields::default.dual_listbox.available');
    }

    public function getSelectedLabel(): string
    {
        $label = $this->evaluate($this->selectedLabel);

        return filled($label)
            ? (string) $label
            : Translations::get('filament-flex-fields::default.dual_listbox.selected');
    }

    public function getMinItems(): ?int
    {
        $count = $this->evaluate($this->minItems);

        if ($count !== null && (! is_int($count) || $count < 0 || $count > 50000)) {
            throw new InvalidArgumentException('Item limits must be integers between 0 and 50000, or null.');
        }

        return $count;
    }

    public function getMaxItems(): ?int
    {
        $count = $this->evaluate($this->maxItems);

        if ($count !== null && (! is_int($count) || $count < 0 || $count > 50000)) {
            throw new InvalidArgumentException('Item limits must be integers between 0 and 50000, or null.');
        }

        return $count;
    }

    public function getExactItems(): ?int
    {
        $min = $this->getMinItems();
        $max = $this->getMaxItems();

        if ($min !== null && $min === $max) {
            return $min;
        }

        return null;
    }

    protected int|Closure $virtualThreshold = self::VIRTUAL_SCROLL_THRESHOLD;

    public function virtualThreshold(int|Closure $threshold): static
    {
        $this->virtualThreshold = $threshold;

        return $this;
    }

    public function getVirtualScrollThreshold(): int
    {
        $threshold = $this->evaluate($this->virtualThreshold);
        if (! is_int($threshold) || $threshold < 1 || $threshold > 50000) {
            throw new InvalidArgumentException('Virtual threshold must be between 1 and 50000.');
        }

        return $threshold;
    }
}
