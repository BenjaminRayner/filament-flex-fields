<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Feature;

use Bjanczak\FilamentFlexFields\Tests\Support\FlexFieldsTestPanelProvider;
use Bjanczak\FilamentFlexFields\Tests\TestCase;

final class PlaygroundDisabledRoutesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), FlexFieldsTestPanelProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-flex-fields.playground.enabled', false);
    }

    public function test_disabled_catalog_has_no_registered_routes_and_direct_access_is_not_found(): void
    {
        foreach ($this->app['router']->getRoutes() as $route) {
            $this->assertStringNotContainsString('admin/flex-fields', $route->uri());
        }
        $this->get('/admin/flex-fields/index')->assertNotFound();
        $this->get('/admin/flex-fields/license-guide')->assertNotFound();
    }
}
