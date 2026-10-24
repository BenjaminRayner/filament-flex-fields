<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\RendersStockFieldView;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\HasSelectFieldIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker\ConfiguresIconPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker\PresentsIconPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker\ResolvesIconPickerCatalog;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker\TransportsIconPicker;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasAffixes;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

class IconPickerField extends Field
{
    use ConfiguresIconPicker;
    use PresentsIconPicker;
    use ResolvesIconPickerCatalog;
    use TransportsIconPicker;

    public const int MAX_SVG_PREVIEW_BATCH = 64;

    use CanBeReadOnly;
    use HasAffixes;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;
    use HasSelectFieldIcons;
    use RendersStockFieldView;

    protected string $view = 'filament-flex-fields::forms.components.icon-picker-field';

    /** A schema clone resolves its own state-dependent catalog policy. */
    public function __clone(): void
    {
        $this->forgetIconCatalogSnapshot();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(null);

        $this->rule(function (IconPickerField $component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if ($value === null || $value === '') {
                    if ($component->isRequired()) {
                        $fail(__('validation.required', ['attribute' => $component->getLabel()]));
                    }

                    return;
                }

                if (! is_string($value)) {
                    $fail(__('filament-flex-fields::default.validation.icon_picker.invalid'));

                    return;
                }

                if (! $component->isAllowedIcon($value)) {
                    $fail(__('filament-flex-fields::default.validation.icon_picker.invalid'));
                }
            };
        });
    }
}
