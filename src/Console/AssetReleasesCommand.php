<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\CoreReleaseRetention;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\PackageReleaseRetention;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'fff:assets:releases')]
final class AssetReleasesCommand extends Command
{
    protected $signature = 'fff:assets:releases {--package= : Select one installed Composer package instead of core} {--prune : Remove eligible old releases} {--keep=3 : Number of newest releases to retain} {--min-age-days=30 : Minimum age before deletion} {--protect=* : Additional release hashes serving on other nodes} {--public-root= : Existing shared public asset store} {--json : Emit structured results}';

    protected $description = 'Verify retained core or installed package releases and preview or explicitly apply retention';

    public function handle(CoreReleaseRetention $retention, PackageReleaseRetention $packages, ComponentRegistry $registry): int
    {
        try {
            $keep = $this->integerOption('keep', 1, 256);
            $days = $this->integerOption('min-age-days', 1, 3650);
            $protected = $this->option('protect');
            $prefix = config('filament.assets_path') ?? '';
            if (! is_array($protected) || ! array_is_list($protected) || ! is_string($prefix)) {
                throw new \InvalidArgumentException('Invalid release retention options.');
            }
            foreach ($protected as $release) {
                if (! is_string($release)) {
                    throw new \InvalidArgumentException('Protected releases must be strings.');
                }
            }
            $selected = $this->option('package');
            if ($selected !== null && (! is_string($selected) || $selected === '' || strlen($selected) > 192)) {
                throw new \InvalidArgumentException('Select an installed Composer package identity.');
            }
            $package = $selected === null ? null : ($registry->packageAssets()->packages[$selected] ?? throw new \InvalidArgumentException('Selected asset package is not installed in the component registry.'));
            $current = $package?->manifest->release ?? (new AssetVersionManifest(allowDevelopmentFallback: false))->release() ?? throw new \RuntimeException('Current release is not sealed.');
            $root = $this->option('public-root') ?? public_path();
            if (! is_string($root) || $root === '') {
                throw new \InvalidArgumentException('Public root must be an existing directory.');
            }
            $rows = $package === null
                ? $retention->run($root, $prefix, $current, $keep, $days * 86400, $protected, (bool) $this->option('prune'))
                : $packages->run($root, $prefix, $package, $keep, $days * 86400, $protected, (bool) $this->option('prune'));
            if ($this->option('json')) {
                $this->line(json_encode(['current' => $current, 'releases' => $rows, ...($package === null ? [] : ['package' => $package->manifest->package])], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } else {
                $this->table(['Release', 'Published', 'Protected', 'Eligible', 'Removed'], array_map(static fn (array $row): array => [$row['release'], gmdate('Y-m-d H:i:s', $row['publishedAt']).' UTC', $row['protected'] ? 'yes' : 'no', $row['eligible'] ? 'yes' : 'no', $row['removed'] ? 'yes' : 'no'], $rows));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->line(json_encode(['error' => 'release_retention_failed'], JSON_THROW_ON_ERROR));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    private function integerOption(string $name, int $minimum, int $maximum): int
    {
        $value = $this->option($name);
        if (! is_string($value) || preg_match('/^[0-9]{1,4}$/D', $value) !== 1 || (int) $value < $minimum || (int) $value > $maximum) {
            throw new \InvalidArgumentException('Invalid release retention '.$name.'.');
        }

        return (int) $value;
    }
}
