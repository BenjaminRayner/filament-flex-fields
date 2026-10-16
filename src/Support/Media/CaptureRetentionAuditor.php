<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\RetentionPruned;

final class CaptureRetentionAuditor
{
    /**
     * @param  list<string>  $items
     */
    public static function record(string $category, array $items, bool $dryRun = false): void
    {
        if ($items === []) {
            return;
        }

        app(ActivityDispatcher::class)->dispatch(new RetentionPruned(
            $category, count($items), array_slice($items, 0, 25), $dryRun,
        ));
    }
}
