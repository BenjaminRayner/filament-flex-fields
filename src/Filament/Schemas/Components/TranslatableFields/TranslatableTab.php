<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocales;

class TranslatableTab extends SegmentTab
{
    protected string $locale = '';

    public function locale(string $locale): static
    {
        TranslatableLocales::assertLocale($locale);
        $this->locale = $locale;
        $this->key($this->locale);

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
