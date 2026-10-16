<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Concerns\HasChecklistOptions;
use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists\HasChecklistLimits;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists\HasChoiceListAppearance;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\ChecklistStateCast;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation\ChecklistSelection;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Contracts\Support\Htmlable;

class FlexChecklist extends Field
{
    use HasChecklistLimits;
    use HasChecklistOptions;
    use HasChoiceListAppearance;
    use ResolvesConfiguredIcons;

    protected string $view = 'filament-flex-fields::forms.components.flex-checklist';

    public function getLockIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveConfiguredIcon('flex_checklist_lock_icon', FlexIcon::Lock);
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return $this->choiceListClasses('fff-flex-checklist');
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new ChecklistStateCast];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
        $this->rule('array');
        $this->rule(static fn (FlexChecklist $component): Closure => static fn (string $attribute, mixed $value, Closure $fail) => (new ChecklistSelection)->validate($component, $value, $fail));
    }
}
