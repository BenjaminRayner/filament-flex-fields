<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields;

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCss;
use Bjanczak\FilamentFlexFields\Filament\Livewire\CodeExamples;
use Bjanczak\FilamentFlexFields\Filament\Livewire\ScheduleDisplayExamples;
use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Policies\FlexFieldGroupPolicy;
use Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaLayout;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewirePlanProjection;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\AssetRegistration;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\ConfigurationDefaults;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\ConsoleIntegration;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\IconRegistration;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\RenderHookRegistration;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\ServiceBindings;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadModelSavedRegistration;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordSaved;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\RichEditorFlexIcons;
use Bjanczak\FilamentFlexFields\Support\Translatable\RegistersTranslatableFieldMacros;
use Filament\Resources\Events\RecordSaved;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FilamentFlexFieldsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $repository = $this->app->make(Repository::class);
            $overrides = $repository->get('filament-flex-fields', []);
            if (! is_array($overrides)) {
                throw new \InvalidArgumentException('FLEX configuration must be an array.');
            }
            $repository->set('filament-flex-fields', ConfigurationDefaults::merge(
                require __DIR__.'/../config/filament-flex-fields.php', $overrides,
            ));
        }

        // Panel providers may resolve translated navigation during their register phase.
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-flex-fields');

        ServiceBindings::register($this->app);

        IconRegistration::register($this->app);

    }

    public function boot(): void
    {
        RichEditorFlexIcons::register();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/filament-flex-fields.php' => config_path('filament-flex-fields.php'),
        ], 'filament-flex-fields-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'filament-flex-fields-migrations');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/filament-flex-fields'),
        ], 'filament-flex-fields-translations');

        Gate::policy(FlexFieldGroup::class, FlexFieldGroupPolicy::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-flex-fields');
        Livewire::component('flex-code-examples', CodeExamples::class);
        Livewire::component('flex-schedule-display-examples', ScheduleDisplayExamples::class);

        InitialSchemaLayout::register();
        LivewirePlanProjection::register();

        FilamentAsset::register([
            ...$this->registeredStylesheets(),
            ...$this->registeredScripts(),
            ...$this->registeredAlpineComponents(),
            ...$this->registeredAlpineChunkComponents(),
        ], package: FilamentFlexFieldsPlugin::PACKAGE_NAME);

        ConsoleIntegration::register($this->app, fn () => $this->publishStalePackageAssets());

        if (! $this->app->runningInConsole()
            && $this->app->environment('local', 'testing')
            && config('filament-flex-fields.assets.publish_on_boot', false)) {
            $this->app->booted(fn () => $this->publishStalePackageAssets());
        }

        RenderHookRegistration::register();

        RegistersTranslatableFieldMacros::boot();
        // Warm only dispatcher identity bookkeeping, not the upload graph or saved listener.
        // Octane sandboxes share this registration owner even when first use is in a request.
        $this->app->make(UploadModelSavedRegistration::class);
        $this->app->make(Dispatcher::class)->listen(RecordSaved::class,
            static function (Model|RecordSaved $record, array $data = [], ?ResourcePage $page = null): void {
                app(UploadRecordSaved::class)->handle($record, $data, $page);
            });

    }

    /** @return list<FlexFieldsCss> */
    protected function registeredStylesheets(): array
    {
        return (new AssetRegistration)->stylesheets();
    }

    /** @return list<Js> */
    protected function registeredScripts(): array
    {
        return (new AssetRegistration)->scripts();
    }

    /** @return list<FlexFieldsAlpineComponent> */
    protected function registeredAlpineComponents(): array
    {
        return (new AssetRegistration)->alpineComponents();
    }

    /** @return list<FlexFieldsAlpineComponent> */
    protected function registeredAlpineChunkComponents(): array
    {
        return (new AssetRegistration)->alpineChunks();
    }

    protected function publishStalePackageAssets(): void
    {
        if (! FlexFieldAssets::shouldPublishStalePackageAssets()) {
            return;
        }

        FlexFieldAssets::publishStalePackageAssets($this->app->make(Filesystem::class));
    }
}
