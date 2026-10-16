<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexVerificationCode;
use Filament\Actions\Action;

FlexVerificationCode::make('otp')
    ->label('Verification code')
    ->length(6)
    ->groups([3, 3])
    ->groupSeparator('-')
    ->required();

FlexVerificationCode::make('backup_code')
    ->length(8)
    ->groups([4, 4])
    ->allowedCharacters('alphanumeric')
    ->size('sm')
    ->rounding('full');

// A public Livewire method receives the complete string; it verifies the OTP itself.
FlexVerificationCode::make('verification_code')
    ->autoSubmitMethod('verifyOtp')
    ->loading();

// Choose a PHP callback instead of autoSubmitMethod() for this field.
FlexVerificationCode::make('callback_code')
    ->submitUsing(function (string $code, $livewire): void {
        $livewire->verifyOtp($code);
    })
    ->loading();

FlexVerificationCode::make('account_code')
    ->hiddenLabel()
    ->heading('Verify your account')
    ->description('Enter the code sent to your email.')
    ->footer("Didn't receive a code?")
    ->footerAction(Action::make('resendOtp')->label('Resend')->action('resendOtp'))
    ->groups([3, 3])
    ->groupSeparator('-');

FlexVerificationCode::make('disabled_code')
    ->default('012345')
    ->disabled();
