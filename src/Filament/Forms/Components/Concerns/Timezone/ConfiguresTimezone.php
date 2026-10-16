<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Timezone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;

/** @mixin TimezoneField */
trait ConfiguresTimezone
{
    protected string|Closure|null $defaultTimezone = null;

    /**
     * @var list<string>|Closure|null
     */
    protected array|Closure|null $timezones = null;

    /**
     * @var list<string>|Closure
     */
    protected array|Closure $exceptTimezones = [];

    protected bool|Closure $showOffset = true;

    protected bool|Closure $browserTimezoneDefault = false;

    protected bool|Closure $browserTimezoneSortFirst = false;

    protected bool|Closure $includeUtc = true;

    protected string|Closure|null $locale = null;

    /**
     * @param  list<string>|Closure|null  $timezones
     */
    public function timezones(array|Closure|null $timezones): static
    {
        $this->timezones = $timezones;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $timezones
     */
    public function exceptTimezones(array|Closure $timezones): static
    {
        $this->exceptTimezones = $timezones;

        return $this;
    }

    public function defaultTimezone(string|Closure|null $timezone): static
    {
        $this->defaultTimezone = $timezone;

        return $this;
    }

    public function browserTimezoneDefault(bool|Closure $condition = true): static
    {
        $this->browserTimezoneDefault = $condition;

        return $this;
    }

    public function browserTimezoneSortFirst(bool|Closure $condition = true): static
    {
        $this->browserTimezoneSortFirst = $condition;

        return $this;
    }

    public function includeUtc(bool|Closure $condition = true): static
    {
        $this->includeUtc = $condition;

        return $this;
    }

    public function showOffset(bool|Closure $condition = true): static
    {
        $this->showOffset = $condition;

        return $this;
    }

    public function locale(string|Closure|null $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        $locale = PickerConfiguration::optionalText($this->evaluate($this->locale), 'locale');

        return filled($locale) ? (string) $locale : app()->getLocale();
    }

    public function shouldUseBrowserTimezoneDefault(): bool
    {
        return (bool) $this->evaluate($this->browserTimezoneDefault);
    }

    public function shouldSortTimezonesByBrowserTimezone(): bool
    {
        return (bool) $this->evaluate($this->browserTimezoneSortFirst);
    }

    public function shouldIncludeUtc(): bool
    {
        return (bool) $this->evaluate($this->includeUtc);
    }

    public function shouldShowOffset(): bool
    {
        return (bool) $this->evaluate($this->showOffset);
    }
}
