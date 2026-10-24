@php
    $fffPresentations = app(\Bjanczak\FilamentFlexFields\Support\Rendering\BladePresentation::class);
    use Filament\Support\Enums\IconSize;

    $fieldWrapperView = $getFieldWrapperView();
    $clearIconHtml = app(\Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon::class)->render($field->getClearIcon(), attributes: new \Illuminate\View\ComponentAttributeBag(['stroke-width' => 2]), size: IconSize::ExtraSmall);
    $chevronIconHtml = app(\Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon::class)->render($field->getChevronIcon(), attributes: new \Illuminate\View\ComponentAttributeBag(['stroke-width' => 2]), size: IconSize::ExtraSmall);
    $extraInputAttributeBag = $getExtraInputAttributeBag();
    $canSelectPlaceholder = $canSelectPlaceholder();
    $isAutofocused = $isAutofocused();
    $isDisabled = $field->isSelectControlDisabled();
    $isMultiple = $isMultiple();
    $isReorderable = $isReorderable();
    $isSearchable = $isSearchable();
    $hasDynamicSearchResults = $hasDynamicSearchResults();
    $hasInitialNoOptionsMessage = $hasInitialNoOptionsMessage();
    $hasDynamicOptions = $hasDynamicOptions();
    $hasClientSideOptionList = $hasClientSideOptionList();
    $canOptionLabelsWrap = $canOptionLabelsWrap();
    $isRequired = $isRequired();
    $isConcealed = $isConcealed();
    $usesRichOptionHtml = $usesRichOptionHtml();
    $optionLayout = $getOptionLayout();
    $isHtmlAllowed = $isHtmlAllowed() || $usesRichOptionHtml;
    $isNative = (! ($isSearchable || $isMultiple || $isHtmlAllowed) && $isNative());
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $key = $getKey();
    $id = $getId();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixIconColor = $getPrefixIconColor();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixIconColor = $getSuffixIconColor();
    $suffixLabel = $getSuffixLabel();
    $statePath = $getStatePath();
    $state = $getRawState();
    $livewireKey = $getLivewireKey();
    $wrapperClasses = $getWrapperClasses();
    $fieldLabel = $getLabel();
    $showInlineFieldLabel = $hasInlineFieldLabel() && filled($fieldLabel) && ! $isLabelHidden();
    $isInlineSearch = $hasInlineSearch();
    $isGridLayout = $optionLayout === 'grid';
    $useRichListTriggerDisplay = $field->shouldUseRichListTriggerDisplay();
    $useRichListDropdownLayout = $field->shouldUseRichListDropdownLayout();
    $variant = $getVariant();
    $isItemCardVariant = $variant === 'item-card';
    $itemCardInitialTriggerLabel = $field->getItemCardInitialTriggerLabel();
    $isInitialTriggerPlaceholder = blank($state);
    $isUserSelectField = method_exists($field, 'renderUserOption');
    // The guarded stock renderer has no custom view data or partials. Reuse
    // this instance's bootstrap snapshot for its SSR label/chips; never share
    // selections, resolver results or callbacks between fields/renders.
    $reuseInitialPresentation = ($fffStockViewContext ?? false) && ! $isNative;
    if ($reuseInitialPresentation) {
        $headlessAlpineConfig = $getHeadlessAlpineConfig();
    }
    $initialTriggerLabel = $reuseInitialPresentation && filled($state)
        ? $headlessAlpineConfig['initialOptionLabel'] : $field->getInitialTriggerLabel();
    $initialTriggerBadges = $reuseInitialPresentation && filled($state) && ! $isItemCardVariant
        ? ($isMultiple ? array_map(static fn (array $entry): array => [
            'value' => $entry['value'], 'label' => $entry['triggerLabel'] ?? $entry['label'],
        ], $headlessAlpineConfig['initialOptionLabels']) : []) : $field->getInitialTriggerBadges();
    $selectedOptionCheckIconHtml = $reuseInitialPresentation
        ? $headlessAlpineConfig['selectedOptionCheckIconHtml']
        : (app(\Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon::class)->render($field->getSelectedOptionCheckIcon(), size: IconSize::Small));
    $initialUserSelectDisplay = $isUserSelectField && $isMultiple ? $field->getInitialUserSelectDisplay() : null;
    $initialMultipleTriggerHtml = $initialUserSelectDisplay['triggerHtml'] ?? null;
    $userSelectTagsHtml = $initialUserSelectDisplay['tagsHtml'] ?? null;
    $initialSelectedUserEntriesForJs = $isUserSelectField ? $field->getInitialSelectedUserEntriesForJs() : [];
    $chipRemoveIconHtml = ($fffStockViewContext ?? false) && ! $isMultiple && ! $isUserSelectField
        ? '' : view('filament-flex-fields::forms.components.partials.tag-pill-remove-icon')->render();
    $tagRemoveIconHtml = $isUserSelectField
        ? $chipRemoveIconHtml
        : $clearIconHtml;
    $skipInitialOptionLabels = $isUserSelectField
        && $isMultiple
        && filled($initialSelectedUserEntriesForJs ?? []);
    $showInitialTriggerSsr = ! $isNative
        && ! $isItemCardVariant
        && (
            $initialTriggerLabel !== null
            || $initialTriggerBadges !== []
            || ($initialMultipleTriggerHtml ?? null) !== null
            || ($isMultiple && filled($getPlaceholder()))
        );
    $showItemCardTriggerSsr = ! $isNative
        && $isItemCardVariant
        && $itemCardInitialTriggerLabel !== null;
