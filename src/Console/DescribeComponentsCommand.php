<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentUsageCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExampleRepository;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:components:describe')]
final class DescribeComponentsCommand extends Command
{
    protected $signature = 'fff:components:describe {component? : Installed component ID} {--kind= : Filter listing by field, display, column, entry, action or primitive} {--limit=100 : Listing page size, from 1 to 100} {--offset=0 : Listing offset, from 0 to 8192} {--example= : Read a one-based declared example ID} {--json : Print machine-readable metadata or source}';

    protected $description = 'Discover installed component definitions and declared PHP examples without executing them';

    public function handle(ComponentRegistry $registry, ComponentUsageCatalog $catalog): int
    {
        try {
            $id = $this->argument('component');
            $kindOption = $this->option('kind');
            $exampleOption = $this->option('example');
            $limit = $this->option('limit');
            $offset = $this->option('offset');
            $kind = is_string($kindOption) ? ComponentKind::tryFrom($kindOption) : null;
            if (($id !== null && ! is_string($id)) || ($kindOption !== null && $kind === null)
                || ($id !== null && $kindOption !== null) || ($id === null && $exampleOption !== null)) {
                throw new InvalidArgumentException('Use --kind only for listing; --example requires a component ID. Unknown component kinds are rejected.');
            }
            if (! is_string($limit) || preg_match('/^[1-9][0-9]{0,2}$/D', $limit) !== 1 || (int) $limit > 100
                || ! is_string($offset) || preg_match('/^(?:0|[1-9][0-9]{0,3})$/D', $offset) !== 1 || (int) $offset > 8192) {
                throw new InvalidArgumentException('Listing requires a limit from 1 to 100 and an offset from 0 to 8192.');
            }
            $report = ['format' => 1, 'package' => FilamentFlexFieldsPlugin::PACKAGE_NAME,
                'installedVersion' => InstalledVersions::isInstalled(FilamentFlexFieldsPlugin::PACKAGE_NAME)
                    ? InstalledVersions::getPrettyVersion(FilamentFlexFieldsPlugin::PACKAGE_NAME) : null];
            if ($id === null) {
                $listing = $catalog->listing($registry, $kind);
                $report['components'] = array_slice($listing, (int) $offset, (int) $limit);
                $next = (int) $offset + count($report['components']);
                $report['pagination'] = ['total' => count($listing), 'offset' => (int) $offset,
                    'limit' => (int) $limit, 'nextOffset' => $next < count($listing) ? $next : null];
            } else {
                CatalogValue::id($id);
                $report['component'] = $catalog->describe($registry, $id);
                if ($exampleOption !== null) {
                    if (! is_string($exampleOption) || preg_match('/^[1-9][0-9]?$/D', $exampleOption) !== 1) {
                        throw new InvalidArgumentException('Example must be an existing one-based numeric ID.');
                    }
                    $reference = $catalog->examples($registry, $id)[(int) $exampleOption - 1] ?? null;
                    if ($reference === null) {
                        throw new InvalidArgumentException('Example ID is not declared for this component.');
                    }
                    $source = app(CodeExampleRepository::class)->source($reference);
                    $report['example'] = ['id' => (int) $exampleOption,
                        'source' => $source, 'sha256' => hash('sha256', $source),
                        'scope' => 'Trusted installed package example. Adapt it to the application; it is not executed by this command.'];
                }
            }
        } catch (InvalidArgumentException|RuntimeException $error) {
            if ($this->option('json')) {
                $this->line(json_encode(['format' => 1, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } else {
                $this->components->error($error->getMessage());
            }

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } elseif (isset($report['example'])) {
            $this->line($report['example']['source']);
        } elseif (isset($report['components'])) {
            $this->table(['ID', 'Kind', 'Label'], $report['components']);
        } else {
            $this->line(json_encode($report['component'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
