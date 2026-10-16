<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns\BuildsTranslatableTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns\ConfiguresTranslatableLocales;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns\CustomizesTranslatableComponents;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns\HasTranslatableMigrationAliases;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns\ProvidesTranslatablePresets;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\Concerns\SyncsTranslatableLocaleFieldState;
use Filament\Schemas\Schema;

/** Locale composition: shared tab renderer, independently owned child fields. */
class TranslatableFields extends SegmentTabs
{
    use BuildsTranslatableTabs;
    use ConfiguresTranslatableLocales;
    use CustomizesTranslatableComponents;
    use HasTranslatableMigrationAliases;
    use ProvidesTranslatablePresets;
    use SyncsTranslatableLocaleFieldState;

    public function container(Schema $schema): static
    {
        $changed = isset($this->container) && $this->container !== $schema;
        parent::container($schema);
        if ($changed) {
            $this->clearCachedChildSchemas();
            unset($this->rootContainer);
        }

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->separators(false);
        $this->extraAttributes(fn (TranslatableFields $component): array => [
            'class' => 'fff-translatable-fields'.($component->hasBorderedPanels() ? ' fff-translatable-fields--bordered' : ''),
        ]);
        $this->tabs(fn (): array => $this->buildTranslatableTabs());
        $this->afterCloned(function (TranslatableFields $clone): void {
            $clone->clearCachedChildSchemas();
        });
    }

    public function getSegmentAssetComponent(): string
    {
        return 'translatable-fields';
    }
}
