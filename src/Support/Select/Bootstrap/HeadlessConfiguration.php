<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Bootstrap;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Filament\Support\Enums\IconSize;

/** One bootstrap snapshot; no persistent cache of tenant-dependent field data. */
final class HeadlessConfiguration
{
    public function __construct(private readonly SelectField $field) {}

    /** @return array<string, mixed> */
    public function build(?string $livewireId): array
    {
        $field = $this->field;
        $users = method_exists($field, 'renderUserOption');
        $multiple = $field->isMultiple();
        $searchable = $field->isSearchable();
        $search = $field->hasDynamicSearchResults();
        $pages = $field->hasPaginatedSearchResults();
        $dynamic = $field->hasDynamicOptions();
        $state = $field->getRawState();

        return [
            'statePath' => $field->getStatePath(),
            'dependencyStatePaths' => $field->getSelectDependencyStatePaths(),
            'resetOnDependencyChange' => $field->shouldResetOnDependencyChange(),
            'componentKey' => $field->getKey(),
            'menuDomId' => $field->getId().'-fff-headless-menu',
            'livewireId' => $livewireId,
            ...$this->selection($state, $multiple, $users),
            ...$this->presentation($users, $searchable),
            ...$this->requests($search, $pages, $dynamic, $users),
            ...$this->messages($searchable, $search || $pages || $dynamic, $pages),
            ...$this->smartSuggestions(),
            ...$field->getSelectSurfaceConfiguration(),
        ];
    }

