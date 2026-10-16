<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionJournal;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'flex-fields:prune-media-conversions')]
final class PruneMediaConversionJournalsCommand extends Command
{
    protected $signature = 'flex-fields:prune-media-conversions {--days=7 : Retain cancellation journals for this many days} {--limit=1000 : Maximum deleted journals}';

    protected $description = 'Remove old cancelled conversion journals only after all owned files are gone';

    public function handle(ConversionJournal $journal): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 3650 || $limit === false || $limit < 1 || $limit > 10000) {
            $this->components->error('Use days 1–3650 and limit 1–10000.');

            return self::INVALID;
        }
        $deleted = $journal->pruneCancelled(now()->subDays($days)->getTimestamp(), $limit);
        $this->components->info("Pruned {$deleted} cancelled conversion journal(s). Active receipts were retained.");

        return self::SUCCESS;
    }
}
