<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\LinkPreviewRenderState;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\MetadataPresentation;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\Validation\LinkPreviewUrlRule;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

class LinkPreviewField extends Field
{
    use CanBeReadOnly;
    use Concerns\LinkPreview\ConfiguresLinkPreview;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;

    protected string $view = 'filament-flex-fields::forms.components.link-preview-field';

    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->placeholder(fn (): string => Translations::get('filament-flex-fields::default.link_preview.placeholder'));
        $this->visitIcon(FlexIcon::Paperclip);
        $this->rules(['nullable', 'string', 'max:2048', new LinkPreviewUrlRule]);
        $this->afterStateHydrated(static function (LinkPreviewField $component, mixed $state): void {
            if (is_string($state)) {
                $component->state(trim($state) === '' ? null : trim($state));
            }
        });
        $this->dehydrateStateUsing(static fn (mixed $state): ?string => is_string($state) && trim($state) !== '' ? trim($state) : null);
    }

    public function getScrapeUrl(): string
    {
        return route('filament-flex-fields.url-meta.scrape', absolute: false);
    }

    /** Initial rendering only reads existing metadata. Never fetch from the network here.
     * @return array{title?: string, description?: string, image?: string}|null
     */
    public function resolveInitialPreview(?string $url): ?array
    {
        if (! $this->isPreviewEnabled() || $url === null) {
            return null;
        }
        $data = app(UrlMetaScraper::class)->cached($url);

        return $data === null || $data === [] ? null : MetadataPresentation::forBrowser($data);
    }

    /** @return array<string, mixed> */
    public function getAlpineConfiguration(): array
    {
        return [
            'scrapeUrl' => $this->getScrapeUrl(), 'previewEnabled' => $this->isPreviewEnabled(),
            'previewDebounce' => $this->getPreviewDebounce(), 'previewMinUrlLength' => $this->getPreviewMinUrlLength(),
            'previewMinSkeletonMs' => $this->getPreviewMinSkeletonMs(), 'previewLayout' => $this->getPreviewLayout(),
            'showVisitLink' => $this->shouldShowVisitLink(), 'prefix' => $this->getPrefix(),
            'labels' => ['error' => __('filament-flex-fields::default.link_preview.error'), 'visit' => $this->getVisitLabel()],
        ];
    }

    /** @return array<string, bool> */
    public function getWrapperClasses(): array
    {
        return ['fff-link-preview-field' => true, 'fff-link-preview-field--'.$this->getSize() => true, 'fff-link-preview-field--layout-'.$this->getPreviewLayout() => true];
    }

    public function getLinkPreviewRenderState(mixed $state): LinkPreviewRenderState
    {
        return LinkPreviewRenderState::from($this, $state);
    }
}
