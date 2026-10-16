<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns;

use Bjanczak\FilamentFlexFields\Enums\TranslatableDirectionScope;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\TranslatableTab;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocaleDirection;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableTabState;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Forms\Components\Field;

trait ProvidesTranslatablePresets
{
    protected ?TranslatableDirectionScope $translatableDirectionScope = null;

    protected bool $translatableEmptyBadge = false;

    protected ?string $translatableEmptyBadgeLabel = null;

    protected bool|Closure $translatableBorderedPanels = false;

    public function directionByLocale(TranslatableDirectionScope $scope = TranslatableDirectionScope::Auto): static
    {
        $this->translatableDirectionScope = $scope;
        $this->clearCachedChildSchemas();

        return $this;
    }

    public function emptyBadgeWhenAllFieldsAreEmpty(?string $emptyLabel = null): static
    {
        $this->translatableEmptyBadge = true;
        $this->translatableEmptyBadgeLabel = $emptyLabel;
        // Children inherit this unless a template declares its own binding policy.
        $this->live(debounce: 250);
        $this->clearCachedChildSchemas();

        return $this;
    }

    public function activeTabWithValue(): static
    {
        return $this->activeTab(fn (callable $get, TranslatableFields $component): int => TranslatableTabState::resolveActiveTabWithValue($component, $get));
    }

    public function withRecommendedDefaults(?string $emptyBadgeLabel = null): static
    {
        return $this->directionByLocale()->emptyBadgeWhenAllFieldsAreEmpty($emptyBadgeLabel)->activeTabWithValue();
    }

    public function borderedPanels(bool|Closure $condition = true): static
    {
        $this->translatableBorderedPanels = $condition;

        return $this;
    }

    public function hasBorderedPanels(): bool
    {
        return (bool) $this->evaluate($this->translatableBorderedPanels);
    }

    protected function configureLocaleTab(TranslatableTab $tab): void
    {
        if (! $this->translatableEmptyBadge) {
            return;
        }
        $tab->badge(fn (TranslatableTab $component, callable $get): ?string => TranslatableTabState::tabHasAnyFieldValue($component, $get) ? null : $this->resolveEmptyBadgeLabel())
            ->badgeColor(fn (TranslatableTab $component, callable $get): ?string => TranslatableTabState::tabHasAnyFieldValue($component, $get) ? null : 'warning');
    }

    protected function resolveEmptyBadgeLabel(): string
    {
        $configured = $this->translatableEmptyBadgeLabel ?? config('filament-flex-fields.translatable.empty_badge_label');

        return is_string($configured) ? $configured : Translations::get('filament-flex-fields::default.translatable.empty');
    }

    protected function configureLocaleField(Field $field, string $locale): void
    {
        if ($this->translatableDirectionScope !== null) {
            TranslatableLocaleDirection::applyToField($field, $locale, $this->translatableDirectionScope);
        }
    }
}
