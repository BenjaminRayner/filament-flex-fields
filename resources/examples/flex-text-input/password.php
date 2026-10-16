<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;

FlexTextInput::make('flex_text_input__hold_password')
    ->label('Hold to reveal · conceals on blur')
    ->password(true)
    ->revealable(true)
    ->revealMode('hold')
    ->concealOnBlur(true)
    ->capsLockIndicator(true)
    ->passwordStrength(true);

FlexTextInput::make('flex_text_input__custom_password')
    ->label('Custom strength labels & reveal icons')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordStrengthLabels([
        'very_weak' => '0/4',
        'weak' => '1/4',
        'fair' => '2/4',
        'good' => '3/4',
        'strong' => '4/4',
    ])
    ->showPasswordIcon('hugeicons-view')
    ->hidePasswordIcon('hugeicons-view-off');

FlexTextInput::make('flex_text_input__revealMode_0')
    ->label('revealMode(toggle)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->revealMode('toggle');

FlexTextInput::make('flex_text_input__revealMode_1')
    ->label('revealMode(hold)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->revealMode('hold');

FlexTextInput::make('flex_text_input__concealOnBlur_0')
    ->label('concealOnBlur(false)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->concealOnBlur(false);

FlexTextInput::make('flex_text_input__concealOnBlur_1')
    ->label('concealOnBlur(true)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->concealOnBlur(true);

FlexTextInput::make('flex_text_input__capsLockIndicator_0')
    ->label('capsLockIndicator(false)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->capsLockIndicator(false);

FlexTextInput::make('flex_text_input__capsLockIndicator_1')
    ->label('capsLockIndicator(true)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->capsLockIndicator(true);

FlexTextInput::make('flex_text_input__revealTimeout_0')
    ->label('revealTimeout(3000)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->revealTimeout(3000);

FlexTextInput::make('flex_text_input__passwordRequirements_0')
    ->label('passwordRequirements(…)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ]);

FlexTextInput::make('flex_text_input__passwordRequirementsVisible_0')
    ->label('passwordRequirementsVisible(false)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->passwordRequirementsVisible(false);

FlexTextInput::make('flex_text_input__passwordRequirementsVisible_1')
    ->label('passwordRequirementsVisible(true)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->passwordRequirementsVisible(true);

FlexTextInput::make('flex_text_input__passwordStrengthAlgorithm_0')
    ->label('passwordStrengthAlgorithm(balanced)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->passwordStrengthAlgorithm('balanced');

FlexTextInput::make('flex_text_input__passwordStrengthAlgorithm_1')
    ->label('passwordStrengthAlgorithm(requirements)')
    ->password(true)
    ->revealable(true)
    ->passwordStrength(true)
    ->passwordRequirements([
        'min' => 12,
        'uppercase' => 1,
        'lowercase' => 1,
        'numbers' => 1,
        'symbols' => 1,
    ])
    ->passwordStrengthAlgorithm('requirements');
