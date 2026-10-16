<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;

final class WorkerActivityListener
{
    public function handle(FieldMounted $event): void
    {
        app(TenantContext::class)->activity = $event->field;
    }
}
