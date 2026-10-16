<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Components\Authoring\ComponentPackageBlueprint;
use Bjanczak\FilamentFlexFields\Support\Components\Authoring\ComponentPackageGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:flex-component-package')]
final class MakeFlexComponentPackageCommand extends Command
{
    protected $signature = 'make:flex-component-package {package : Composer vendor/package} {path : New package directory; its parent must exist} {--prefix= : Unique asset namespace, defaults to vendor-package} {--namespace= : PHP Vendor\\Package namespace} {--class= : Base class name} {--dry-run : List files without creating anything} {--json : Print a machine-readable result}';

    protected $description = 'Generate an extension family with a form field, table column, infolist entry and shared assets';

    public function handle(ComponentPackageGenerator $generator): int
    {
        try {
            $package = $this->argument('package');
            $path = $this->argument('path');
            if (! is_string($package) || ! is_string($path)) {
                throw new InvalidArgumentException('Package and destination must be strings.');
            }
            $segments = explode('/', $package);
            $blueprint = new ComponentPackageBlueprint($package,
                $this->stringOption('prefix', str_replace('/', '-', $package)),
                $this->stringOption('namespace', implode('\\', array_map(Str::studly(...), $segments))),
                $this->stringOption('class', Str::studly($segments[1] ?? '')));
            $files = $this->option('dry-run') ? array_keys($generator->plan($blueprint)) : $generator->generate($blueprint, $path);
            $report = ['package' => $package, 'prefix' => $blueprint->prefix, 'namespace' => $blueprint->phpNamespace,
                'path' => $path, 'dryRun' => (bool) $this->option('dry-run'), 'files' => $files];
            if ($this->option('json')) {
                $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } else {
                $this->components->info(($report['dryRun'] ? 'Planned' : 'Created').' component package: '.$package);
                foreach ($files as $file) {
                    $this->line($file);
                }
                $this->line('Follow the generated README to build, install and publish; dependencies were not installed.');
            }

            return self::SUCCESS;
        } catch (InvalidArgumentException|RuntimeException $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);
        if ($value === null) {
            return $default;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Option must be a string: '.$name);
        }

        return $value;
    }
}
