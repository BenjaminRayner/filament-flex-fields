<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexChecklist;
use Bjanczak\FilamentFlexFields\Support\Select\Validation\StaticOptionSelection;
use Closure;

final class ChecklistSelection
{
    public function validate(FlexChecklist $field, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return; // The separate array rule reports the invalid shape.
        }
        if (! (new StaticOptionSelection)->acceptsList($field->getEnabledOptionKeys(), $value)) {
            $fail(__('filament-flex-fields::default.validation.flex_checklist.invalid_option'));

            return;
        }
        $limits = $field->getSelectionLimits();
        if ($violation = $limits->violation(count($value), $field->isRequired())) {
            $fail(__('filament-flex-fields::default.validation.flex_checklist.'.$violation, ['count' => $limits->countFor($violation)]));
        }
    }
}
