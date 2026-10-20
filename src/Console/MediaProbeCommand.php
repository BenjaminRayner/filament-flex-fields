<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\MediaDiagnosticsReported;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaOperationalProbe;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaProbeOptions;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:media:probe')]
final class MediaProbeCommand extends Command
{
    protected $signature = 'fff:media:probe {--write : Explicitly allow diagnostic storage, cache and temporary-file writes} {--disk= : Explicit filesystem disk to write, read and clean a diagnostic object} {--lock-store= : Explicit cache store to exercise owner-aware locks} {--format=* : Exercise the bundled PNG encoder path to jpg, png, webp or avif} {--emit : Dispatch a redacted summary to native Laravel monitoring listeners} {--json : Export a redacted report}';

    protected $description = 'Actively probe explicitly selected media infrastructure using isolated diagnostic data';

    public function handle(MediaOperationalProbe $probe): int
    {
        try {
            $disk = $this->option('disk');
            $store = $this->option('lock-store');
            $formats = $this->option('format');
            if (($disk !== null && ! is_string($disk)) || ($store !== null && ! is_string($store)) || ! is_array($formats)
                || count(array_filter($formats, is_string(...))) !== count($formats)) {
                throw new InvalidArgumentException('Select disk/store names and a list of output formats.');
            }
            $options = new MediaProbeOptions(allowWrites: (bool) $this->option('write'), disk: $disk, lockStore: $store,
                formats: array_values(array_filter($formats, is_string(...))));
        } catch (InvalidArgumentException $error) {
            $this->components->error($error->getMessage());

            return self::INVALID;
        }
        $report = $probe->inspect($options, $this->option('json') ? null : fn (string $id) => $this->line('Diagnostic run: '.$id));
        $result = $report->toArray();
        if ($this->option('emit')) {
            app(ActivityDispatcher::class)->dispatch(new MediaDiagnosticsReported($report));
        }
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($result['passed'] ? 'Selected probes passed in this process.' : 'A selected probe failed.');
            foreach ($result['checks'] as $check) {
                $this->line(strtoupper($check['status']).' / '.$check['code'].': '.$check['observation']);
                if ($check['status'] === 'failed') {
                    $this->line('Next step: '.$check['action']);
                }
            }
            foreach ($result['limitations'] as $limit) {
                $this->line($limit);
            }
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
