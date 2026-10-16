<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentDependencyExplanation;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentRegistrationExplanation;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:assets:explain')]
final class AssetsExplainCommand extends Command
{
    protected $signature = 'fff:assets:explain {component : Registered component identity} {--capability=* : Enabled per-instance capability} {--locations : Include local definition files in this server-side report} {--json : Print a machine-readable report}';

    protected $description = 'Explain component composition, stylesheet owners and package artifacts without rendering or publishing';

    public function handle(ComponentRegistry $registry, ComponentDependencyExplanation $explanation): int
    {
        try {
            $component = $this->argument('component');
            $capabilities = $this->option('capability');
            if (! is_string($component) || ! is_array($capabilities) || ! array_is_list($capabilities)) {
                throw new InvalidArgumentException('Component and capability arguments must be strings.');
            }
            foreach ($capabilities as $capability) {
                if (! is_string($capability)) {
                    throw new InvalidArgumentException('Capability arguments must be strings.');
                }
            }
            $report = $explanation->explain($registry, $component, $capabilities);
            $registration = new ComponentRegistrationExplanation;
            foreach ($report['definitions'] as $id => $definition) {
                $report['definitions'][$id]['registration'] = $registration->explain($registry, $id, (bool) $this->option('locations'));
            }
        } catch (InvalidArgumentException $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Installation dependencies: '.$report['component']);
            $this->table(['From', 'Dependency', 'Reason'], array_map(static fn (array $edge): array => array_values($edge), $report['edges']));
            $this->table(['Stylesheet', 'Owner'], array_map(static fn (string $owner, string $style): array => [$style, $owner], $report['styles'], array_keys($report['styles'])));
            $this->line($report['browserState']);
            foreach ($report['definitions'] as $id => $definition) {
                $metadata = $definition['registration'];
                if ($metadata['location'] !== null) {
                    $this->line($id.': '.$metadata['location']['file'].':'.$metadata['location']['line']);
                }
                if ($metadata['deprecation'] !== null) {
                    $this->components->warn($id.': '.$metadata['deprecation']['message'].' Replacement: '.$metadata['deprecation']['replacement'].'; removal not before '.$metadata['deprecation']['removalNotBefore'].'.');
                }
            }
        }

        return self::SUCCESS;
    }
}
