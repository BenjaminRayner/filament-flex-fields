<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndex;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogResolver;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

/** @mixin IconPickerField */
trait ConfiguresIconPicker
{
    protected string|Closure $variant = 'bordered';

    protected bool $inlineTrigger = false;

    public function inlineTrigger(bool $condition = true): static
    {
        $this->inlineTrigger = $condition;

        return $this;
    }

    public function isInlineTrigger(): bool
    {
        return $this->inlineTrigger;
    }

    protected bool|Closure|null $clearable = null;

    /**
     * @var list<string>|string|Closure|null
     */
    protected array|string|Closure|null $sets = null;

    /**
     * @var list<string>|Closure|null
     */
    protected array|Closure|null $icons = null;

    /**
     * @var list<string>|Closure
     */
    protected array|Closure $excludeIcons = [];

    protected string|Closure $searchResultsLayout = 'icons';

    protected bool|Closure $closeOnSelect = true;

    protected int|Closure $gridColumns = 8;

    protected bool|Closure $preload = false;

    protected int|Closure|null $limitPerSet = null;

    protected int|Closure $perPage = IconCatalogResolver::DEFAULT_PER_PAGE;

    /**
     * @var array<string, array{prefix: string, label: string, icons: list<string>}>|null
     */
    protected ?array $memoizedCatalog = null;

    protected ?IconCatalogIndex $memoizedIndex = null;

    protected ?string $memoizedCatalogSignature = null;

    protected ?string $memoizedIndexSignature = null;

    protected ?string $memoizedSearchFingerprint = null;

    protected function forgetIconCatalogSnapshot(): void
    {
        $this->memoizedCatalog = null;
        $this->memoizedIndex = null;
        $this->memoizedCatalogSignature = null;
        $this->memoizedIndexSignature = null;
        $this->memoizedSearchFingerprint = null;
    }

    public function clearable(bool|Closure $condition = true): static
    {
        $this->clearable = $condition;

        return $this;
    }

    public function isClearable(): bool
    {
        if ($this->clearable !== null) {
            return (bool) $this->evaluate($this->clearable);
        }

        return true;
    }

    /**
     * @param  list<string>|string|Closure|null  $sets
     */
    public function sets(array|string|Closure|null $sets = null): static
    {
        $this->sets = $sets;
        $this->forgetIconCatalogSnapshot();

        return $this;
    }

    /**
     * @param  list<string>|Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;
        $this->forgetIconCatalogSnapshot();

        return $this;
    }

    /**
     * @param  list<string>|Closure  $icons
     */
    public function excludeIcons(array|Closure $icons): static
    {
        $this->excludeIcons = $icons;
        $this->forgetIconCatalogSnapshot();

        return $this;
    }

    public function searchResultsLayout(string|Closure $layout): static
    {
        $this->searchResultsLayout = $layout;

        return $this;
    }

    public function grid(): static
    {
        return $this->searchResultsLayout('grid');
    }

    public function list(): static
    {
        return $this->searchResultsLayout('list');
    }

    public function iconsOnly(): static
    {
        return $this->searchResultsLayout('icons');
    }

    public function closeOnSelect(bool|Closure $condition = true): static
    {
        $this->closeOnSelect = $condition;

        return $this;
    }

    public function gridColumns(int|Closure $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    public function preload(bool|Closure $condition = true): static
    {
        $this->preload = $condition;

        return $this;
    }

    public function limitPerSet(int|Closure|null $limit): static
    {
        $this->limitPerSet = $limit;
        $this->forgetIconCatalogSnapshot();

        return $this;
    }

    public function perPage(int|Closure $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = PickerConfiguration::text($this->evaluate($this->variant), 'variant');

        if ($variant === 'primary') {
            $variant = 'bordered';
        }

        return PickerConfiguration::choice($variant, ['bordered', 'secondary', 'flat', 'soft', 'faded', 'underlined'], 'variant');
    }

    public function getSearchResultsLayout(): string
    {
        $layout = PickerConfiguration::text($this->evaluate($this->searchResultsLayout), 'searchResultsLayout');

        if (! in_array($layout, ['grid', 'list', 'icons'], true)) {
            throw new InvalidArgumentException("Icon picker search layout [{$layout}] is not supported.");
        }

        return $layout;
    }

    public function shouldCloseOnSelect(): bool
    {
        return (bool) $this->evaluate($this->closeOnSelect);
    }

    public function getGridColumns(): int
    {
        return max(2, min(12, PickerConfiguration::integer($this->evaluate($this->gridColumns), 'gridColumns')));
    }

    public function shouldPreload(): bool
    {
        return (bool) $this->evaluate($this->preload);
    }

    public function getLimitPerSet(): ?int
    {
        $limit = $this->evaluate($this->limitPerSet);

        return $limit === null ? null : max(0, PickerConfiguration::integer($limit, 'limitPerSet'));
    }

    public function getPerPage(): int
    {
        return max(1, min(PickerConfiguration::integer($this->evaluate($this->perPage), 'perPage'), IconCatalogResolver::MAX_PER_PAGE));
    }
}
