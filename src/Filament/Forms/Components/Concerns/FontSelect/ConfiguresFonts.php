<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FontSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use InvalidArgumentException;

/** @mixin FontSelect */
trait ConfiguresFonts
{
    /** @var list<string>|Closure */
    protected array|Closure $fontProviders = ['google'];

    /** @var list<string>|Closure|null */
    protected array|Closure|null $fontAllowList = null;

    /** @var list<string>|Closure */
    protected array|Closure $fontDenyList = [];

    /** @var list<string>|Closure */
    protected array|Closure $fontCategories = [];

    /** @var list<string>|Closure */
    protected array|Closure $fontSubsets = [];

    /** @var array<string, mixed>|Closure */
    protected array|Closure $localFontCatalog = [];

    /** @var bool|list<string>|Closure */
    protected bool|array|Closure $fontCategoryTabs = true;

    /** @var array<string, string>|Closure */
    protected array|Closure $fontCategoryLabels = [];

    protected bool|Closure $fontVariableOnly = false;

    protected int|Closure $fontPageSize = 48;

    protected int|Closure $fontPreviewWeight = 400;

    protected string|Closure $fontPreviewStyle = 'normal';

    protected string|Closure|null $fontPreviewText = null;

    protected bool|Closure $previewSelectedFont = true;

    protected string|Closure $fontSort = 'popularity';

    /** @param list<string>|Closure $providers */
    public function providers(array|Closure $providers): static
    {
        $this->fontProviders = $providers;

        return $this;
    }

    /** @param list<string>|Closure|null $fonts */
    public function fonts(array|Closure|null $fonts): static
    {
        $this->fontAllowList = $fonts;

        return $this;
    }

    /** @param list<string>|Closure $fonts */
    public function exceptFonts(array|Closure $fonts): static
    {
        $this->fontDenyList = $fonts;

        return $this;
    }

    /** @param list<string>|Closure $categories */
    public function categories(array|Closure $categories): static
    {
        $this->fontCategories = $categories;

        return $this;
    }

    /** @param list<string>|Closure $subsets */
    public function subsets(array|Closure $subsets): static
    {
        $this->fontSubsets = $subsets;

        return $this;
    }

    /** @param array<string, mixed>|Closure $fonts */
    public function localFonts(array|Closure $fonts): static
    {
        $this->localFontCatalog = $fonts;

        return $this;
    }

    /** @param bool|list<string>|Closure $categories */
    public function categoryTabs(bool|array|Closure $categories = true): static
    {
        $this->fontCategoryTabs = $categories;

        return $this;
    }

    /** @param array<string, string>|Closure $labels */
    public function categoryLabels(array|Closure $labels): static
    {
        $this->fontCategoryLabels = $labels;

        return $this;
    }

    public function variableOnly(bool|Closure $condition = true): static
    {
        $this->fontVariableOnly = $condition;

        return $this;
    }

    public function perPage(int|Closure $size): static
    {
        $this->fontPageSize = $size;

        return $this;
    }

    public function previewText(string|Closure|null $text): static
    {
        $this->fontPreviewText = $text;

        return $this;
    }

    public function previewWeight(int|Closure $weight): static
    {
        $this->fontPreviewWeight = $weight;

        return $this;
    }

    public function previewStyle(string|Closure $style): static
    {
        $this->fontPreviewStyle = $style;

        return $this;
    }

    public function previewSelectedFont(bool|Closure $condition = true): static
    {
        $this->previewSelectedFont = $condition;

        return $this;
    }

    public function isSelectedFontPreviewEnabled(): bool
    {
        return (bool) $this->evaluate($this->previewSelectedFont);
    }

    public function sortFonts(string|Closure $sort): static
    {
        $this->fontSort = $sort;

        return $this;
    }

    public function getFontPerPage(): int
    {
        return $this->fontInteger($this->evaluate($this->fontPageSize), 'perPage', 1, 96);
    }

    /** @return array{providers: list<string>, fonts: ?list<string>, except: list<string>, categories: list<string>, subsets: list<string>, local: array<string, mixed>, variable: bool, sort: string} */
    public function getFontPolicy(): array
    {
        $providers = IdentifierList::normalize($this->evaluate($this->fontProviders));
        if ($providers === [] || array_diff($providers, ['google', 'local']) !== []) {
            throw new InvalidArgumentException('Unknown font provider.');
        }
        $categories = IdentifierList::normalize($this->evaluate($this->fontCategories));
        if (array_diff($categories, FontCatalog::CATEGORIES) !== []) {
            throw new InvalidArgumentException('Unknown font category.');
        }
        $only = $this->evaluate($this->fontAllowList);
        $local = $this->evaluate($this->localFontCatalog);
        if (! is_array($local)) {
            throw new InvalidArgumentException('Local fonts must be a metadata map.');
        }

        return ['providers' => $providers, 'fonts' => $only === null ? null : IdentifierList::normalize($only),
            'except' => IdentifierList::normalize($this->evaluate($this->fontDenyList)), 'categories' => $categories,
            'subsets' => IdentifierList::normalize($this->evaluate($this->fontSubsets)), 'local' => $local,
            'variable' => (bool) $this->evaluate($this->fontVariableOnly),
            'sort' => PickerConfiguration::choice($this->evaluate($this->fontSort), ['popularity', 'alpha'], 'sortFonts')];
    }

    /** @return list<array{value: string, label: string}> */
    public function getFontCategoryTabs(): array
    {
        $value = $this->evaluate($this->fontCategoryTabs);
        if ($value === false) {
            return [];
        }
        $categories = $value === true ? FontCatalog::CATEGORIES : IdentifierList::normalize($value);
        if (array_diff($categories, FontCatalog::CATEGORIES) !== []) {
            throw new InvalidArgumentException('Unknown font category tab.');
        }
        $labels = $this->evaluate($this->fontCategoryLabels);
        if (! is_array($labels)) {
            throw new InvalidArgumentException('Font category labels must be a map.');
        }
        foreach ($labels as $key => $label) {
            if (! in_array($key, FontCatalog::CATEGORIES, true) || ! is_string($label) || trim($label) === '' || mb_strlen($label) > 64) {
                throw new InvalidArgumentException('Invalid font category label.');
            }
        }
        $tabs = [['value' => '', 'label' => Translations::get('filament-flex-fields::font-select.all')]];
        foreach ($categories as $category) {
            $tabs[] = ['value' => $category, 'label' => $labels[$category] ?? Translations::get('filament-flex-fields::font-select.categories.'.$category)];
        }

        return $tabs;
    }

    private function fontInteger(mixed $value, string $name, int $minimum, int $maximum): int
    {
        $number = PickerConfiguration::integer($value, $name);
        if ($number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException("Font {$name} is outside its allowed range.");
        }

        return $number;
    }

    /** @return array{weight: int, style: string, text: ?string} */
    public function getFontPreviewPolicy(): array
    {
        $text = PickerConfiguration::optionalText($this->evaluate($this->fontPreviewText), 'previewText');
        if ($text !== null && (mb_strlen($text) > 128 || trim($text) === '' || preg_match('/[\x00-\x1f\x7f]/u', $text))) {
            throw new InvalidArgumentException('Font preview text must contain 1–128 printable characters.');
        }

        return ['weight' => $this->fontInteger($this->evaluate($this->fontPreviewWeight), 'previewWeight', 1, 1000),
            'style' => PickerConfiguration::choice($this->evaluate($this->fontPreviewStyle), ['normal', 'italic'], 'previewStyle'), 'text' => $text];
    }
}
