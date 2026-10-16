<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\CreditCard\ConfiguresCreditCard;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\CreditCard\PresentsCreditCard;
use Bjanczak\FilamentFlexFields\StateCasts\CreditCardStateCast;
use Bjanczak\FilamentFlexFields\Support\CreditCard\CardValues;
use Bjanczak\FilamentFlexFields\Support\CreditCard\Storage\CardStorage;
use Bjanczak\FilamentFlexFields\Support\CreditCard\Validation\CardRule;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class CreditCardField extends Field
{
    use CanBeReadOnly;
    use ConfiguresCreditCard;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use PresentsCreditCard;

    protected string $view = 'filament-flex-fields::forms.components.credit-card-field';

    private ?CardStorage $cardStorage = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(fn (CreditCardField $component): CardRule => new CardRule($component));
        $this->dehydrateStateUsing(fn (CreditCardField $component, mixed $state): array => $component->storage()->dehydrate($state));
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new CreditCardStateCast];
    }

    public function getRequiredValidationRule(): string|Closure
    {
        return 'nullable';
    }

    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    public function storage(): CardStorage
    {
        return $this->cardStorage ??= new CardStorage;
    }

    /** @param array{number:string,name:string,expiry:string,cvv:string} $normalized */
    public function passesPciTokenizationRules(array $normalized, Closure $fail): bool
    {
        return $this->storage()->validateTokenization($normalized['number'], $fail);
    }

    /** @param array<string,mixed> $state
     * @return array<string,string> */
    public function dehydrateStateForStorage(array $state): array
    {
        return $this->storage()->dehydrate($state);
    }

    public function passesLuhnCheck(string $number): bool
    {
        return CardValues::passesLuhn($number);
    }

    /** Presentation normalization only; submission validation never repairs input.
     * @param array<string,mixed> $state
     * @return array{number:string,name:string,expiry:string,cvv:string} */
    public function normalizeState(array $state): array
    {
        return CardValues::preview($state);
    }

    public function getExpiryValidationMessage(string $expiry): ?string
    {
        return CardValues::expiryError($expiry);
    }
}
