<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentRemovalImpact;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:components:removal')]
final class ComponentRemovalImpactCommand extends Command
{
    protected $signature = 'fff:components:removal {package : Exact installed asset package identity} {--json : Print a machine-readable report}';

    protected $description = 'Inspect declared dependencies before removing a component package; never remove files or data';

    public function handle(ComponentRegistry $registry, ComponentRemovalImpact $impact): int
    {
        try {
            $package = $this->argument('package');
            if (! is_string($package)) {
                throw new InvalidArgumentException('Package identity must be a string.');
            }
            $report = $impact->inspect($registry, $package);
        } catch (InvalidArgumentException $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Dependent', 'Dependency', 'Reason'], array_map(static fn (array $edge): array => array_values($edge), $report['dependencyEdges']));
            $this->components->warn($report['limitation']);
        }

        return $report['readyWithinSuppliedScope'] ? self::SUCCESS : self::FAILURE;
    }
}
