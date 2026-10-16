<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;

/** Only server-known initial visibility may promote rendered CSS into the head.
 * This does not acquire consumers, preload JS or predict client-persisted state.
 */
final class InitialSchemaVisibility
{
    public static function allows(mixed $component): bool
    {
        if (! $component instanceof Component || request()->hasHeader('X-Livewire')) {
            return false;
        }

        $root = $component->getRootContainer();
        if (str_starts_with((string) $root->getKey(), 'mountedAction')
            || str_starts_with((string) $root->getStatePath(), 'mountedActions.')) {
            return false;
        }

        // Table filter schemas are roots: their closed dropdown/modal is outside
        // the schema ancestor tree. Only always-visible layouts may be critical.
        if ($root->getKey() === 'tableFiltersForm'
            || in_array($root->getStatePath(), ['tableFilters', 'tableDeferredFilters'], true)) {
            $owner = $root->getLivewire();
            if (! $owner instanceof HasTable || ! in_array($owner->getTable()->getFiltersLayout(), [
                FiltersLayout::AboveContent, FiltersLayout::BelowContent,
                FiltersLayout::BeforeContent, FiltersLayout::AfterContent,
            ], true)) {
                return false;
            }
        }

        for ($node = $component; $node !== null; $node = $parent) {
            $container = $node->getContainer();
            $parent = $container->getParentComponent();
            if ($node->isHidden() || filled($node->getHiddenJs()) || filled($node->getVisibleJs()) || $container->isLoadingDeferred()) {
                return false;
            }
            $attributes = $node->getExtraAttributes();
            if (array_key_exists('x-show', $attributes) || array_key_exists('x-if', $attributes)
                || array_key_exists('hidden', $attributes)
                || array_key_exists('x-bind:class', $attributes) || array_key_exists('x-bind:style', $attributes)
                || in_array($attributes['data-fff-asset-active'] ?? null, [false, 'false'], true)
                || in_array($attributes['aria-hidden'] ?? null, [true, 'true'], true)
                || (is_string($attributes['class'] ?? null) && preg_match('/(?:^|\s)(?:hidden|invisible|fi-hidden)(?:\s|$)/', $attributes['class']))
                || (is_string($attributes['style'] ?? null) && preg_match('/(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $attributes['style']))) {
                return false;
            }
            if ($parent instanceof Section && ($parent->isCollapsed($container) || $parent->shouldPersistCollapsed())) {
                return false;
            }
            if ($node instanceof Tab && $parent instanceof Tabs) {
                $tabs = array_values(array_filter($parent->getChildSchema()?->getComponents() ?? [], static fn (mixed $child): bool => $child instanceof Tab));
                $active = $tabs[$parent->getActiveTab() - 1] ?? null;
                if (filled($property = $parent->getLivewireProperty())) {
                    foreach ($tabs as $tab) {
                        if ((string) $parent->getLivewire()->{$property} === (string) $tab->getKey(isAbsolute: false)) {
                            $active = $tab;
                            break;
                        }
                    }
                } elseif ($parent->isTabPersisted()) {
                    return false;
                } elseif ($parent->isTabPersistedInQueryString()) {
                    // Filament's browser writes relative tab keys; its server
                    // getActiveTab() may compare absolute DOM IDs instead.
                    $query = request()->query($parent->getTabQueryStringKey());
                    foreach ($tabs as $tab) {
                        if ($tab->isVisible() && $tab->getKey(isAbsolute: false) === $query) {
                            $active = $tab;
                            break;
                        }
                    }
                }
                if ($active !== $node) {
                    return false;
                }
            }
            if ($node instanceof SegmentTab && $parent instanceof SegmentTabs
                && ($parent->isTabPersisted() || ! $parent->isTabActive($node))) {
                return false;
            }
            if ($node instanceof Step && $parent instanceof Wizard) {
                $steps = array_values(array_filter($parent->getChildSchema()?->getComponents() ?? [], static fn (mixed $child): bool => $child instanceof Step));
                if (($steps[$parent->getStartStep() - 1] ?? null) !== $node) {
                    return false;
                }
            }
        }

        return true;
    }
}
