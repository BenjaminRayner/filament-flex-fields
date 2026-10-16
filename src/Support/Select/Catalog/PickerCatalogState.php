<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Catalog;

/** Request-owned localized metadata; standalone callers have a bounded locale working set. */
final class PickerCatalogState
{
    /** @var array<string, array<string, string>> */
    public array $countryNames = [];

    /** @var array<string, array<string, array{code: string, name: string, dial_code: ?string, flag_url: string}>> */
    public array $countryMetadata = [];

    /** @var array<string, array<string, array{code: string, name: string, dial_code: string, flag_url: string}>> */
    public array $phoneMetadata = [];

    /** @var array<string, array<string, string>> */
    public array $timezoneNames = [];

    /** @var array<string, array<string, array{id: string, label: string, offset: string, offset_seconds: int, region: string}>> */
    public array $timezoneMetadata = [];

    /** @var array<string, string> */
    public array $decimalSeparators = [];

    /** @var array<string, string> */
    public array $thousandsSeparators = [];

    /** @var array<string, true> */
    private array $locales = [];

    private ?int $timezoneMinute = null;

    public function touchLocale(string $locale): void
    {
        unset($this->locales[$locale]);
        $this->locales[$locale] = true;
        while (count($this->locales) > 32) {
            $oldest = array_key_first($this->locales);
            unset($this->locales[$oldest], $this->countryNames[$oldest], $this->countryMetadata[$oldest],
                $this->phoneMetadata[$oldest], $this->timezoneNames[$oldest], $this->timezoneMetadata[$oldest],
                $this->decimalSeparators[$oldest], $this->thousandsSeparators[$oldest]);
        }
    }

    /** A day-long cache misses offset changes when daylight-saving time changes mid-day. */
    public function synchronizeTimezoneInstant(int $timestamp): void
    {
        $minute = intdiv($timestamp, 60);
        if ($this->timezoneMinute !== $minute) {
            $this->timezoneMetadata = [];
            $this->timezoneMinute = $minute;
        }
    }

    public function clear(): void
    {
        $this->countryNames = $this->countryMetadata = $this->phoneMetadata = [];
        $this->timezoneNames = $this->timezoneMetadata = [];
        $this->decimalSeparators = $this->thousandsSeparators = $this->locales = [];
        $this->timezoneMinute = null;
    }
}
