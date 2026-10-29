<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexCatalogPluginPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundCluster;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundComponentPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexLicenseGuidePage;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\AssetRegistration;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundAccess;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

it('omits all catalog pages and catalog CSS when globally disabled while fields remain usable', function (): void {
    config()->set('filament-flex-fields.playground.enabled', false);
    $panel = Panel::make()->id('without-catalog')->plugin(FilamentFlexFieldsPlugin::make());
    expect($panel->getPages())->toBe([])
        ->and($panel->getClusters())->toBe([])
        ->and(SelectField::make('status')->options(['ready' => 'Ready'])->getOptions())->toBe(['ready' => 'Ready'])
        ->and(array_map(fn ($asset) => $asset->getId(), (new AssetRegistration)->stylesheets()))->not->toContain(FlexFieldAssets::PLAYGROUND_STYLESHEET_ID);
});

it('can disable the catalog independently in one panel', function (): void {
    $disabled = Panel::make()->id('staff')->plugin(FilamentFlexFieldsPlugin::make()->playground(false));
    $enabled = Panel::make()->id('development')->plugin(FilamentFlexFieldsPlugin::make());
    expect($disabled->getPages())->toBe([])
        ->and($enabled->getPages())->toContain(FlexFieldsPlaygroundIndexPage::class)
        ->and(PlaygroundAccess::isEnabled($disabled))->toBeFalse()
        ->and(PlaygroundAccess::isEnabled($enabled))->toBeTrue();
    Filament::setCurrentPanel($disabled);
    expect(PlaygroundAccess::isEnabled())->toBeFalse();
    $another = Panel::make()->id('another')->plugin(FilamentFlexFieldsPlugin::make());
    expect($another->getPageConfigurations())->not->toBeEmpty();
    Filament::setCurrentPanel($enabled);
    expect(PlaygroundAccess::isEnabled())->toBeTrue();
});

it('does not let a panel override the global catalog kill switch', function (): void {
    config()->set('filament-flex-fields.playground.enabled', false);
    $panel = Panel::make()->id('disabled')->plugin(FilamentFlexFieldsPlugin::make()->playground());
    expect($panel->getPages())->toBe([])->and(PlaygroundAccess::isEnabled($panel))->toBeFalse();
});

it('requires authentication and evaluates the global gate without caching a prior user', function (): void {
    $panel = Panel::make()->id('admin')->plugin(FilamentFlexFieldsPlugin::make());
    Filament::setCurrentPanel($panel);
    config()->set('filament-flex-fields.playground.gate', 'view-flex-catalog');
    Gate::define('view-flex-catalog', fn ($user): bool => $user->id === 7);
    expect(PlaygroundAccess::canAccess())->toBeFalse();
    Auth::setUser(new GenericUser(['id' => 7]));
    expect(PlaygroundAccess::canAccess())->toBeTrue();
    Auth::setUser(new GenericUser(['id' => 8]));
    expect(PlaygroundAccess::canAccess())->toBeFalse();
});

it('combines panel authorization with the global gate and passes the current user and panel', function (): void {
    $calls = [];
    $plugin = FilamentFlexFieldsPlugin::make()->playgroundAuthorizeUsing(function ($user, Panel $panel) use (&$calls): bool {
        $calls[] = [$user->id, $panel->getId()];

        return $user->id === 7;
    });
    $panel = Panel::make()->id('restricted')->plugin($plugin);
    Filament::setCurrentPanel($panel);
    Auth::setUser(new GenericUser(['id' => 7]));
    expect(PlaygroundAccess::canAccess())->toBeTrue()->and($calls)->toBe([[7, 'restricted']]);
    config()->set('filament-flex-fields.playground.gate', 'deny-catalog');
    Gate::define('deny-catalog', fn (): bool => false);
    expect(PlaygroundAccess::canAccess())->toBeFalse()->and($calls)->toHaveCount(1);
});

it('uses the panel guard rather than an unrelated authenticated default guard', function (): void {
    config()->set('auth.guards.catalog', ['driver' => 'session', 'provider' => 'users']);
    $panel = Panel::make()->id('guarded')->authGuard('catalog')->plugin(FilamentFlexFieldsPlugin::make());
    Filament::setCurrentPanel($panel);
    Auth::guard('web')->setUser(new GenericUser(['id' => 7]));
    expect(PlaygroundAccess::canAccess())->toBeFalse();
    Auth::guard('catalog')->setUser(new GenericUser(['id' => 7]));
    expect(PlaygroundAccess::canAccess())->toBeTrue();
});

it('enforces the same authorization on every catalog entry point', function (string $page): void {
    Auth::setUser(new GenericUser(['id' => 7]));
    config()->set('filament-flex-fields.playground.gate', 'deny-all-catalog');
    Gate::define('deny-all-catalog', fn (): bool => false);
    expect($page::canAccess())->toBeFalse();
})->with([FlexFieldsPlaygroundCluster::class, FlexFieldsPlaygroundIndexPage::class, FlexFieldsPlaygroundComponentPage::class, FlexCatalogPluginPage::class, FlexLicenseGuidePage::class]);
