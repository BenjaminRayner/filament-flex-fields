<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Admin;

final class HoldConfirmState
{
    public bool $requiresAuditReason = false;

    public ?int $bulkHoldMs = null;
}
