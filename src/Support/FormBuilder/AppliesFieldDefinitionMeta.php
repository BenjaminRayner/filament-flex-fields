<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FormBuilder;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldWidth;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\FlexSliderFieldConfigurator;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Forms\FormulaFields;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Schema\JsonFieldConditions;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

trait AppliesFieldDefinitionMeta
{
    protected function applyDefinitionMeta(Component $field, FlexFieldDefinition $definition, string $statePathPrefix = ''): Component
    {
        if ($field instanceof Field) {
            $field->label($definition->label);

            if ($definition->hiddenLabel) {
                $field->hiddenLabel();
            }

            if (filled($definition->helpText)) {
                $field->helperText($definition->helpText);
            }

            if (filled($definition->placeholder)) {
                $field->placeholder($definition->placeholder);
            }

            if ($definition->isRequired && $definition->requiredWhen === null) {
                $field->required();
            }

            $minLength = $definition->config['min_length'] ?? null;
            $maxLength = $definition->config['max_length'] ?? null;

            if (is_numeric($minLength) && method_exists($field, 'minLength')) {
                $field->minLength((int) $minLength);
            }

            if (is_numeric($maxLength) && method_exists($field, 'maxLength')) {
                $field->maxLength((int) $maxLength);
            }

            foreach ($definition->validation as $rule) {
                if (is_string($rule)) {
                    $field->rule($rule);
                }
            }

            $this->applyDefaultValue($field, $definition);
            $this->applyFormulaMeta($field, $definition, $statePathPrefix);
        }

        $this->applyFieldConditions($field, $definition, $statePathPrefix);

        return $field;
    }

    protected function applyFormulaMeta(Field $field, FlexFieldDefinition $definition, string $statePathPrefix = ''): void
    {
        if (! $definition->hasFormula()) {
            return;
        }

        $binding = new FormulaFields(
            FormulaEngine::compileMap([$definition->slug => (string) $definition->formula]),
            $statePathPrefix !== '' ? $statePathPrefix.'.' : '',
        );
        $binding->bindOutput($field, $definition->slug);
    }

    /**
     * @param  array<string, Component>  $componentsBySlug
     * @param  array<string, string>  $formulas
     */
    protected function wireFormulaDependencies(array $componentsBySlug, array $formulas, string $statePathPrefix = ''): void
    {
        if ($formulas === []) {
            return;
        }

        (new FormulaFields(
            FormulaEngine::compileMap($formulas),
            $statePathPrefix !== '' ? $statePathPrefix.'.' : '',
        ))->bind($componentsBySlug);
    }

    protected function applyFieldConditions(Component $field, FlexFieldDefinition $definition, string $statePathPrefix = ''): void
    {
        if ($definition->visibleWhen !== null) {
            $field->visible(JsonFieldConditions::compileVisibleWhen($definition->visibleWhen, $statePathPrefix));
        }

        if ($definition->disabledWhen !== null) {
            $field->disabled(JsonFieldConditions::compileDisabledWhen($definition->disabledWhen, $statePathPrefix));
        }

        if ($definition->width !== FlexFieldWidth::Full) {
            $field->columnSpan($definition->width->columnSpan());
        }

        if ($field instanceof Field) {
            $this->applyRequiredWhen($field, $definition, $statePathPrefix);
        }
    }

    protected function applyRequiredWhen(Field $field, FlexFieldDefinition $definition, string $statePathPrefix = ''): void
    {
        if ($definition->requiredWhen === null) {
            return;
        }

        $requiredWhen = JsonFieldConditions::compileRequiredWhen($definition->requiredWhen, $statePathPrefix);

        if ($definition->isRequired) {
            $field->required();

            return;
        }

        $field->required($requiredWhen);
    }

    protected function applyDefaultValue(Field $field, FlexFieldDefinition $definition): void
    {
        $isRangeFlexSlider = $field instanceof FlexSlider
            && (bool) ($definition->config['is_range'] ?? false);

        if ($definition->defaultValue !== null) {
            $default = $definition->defaultValue;

            if ($isRangeFlexSlider && ! is_array($default)) {
                $default = FlexSliderFieldConfigurator::defaultRangeValues($definition->config);
            }

            $field->default($default);

            return;
        }

        if ($isRangeFlexSlider) {
            $field->default(FlexSliderFieldConfigurator::defaultRangeValues($definition->config));
        }
    }
}
