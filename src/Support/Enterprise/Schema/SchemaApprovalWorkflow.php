<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise\Schema;

use InvalidArgumentException;

/** State changes are explicit; authorization remains the application's policy decision. */
final class SchemaApprovalWorkflow
{
    public static function assertState(string $state): void
    {
        if (! in_array($state, ['draft', 'review', 'approved', 'live'], true)) {
            throw new InvalidArgumentException("Invalid schema approval state [{$state}].");
        }
    }

    public static function assertTransition(string $from, string $to): void
    {
        self::assertState($from);
        self::assertState($to);
        $allowed = match ($from) {
            'draft' => ['draft', 'review'],
            'review' => ['draft', 'review', 'approved', 'live'],
            'approved' => ['review', 'approved', 'live'],
            'live' => ['review', 'live'],
            default => throw new InvalidArgumentException('Unsupported schema state.'),
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Invalid schema approval transition [{$from} -> {$to}].");
        }
    }
}
