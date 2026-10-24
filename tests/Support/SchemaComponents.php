<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

final class SchemaComponents
{
    /** Inspect every declared branch, including a natively deferred child schema.
     *
     * @param array<mixed>|Schema $schema
     * @return list<Component>
     */
    public static function all(array|Schema $schema): array
    {
        $components = $schema instanceof Schema ? $schema->getComponents(withHidden: true) : $schema;
        $result = [];
        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }
            $result[] = $component;
            $result = [...$result, ...self::all($component->getDefaultChildComponents())];
        }

        return $result;
    }
}
