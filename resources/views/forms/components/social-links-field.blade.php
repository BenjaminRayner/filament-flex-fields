@php
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $ownerId = 'fff-social-'.md5($livewireKey);
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $locked = $isDisabled || $isReadOnly;
    $config = $field->getAlpineConfiguration();
    $config['disabled'] = $isDisabled;
    $config['readOnly'] = $isReadOnly;
    $config['statePath'] = $statePath;
    $config['showValidationErrors'] = $errors->has($statePath);
    $initialLinks = \Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialLinksNormalizer::normalize(\Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableHydrator::resolveRenderedState($field));
    $definitions = array_column($config['platforms'], null, 'value');
    $used = array_column($initialLinks, 'platform');
    $available = array_diff(array_keys($definitions), $used);
    $canAdd = ! $locked && $available !== [] && ($config['maxLinks'] === null || count($initialLinks) < $config['maxLinks']);
    $selected = array_values($available)[0] ?? '';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'social-links-field', 'livewireKey' => $livewireKey])
    <div wire:ignore wire:key="{{ $livewireKey }}.social.{{ md5(serialize([$config, $getSize(), $getVariant(), $getRounding(), $shouldShowFocusOutline(), $getLabel()])) }}"
        data-social-config="{{ json_encode($config, JSON_THROW_ON_ERROR) }}" data-social-owner="{{ $ownerId }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('social-links-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="socialLinksFieldFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        class="fff-social-links" role="group" aria-label="{{ $getLabel() }}">
        <div class="fff-social-links__toolbar">
            <x-filament-flex-fields::select-control
                :options="array_map(fn (array $platform): array => ['value' => $platform['value'], 'label' => $platform['label']], $config['platforms'])"
                options-expression="platformChoices" state-expression="selectedPlatform" on-change="values => selectPlatform(values[0] ?? '')"
                disabled-expression="!canAddMore" :disabled-initially="! $canAdd" :placeholder="$config['labels']['choosePlatform']" :label="$config['labels']['addPlatform']" :initial-value="$definitions[$selected]['label'] ?? $config['labels']['choosePlatform']"
                :size="$getSize()" :focus-outline="$shouldShowFocusOutline()" :variant="$getVariant() === 'ghost' ? 'flat' : $getVariant()" />
            <button type="button" class="fff-social-links__add-btn" data-social-add @disabled(! $canAdd)>{{ $config['labels']['add'] }}</button>
        </div>
        <p class="fff-social-links__empty" data-social-empty @if ($initialLinks !== []) hidden @endif>{{ $config['labels']['empty'] }}</p>
        <ul class="fff-social-links__list" data-social-list>
            @foreach ($initialLinks as $index => $link)
                @include('filament-flex-fields::forms.components.social-links.row', ['platform' => $link['platform'], 'url' => $link['url'], 'definition' => $definitions[$link['platform']] ?? []])
            @endforeach
        </ul>
        <template data-social-template>
            @include('filament-flex-fields::forms.components.social-links.row', ['platform' => '', 'url' => '', 'definition' => [], 'index' => 0])
        </template>
    </div>
</x-dynamic-component>
