<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Support\Icons\IconSvgCache;
use Bjanczak\FilamentFlexFields\Support\Icons\Picker\IconSelectPage;
use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Renderless;

/** @mixin IconPickerField */
trait TransportsIconPicker
{
    /**
     * @param  list<string>  $icons
     * @return list<array{name: string, html: string}>
     */
    public function renderIconSvgs(array $icons): array
    {
        $index = $this->getCatalogIndex();
        $icons = array_values(array_unique(array_filter(
            array_map(static fn (mixed $icon): string => is_string($icon) ? trim($icon) : '', $icons),
            static fn (string $icon): bool => $icon !== '' && $index->isAllowed($icon),
        ), SORT_STRING));

        if ($icons === []) {
            return [];
        }

        $cached = $this->getSvgCache()->rememberMany(
            $icons,
            fn (array $missing): array => $this->renderMissingIconSvgs($missing),
        );

        return array_map(
            static fn (string $icon): array => [
                'name' => $icon,
                'html' => $cached[$icon] ?? '',
            ],
            $icons,
        );
    }

    /**
     * @param  list<string>  $icons
     * @return array<string, string>
     */
    protected function renderMissingIconSvgs(array $icons): array
    {
        $rendered = [];

        foreach ($icons as $icon) {
            $html = $this->renderIconHtml($icon);

            if ($html !== '') {
                $rendered[$icon] = $html;
            }
        }

        return $rendered;
    }

    public function renderIconHtml(?string $icon): string
    {
        $icon = $icon === null ? null : trim($icon);
        if (blank($icon) || ! $this->isAllowedIcon((string) $icon)) {
            return '';
        }

        $html = \Filament\Support\generate_icon_html($icon, size: IconSize::Medium);

        return $html instanceof Htmlable ? $html->toHtml() : '';
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function getIconPickerSearchResults(string $query = '', ?string $set = null, int $page = 1): array
    {
        app(SelectSearchRateLimiter::class)->requireAttempt((string) $this->getName());

        $results = $this->searchIcons($query, $set, $page);

        if ($this->getSearchResultsLayout() !== 'list') {
            $results['previews'] = $this->renderIconSvgs(
                array_column($results['icons'], 'name'),
            );
        }

        return $results;
    }

    /** @return array{items: list<array{value: string, label: string, preview: string}>, hasMore: bool, cursor: ?string} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getIconPickerSelectPage(string $query = '', ?string $set = null, ?string $cursor = null): array
    {
        app(SelectSearchRateLimiter::class)->requireAttempt((string) $this->getName());
        $context = IconSelectPage::context($query, $set, $this->getPerPage());
        $page = IconSelectPage::resolve($cursor, $context);
        $results = $this->searchIcons($query, $set, $page);
        $previews = array_column($this->renderIconSvgs(array_column($results['icons'], 'name')), 'html', 'name');
        $items = array_map(static fn (array $icon): array => [
            'value' => $icon['name'], 'label' => $icon['label'], 'preview' => $previews[$icon['name']] ?? '',
        ], $results['icons']);

        return IconSelectPage::publish($items, $page, (bool) $results['hasMore'], $context);
    }

    /**
     * @param  list<string>  $icons
     * @return list<array{name: string, html: string}>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getIconPickerSvgPreviews(array $icons = []): array
    {
        app(SelectSearchRateLimiter::class)->requireAttempt((string) $this->getName());

        if (count($icons) > self::MAX_SVG_PREVIEW_BATCH) {
            $icons = array_slice($icons, 0, self::MAX_SVG_PREVIEW_BATCH);
        }

        return $this->renderIconSvgs($icons);
    }

    protected function getSvgCache(): IconSvgCache
    {
        return app(IconSvgCache::class);
    }
}
