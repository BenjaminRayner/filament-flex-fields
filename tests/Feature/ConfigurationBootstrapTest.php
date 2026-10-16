<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Feature;

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class PartialFlexConfigProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make('config')->set('filament-flex-fields', [
            'ui' => ['flex_text_input_size' => 'sm', 'icon_picker_sets' => []],
            'select' => ['max_page_size' => 17],
            'assets' => ['delivery' => ['allowed_origins' => []]],
        ]);
    }
}

final class ConfigurationBootstrapTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PartialFlexConfigProvider::class, ...parent::getPackageProviders($app)];
    }

    public function test_native_provider_registration_merges_partial_application_defaults(): void
    {
        $this->assertSame('sm', config('filament-flex-fields.ui.flex_text_input_size'));
        $this->assertSame([], config('filament-flex-fields.ui.icon_picker_sets'));
        $this->assertSame('hugeicons-file-01', config('filament-flex-fields.ui.signature_pdf_preview_icon'));
        $this->assertSame(17, config('filament-flex-fields.select.max_page_size'));
        $this->assertSame(4194304, config('filament-flex-fields.select.search_cache_bytes'));
        $this->assertSame([], config('filament-flex-fields.assets.delivery.allowed_origins'));
        $this->assertFalse(config('filament-flex-fields.assets.delivery.require_integrity'));
    }

    public function test_cached_configuration_remains_authoritative_during_provider_registration(): void
    {
        $application = new class extends Application
        {
            public function configurationIsCached(): bool
            {
                return true;
            }
        };
        try {
            $cached = ['ui' => [], 'enabled' => false];
            $repository = new Repository(['filament-flex-fields' => $cached]);
            $application->instance('config', $repository);
            (new FilamentFlexFieldsServiceProvider($application))->register();
            $this->assertSame($cached, $repository->get('filament-flex-fields'));
        } finally {
            Container::setInstance($this->app);
        }
    }
}