    /** @return array<string, mixed> */
    private function selection(mixed $state, bool $multiple, bool $users): array
    {
        $field = $this->field;
        // UserSelected entries may resolve records. Evaluate once for this payload.
        $userEntries = $users && method_exists($field, 'getInitialSelectedUserEntriesForJs')
            ? $field->getInitialSelectedUserEntriesForJs() : [];
        $labels = [];
        if (filled($state) && $multiple && ! ($users && filled($userEntries))) {
            $labels = $users ? $field->getOptionLabelsForJs() : $field->getHeadlessInitialOptionLabelsForJs();
        }

        return [
            'initialState' => $state,
            'multiple' => $multiple,
            'options' => $users && method_exists($field, 'getInitialOptionsForJs')
                ? $field->getInitialOptionsForJs() : $field->getHeadlessInitialOptionsForJs(),
            'initialOptionLabel' => blank($state) || $multiple ? null : $field->getInitialTriggerLabel(),
            'initialOptionLabels' => $labels,
            'initialSelectedUserEntries' => $userEntries,
            'keepSelectedOptionsInDropdown' => $field->shouldKeepSelectedOptionsInDropdown(),
            'clearable' => $field->isClearableInUi(),
            'isReorderable' => $field->isReorderable(),
            'maxItems' => $field->getMaxItems(),
            'maxItemsMessage' => $field->getMaxItemsMessage(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentation(bool $users, bool $searchable): array
    {
        $field = $this->field;

        return [
            'searchable' => $searchable,
            'placeholder' => $field->getPlaceholder(),
            'disabled' => $field->isSelectControlDisabled(),
            'isHtmlAllowed' => $field->isHtmlAllowed() || $field->usesRichOptionHtml(),
            'isGridLayout' => $field->getOptionLayout() === 'grid',
            'useRichListDropdownLayout' => $field->shouldUseRichListDropdownLayout(),
            'position' => $field->getPosition(),
            'canOptionLabelsWrap' => $field->canOptionLabelsWrap(),
            'inlineSearch' => $field->hasInlineSearch(),
            'optionGroupSeparators' => $field->hasOptionGroupSeparators(),
            'dropdownAlign' => $field->getDropdownAlign(),
            'matchTriggerWidth' => $field->getVariant() !== 'item-card',
            'isUserSelectField' => $users,
            'selectedOptionCheckIconHtml' => \Filament\Support\generate_icon_html($field->getSelectedOptionCheckIcon(), size: IconSize::Small)?->toHtml() ?? '',
            // Do not render the UserSelect partial for every plain Select field.
            'tagRemoveIconHtml' => $users ? view('filament-flex-fields::forms.components.partials.tag-pill-remove-icon')->render()
                : \Filament\Support\generate_icon_html($field->getClearIcon(), size: IconSize::ExtraSmall)?->toHtml() ?? '',
            'verifiedIconHtml' => $users ? $this->icon(FlexIcon::SealCheck, IconSize::ExtraSmall) : '',
            'userSelectNoOptionsIconHtml' => $users ? $this->icon(FlexIcon::Persons) : '',
            'userSelectNoResultsIconHtml' => $users ? $this->icon(FlexIcon::Magnifier) : '',
            'selectNoOptionsIconHtml' => ! $users ? $this->icon(FlexIcon::LayoutCells) : '',
            'selectNoResultsIconHtml' => ! $users && $searchable ? $this->icon(FlexIcon::Magnifier) : '',
            'userSelectEmptyStateHints' => $users && method_exists($field, 'getUserSelectEmptyStateHintsForJs')
                ? $field->getUserSelectEmptyStateHintsForJs() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function requests(bool $search, bool $pages, bool $dynamic, bool $users): array
    {
        $field = $this->field;

        return [
            'hasDynamicSearchResults' => $search,
            'hasPaginatedSearchResults' => $pages,
            'hasDynamicOptions' => $dynamic,
            'hasDynamicSelectedOptionLabels' => $field->hasDynamicSelectedOptionLabels(),
            'hasClientSideOptionList' => $field->hasClientSideOptionList(),
            'isPreloaded' => $field->isPreloaded(),
            'hasInitialNoOptionsMessage' => $field->hasInitialNoOptionsMessage(),
            'searchDebounce' => $field->getSearchDebounce(),
            'minSearchLength' => $users && method_exists($field, 'getMinSearchLength') ? (int) $field->getMinSearchLength() : 0,
            'optionsLimit' => $field->getOptionsLimit(),
            'maxPageBytes' => min(16777216, max(1, (int) config('filament-flex-fields.select.max_page_bytes', 1048576))),
            'searchableOptionFields' => $field->getSearchableOptionFields(),
        ];
    }

    /** @return array<string, mixed> */
    private function messages(bool $searchable, bool $async, bool $pages): array
    {
        $messages = $this->field->getSelectMessagesForJs();

        return [
            'loadingMessage' => $async ? $messages['loading'] : '',
            'searchingMessage' => $async ? $messages['searching'] : '',
            'loadingMoreMessage' => $async && $pages ? $messages['loadingMore'] : '',
            'noOptionsMessage' => $messages['noOptions'],
            'noMoreOptionsMessage' => $async && $pages ? ($messages['noMoreOptions'] ?? '') : '',
            'noSearchResultsMessage' => $searchable ? $messages['noSearchResults'] : '',
            'searchPrompt' => $searchable ? $messages['searchPrompt'] : '',
            'selectEmptyStateHints' => $searchable || $async ? $this->field->getSelectEmptyStateHintsForJs() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function smartSuggestions(): array
    {
        $smart = $this->field->getSmartSuggestConfigForJs();

        return [
            'smartSuggestEnabled' => $smart['enabled'],
            'recentOptionValues' => $smart['recent'],
            'suggestedOptionValues' => $smart['suggested'],
            'allowCreateOption' => $smart['allowCreate'],
            'createOptionLabel' => $smart['createLabel'],
            'entityMentionsEnabled' => $smart['entityMentions'],
            'mentionTrigger' => $smart['mentionTrigger'],
            'entityMentionSectionLabel' => __('filament-flex-fields::default.select_field.entity_mentions.section'),
        ];
    }

    private function icon(string $icon, IconSize $size = IconSize::Large): string
    {
        return \Filament\Support\generate_icon_html($icon, size: $size)?->toHtml() ?? '';
    }
}
