<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone\PhoneStateNormalizer;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\StateCasts\PhoneFieldStateCast;
use Closure;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** @mixin PhoneField */
trait NormalizesPhoneState
{
    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), app(PhoneFieldStateCast::class, ['field' => $this])];
    }

    public function getRequiredValidationRule(): string|Closure
    {
        return 'nullable';
    }

    /** @return array<string, mixed> */
    public function normalizeState(mixed $state): array
    {
        return (new PhoneStateNormalizer($this))->normalize($state);
    }

    /** @param array<string, mixed> $state */
    public function getPhoneValidationMessage(array $state): ?string
    {
        return (new PhoneStateNormalizer($this))->validationMessage($state);
    }
}
