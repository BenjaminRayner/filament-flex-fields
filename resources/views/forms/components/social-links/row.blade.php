<li class="fff-social-links__item" data-social-row data-platform="{{ $platform }}">
    <div class="fff-social-links__item-head">
        <span class="fff-social-links__item-icon" data-social-icon>{!! $config['brandIcons'][$platform] ?? '' !!}</span>
        <span class="fff-social-links__item-label" data-social-label>{{ $definition['label'] ?? $platform }}</span>
        <div class="fff-social-links__item-actions">
            @if ($config['reorderable'])
                <button type="button" class="fff-social-links__reorder-btn" data-social-action="up" @disabled($locked || $index === 0)
                    aria-label="{{ $config['labels']['moveUp'] }} {{ $definition['label'] ?? $platform }}"><x-filament::icon :icon="$config['icons']['chevronUp']" stroke-width="2" /></button>
                <button type="button" class="fff-social-links__reorder-btn" data-social-action="down" @disabled($locked || $index === count($initialLinks) - 1)
                    aria-label="{{ $config['labels']['moveDown'] }} {{ $definition['label'] ?? $platform }}"><x-filament::icon :icon="$config['icons']['chevronDown']" stroke-width="2" /></button>
            @endif
            <button type="button" class="fff-social-links__remove-btn" data-social-action="remove" @disabled($locked)
                aria-label="{{ $config['labels']['remove'] }} {{ $definition['label'] ?? $platform }}"><x-filament::icon :icon="$config['icons']['remove']" stroke-width="2" /></button>
        </div>
    </div>
    <div class="fff-flex-text-input fff-flex-text-input--{{ $getSize() }} fff-flex-text-input--{{ $getVariant() === 'ghost' ? 'flat' : $getVariant() }} fff-rounding-{{ $getRounding() }} {{ $shouldShowFocusOutline() ? 'has-focus-outline' : '' }}">
        <x-filament-flex-fields::text-input.shell>
            <x-filament::input.wrapper class="fff-flex-text-input__wrapper" :disabled="$isDisabled">
                <x-filament-flex-fields::text-input.control type="text" inputmode="url" autocomplete="url" autocapitalize="off" spellcheck="false"
                    :value="$url" :id="$ownerId.'-'.$platform" :placeholder="$definition['placeholder'] ?? 'https://'" :disabled="$isDisabled" :readonly="$isReadOnly"
                    :aria-label="($definition['label'] ?? $platform).' '.$config['labels']['url']" data-social-url maxlength="4096" />
            </x-filament::input.wrapper>
        </x-filament-flex-fields::text-input.shell>
    </div>
    <p class="fff-social-links__row-error" data-social-error id="{{ $ownerId.'-'.$platform }}-error" role="alert" hidden></p>
</li>
