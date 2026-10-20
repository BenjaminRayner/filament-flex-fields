<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Composer\InstalledVersions;
use LogicException;

/** No raw configuration, resource identifiers, exceptions or callback output. */
final class MediaDiagnosticReport
{
    public function __construct(private readonly MediaDoctorOptions $options) {}

    /** @var list<array{code: string, status: string, impact: string, action: string}> */
    private array $checks = [];

    public function add(string $code, string $status, string $impact, string $action): void
    {
        if (count($this->checks) >= 128 || ! preg_match('/^[a-z][a-z0-9_]{0,95}$/D', $code)
            || ! in_array($status, ['passed', 'failed', 'unverified', 'skipped'], true)) {
            throw new LogicException('Invalid media diagnostic check.');
        }
        $this->checks[] = compact('code', 'status', 'impact', 'action');
    }

    /**
     * @return array{format: int, scope: string, configurationValid: bool, operationallyVerified: bool,
     *     counts: array<string, int>, checks: list<array{code: string, status: string, impact: string, action: string}>,
     *     documentation: string, readOnly: bool, profile: array<string, mixed>, versions: array<string, string|null>}
     */
    public function toArray(): array
    {
        $counts = array_fill_keys(['passed', 'failed', 'unverified', 'skipped'], 0);
        foreach ($this->checks as $check) {
            $counts[$check['status']]++;
        }

        return ['format' => 1, 'scope' => 'media-configuration', 'configurationValid' => $counts['failed'] === 0,
            'operationallyVerified' => false, 'counts' => $counts, 'checks' => $this->checks,
            'readOnly' => true,
            'profile' => ['images' => $this->options->checksImages(), 'queued' => $this->options->queued,
                'distributed' => $this->options->distributed,
                'encoder' => $this->options->checksImages() ? ($this->options->customEncoder ? 'custom' : 'bundled-gd') : null,
                'formats' => $this->options->checksImages() ? ($this->options->formats ?: ['webp']) : [],
                'queueConnection' => $this->options->connection === null ? 'default' : 'explicit',
                'queueName' => $this->options->queue === 'media-conversions' ? 'default' : 'explicit',
                'jobTimeout' => $this->options->queued ? $this->options->timeout : null],
            'versions' => ['php' => PHP_VERSION, 'laravel' => $this->version('laravel/framework'),
                'filament' => $this->version('filament/filament'), 'livewire' => $this->version('livewire/livewire')],
            'documentation' => 'https://flex-fields.bjanczak.com/docs/media-os-diagnostics'];
    }

    private function version(string $package): ?string
    {
        $version = InstalledVersions::isInstalled($package) ? InstalledVersions::getVersion($package) : null;

        return is_string($version) && preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?(?:-(?:alpha|beta|RC)\d+)?$/D', $version) ? $version : null;
    }
}
