<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages;

use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Illuminate\Support\Facades\Auth;

class FlexFieldsPlaygroundCluster extends Cluster
{
    public const SLUG = 'flex-fields';

    protected static string|\BackedEnum|null $navigationIcon = 'flexbrand-logo';

    protected static ?string $navigationLabel = 'FLEX FIELDS';

    protected static ?string $title = 'FLEX';

    protected static ?string $slug = self::SLUG;

    protected static ?int $navigationSort = 91;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    public static function shouldRegisterNavigation(): bool
    {
        return FlexFieldsPlaygroundRegistry::isEnabled();
    }

    public static function canAccess(): bool
    {
        return FlexFieldsPlaygroundRegistry::isEnabled()
            && Auth::check();
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return Translations::get('filament-flex-fields::catalog.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-flex-fields.navigation.sort', static::$navigationSort);
    }

    public function mount(): void
    {
        redirect(FlexFieldsPlaygroundIndexPage::getUrl());
    }

    public function getSubNavigation(): array
    {
        return [];
    }
}
