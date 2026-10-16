<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FontSelect\ConfiguresFonts;
use Bjanczak\FilamentFlexFields\Support\Fonts\Data\FontFamily;
use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;
use Bjanczak\FilamentFlexFields\Support\Fonts\FontPage;
use Bjanczak\FilamentFlexFields\Support\Fonts\Providers\GoogleFonts;
use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/** Font data adapts the real Select; no second dropdown or virtualizer. */
class FontSelect extends SelectField
{
    use CanBeReadOnly;
    use ConfiguresFonts;

    protected string $view = 'filament-flex-fields::forms.components.font-select';

    private ?string $fontPolicySignature = null;

    /** @var array<string, FontFamily> */
    private array $allowedFonts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchable()->clearable()->plainTrigger()->placeholder(fn (): string => Translations::get('filament-flex-fields::font-select.placeholder'));
        $this->dehydrateStateUsing(static fn (mixed $state): mixed => is_string($state) && trim($state) === '' ? null : $state);
        $this->rule(static fn (FontSelect $field): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            if ($value !== null && $value !== '' && (! is_string($value) || ! isset($field->getAllowedFontFamilies()[$value]))) {
                $fail(__('validation.in', ['attribute' => $field->getLabel()]));
            }
        });
    }

    public function getAssetComponent(): string
    {
        return 'font-select';
    }

    public function shouldEnforceStaticOptionKeys(): bool
    {
        return false;
    }

    public function getInValidationRuleValues(): ?array
    {
        return null;
    }

    /** @return array<string, FontFamily> */
    public function getAllowedFontFamilies(): array
    {
        if ($this->isMultiple() || $this->isNative()) {
            throw new \InvalidArgumentException('Font Select requires the shared single-selection dropdown.');
        }
        $policy = $this->getFontPolicy();
        $signature = hash('xxh128', serialize($policy));
        if ($this->fontPolicySignature === $signature) {
            return $this->allowedFonts;
        }
        $families = FontCatalog::families($policy['providers'], $policy['local']);
        $only = $policy['fonts'] === null ? null : array_fill_keys($policy['fonts'], true);
        $except = array_fill_keys($policy['except'], true);
        $families = array_filter($families, static fn (FontFamily $font): bool => ($only === null || isset($only[$font->id]))
            && ! isset($except[$font->id]) && ($policy['categories'] === [] || in_array($font->category, $policy['categories'], true))
            && ($policy['subsets'] === [] || array_intersect($policy['subsets'], $font->subsets) !== [])
            && (! $policy['variable'] || $font->variable));
        uasort($families, static fn (FontFamily $a, FontFamily $b): int => ($policy['sort'] === 'popularity' ? $a->rank <=> $b->rank : 0) ?: strcasecmp($a->family, $b->family));
        $this->fontPolicySignature = $signature;

        return $this->allowedFonts = $families;
    }

    /** @return array<string, mixed>|null */
    public function getFontSeed(): ?array
    {
        $state = $this->getRawState();
        $family = is_string($state) ? ($this->getAllowedFontFamilies()[$state] ?? null) : null;

        return $family === null ? null : $this->fontOption($family);
    }

    /** @return array<string, mixed> */
    public function fontOption(FontFamily $font): array
    {
        $policy = $this->getFontPreviewPolicy();
        $variant = $font->variant($policy['weight'], $policy['style']);
        $sample = $policy['text'] ?? (in_array('emoji', $font->subsets, true) && ! in_array('latin', $font->subsets, true) ? '😀 🎨 ✨' : $font->family);
        $suffix = $variant['style'] === 'italic' ? ':ital,wght@1,'.$variant['weight'] : ':wght@'.$variant['weight'];
        $url = $font->file ?? 'https://fonts.googleapis.com/css2?family='.rawurlencode($font->family.$suffix).'&text='.rawurlencode($sample === $font->family ? $sample : $sample.' '.$font->family).'&display=swap';
        $key = hash('sha256', serialize([$font->id, $font->version, $variant, $sample, $url]));

        return ['value' => $font->id, 'label' => $font->family,
            'description' => Translations::get('filament-flex-fields::font-select.categories.'.$font->category),
            'font' => ['key' => $key, 'kind' => $font->file === null ? 'google-css' : 'file', 'url' => $url,
                'text' => $sample, 'weight' => $variant['weight'], 'style' => $variant['style']]];
    }

    /** @return array{items: list<array<string, mixed>>, hasMore: bool, cursor: ?string} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getFontSelectPage(string $query = '', ?string $category = null, ?string $cursor = null): array
    {
        if (! app(SelectSearchRateLimiter::class)->attempt((string) $this->getName())) {
            return ['items' => [], 'hasMore' => false, 'cursor' => null];
        }
        if (mb_strlen($query) > 128 || ($category !== null && $category !== '' && ! in_array($category, FontCatalog::CATEGORIES, true))) {
            throw new \InvalidArgumentException('Invalid font search.');
        }
        $context = hash('sha256', serialize([$this->getFontPolicy(), GoogleFonts::revision(), $query, $category, $this->getFontPerPage()]));
        $offset = FontPage::offset($cursor, $context);
        $needle = mb_strtolower(trim($query));
        $families = array_values(array_filter($this->getAllowedFontFamilies(), static fn (FontFamily $font): bool => ($category === null || $category === '' || $font->category === $category)
            && ($needle === '' || str_contains(mb_strtolower($font->family), $needle))));
        $slice = array_slice($families, $offset, $this->getFontPerPage());
        $more = $offset + count($slice) < count($families);

        return ['items' => array_map($this->fontOption(...), $slice), 'hasMore' => $more,
            'cursor' => $more ? FontPage::cursor($offset + count($slice), $context) : null];
    }

    /** @return array<string, mixed>|null */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getFontSelectionPreview(string $value): ?array
    {
        if (strlen($value) > 160 || ! app(SelectSearchRateLimiter::class)->attempt((string) $this->getName())) {
            return null;
        }
        $font = $this->getAllowedFontFamilies()[$value] ?? null;

        return $font === null ? null : $this->fontOption($font);
    }
}
