<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:assets:publish')]
final class PublishComponentAssetsCommand extends Command
{
    protected $signature = 'fff:assets:publish {--public-root= : Existing public asset store for a staged deployment}';

    protected $description = 'Publish all declared Flex component resources and registered CSS/JS';

    public function handle(ComponentAssetPublisher $publisher): int
    {
        $root = $this->option('public-root');
        if ($root !== null && (! is_string($root) || $root === '')) {
            $this->components->error('Public root must be an existing directory.');

            return self::FAILURE;
        }
        $result = $publisher->publish($root);
        $this->components->info(sprintf('Published %d files; %d unchanged.', $result['copied'], $result['unchanged']));

        return self::SUCCESS;
    }
}
