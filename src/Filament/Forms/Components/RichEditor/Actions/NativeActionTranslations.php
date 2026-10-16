<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Actions;

use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use ReflectionProperty;

/** Keep inherited editor actions reactive to locale changes without copying their behavior. */
final class NativeActionTranslations
{
    public static function apply(Action $action): Action
    {
        if (! in_array($action->getName(), ['link', 'grid', 'textColor'], true)) {
            return $action;
        }

        $prefix = 'filament-forms::components.rich_editor.actions.'.Str::snake($action->getName());
        self::defer($action, 'label', $prefix.'.label');
        self::defer($action, 'modalHeading', $prefix.'.modal.heading');

        $schema = (new ReflectionProperty(Action::class, 'schema'))->getValue($action);

        if (is_array($schema)) {
            self::localizeFields($schema, $prefix.'.modal.form');
        } elseif ($schema instanceof Closure) {
            $action->schema(function () use ($action, $schema, $prefix) {
                $components = $action->evaluate($schema);

                if (is_array($components)) {
                    self::localizeFields($components, $prefix.'.modal.form');
                }

                return $components;
            });
        }

        return $action;
    }

    /** @param array<mixed> $components */
    private static function localizeFields(array $components, string $prefix): void
    {
        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            if ($component instanceof Field) {
                $fieldPrefix = $prefix.'.'.Str::snake($component->getName());
                self::defer($component, 'label', $fieldPrefix.'.label');

                if ($component instanceof Select) {
                    self::defer($component, 'placeholder', $fieldPrefix.'.placeholder');
                    $options = (new ReflectionProperty($component, 'options'))->getValue($component);

                    if (is_array($options)) {
                        foreach ($options as $value => &$label) {
                            $key = $fieldPrefix.'.options.'.$value;

                            if (is_string($label) && $label === Translations::get($key)) {
                                $label = fn (): string => Translations::get($key);
                            }
                        }
                        unset($label);

                        $component->options(fn (): array => array_map(
                            fn (mixed $label): mixed => $component->evaluate($label), $options,
                        ));
                    }
                }
            }

            // Only traverse concrete native schemas; leave application callbacks lazy.
            $children = (new ReflectionProperty($component, 'childComponents'))->getValue($component);
            foreach ($children as $schema) {
                if (is_array($schema)) {
                    self::localizeFields($schema, $prefix);
                }
            }
        }
    }

    private static function defer(Action|Field $component, string $property, string $key): void
    {
        $value = (new ReflectionProperty($component, $property))->getValue($component);

        if (is_string($value) && $value === Translations::get($key)) {
            $component->{$property}(fn (): string => Translations::get($key));
        }
    }
}
