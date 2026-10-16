<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\DateTime;

use Bjanczak\FilamentFlexFields\Support\Playground\Contracts\PlaygroundWithValidationExamples;
use Filament\Schemas\Components\Component;

abstract class DateTimePlayground implements PlaygroundWithValidationExamples
{
    abstract protected function slug(): string;

    /** @return list<ExampleGroup> */
    abstract protected function groups(): array;

    /** @return array<string, mixed> */
    final public function defaultState(): array
    {
        $state = [];
        foreach ($this->groups() as $group) {
            foreach ($group->examples as $example) {
                $field = $example instanceof ValidationExample ? $example->field : $example;
                $state[$field->getName()] = $field->getDefaultState();
            }
        }

        return $state;
    }

    /** @return array<string, ValidationExample> */
    final public function validationExamples(): array
    {
        $examples = [];
        foreach ($this->groups() as $group) {
            foreach ($group->examples as $example) {
                if ($example instanceof ValidationExample) {
                    $examples[$example->field->getName()] = $example;
                }
            }
        }

        return $examples;
    }

    /** @return list<Component> */
    final public function components(): array
    {
        return [
            DateTimeNavigation::view($this->slug()),
            ...array_map(static fn (ExampleGroup $group): Component => $group->component(), $this->groups()),
            ...$this->displayComponents(),
        ];
    }

    /** @return list<Component> */
    protected function displayComponents(): array
    {
        return [];
    }
}
