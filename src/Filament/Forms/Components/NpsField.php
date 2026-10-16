<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NpsField\ConfiguresNpsAppearance;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NpsField\ConfiguresNpsOptions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NpsField\PresentsNpsField;
use Bjanczak\FilamentFlexFields\StateCasts\NpsStateCast;
use Bjanczak\FilamentFlexFields\Support\Nps\OptionValue;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Validation\Rule;

class NpsField extends Field
{
    use ConfiguresNpsAppearance;
    use ConfiguresNpsOptions;
    use HasControlSize;
    use HasFieldRounding;
    use PresentsNpsField;

    protected string $view = 'filament-flex-fields::forms.components.nps-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default(null);
        $this->rule('nullable');
        $this->rule(static fn (NpsField $component) => Rule::in($component->getEnabledOptionKeys()));
        $this->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
            if (OptionValue::identity($value) === null) {
                $fail(__('validation.in', ['attribute' => $attribute]));
            }
        });
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new NpsStateCast($this->getOptions())];
    }
}
