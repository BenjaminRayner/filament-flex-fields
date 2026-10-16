<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Runtime;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAccessPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaScanner;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\DiskAdapter;

final class MediaRegistry
{
    /** @var array<string, object> */
    private array $instances = [];

    private int $revision = -1;

    private ?MediaAdapter $disk = null;

    private ?MediaAdapter $spatie = null;

    public function __construct(private readonly MediaExtensionState $state) {}

    public function adapter(string $name): MediaAdapter
    {
        $this->refresh();
        if (! isset($this->state->adapters[$name])) {
            return match ($name) {
                'disk' => $this->disk ??= app(DiskAdapter::class),
                'spatie' => $this->spatie ??= app(SpatieAdapter::class),
                default => throw new MediaFailure('adapter_unavailable', 'Unknown Media OS adapter: '.$name),
            };
        }

        return $this->resolve('adapters', $name, MediaAdapter::class);
    }

    public function processor(string $name): MediaProcessor
    {
        return $this->resolve('processors', $name, MediaProcessor::class);
    }

    /** @return list<MediaScanner> */
    public function scanners(): array
    {
        return array_map(fn (string $name): MediaScanner => $this->resolve('scanners', $name, MediaScanner::class), array_keys($this->state->scanners));
    }

    /** @return list<MediaAccessPolicy> */
    public function accessPolicies(): array
    {
        return array_map(fn (string $name): MediaAccessPolicy => $this->resolve('accessPolicies', $name, MediaAccessPolicy::class), array_keys($this->state->accessPolicies));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function resolve(string $group, string $name, string $contract): object
    {
        $this->refresh();
        $key = $group.':'.$name;
        if (isset($this->instances[$key])) {
            $existing = $this->instances[$key];
            if (! $existing instanceof $contract) {
                throw new MediaFailure('extension_invalid', 'Cached Media OS extension has the wrong contract.');
            }

            return $existing;
        }
        $factory = $this->state->{$group}[$name] ?? null;
        if ($factory === null) {
            throw new MediaFailure('extension_unavailable', 'Unknown Media OS extension: '.$name);
        }
        $instance = $factory();
        if (! $instance instanceof $contract) {
            throw new MediaFailure('extension_invalid', 'Media OS factory does not implement '.$contract);
        }

        return $this->instances[$key] = $instance;
    }

    private function refresh(): void
    {
        if ($this->revision !== $this->state->revision) {
            $this->instances = [];
            $this->revision = $this->state->revision;
        }
    }
}
