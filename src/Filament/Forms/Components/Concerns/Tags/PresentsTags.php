<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Tags;

trait PresentsTags
{
    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-tags-field', 'fff-flex-text-input',
            'fff-tags-field--'.$this->getSize(), 'fff-flex-text-input--'.$this->getSize(),
            'fff-tags-field--'.$this->getVariant(), 'fff-flex-text-input--'.$this->getVariant(),
            'fff-rounding-'.$this->getRounding(), 'fi-color-'.($this->getColor() ?? 'primary')];
    }

    public function getTagDisplayLabel(string $tag): string
    {
        return ($this->getTagPrefix() ?? '').$tag.($this->getTagSuffix() ?? '');
    }

    /** @return list<string> */
    public function getInitialTagsForSsr(): array
    {
        return $this->normalizeTagsState($this->getState());
    }

    /** @return array<string, mixed> */
    public function getTagSelectConfiguration(): array
    {
        $remote = $this->shouldSearchSuggestions();

        return [...$this->getSelectSurfaceConfiguration(),
            'initialState' => $this->getInitialTagsForSsr(),
            'options' => array_map(static fn (string $value): array => ['value' => $value, 'label' => $value], $this->getSuggestionsForJs()),
            'componentKey' => $this->getKey(), 'statePath' => $this->getStatePath(),
            'livewireId' => $this->getLivewire()->getId(),
            'disabled' => $this->isDisabled(), 'maxItems' => $this->getMaxTags(),
            'maxItemsMessage' => __('filament-flex-fields::default.validation.tags.max', ['max' => $this->getMaxTags()]),
            'isReorderable' => $this->isReorderable(), 'searchDebounce' => $this->getSearchDebounce(),
            'hasDynamicSearchResults' => $remote, 'hasClientSideOptionList' => ! $remote,
            'minSearchLength' => $remote ? $this->getMinSearchLength() : 0,
            'optionsLimit' => 0, 'isHtmlAllowed' => false,
            'searchPrompt' => $this->getPlaceholder(),
            'selectEmptyStateHints' => ['minSearchLength' => __('filament-flex-fields::default.tags.suggestions.min_chars', ['min' => $this->getMinSearchLength()])],
            'searchingMessage' => __('filament-flex-fields::default.tags.suggestions.loading'),
            'noSearchResultsMessage' => __('filament-flex-fields::default.tags.suggestions.no_results'),
            'noOptionsMessage' => __('filament-flex-fields::default.tags.suggestions.no_results'),
        ];
    }
}
