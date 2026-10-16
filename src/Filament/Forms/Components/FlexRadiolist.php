<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Concerns\HasChecklistOptions;
use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists\HasChoiceListAppearance;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Presentation\SizeStyles;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\RadioStateCast;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use InvalidArgumentException;

class FlexRadiolist extends Field
{
    use HasChecklistOptions { getNormalizedOptions as protected normalizedChoiceOptions; }
    use HasChoiceListAppearance;
    use ResolvesConfiguredIcons;

    protected string $view = 'filament-flex-fields::forms.components.flex-radiolist';

    protected string|Closure $variant = 'default';

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);
        if (! in_array($variant, ['default', 'label-only'], true)) {
            throw new InvalidArgumentException("Flex radiolist variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function isLabelOnlyVariant(): bool
    {
        return $this->getVariant() === 'label-only';
    }

    public function getLockIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveConfiguredIcon('flex_radiolist_lock_icon', FlexIcon::Lock);
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return $this->choiceListClasses('fff-flex-radiolist', $this->getVariant());
    }

    /** @return array<string|int, array{label:string,description:?string,icon:?string,disabled:bool}> */
    public function getNormalizedOptions(): array
    {
        $options = $this->normalizedChoiceOptions();
        if ($this->isLabelOnlyVariant()) {
            foreach ($options as &$option) {
                $option['description'] = null;
                $option['icon'] = null;
            }
            unset($option);
        }

        return $options;
    }

    /** @return array<string,string> */
    public function getRadiolistSizeStyles(): array
    {
        return SizeStyles::for($this->getSize(), 'flex-radiolist');
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new RadioStateCast];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule('bail');
        $this->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! is_string($value) && ! is_int($value)) {
                $fail(__('validation.in', ['attribute' => $attribute]));
            }
        });
        $this->rule(static fn (FlexRadiolist $component): In => Rule::in($component->getEnabledOptionKeys()));
    }
}
