<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages;

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogBanner;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\OfficialPromotions;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionRepository;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\SponsorPreview;
use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\SponsorProfile;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundAccess;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/** Full-width FLEX discovery page. The old class/route remains a bookmark-compatible entry. */
class FlexFieldsPlaygroundIndexPage extends Page
{
    protected static ?string $cluster = FlexFieldsPlaygroundCluster::class;

    protected static ?string $slug = 'index';

    protected static ?string $navigationLabel = 'FLEX';

    protected static ?string $title = 'FLEX';

    protected string $view = 'filament-flex-fields::pages.flex-fields-playground-index';

    #[Locked]
    public bool $promotionsResolved = false;

    #[Locked]
    #[Url(as: 'sponsors-preview', except: false)]
    public bool $sponsorPreview = false;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return FlexFieldsPlaygroundRegistry::isEnabled() && PlaygroundAccess::canAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return 'FLEX';
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function loadPromotions(): void
    {
        abort_unless(static::canAccess(), 403);
        app(PromotionRepository::class)->refresh();
        $this->promotionsResolved = true;
    }

    /** @return array<string, mixed> */
    public function catalogData(): array
    {
        $catalog = app(FlexCatalog::class);
        $entries = array_values(array_map(static fn (array $entry): array => [
            ...$entry,
            'url' => $entry['demoSlug'] !== null
                ? FlexFieldsPlaygroundComponentPage::getUrl(configuration: $entry['demoSlug'])
                : FlexCatalogPluginPage::getUrl(['entry' => $entry['id']]),
        ], array_filter($catalog->entries(), static fn (array $entry): bool => $entry['listed'])));
        $popular = array_values(array_filter($entries, static fn (array $entry): bool => $entry['popularRank'] !== null));
        usort($popular, static fn (array $left, array $right): int => [$left['popularRank'], $left['label']] <=> [$right['popularRank'], $right['label']]);
        $groups = [];
        foreach ($catalog->categories() as $category) {
            $groups[$category['id']] = [...$category, 'entries' => []];
        }
        foreach ($entries as $entry) {
            $groups[$entry['categories'][0]]['entries'][] = $entry;
        }
        $availableKinds = [];
        foreach ($entries as $entry) {
            foreach ($entry['kinds'] as $kind) {
                $availableKinds[$kind] = true;
            }
        }
        $componentKinds = array_values(array_filter(
            [ComponentKind::Field->value, ComponentKind::Column->value, ComponentKind::Display->value, ComponentKind::Entry->value, ComponentKind::Action->value],
            static fn (string $kind): bool => isset($availableKinds[$kind]),
        ));
        $snapshot = app(PromotionRepository::class)->snapshot(); // Cache-only: never waits for GitHub during SSR.
        $banners = array_map(static fn (array $banner): array => (new CatalogBanner(...$banner))->presentation(), $snapshot->banners ?? []);
        $previewSponsors = app()->environment(['local', 'testing'])
            && $this->sponsorPreview;

        return [
            'entries' => $entries, 'popular' => $popular, 'componentKinds' => $componentKinds,
            'noteworthy' => array_values(array_filter($entries, static fn (array $entry): bool => $entry['newAndNoteworthy'])),
            'groups' => array_values(array_filter($groups, static fn (array $group): bool => $group['entries'] !== [])), 'banners' => $banners,
            'promotionsRefreshDue' => ! $this->promotionsResolved && $snapshot->refreshDue,
            'bannerMode' => count($banners) > 1 ? 'slider' : 'static',
            'promotionsPending' => ! $this->promotionsResolved && $snapshot->banners === null && $snapshot->refreshDue,
            'sponsorsPreview' => $previewSponsors,
            'sponsors' => $previewSponsors ? SponsorPreview::profiles() : array_map(static fn (array $sponsor): array => SponsorProfile::fromArray($sponsor)->presentation(), $snapshot->sponsors),
            'bannerOptions' => $snapshot->options ?? OfficialPromotions::sliderOptions(),
        ];
    }
}
