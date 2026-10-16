<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor;

use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;

/** Adds deferred names while preserving Filament's native group presentation. */
class LocalizedToolbarButtonGroup extends ToolbarButtonGroup
{
    protected ?string $nameTranslationKey = null;

    public function translatedName(string $key): static
    {
        $this->nameTranslationKey = $key;

        return $this;
    }

    public function name(string $name): static
    {
        $this->nameTranslationKey = null;

        return parent::name($name);
    }

    public function getName(): string
    {
        return $this->nameTranslationKey === null
            ? parent::getName()
            : Translations::get($this->nameTranslationKey);
    }
}
