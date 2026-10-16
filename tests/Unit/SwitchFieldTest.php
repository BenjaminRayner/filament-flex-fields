<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CellSwitch;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;
use Bjanczak\FilamentFlexFields\StateCasts\SwitchStateCast;
use Bjanczak\FilamentFlexFields\Support\GravityIcon;

it('exposes switch configuration via fluent api', function () {
    $field = SwitchField::make('animations')
        ->label('Animations')
        ->description('Enable motion effects')
        ->badge('New')
        ->badgeColor('primary')
        ->layout('card')
        ->labelPosition('end')
        ->compact()
        ->ripple()
        ->size(ControlSize::Lg)
        ->variant('secondary')
        ->color('success');

    expect($field->getSize())->toBe('lg')
        ->and($field->getVariant())->toBe('secondary')
        ->and($field->getLayout())->toBe('card')
        ->and($field->getLabelPosition())->toBe('end')
        ->and($field->isCompact())->toBeTrue()
        ->and($field->isRippleEnabled())->toBeTrue()
        ->and($field->getColor())->toBe('success')
        ->and($field->getDescription())->toBe('Enable motion effects')
        ->and($field->getBadge())->toBe('New')
        ->and($field->getBadgeColor())->toBe('primary');
});

it('supports toggle icons and on off colors like filament toggle', function () {
    $field = SwitchField::make('is_admin')
        ->onIcon(GravityIcon::Thunderbolt)
        ->offIcon(GravityIcon::Person)
        ->onColor('success')
        ->offColor('danger');

    expect($field->getOnIcon())->toBe(GravityIcon::Thunderbolt)
        ->and($field->getOffIcon())->toBe(GravityIcon::Person)
        ->and($field->getOnColor())->toBe('success')
        ->and($field->getOffColor())->toBe('danger')
        ->and($field->getEffectiveOnColor())->toBe('success')
        ->and($field->getEffectiveOffColor())->toBe('danger')
        ->and($field->hasOnIcon())->toBeTrue()
        ->and($field->hasOffIcon())->toBeTrue();
});

it('accepts heroicon or any icon set for on and off icons', function () {
    $field = SwitchField::make('is_admin')
        ->onIcon('heroicon-o-bolt')
        ->offIcon('heroicon-o-user');

    expect($field->getOnIcon())->toBe('heroicon-o-bolt')
        ->and($field->getOffIcon())->toBe('heroicon-o-user');
});

it('defaults switch color to primary and off color to gray', function () {
    $field = SwitchField::make('animations')
        ->label('Animations');

    expect($field->getColor())->toBe('primary')
        ->and($field->getEffectiveOnColor())->toBe('primary')
        ->and($field->getEffectiveOffColor())->toBe('gray')
        ->and($field->getLayout())->toBe('row')
        ->and($field->getLabelPosition())->toBe('start')
        ->and($field->getVariant())->toBe('default')
        ->and($field->isInlineToggle())->toBeFalse()
        ->and($field->isLabelHidden())->toBeTrue();
});

it('supports inline toggle without the track box', function () {
    $field = SwitchField::make('notifications')
        ->label('Notifications')
        ->inline()
        ->onColor('success');

    expect($field->isInlineToggle())->toBeTrue()
        ->and($field->showsInlineFieldLabel())->toBeFalse()
        ->and($field->getEffectiveOnColor())->toBe('success');
});

it('supports inline toggle with filament label and without the track box', function () {
    $field = SwitchField::make('notifications')
        ->label('Notifications')
        ->inlineWithLabel()
        ->labelPosition('end');

    expect($field->isInlineToggle())->toBeTrue()
        ->and($field->showsInlineFieldLabel())->toBeTrue()
        ->and($field->isLabelHidden())->toBeFalse()
        ->and($field->hasInlineLabel())->toBeTrue()
        ->and($field->getLabelPosition())->toBe('end');
});

it('rejects unsupported switch layouts', function () {
    SwitchField::make('notifications')
        ->layout('stack')
        ->getLayout();
})->throws(InvalidArgumentException::class);

it('supports accepted and declined validation like filament toggle', function () {
    $accepted = SwitchField::make('terms_of_service')->accepted();
    $declined = SwitchField::make('is_under_18')->declined();

    expect($accepted->getValidationRules())->toContain('accepted')
        ->and($declined->getValidationRules())->toContain('declined');
});

it('casts supported boolean values without coercing malformed data', function () {
    $field = SwitchField::make('is_admin');

    $casts = collect($field->getDefaultStateCasts());

    expect($casts->contains(fn ($cast): bool => $cast instanceof SwitchStateCast))->toBeTrue();
});

it('rejects unsupported label positions', function () {
    SwitchField::make('notifications')
        ->labelPosition('center')
        ->getLabelPosition();
})->throws(InvalidArgumentException::class);

it('keeps cell switch as a backward compatible alias', function () {
    $field = CellSwitch::make('legacy_toggle')
        ->label('Legacy toggle')
        ->color('danger');

    expect($field)->toBeInstanceOf(SwitchField::class)
        ->and($field->getColor())->toBe('danger');
});

it('rejects unsupported variants', function (): void {
    SwitchField::make('flag')->variant('unknown')->getVariant();
})->throws(InvalidArgumentException::class);

it('keeps malformed values for authoritative validation', function (): void {
    $cast = new SwitchStateCast;
    foreach ([true, 1, '1'] as $value) {
        expect($cast->set($value))->toBeTrue();
    }
    foreach ([false, 0, '0', null] as $value) {
        expect($cast->set($value))->toBeFalse();
    }
    foreach ([[], ['admin'], 'false', 'true', 'invalid', 2, ''] as $value) {
        expect($cast->set($value))->toBe($value);
    }
});

it('can restore row mode after configuring an inline field label', function (): void {
    $field = SwitchField::make('flag')->inlineWithLabel()->inline(false);
    expect($field->isInlineToggle())->toBeFalse()
        ->and($field->showsInlineFieldLabel())->toBeFalse()
        ->and($field->isLabelHidden())->toBeTrue()
        ->and($field->hasInlineLabel())->toBeFalse();
    $field->inlineWithLabel(false);
    expect($field->isLabelHidden())->toBeTrue()
        ->and($field->hasInlineLabel())->toBeFalse();
});
