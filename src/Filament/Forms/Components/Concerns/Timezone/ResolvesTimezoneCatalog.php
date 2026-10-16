<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Timezone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Bjanczak\FilamentFlexFields\Support\TimezoneRegistry;
use Bjanczak\FilamentFlexFields\Support\TimezoneRegistryQueue;
use Bjanczak\FilamentFlexFields\Support\Timezones;

/** @mixin TimezoneField */
trait ResolvesTimezoneCatalog
{
    /**
     * @return list<string>|null
     */
    public function getAllowedTimezoneIdentifiers(): ?array
    {
        $timezones = $this->evaluate($this->timezones);

        if ($timezones === null) {
            return null;
        }

        return IdentifierList::normalize($timezones);
    }

    /**
     * @return list<string>
     */
    public function getExceptTimezoneIdentifiers(): array
    {
        $except = IdentifierList::normalize($this->evaluate($this->exceptTimezones));

        if (! $this->shouldIncludeUtc() && ! in_array('UTC', $except, true)) {
            $except[] = 'UTC';
        }

        return $except;
    }

    /**
     * @return list<string>
     */
    public function getResolvedTimezoneIdentifiers(): array
    {
        return Timezones::resolve(
            $this->getAllowedTimezoneIdentifiers(),
            $this->getExceptTimezoneIdentifiers(),
        );
    }

    public function getBrowserTimezoneIdentifier(): ?string
    {
        return Timezones::fromBrowserTimezone($this->getResolvedTimezoneIdentifiers());
    }

    public function getDefaultTimezoneIdentifier(): ?string
    {
        if ($this->shouldUseBrowserTimezoneDefault()) {
            $detected = $this->getBrowserTimezoneIdentifier();

            if ($detected !== null) {
                return $detected;
            }
        }

        $default = PickerConfiguration::optionalText($this->evaluate($this->defaultTimezone), 'defaultTimezone');

        if (blank($default)) {
            return null;
        }

        $timezone = trim($default);
        $allowed = $this->getResolvedTimezoneIdentifiers();

        if (in_array($timezone, $allowed, true)) {
            return $timezone;
        }

        return $allowed[0] ?? null;
    }

    /**
     * @return list<array{id: string, label: string, offset: string, offset_seconds: int, region: string}>
     */
    public function getTimezonesMetadata(): array
    {
        $metadata = Timezones::metadata(
            $this->getAllowedTimezoneIdentifiers(),
            $this->getExceptTimezoneIdentifiers(),
            $this->getLocale(),
        );

        if ($this->shouldSortTimezonesByBrowserTimezone()) {
            return Timezones::sortWithPreferredFirst(
                $metadata,
                $this->getBrowserTimezoneIdentifier(),
            );
        }

        return $metadata;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function getTimezoneSelectOptions(): array
    {
        return Timezones::selectOptions(
            $this->getAllowedTimezoneIdentifiers(),
            $this->getExceptTimezoneIdentifiers(),
            $this->getLocale(),
        );
    }

    /**
     * Default full IANA list uses a shared CountryRegistry-style catalog
     * (one HTML template per request) instead of embedding options in every field's `@js`.
     * Explicit `timezones([...])` whitelists stay inline for lean payloads.
     */
    public function shouldUseTimezoneRegistry(): bool
    {
        return $this->getAllowedTimezoneIdentifiers() === null;
    }

    public function getTimezonePool(): string
    {
        return TimezoneRegistry::POOL_IANA;
    }

    public function hasCustomTimezoneFilter(): bool
    {
        return $this->getAllowedTimezoneIdentifiers() !== null
            || $this->getExceptTimezoneIdentifiers() !== [];
    }

    public function getTimezoneFilterKey(): ?string
    {
        if (! $this->shouldUseTimezoneRegistry()) {
            return null;
        }

        if (! $this->hasCustomTimezoneFilter()) {
            return null;
        }

        return TimezoneRegistryQueue::registerTimezoneFilter($this->getResolvedTimezoneIdentifiers());
    }

    /**
     * Selected-row seed for SSR trigger label when the full catalog is deferred.
     *
     * @return array{id: string, label: string, offset: string, region: string}|null
     */
    public function getSelectedTimezoneSeed(): ?array
    {
        try {
            $stateValue = $this->getState();
        } catch (\Throwable) {
            $stateValue = null;
        }

        $selectedId = is_string($stateValue) && trim($stateValue) !== '' ? trim($stateValue) : null;

        if ($selectedId === null) {
            return null;
        }

        $allowed = $this->getResolvedTimezoneIdentifiers();

        if (! in_array($selectedId, $allowed, true)) {
            return null;
        }

        $metadata = Timezones::metadata([$selectedId], [], $this->getLocale());
        $row = $metadata[0] ?? null;

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row['id'],
            'label' => $row['label'],
            'offset' => $row['offset'],
            'region' => $row['region'],
        ];
    }

    /**
     * Compact id → [label, offset] map for blocking browser-timezone SSR boot.
     * Must match Alpine option labels exactly so the trigger never swaps names.
     * Registry-backed fields omit the per-field attribute — boot reads the shared template.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function getBrowserTimezoneBootCatalog(): array
    {
        if ($this->shouldUseTimezoneRegistry()) {
            return [];
        }

        $catalog = [];

        foreach ($this->getTimezonesMetadata() as $timezone) {
            $catalog[$timezone['id']] = [$timezone['label'], $timezone['offset']];
        }

        return $catalog;
    }

    public function getVirtualScrollThreshold(): int
    {
        return 50;
    }
}