@endphp

@php
    $fffPresentationView = $fffPresentations->view('filament-flex-fields::field-wrapper', [
        'component' => $fieldWrapperView,
        'field' => $field,
        'labelSrOnly' => $showInlineFieldLabel || $isLabelHidden(),
        'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'fi-fo-select-wrp fff-select-field-wrapper fi-fixed-positioning-context', 'data-fff-lazy-alpine-mount' => 'select-field'])
    ]);
    if ($fffPresentationView['view'] !== null):
@endphp
@component($fffPresentationView['view'], $fffPresentationView['data'])
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => $field->getAssetComponent(),
        'livewireKey' => $getLivewireKey(),
    ])
    {{ $field->getSelectCatalogMarkup() }}
    @php
    $fffPresentationView = $fffPresentations->view('filament::input.wrapper', [
        'disabled' => $isDisabled,
        'inlinePrefix' => $isPrefixInline,
        'inlineSuffix' => $isSuffixInline,
        'prefix' => $prefixLabel,
        'prefixActions' => $prefixActions,
        'prefixIcon' => $prefixIcon,
        'prefixIconColor' => $prefixIconColor,
        'suffix' => $suffixLabel,
        'suffixActions' => $suffixActions,
        'suffixIcon' => $suffixIcon,
        'suffixIconColor' => $suffixIconColor,
        'valid' => ! $errors->has($statePath),
        'attributes' => (
            \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
                ->class([
                    'fi-fo-select',
                    'fi-fo-select-has-inline-prefix' => $isPrefixInline && (count($prefixActions) || $prefixIcon || filled($prefixLabel)),
                    'fi-fo-select-native' => $isNative,
                    ...$wrapperClasses,
                ])
        )->merge(['x-on:focus-input.stop' => $isNative ? '$el.querySelector(\'select\')?.focus()' : '$el.querySelector(\'.fi-select-input-btn\')?.focus()'])
    ]);
    if ($fffPresentationView['view'] !== null):
@endphp
@component($fffPresentationView['view'], $fffPresentationView['data'])
        @if ($isNative)
            @php
                $options = $getOptions();
            @endphp

            <select
                {{
                    $extraInputAttributeBag
                        ->merge([
                            'autofocus' => $isAutofocused,
                            'disabled' => $isDisabled,
                            'id' => $id,
                            'required' => $isRequired && (! $isConcealed),
                            'wire:key' => $hasDynamicOptions ? ($livewireKey . '.' . substr(md5(serialize($options)), 0, 64)) : null,
                            $applyStateBindingModifiers('wire:model') => $statePath,
                        ], escape: false)
                        ->class([
                            'fi-select-input',
                            'fi-select-input-has-inline-prefix' => $isPrefixInline && (count($prefixActions) || $prefixIcon || filled($prefixLabel)),
                        ])
                }}
            >
                @if ($canSelectPlaceholder)
                    <option value="">
                        @if (! $isDisabled)
                            {{ $getPlaceholder() }}
                        @endif
                    </option>
                @endif

                @foreach ($options as $value => $label)
                    @if (is_array($label))
                        <optgroup label="{{ $value }}">
                            @foreach ($label as $groupedValue => $groupedLabel)
                                <option
                                    @disabled($isOptionDisabled($groupedValue, $groupedLabel))
                                    value="{{ $groupedValue }}"
                                >
                                    {{ is_array($groupedLabel) ? ($groupedLabel['label'] ?? $groupedValue) : $groupedLabel }}
                                </option>
                            @endforeach
                        </optgroup>
                    @else
                        <option
                            @disabled($isOptionDisabled($value, $label))
                            value="{{ $value }}"
                        >
                            {{ is_array($label) ? ($label['label'] ?? $value) : $label }}
                        </option>
                    @endif
                @endforeach
            </select>
            <span class="fff-select-native-chevron" aria-hidden="true">{!! $chevronIconHtml !!}</span>
        @else
            @include('filament-flex-fields::forms.components.partials.select-field-headless')
        @endif
    @endcomponent
@php endif; @endphp

    @if ($isUserSelectField && $isMultiple && $field->shouldRenderMultipleUserTags())
        @if (($userSelectTagsHtml ?? null) !== null)
            {!! $userSelectTagsHtml !!}
        @else
            <div class="fff-user-select__selected-tags" data-fff-user-select-tags hidden></div>
        @endif
    @endif
@endcomponent
@php endif; @endphp
