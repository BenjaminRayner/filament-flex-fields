<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns;

use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableHydrator;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;

trait SyncsTranslatableLocaleFieldState
{
    /**
     * @param  array<string, mixed>|null  $hydratedDefaultState
     * @param  array<string, true>  $appliedStateCastPaths
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        if ($schema = $this->getChildSchema()) {
            $this->syncLocaleFieldStates($schema, fromRecord: $hydratedDefaultState === null);
        }
        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }

    /** @param array<string> $statePaths */
    public function hydrateStatePartially(array $statePaths, bool $shouldCallHydrationHooks = true): void
    {
        if ($schema = $this->getChildSchema()) {
            $prepared = [];
            foreach ($schema->getFlatComponents(withHidden: true) as $field) {
                if (! $field instanceof Field) {
                    continue;
                }
                foreach ($statePaths as $path) {
                    $fieldPath = $field->getStatePath();
                    if (is_string($fieldPath) && ($fieldPath === $path || str_starts_with($fieldPath, $path.'.'))) {
                        TranslatableHydrator::prepareField($field, true, $prepared);
                        break;
                    }
                }
            }
        }
        parent::hydrateStatePartially($statePaths, $shouldCallHydrationHooks);
    }

    /** @param array<string, mixed> $state */
    public function dehydrateState(array &$state, bool $isDehydrated = true): void
    {
        parent::dehydrateState($state, $isDehydrated);
        if ($schema = $this->getChildSchema()) {
            foreach ($schema->getFlatComponents(withHidden: true) as $field) {
                if ($field instanceof Field) {
                    TranslatableHydrator::dehydrateNestedAttribute($field, $state);
                }
            }
        }
    }

    protected function syncLocaleFieldStates(Schema $schema, bool $fromRecord = false): void
    {
        $preparedPaths = [];
        foreach ($schema->getFlatComponents(withHidden: true) as $field) {
            if ($field instanceof Field) {
                TranslatableHydrator::prepareField($field, $fromRecord, $preparedPaths);
            }
        }
    }
}
