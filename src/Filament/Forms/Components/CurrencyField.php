<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\RendersStockFieldView;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency\ConfiguresCurrency;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency\NormalizesCurrencyState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency\PresentsCurrency;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency\ResolvesCurrencyCatalog;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Currency\CurrencyValueValidator;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

class CurrencyField extends Field
{
    use CanBeReadOnly;
    use ConfiguresCurrency;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;
    use NormalizesCurrencyState;
    use PresentsCurrency;
    use RendersStockFieldView;
    use ResolvesCurrencyCatalog;

    protected string $view = 'filament-flex-fields::forms.components.currency-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(fn (CurrencyField $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            CurrencyValueValidator::validate($component, $value, $fail);
        });
    }
}
