<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\Segments\HasSegmentAppearance;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\Composition\SegmentTabsV2;
use Bjanczak\FilamentFlexFields\Support\Segments\SegmentItem;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Concerns\CanPersistTab;
use Filament\Schemas\Components\Concerns\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class SegmentTabs extends Component
{
    use CanPersistTab;
    use HasControlSize;
    use HasLabel;
    use HasSegmentAppearance;

    protected string $view = 'filament-flex-fields::schemas.components.segment-tabs';

    protected int|Closure $activeTab = 1;

    protected string|Closure|null $tabQueryStringKey = null;

    protected string|Htmlable|Closure|null $helperText = null;

    final public function __construct(string|Htmlable|Closure|null $label = null)
    {
        $this->label($label);
    }

    public static function make(string|Htmlable|Closure|null $label = null): static
    {
        $component = app(static::class, ['label' => $label]);
        $component->configure();

        return $component;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->key(function (SegmentTabs $component): ?string {
            $label = $component->getLabel();
            if (blank($label)) {
                return null;
            }
            $statePath = $component->getStatePath();

            return Str::slug(Str::transliterate($label, strict: true)).'::'.(filled($statePath) ? "{$statePath}::segment-tabs" : 'segment-tabs');
        }, isInheritable: true);
    }

    /** @param array<SegmentTab>|Closure $tabs */
    public function tabs(array|Closure $tabs): static
    {
        $this->components($tabs);

        return $this;
    }

    public function activeTab(int|Closure $activeTab): static
    {
        $this->activeTab = $activeTab;

        return $this;
    }

    public function persistTabInQueryString(string|Closure|null $key = 'segment-tab'): static
    {
        $this->tabQueryStringKey = $key;

        return $this;
    }

    public function getTabQueryStringKey(): ?string
    {
        return $this->evaluate($this->tabQueryStringKey);
    }

    public function isTabPersistedInQueryString(): bool
    {
        return filled($this->getTabQueryStringKey());
    }

    public function getTabPersistKey(): string
    {
        return SegmentTabsV2::persistKey((string) ($this->getId() ?? ''));
    }

    /** @return list<SegmentTab> */
    public function getVisibleTabs(): array
    {
        return array_values(array_filter($this->getChildSchema()?->getComponents() ?? [],
            static fn (mixed $tab): bool => $tab instanceof SegmentTab && $tab->isVisible()));
    }

    public function getActiveTab(): int
    {
        $tabs = $this->getVisibleTabs();
        if ($this->isTabPersistedInQueryString()) {
            $raw = request()->query($this->getTabQueryStringKey());
            $query = SegmentTabsV2::parsePersisted(is_string($raw) ? $raw : null);
            foreach ($tabs as $index => $tab) {
                // Browser writes relative keys; accept old absolute IDs as well.
                if ($query !== null && ($tab->getKey(isAbsolute: false) === $query || $tab->getId() === $query)) {
                    return $index + 1;
                }
            }
        }
        $index = (int) $this->evaluate($this->activeTab);

        return isset($tabs[$index - 1]) ? $index : 1;
    }

    public function getActiveTabKey(): ?string
    {
        return ($this->getVisibleTabs()[$this->getActiveTab() - 1] ?? null)?->getKey(isAbsolute: false);
    }

    public function isTabActive(SegmentTab $tab): bool
    {
        return $tab->getKey(isAbsolute: false) === $this->getActiveTabKey();
    }

    public function helperText(string|Htmlable|Closure|null $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function getHelperText(): string|Htmlable|null
    {
        $text = $this->evaluate($this->helperText);

        return ($text instanceof Htmlable || (is_string($text) && filled($text))) ? $text : null;
    }

    public function getSegmentAssetComponent(): string
    {
        return 'segment-tabs';
    }

    public static function tabVisibilityExpression(SegmentTab $tab): ?string
    {
        $conditions = [];
        if (filled($hidden = $tab->getHiddenJs())) {
            $conditions[] = "! ({$hidden})";
        }
        if (filled($visible = $tab->getVisibleJs())) {
            $conditions[] = "({$visible})";
        }

        return $conditions === [] ? null : implode(' && ', $conditions);
    }

    /** @return list<SegmentItem> */
    public function getSegmentItems(): array
    {
        return array_map(static function (SegmentTab $tab): SegmentItem {
            $badge = $tab->getBadge();

            return new SegmentItem(
                key: (string) $tab->getKey(isAbsolute: false), label: $tab->getLabel(), icon: $tab->getIcon(),
                tooltip: $tab->getTooltip(), panelId: $tab->getId(),
                visibilityJs: self::tabVisibilityExpression($tab), badge: $badge,
                badgeColor: filled($badge) ? $tab->getBadgeColor($badge) : null,
                badgeTooltip: filled($badge) ? $tab->getBadgeTooltip($badge) : null,
            );
        }, $this->getVisibleTabs());
    }
}
