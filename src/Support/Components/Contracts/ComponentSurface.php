<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Contracts;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use InvalidArgumentException;
use ReflectionClass;

/** Typed PHP renderer identity. Validation never instantiates a field or record. */
final readonly class ComponentSurface
{
    /** @param class-string $class */
    public function __construct(public ComponentFamily $family, public ComponentKind $kind, public string $class)
    {
        if (! isset($family->surfaces[$kind->value])) {
            throw new InvalidArgumentException('Surface kind is not declared in family '.$family->id);
        }
        self::assertRendererClass($kind, $class);
    }

    /** @param class-string $class */
    public static function assertRendererClass(ComponentKind $kind, string $class): void
    {
        $base = match ($kind) {
            ComponentKind::Field => Field::class,
            ComponentKind::Column => Column::class,
            ComponentKind::Entry => Entry::class,
            ComponentKind::Display => Component::class,
            ComponentKind::Action => Action::class,
            default => throw new InvalidArgumentException('Primitives do not declare family render surfaces.'),
        };
        if (strlen($class) > 512 || ! class_exists($class) || ! is_a($class, $base, true)
            || ($kind === ComponentKind::Display && (is_a($class, Field::class, true) || is_a($class, Entry::class, true)))
            || ! (new ReflectionClass($class))->isInstantiable()) {
            throw new InvalidArgumentException('Invalid '.$kind->value.' renderer: '.$class);
        }
    }

    public function componentId(): string
    {
        return $this->family->surfaces[$this->kind->value];
    }

    /** @return array<string, string> */
    public function export(): array
    {
        return ['family' => $this->family->id, 'kind' => $this->kind->value, 'class' => $this->class];
    }
}
