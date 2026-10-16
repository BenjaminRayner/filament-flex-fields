<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Filament\Forms\Components\Field;

final class TranslatableTabState
{
    public static function tabHasAnyFieldValue(SegmentTab $tab, callable $get): bool
    {
        foreach ($tab->getChildSchema()?->getFlatComponents(withHidden: true) ?? [] as $field) {
            if ($field instanceof Field && ! $field->isHidden() && filled($get($field->getStatePath(isAbsolute: false) ?? $field->getName()))) {
                return true;
            }
        }

        return false;
    }

    public static function resolveActiveTabWithValue(SegmentTabs $component, callable $get): int
    {
        foreach (array_values($component->getVisibleTabs()) as $index => $tab) {
            if (self::tabHasAnyFieldValue($tab, $get)) {
                return $index + 1;
            }
        }

        return 1;
    }
}
