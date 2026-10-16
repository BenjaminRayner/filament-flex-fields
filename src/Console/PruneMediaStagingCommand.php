<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadLeaseRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'flex-fields:prune-media-staging')]
final class PruneMediaStagingCommand extends Command
{
    protected $signature = 'flex-fields:prune-media-staging {--dry-run : Inspect without deleting temporary bytes or leases}';

    protected $description = 'Remove expired and abandoned Media OS staging leases without touching committed media';

    public function handle(MediaStaging $staging, UploadLeaseRepository $uploads): int
    {
        // Inspect source bindings while pending core manifests still provide cleanup authority.
        $bindings = $uploads->prune((bool) $this->option('dry-run'));
        $result = $staging->prune((bool) $this->option('dry-run'));
        foreach ($bindings as $key => $value) {
            $result[$key] += $value;
        }
        $this->components->info(sprintf('Examined %d; %s %d; uncertain %d.', $result['examined'],
            $this->option('dry-run') ? 'would remove' : 'removed', $result['removed'], $result['uncertain']));
        if ($result['uncertain'] > 0) {
            $this->components->warn('Uncertain leases were retained for recovery; no permanent media was removed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
