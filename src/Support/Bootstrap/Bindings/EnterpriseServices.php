<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\Admin\HoldConfirmState;
use Bjanczak\FilamentFlexFields\Support\Compliance\ComplianceState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\FieldAccessState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\TenantFieldPackState;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\SchemaMemoryState;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Defaults\SmartDefaultsState;
use Illuminate\Contracts\Foundation\Application;

final class EnterpriseServices
{
    public static function register(Application $app): void
    {
        $app->scoped(FieldAccessState::class);
        $app->scoped(TenantFieldPackState::class);
        $app->scoped(SmartDefaultsState::class);
        $app->scoped(SchemaMemoryState::class);
        $app->scoped(HoldConfirmState::class);
        $app->scoped(ComplianceState::class);
    }
}
