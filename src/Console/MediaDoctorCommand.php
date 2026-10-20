<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\MediaDiagnosticsReported;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaConfigurationDoctor;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:media:doctor')]
final class MediaDoctorCommand extends Command
{
    protected $signature = 'fff:media:doctor {--emit : Dispatch a redacted summary to native Laravel monitoring listeners} {--json : Export redacted structured results} {--images : Inspect the bundled GD conversion profile} {--queued : Include durable conversion queue checks} {--distributed : Include cross-process deployment requirements} {--custom-encoder : Inspect an application encoder profile without invoking it} {--connection= : Conversion queue connection used by the application} {--queue=media-conversions : Conversion queue name used by the application} {--timeout=60 : Conversion job timeout in seconds} {--format=* : Requested image output format: jpg, png, webp or avif}';

    protected $description = 'Inspect Media OS configuration without storage access, dispatching jobs or executing extensions';

    public function handle(MediaConfigurationDoctor $doctor): int
    {
        try {
            $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT);
            if ($timeout === false) {
                throw new InvalidArgumentException('Use an integer timeout between 1 and 3600 seconds.');
            }
            $connection = $this->option('connection');
            $queue = $this->option('queue');
            $formats = $this->option('format');
            if (($connection !== null && ! is_string($connection)) || ! is_string($queue) || ! is_array($formats)
                || count(array_filter($formats, is_string(...))) !== count($formats)) {
                throw new InvalidArgumentException('Use a connection name and a list of supported output formats.');
            }
            $options = new MediaDoctorOptions(images: (bool) $this->option('images'), queued: (bool) $this->option('queued'),
                distributed: (bool) $this->option('distributed'), customEncoder: (bool) $this->option('custom-encoder'),
                connection: $connection, timeout: $timeout, formats: array_values(array_filter($formats, is_string(...))), queue: $queue);
        } catch (InvalidArgumentException $error) {
            $this->components->error($error->getMessage());

            return self::INVALID;
        }
        $report = $doctor->inspect($options);
        $result = $report->toArray();
        if ($this->option('emit')) {
            app(ActivityDispatcher::class)->dispatch(new MediaDiagnosticsReported($report));
        }
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($result['configurationValid'] ? 'No known configuration errors. Operational verification is still required.' : 'Media configuration errors detected.');
            foreach ($result['checks'] as $check) {
                $this->line(strtoupper($check['status']).' / '.$check['code'].': '.$check['impact']);
                if ($check['status'] !== 'passed') {
                    $this->line('Next step: '.$check['action']);
                }
            }
        }

        return $result['configurationValid'] ? self::SUCCESS : self::FAILURE;
    }
}
