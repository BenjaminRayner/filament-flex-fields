<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Bjanczak\FilamentFlexFields\Support\Slug\SlugGenerator;
use Bjanczak\FilamentFlexFields\Support\Slug\SpatieSlugIntegration;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;

trait HasPermalinkOptions
{
    protected string|Closure|null $slugHost = null;

    protected string|Closure|null $slugPath = null;

    protected string|Closure|null $slugPostfix = null;

    protected string|Closure|null $slugVisitUrl = null;

    protected string|Closure|null $slugVisitLabel = null;

    protected string|Closure|null $slugPermalinkLabel = null;

    protected bool|Closure $slugHostVisible = true;

    protected bool|Closure $slugPathVisible = true;

    protected bool|Closure $slugPreviewVisible = true;

    public function urlHost(string|Closure|null $value): static
    {
        $this->slugHost = $value;

        return $this;
    }

    public function urlPath(string|Closure|null $value): static
    {
        $this->slugPath = $value;

        return $this;
    }

    public function slugLabelPostfix(string|Closure|null $value): static
    {
        $this->slugPostfix = $value;

        return $this;
    }

    public function visitUrl(string|Closure|null $value): static
    {
        $this->slugVisitUrl = $value;

        return $this;
    }

    public function visitRoute(string|Closure|null $value): static
    {
        return $this->visitUrl($value);
    }

    public function visitLinkLabel(string|Closure|null $value): static
    {
        $this->slugVisitLabel = $value;

        return $this;
    }

    public function permalinkLabel(string|Closure|null $value): static
    {
        $this->slugPermalinkLabel = $value;

        return $this;
    }

    public function urlHostVisible(bool|Closure $value = true): static
    {
        $this->slugHostVisible = $value;

        return $this;
    }

    public function urlPathVisible(bool|Closure $value = true): static
    {
        $this->slugPathVisible = $value;

        return $this;
    }

    public function permalinkPreview(bool|Closure $value = true): static
    {
        $this->slugPreviewVisible = $value;

        return $this;
    }

    public function isUrlHostVisible(): bool
    {
        return (bool) $this->evaluate($this->slugHostVisible);
    }

    public function isUrlPathVisible(): bool
    {
        return (bool) $this->evaluate($this->slugPathVisible);
    }

    public function hasPermalinkPreview(): bool
    {
        return (bool) $this->evaluate($this->slugPreviewVisible);
    }

    public function canVisitLink(): bool
    {
        return $this->shouldShowVisitLink() && $this->getOperation() !== 'create';
    }

    public function hasCustomVisitUrl(): bool
    {
        return $this->slugVisitUrl !== null;
    }

    public function getUrlHost(): ?string
    {
        $host = $this->evaluate($this->slugHost);

        return is_string($host) && trim($host) !== '' ? rtrim(trim($host), '/') : null;
    }

    public function getUrlPath(): ?string
    {
        $path = $this->evaluate($this->slugPath);

        return is_string($path) && trim($path) !== '' ? '/'.trim(trim($path), '/').'/' : null;
    }

    public function getDisplayUrlHost(): ?string
    {
        $host = $this->getUrlHost();

        return $host === null ? null : preg_replace('~^https?://~i', '', $host);
    }

    public function getSlugLabelPostfix(): ?string
    {
        $postfix = $this->evaluate($this->slugPostfix);

        return is_string($postfix) && $postfix !== '' ? $postfix : null;
    }

    public function hasUrlContext(): bool
    {
        return $this->getUrlHost() !== null || $this->getUrlPath() !== null || $this->getSlugLabelPostfix() !== null;
    }

    public function getPermalinkLabel(): string
    {
        return $this->evaluate($this->slugPermalinkLabel) ?? Translations::get('filament-flex-fields::default.slug.permalink');
    }

    public function getVisitLinkLabel(): string
    {
        return $this->evaluate($this->slugVisitLabel) ?? Translations::get('filament-flex-fields::default.slug.visit');
    }

    public function usesSelfHealingPermalink(): bool
    {
        $record = $this->resolveRecord();

        return $record !== null && SpatieSlugIntegration::isAvailable()
            && SpatieSlugIntegration::usesSelfHealingUrls($record, $this->getSpatieSlugField());
    }

    public function getPermalinkRecordKey(): int|string|null
    {
        $key = $this->resolveRecord()?->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }

    public function getSelfHealingSeparator(): string
    {
        $record = $this->resolveRecord();

        return $record !== null && SpatieSlugIntegration::isAvailable()
            ? SpatieSlugIntegration::getSelfHealingSeparator($record, $this->getSpatieSlugField()) : $this->getSeparator();
    }

    public function resolvePermalinkSlugForUrl(string $slug): string
    {
        $slug = $this->normalizeSlug($slug);
        $record = $this->resolveRecord();

        return $record !== null && SpatieSlugIntegration::isAvailable()
            ? SpatieSlugIntegration::buildSelfHealingRouteKey($slug, $record, $this->getSpatieSlugField()) : $slug;
    }

    public function getFullPermalinkUrl(?string $slug = null): ?string
    {
        $slug = $this->normalizeSlug($slug ?? '');
        if ($slug === '') {
            return null;
        }
        $routeKey = $this->resolvePermalinkSlugForUrl($slug);
        $custom = $this->evaluate($this->slugVisitUrl, ['slug' => $slug, 'routeKey' => $routeKey, 'record' => $this->resolveRecord()]);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }
        if (! $this->hasUrlContext()) {
            return null;
        }

        return ($this->getUrlHost() ?? '').($this->getUrlPath() ?? '')
            .SlugGenerator::permalinkSlugSegment($routeKey, $this->getUrlPath(), $this->allowsHomepageSlug()).($this->getSlugLabelPostfix() ?? '');
    }
}
