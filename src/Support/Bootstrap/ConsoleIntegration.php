<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap;

use Bjanczak\FilamentFlexFields\Console\AssetReleasesCommand;
use Bjanczak\FilamentFlexFields\Console\AssetsDoctorCommand;
use Bjanczak\FilamentFlexFields\Console\AssetsExplainCommand;
use Bjanczak\FilamentFlexFields\Console\ComponentRemovalImpactCommand;
use Bjanczak\FilamentFlexFields\Console\InstallWhisperRuntimeCommand;
use Bjanczak\FilamentFlexFields\Console\MakeFlexComponentPackageCommand;
use Bjanczak\FilamentFlexFields\Console\MakeFlexFieldSchemaMigrationCommand;
use Bjanczak\FilamentFlexFields\Console\PruneCaptureMediaCommand;
use Bjanczak\FilamentFlexFields\Console\PruneMediaConversionJournalsCommand;
use Bjanczak\FilamentFlexFields\Console\PruneMediaStagingCommand;
use Bjanczak\FilamentFlexFields\Console\PublishComponentAssetsCommand;
use Bjanczak\FilamentFlexFields\Console\WhisperRuntimeStatusCommand;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\AssetBuildValidator;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\CaptureRetentionSchedule;
use Closure;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class ConsoleIntegration
{
    /** @param Closure(): void $publishAssets */
    public static function register(Application $app, Closure $publishAssets): void
    {
        if (! $app->runningInConsole()) {
            return;
        }

        \Illuminate\Console\Application::starting(static function (\Illuminate\Console\Application $artisan): void {
            $artisan->resolveCommands([
                AssetsDoctorCommand::class,
                AssetReleasesCommand::class,
                AssetsExplainCommand::class,
                ComponentRemovalImpactCommand::class,
                MakeFlexComponentPackageCommand::class,
                PublishComponentAssetsCommand::class,
                InstallWhisperRuntimeCommand::class,
                WhisperRuntimeStatusCommand::class,
                PruneCaptureMediaCommand::class,
                PruneMediaStagingCommand::class,
                PruneMediaConversionJournalsCommand::class,
                MakeFlexFieldSchemaMigrationCommand::class,
            ]);
        });

        // Native Filament copies its own registered files before CommandFinished.
        // Fail before that command starts, rather than discovering corruption afterwards.
        $app->make(Dispatcher::class)->listen(CommandStarting::class, static function (CommandStarting $event) use ($app): void {
            if ($event->command === 'filament:assets') {
                $app->make(AssetBuildValidator::class)->validate($app->make(ComponentRegistry::class))->assertValid();
                $app->make(AssetDeliveryContext::class)->policy()->json();
            }
        });
        $app->make(Dispatcher::class)->listen(CommandFinished::class, static function (CommandFinished $event) use ($publishAssets): void {
            if ($event->command === 'filament:assets' && $event->exitCode === 0) {
                $publishAssets();
            }
        });

        $registerSchedule = static function (Schedule $schedule): void {
            CaptureRetentionSchedule::register($schedule);
            if (config('filament-flex-fields.media_capture.staging.schedule_enabled', true)) {
                $schedule->command('flex-fields:prune-media-staging')->hourly()->withoutOverlapping();
            }
            if (config('filament-flex-fields.media_capture.images.queue.prune_schedule_enabled', true)) {
                $schedule->command('flex-fields:prune-media-conversions')->daily()->withoutOverlapping();
            }
        };
        $app->afterResolving(Schedule::class, $registerSchedule);
        if ($app->resolved(Schedule::class)) {
            $registerSchedule($app->make(Schedule::class));
        }
    }
}
