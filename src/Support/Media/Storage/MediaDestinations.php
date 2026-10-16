<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Storage;

use Bjanczak\FilamentFlexFields\Support\FileUpload\ScopedDirectoryResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantState;
use Illuminate\Database\Eloquent\Model;

/** Explicit disk and native collection always take precedence over tenant defaults. */
final class MediaDestinations
{
    public function __construct(private readonly MediaCaptureTenantState $state) {}

    /** @param array<string, mixed> $context */
    public function disk(?string $explicit = null, ?string $collection = null, array $context = [], bool $tenant = true): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $this->diskName($explicit);
        }
        if ($collection !== null && $collection !== '') {
            return $this->diskName($collection);
        }
        if ($tenant) {
            $resolver = $this->state->diskResolver;
            $name = $resolver === null ? null : $resolver($context);
            if ($name !== null && $name !== '') {
                return $this->diskName($name);
            }
            $name = config('filament-flex-fields.media_capture.tenant.disk');
            if ($name !== null && $name !== '') {
                return $this->diskName($name);
            }
        }

        return $this->diskName(config('filament-flex-fields.media_capture.disk', config('filesystems.default')));
    }

    /** @param array<string, mixed> $context */
    public function spatieDisk(?string $explicit = null, ?string $collection = null, array $context = []): string
    {
        return $this->disk($explicit, $collection, $context, (bool) config('filament-flex-fields.media_capture.tenant.auto_disk', false));
    }

    /**
     * Legacy fallback can be superseded by an explicit tenant resolver.
     *
     * @param  array<string, mixed>  $context
     */
    public function tenantDisk(?string $fallback, array $context): string
    {
        $resolver = $this->state->diskResolver;
        $name = $resolver === null ? null : $resolver($context);
        $name = $name !== null && $name !== '' ? $name : config('filament-flex-fields.media_capture.tenant.disk');

        return $name !== null && $name !== '' ? $this->diskName($name) : $this->disk($fallback, tenant: false);
    }

    /** @param array<string, mixed> $context */
    public function directory(string $prefix, ?Model $record = null, int|string|null $userId = null, array $context = []): string
    {
        MediaAddress::path($prefix);
        $resolver = $this->state->directoryPrefixResolver;
        $tenant = $resolver === null ? null : $resolver(array_replace($context, [
            'prefix' => $prefix, 'record' => $record, 'user_id' => $userId,
        ]));
        $tenant = $tenant !== null && $tenant !== '' ? $tenant : config('filament-flex-fields.media_capture.tenant.directory_prefix');
        if ($tenant !== null && $tenant !== '') {
            if (! is_string($tenant)) {
                throw new MediaFailure('invalid_destination', 'Tenant media directory must be a string.');
            }
            $prefix = MediaAddress::path($tenant).'/'.$prefix;
        }

        return MediaAddress::path(ScopedDirectoryResolver::resolve($prefix, $record, $userId));
    }

    public function hasDirectoryPrefix(): bool
    {
        return $this->state->directoryPrefixResolver !== null || filled(config('filament-flex-fields.media_capture.tenant.directory_prefix'));
    }

    private function diskName(mixed $name): string
    {
        if (! is_string($name) || $name === '' || strlen($name) > 255 || trim($name) !== $name || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new MediaFailure('invalid_destination', 'Invalid media filesystem disk identity.');
        }

        return $name;
    }
}
