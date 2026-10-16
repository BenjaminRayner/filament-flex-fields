<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Illuminate\Support\HtmlString;

/** Local catalogs adapt data only; Select owns every interactive and presentation layer. */
abstract class CatalogSelectField extends SelectField
{
    use CanBeReadOnly;

    private ?Closure $catalogTransformer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogTransformer = $this->transformOptionsForJsUsing;
        $this->searchable()->richOptions()->clearable(false)->optionsLimit(1000)->searchDebounce(0);
        $this->chevronIcon('hugeicons-unfold-more');
    }

    /** @return array<string, array<string, mixed>> */
    abstract protected function catalogOptions(): array;

    /** @return array<string, array<string, mixed>> */
    public function getOptions(): array
    {
        return $this->catalogOptions();
    }

    public function variant(string|Closure $variant): static
    {
        return parent::variant($variant instanceof Closure
            ? fn (): string => $this->resolveCatalogVariant((string) $this->evaluate($variant))
            : $this->resolveCatalogVariant($variant));
    }

    private function resolveCatalogVariant(string $variant): string
    {
        return $variant === 'primary' ? 'bordered' : $variant;
    }

    public function isSelectControlDisabled(): bool
    {
        return $this->isDisabled() || $this->isReadOnly();
    }

    public function shouldEnforceStaticOptionKeys(): bool
    {
        return true;
    }

    protected function shouldDeferHeadlessOptionsUntilOpen(): bool
    {
        return false;
    }

    public function hasClientSideOptionList(): bool
    {
        return true;
    }

    public function hasDynamicOptions(): bool
    {
        return false;
    }

    /** Each boundary owns its transport; the browser shares parsing by content identity.
     * @return list<array<string, mixed>>
     */
    public function getHeadlessInitialOptionsForJs(): array
    {
        return [];
    }

    /** @param array<string|int, string|array<string, mixed>> $options
     * @return list<array<string, mixed>>
     */
    public function transformRichOptionsForJs(array $options): array
    {
        if ($this->hasOptionView() || $this->getOptionLayout() !== 'list' || ! $this->usesRichOptionHtml() || $this->isHtmlAllowed()) {
            return parent::transformRichOptionsForJs($options);
        }
        $records = [];
        foreach ($options as $value => $shape) {
            $option = $this->normalizeOption($value, $shape);
            if ($option['icon'] !== null || $option['badge'] !== null || $option['chip_label'] !== null) {
                return parent::transformRichOptionsForJs($options);
            }
            $records[] = [
                'value' => (string) $value, 'label' => $option['label'],
                'description' => $option['description'], 'image' => $option['image'],
                'isDisabled' => $option['disabled'], 'fffCatalogRender' => true,
                'descriptionPosition' => $this->getOptionDescriptionPosition(),
                'richListTriggerDisplay' => $this->shouldUseRichListTriggerDisplay(),
            ];
        }

        return $records;
    }

    public function getSelectOptionsRegistryKey(): string
    {
        $options = $this->getOptions();
        foreach ($options as $value => &$option) {
            $option['disabled'] = (bool) ($option['disabled'] ?? false)
                || (bool) ($option['isDisabled'] ?? false)
                || $this->isOptionDisabled($value, (string) $option['label']);
        }
        unset($option);
        $customTransformer = $this->transformOptionsForJsUsing !== $this->catalogTransformer;
        $signature = serialize([static::class, $options, $this->getSize(), $this->getOptionLayout(), $this->getOptionDescriptionPosition(), $this->usesRichOptionHtml(), $this->isHtmlAllowed(), $this->shouldUseRichListTriggerDisplay(),
            $customTransformer ? spl_object_id($this) : null,
            $this->hasOptionView() ? $this->getLivewireKey() : null]);
        $key = hash('xxh128', $signature);
        $registry = request()->attributes->get('fff.select.catalogs', []);
        if ($customTransformer || ! array_key_exists($key, $registry)) {
            $registry[$key] = $this->getOptionsForJs();
            request()->attributes->set('fff.select.catalogs', $registry);
        }

        return $key;
    }

    public function getSelectCatalogMarkup(): HtmlString
    {
        $key = $this->getSelectOptionsRegistryKey();
        $registry = request()->attributes->get('fff.select.catalogs', []);
        $json = json_encode($registry[$key], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        return new HtmlString('<template data-fff-select-catalog="'.$key.'">'.$json.'</template>');
    }

    /** @return array<string, mixed> */
    public function getHeadlessAlpineConfig(): array
    {
        return [...parent::getHeadlessAlpineConfig(), 'optionsRegistryKey' => $this->getSelectOptionsRegistryKey(),
            'disabled' => $this->isDisabled() || $this->isReadOnly(), 'virtualizeThreshold' => 50, ...$this->getSelectSurfaceConfiguration()];
    }
}
