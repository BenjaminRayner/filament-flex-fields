<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields;

use Bjanczak\FilamentFlexFields\Enums\Density;
use Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics\AssetFormsPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics\AssetModalsPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics\AssetTablesPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexCatalogPluginPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundCluster;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexLicenseGuidePage;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldGroupResourceRegistrar;
use Bjanczak\FilamentFlexFields\Support\Theme\FlexFieldsTheme;
use Bjanczak\FilamentFlexFields\Support\Theme\ThemeTokenMap;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentFlexFieldsPlugin implements Plugin
{
    public const PACKAGE_NAME = 'janczakb/filament-flex-fields';

    public const PACKAGE_URL = 'https://github.com/janczakb/filament-flex-fields';

    protected bool $playgroundEnabled = true;

    protected ?Closure $playgroundAuthorization = null;

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?string $navigationLabel = null;

    protected ?string $navigationIcon = null;

    protected Density|string|null $density = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $theme = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-flex-fields';
    }

    public function register(Panel $panel): void
    {
        if (! config('filament-flex-fields.enabled', true)) {
            return;
        }

        if ($this->isPlaygroundEnabled()) {
            $panel->pages([
                FlexFieldsPlaygroundCluster::class,
                FlexFieldsPlaygroundIndexPage::class,
                FlexCatalogPluginPage::class,
                FlexLicenseGuidePage::class,
                ...FlexFieldsPlaygroundRegistry::pageConfigurations(),
                ...(app()->environment(['local', 'testing']) ? [
                    AssetFormsPage::class,
                    AssetTablesPage::class,
                    AssetModalsPage::class,
                ] : []),
            ]);

        }

        FlexFieldGroupResourceRegistrar::register($panel);
    }

    public function boot(Panel $panel): void
    {
        if (! config('filament-flex-fields.enabled', true)) {
            return;
        }

        $flexFieldsTheme = app(FlexFieldsTheme::class);

        if ($this->density !== null) {
            $flexFieldsTheme->setDensity($this->density);
        }

        if ($this->theme !== null) {
            $flexFieldsTheme->mergeTheme($this->theme);
        }

    }

    public function playground(bool $enabled = true): static
    {
        $this->playgroundEnabled = $enabled;

        return $this;
    }

    public function isPlaygroundEnabled(): bool
    {
        return FlexFieldsConfig::isPlaygroundEnabled() && $this->playgroundEnabled;
    }

    public function playgroundAuthorizeUsing(?Closure $callback): static
    {
        $this->playgroundAuthorization = $callback;

        return $this;
    }

    public function getPlaygroundAuthorization(): ?Closure
    {
        return $this->playgroundAuthorization;
    }

    public function density(Density|string $density): static
    {
        $this->density = $density;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    public function theme(array $theme): static
    {
        $this->theme = ThemeTokenMap::validate($theme);

        return $this;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('filament-flex-fields.schema.navigation_group');
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('filament-flex-fields.schema.navigation_sort');
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }
}
