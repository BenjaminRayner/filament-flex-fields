@props(['size' => 'sm', 'variant' => 'primary', 'prefixIcon' => null])
<div class="fff-flex-text-input fff-flex-text-input--{{ $size }} fff-flex-text-input--{{ $variant }} has-focus-outline">
    <x-filament-flex-fields::text-input.shell>
        <x-filament::input.wrapper :prefix-icon="$prefixIcon" :inline-prefix="true" class="fff-flex-text-input__wrapper">
            <x-filament-flex-fields::text-input.control :attributes="$attributes->class(['fi-input-has-inline-prefix' => filled($prefixIcon)])" />
        </x-filament::input.wrapper>
    </x-filament-flex-fields::text-input.shell>
</div>
