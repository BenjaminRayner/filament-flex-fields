<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Actions\Concerns;

trait CanRequireHoldConfirm
{
    use HasAuditReasonRequirement;
    use HoldConfirm\ConfiguresConfirmMorph;
    use HoldConfirm\ConfiguresHoldConfirmation;
    use HoldConfirm\RendersConfirmation;
}
