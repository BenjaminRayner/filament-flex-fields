<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Select\Bootstrap\HeadlessConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\InitialSelectionLabels;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionLabelIndex;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Bjanczak\FilamentFlexFields\Support\Select\Data\TransportOptionTree;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @mixin SelectField
 */
trait InteractsWithSelectHeadlessRuntime
{
    protected bool $allowDeferredOptionResolution = false;

    public function shouldUseHeadlessEngine(): bool
    {
        // Mirror select-field.blade.php: native only when not searchable/multiple/HTML.
        return (bool) ($this->isSearchable() || $this->isMultiple() || $this->isHtmlAllowed() || $this->usesRichOptionHtml() || ! $this->isNative());
    }

    /**
     * Alpine bootstrap payload for the headless combobox (everything except Livewire `state`).
     *
     * @return array<string, mixed>
     */
    public function getHeadlessAlpineConfig(): array
    {
        return (new HeadlessConfiguration($this))->build($this->resolveHeadlessLivewireId());
    }

    protected function resolveHeadlessLivewireId(): ?string
    {
        try {
            return $this->getLivewire()->getId();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     pleaseWait: string,
     *     minSearchLength: string,
     *     filterList: string,
     *     tryDifferentSearch: string,
     *     noOptionsAvailable: string,
     *     allOptionsSelected: string,
     * }
     */
    public function getSelectEmptyStateHintsForJs(): array
    {
        $minSearchLength = method_exists($this, 'getMinSearchLength')
            ? (int) $this->getMinSearchLength()
            : 0;

        return [
            'pleaseWait' => Translations::get('filament-flex-fields::default.select_field.empty_hint.please_wait'),
            'minSearchLength' => Translations::get('filament-flex-fields::default.select_field.empty_hint.min_search_length', [
                'count' => $minSearchLength,
            ]),
            'filterList' => Translations::get('filament-flex-fields::default.select_field.empty_hint.filter_list'),
            'tryDifferentSearch' => Translations::get('filament-flex-fields::default.select_field.empty_hint.try_different_search'),
            'noOptionsAvailable' => Translations::get('filament-flex-fields::default.select_field.empty_hint.no_options_available'),
            'allOptionsSelected' => Translations::get('filament-flex-fields::default.select_field.empty_hint.all_options_selected'),
        ];
    }

    /**
     * @return array{
     *     loading: string,
     *     searching: string,
     *     loadingMore: string,
     *     noOptions: string,
     *     noMoreOptions: string,
     *     noSearchResults: string,
     *     searchPrompt: string,
     * }
     */
    public function getSelectMessagesForJs(): array
    {
        return [
            'loading' => $this->stringifySelectMessage($this->getLoadingMessage()),
            'searching' => $this->stringifySelectMessage($this->getSearchingMessage()),
            'loadingMore' => Translations::get('filament-flex-fields::default.select_field.loading_more'),
            'noOptions' => $this->stringifySelectMessage($this->getNoOptionsMessage()),
            'noMoreOptions' => Translations::get('filament-flex-fields::default.select_field.no_more_options'),
            'noSearchResults' => $this->stringifySelectMessage($this->getNoSearchResultsMessage()),
            'searchPrompt' => $this->stringifySelectMessage($this->getSearchPrompt()),
        ];
    }

    protected function stringifySelectMessage(Htmlable|string $message): string
    {
        return $message instanceof Htmlable ? $message->toHtml() : $message;
    }

    /**
     * Options embedded in the headless Alpine payload on first paint.
     *
     * Closure-based option lists are fetched lazily when the dropdown opens unless preloaded.
     *
     * @return list<array<string, mixed>>
     */
    public function getHeadlessInitialOptionsForJs(): array
    {
        if ($this->shouldDeferHeadlessOptionsUntilOpen()) {
            return [];
        }

        return array_values($this->getOptionsForJs());
    }

    /**
     * Selected-value labels for the headless Alpine payload.
     *
     * Unlike {@see Select::getOptionLabelsForJs()}, this keeps rich option metadata
     * (chip labels, descriptions, icons) so trigger chips match SSR on first paint.
     *
     * @return list<array{value: string, label: string, triggerLabel?: string}>
     */
    public function getHeadlessInitialOptionLabelsForJs(): array
    {
        $state = $this->resolveStateForItemCardTrigger();

        if (! is_array($state) || $state === []) {
            return [];
        }

        if ($this->getOptionLabelsUsing) {
            $labels = $this->getOptionLabels();
            $entries = [];
            foreach ($state as $candidate) {
                $value = OptionValue::normalize($candidate);
                if ($value === null) {
                    continue;
                }
                $label = (string) ($labels[$value] ?? $value);
                $entries[] = ['value' => (string) $value, 'label' => $label, 'triggerLabel' => $label];
            }

            return $entries;
        }

        $index = ($this->hasDynamicOptions() && ! $this->isPreloaded()) ? null
            : new OptionLabelIndex($this->getOptions(), fn (array $label): bool => $this->isOptionGroupArray($label));

        return (new InitialSelectionLabels)->project(array_values($state), $index,
            fn (string|int $value, array $label, bool $compact): string => $this->formatOptionLabelForJs($this->normalizeOption($value, $label), compact: $compact),
        );
    }

    protected function shouldDeferHeadlessOptionsUntilOpen(): bool
    {
        if ($this->isPreloaded()) {
            return false;
        }

        if ($this->options instanceof Closure) {
            return true;
        }

        if ($this->hasRelationship()) {
            return $this->hasDynamicOptions();
        }

        return false;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getFlatOptionsForHeadlessJs(): array
    {
        return $this->flattenOptionsForHeadless($this->getOptionsForJs());
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return list<array{value: string, label: string}>
     */
    protected function flattenOptionsForHeadless(array $options): array
    {
        $flat = [];
        foreach ((new TransportOptionTree)->leaves($options) as $option) {
            $flat[] = [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['triggerLabel'] ?? $option['label'] ?? $option['value'] ?? ''),
            ];
        }

        return $flat;
    }
}
