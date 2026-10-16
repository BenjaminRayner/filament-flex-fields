<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Integration\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaExtensions;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Bjanczak\FilamentFlexFields\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

final class MediaScopeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaScopeProvider::class];
    }

    public function test_boot_factories_replay_without_retaining_previous_tenant_instances_or_snapshots(): void
    {
        $intent = new MediaWrite(processors: ['tenant-aware']);
        for ($scope = 0; $scope < 2000; $scope++) {
            config()->set('media-os-test.tenant', $scope);
            app()->forgetScopedInstances();
            $registry = app(MediaRegistry::class);
            $processor = $registry->processor('tenant-aware');
            $this->assertInstanceOf(MediaScopeProcessor::class, $processor);
            $this->assertSame($scope, $processor->tenant);
            $this->assertSame($processor, $registry->processor('tenant-aware'));
            $file = app(MediaOs::class)->inspect(MediaSource::bytes('owned snapshot'), $intent);
            $path = $file->path;
            $file->close();
            $this->assertFileDoesNotExist($path);
        }
    }
}

final class MediaScopeProvider extends ServiceProvider
{
    public function boot(): void
    {
        MediaExtensions::processor('tenant-aware', static fn (): MediaProcessor => new MediaScopeProcessor(config('media-os-test.tenant', 0)));
    }
}

final readonly class MediaScopeProcessor implements MediaProcessor
{
    public function __construct(public int $tenant) {}

    public function process(PreparedMedia $file, MediaWrite $intent): PreparedMedia
    {
        return $file;
    }
}
