<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Illuminate\Contracts\Config\Repository;
use Throwable;

/** Inspect declarations only: never construct a filesystem or call a remote API. */
final class DiskConfigurationCheck
{
    public function __construct(private readonly Repository $config) {}

    public function inspect(MediaDiagnosticReport $report, string $role, mixed $name, bool $private): void
    {
        $disks = $this->config->get('filesystems.disks', []);
        $visited = [];
        for ($depth = 0; $depth < 8; $depth++) {
            if (! is_string($name) || $name === '' || ! is_array($disks) || ! isset($disks[$name])
                || ! is_array($disks[$name]) || isset($visited[$name])) {
                $report->add($role.'_disk_invalid', 'failed', 'A selected media disk is missing, malformed or cyclic.',
                    'Select an existing Laravel filesystem disk for this role; correct any scoped-disk reference cycle.');

                return;
            }
            $visited[$name] = true;
            $disk = $disks[$name];
            if (($disk['read-only'] ?? false) !== false) {
                $report->add($role.'_disk_read_only', 'failed', 'This media role must write but its disk is configured read-only.',
                    'Select a writable disk for this role. Keep read-only disks for serving existing content.');

                return;
            }
            if ($private && ($disk['visibility'] ?? 'private') !== 'private') {
                $report->add($role.'_disk_public', 'failed', 'Media expected to remain private is configured on a public or invalid-visibility disk.',
                    'Use a private disk outside public web paths for private originals, staging, upload manifests and conversion journals. Public originals require an explicit public media policy.');

                return;
            }
            $driver = $disk['driver'] ?? null;
            if ($driver === 'scoped') {
                try {
                    if (! is_string($disk['prefix'] ?? null)) {
                        throw new \InvalidArgumentException;
                    }
                    MediaAddress::path($disk['prefix'], allowEmpty: true);
                } catch (Throwable) {
                    $report->add($role.'_disk_prefix_invalid', 'failed', 'A scoped media disk has an unsafe or missing prefix.',
                        'Set a safe relative prefix in the scoped filesystem configuration.');

                    return;
                }
                $name = $disk['disk'] ?? null;

                continue;
            }
            $valid = match ($driver) {
                'local' => is_string($disk['root'] ?? null) && $disk['root'] !== '',
                's3' => is_string($disk['bucket'] ?? null) && $disk['bucket'] !== '',
                default => is_string($driver) && $driver !== '',
            };
            if (! $valid) {
                $report->add($role.'_disk_config_invalid', 'failed', 'A selected filesystem has incomplete driver configuration.',
                    'Configure its driver and required local root or S3 bucket in config/filesystems.php.');

                return;
            }
            $report->add($role.'_disk_declared', in_array($driver, ['local', 's3'], true) ? 'passed' : 'unverified',
                'The selected disk declaration was inspected; storage access was not attempted.',
                'Verify actual read/write permissions and shared access with your deployment checks; custom drivers require their own validation.');

            return;
        }
        $report->add($role.'_disk_depth', 'failed', 'A scoped-disk chain exceeds the diagnostic depth limit.',
            'Reduce the scoped-disk chain to fewer than eight wrappers.');
    }
}
