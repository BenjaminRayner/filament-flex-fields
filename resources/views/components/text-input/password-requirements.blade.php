@if ($options['isPassword'] && $options['passwordRequirementsVisible'] && $options['passwordRequirements'] !== [])
    @php
        $checks = \Bjanczak\FilamentFlexFields\Support\TextInput\PasswordPolicy::checks($presentation->value, $options['passwordRequirements']);
    @endphp
    <ul role="list" class="fff-flex-text-input__requirements" aria-label="{{ __('filament-flex-fields::text-input.requirements_label') }}">
        @foreach ($options['passwordRequirements'] as $name => $minimum)
            <li role="listitem" class="fff-flex-text-input__requirement" data-met="{{ $checks[$name] ? 'true' : 'false' }}"
                x-bind:data-met="String(passwordRequirementChecks['{{ $name }}'])">
                <span class="fff-flex-text-input__requirement-indicator" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html('hugeicons-tick-02', attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-text-input__requirement-tick'])) }}
                </span>
                <span class="fff-flex-text-input__requirement-label">
                    {{ trans_choice('filament-flex-fields::text-input.requirements.'.$name, $minimum, ['count' => $minimum]) }}
                    <span class="sr-only" x-text="passwordRequirementChecks['{{ $name }}'] ? options.messages.requirement_met : options.messages.requirement_not_met">{{ $checks[$name] ? __('filament-flex-fields::text-input.requirement_met') : __('filament-flex-fields::text-input.requirement_not_met') }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
