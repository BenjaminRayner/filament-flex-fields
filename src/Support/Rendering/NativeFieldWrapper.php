<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CalculatorField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\FilamentApi;
use Filament\Forms\Components\Field;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\Factory;
use ReflectionClass;
use ReflectionMethod;

/** Reuse Filament's own renderer; unknown APIs and customized wrappers stay on Blade. */
final class NativeFieldWrapper
{
    private readonly bool $supported;

    private readonly string|false $stockView;

    public function __construct()
    {
        $method = FilamentApi::hasMethod(Field::class, 'wrapEmbeddedHtml')
            ? new ReflectionMethod(Field::class, 'wrapEmbeddedHtml') : null;
        $this->supported = $method !== null && $method->isPublic()
            && array_slice(array_map(static fn ($parameter): string => $parameter->getName(), $method->getParameters()), 0, 3)
                === ['html', 'extraWrapperAttributes', 'inlineLabelVerticalAlignment']
            && $method->getNumberOfRequiredParameters() <= 3;
        $source = (new ReflectionClass(Field::class))->getFileName();
        $this->stockView = $source === false ? false : realpath(dirname($source, 3).'/resources/views/components/field-wrapper.blade.php');
    }

    public function render(
        Field $field,
        string $component,
        string $html,
        ComponentAttributeBag $attributes,
        ?VerticalAlignment $alignment = null,
        ?bool $labelSrOnly = null,
    ): ?string {
        if (! $this->supported || ! in_array($field::class, [FlexTextInput::class, SelectField::class, FlexTextareaField::class, NumberStepper::class, CalculatorField::class, CountryField::class, TimezoneField::class, PhoneField::class, CurrencyField::class, FontSelect::class, IconPickerField::class], true)
            || $component !== 'filament-forms::field-wrapper'
            || ($labelSrOnly !== null && $labelSrOnly !== $field->isLabelHidden())) {
            return null;
        }
        $view = 'filament-forms::components.field-wrapper';
        /** @var Factory $factory */
        $factory = view();
        if ($this->stockView === false
            || realpath($factory->getFinder()->find($view)) !== $this->stockView
            || ViewCustomization::hasHooks($view, app('events'))) {
            return null;
        }

        return $field->wrapEmbeddedHtml($html, $attributes->getAttributes(), $alignment);
    }
}
