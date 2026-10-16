<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Timezone\ConfiguresTimezone;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Timezone\NormalizesTimezoneState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Timezone\ResolvesTimezoneCatalog;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;

/** IANA timezone policy on the shared Select, including its measured virtualization. */
class TimezoneField extends CatalogSelectField
{
    use ConfiguresTimezone;
    use NormalizesTimezoneState;
    use ResolvesTimezoneCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plainTrigger();
        $this->chevronIcon(null);
        $this->placeholder(fn (): string => OptionText::required(__('filament-flex-fields::default.timezone.placeholder'), 'timezone placeholder'));
        $this->default(fn (): ?string => $this->getDefaultTimezoneIdentifier());
        $this->afterStateHydrated(fn (TimezoneField $component, mixed $state) => $component->state($component->normalizeState($state)));
        $this->dehydrateStateUsing(fn (TimezoneField $component, mixed $state): ?string => $component->normalizeState($state));
    }

    public function getAssetComponent(): string
    {
        return 'timezone-field';
    }

    /** @return array<string, array<string, mixed>> */
    protected function catalogOptions(): array
    {
        $options = [];
        foreach ($this->getTimezonesMetadata() as $timezone) {
            $options[$timezone['id']] = ['label' => $timezone['label'],
                'description' => $this->shouldShowOffset() ? $timezone['offset'] : ''];
        }

        return $options;
    }

    /** @return array<string, mixed> */
    public function getHeadlessAlpineConfig(): array
    {
        return [...parent::getHeadlessAlpineConfig(), 'browserTimezoneDefault' => $this->shouldUseBrowserTimezoneDefault(),
            'browserTimezoneSortFirst' => $this->shouldSortTimezonesByBrowserTimezone()];
    }
}
