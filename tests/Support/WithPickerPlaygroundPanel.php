<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

trait WithPickerPlaygroundPanel
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), FlexFieldsTestPanelProvider::class];
    }
}
