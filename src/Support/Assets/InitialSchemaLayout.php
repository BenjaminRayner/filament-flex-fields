<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\SchemaRendererIdentity;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/** Prepare native layout state before its HTML is serialized, without loading JS. */
final class InitialSchemaLayout
{
    public static function register(): void
    {
        Schema::configureUsing(static function (Schema $schema): void {
            $schema->extraAttributes(static function (Schema $schema): array {
                self::prepare($schema);

                return [];
            }, merge: true);
        });
    }

    public static function prepare(Schema $schema): void
    {
        if (request()->hasHeader('X-Livewire') || $schema->getParentComponent() !== null) {
            return;
        }

        $tabs = [];
        self::collectVisibleTabs($schema, $tabs);

        foreach ($tabs as $group) {
            if (! $group->isScrollable() || $group->isTabPersisted()
                || filled($group->getLivewireProperty()) || ! InitialSchemaVisibility::allows($group)) {
                continue;
            }
            foreach ($group->getChildSchema()?->getComponents() ?? [] as $tab) {
                if (! $tab instanceof Tab || ! InitialSchemaVisibility::allows($tab)) {
                    continue;
                }
                if (! preg_match('/(?:^|\s)fi-active(?:\s|$)/', (string) ($tab->getExtraAttributes()['class'] ?? ''))) {
                    $tab->extraAttributes(['class' => 'fi-active'], merge: true);
                }
                $group->extraAttributes(['data-fff-initial-layout' => 'native-tabs'], merge: true);
            }
        }
    }

    /** @param array<int, Tabs> $tabs */
    private static function collectVisibleTabs(Schema $schema, array &$tabs): void
    {
        foreach ($schema->getComponents() as $component) {
            if (! $component instanceof Component || ! InitialSchemaVisibility::allows($component)) {
                continue;
            }
            if (SchemaRendererIdentity::isFlex($component)) {
                for ($parent = $component->getContainer()->getParentComponent(); $parent !== null; $parent = $parent->getContainer()->getParentComponent()) {
                    if ($parent instanceof Tabs) {
                        $tabs[spl_object_id($parent)] = $parent;
                    }
                }
            }
            if ($children = $component->getChildSchema()) {
                self::collectVisibleTabs($children, $tabs);
            }
        }
    }
}
