<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Closure;
use LogicException;
use Throwable;

/** Only fixed findings and an independent random diagnostic identity enter the exported report. */
final class MediaProbeReport
{
    private const FINDINGS = [
        'storage_resolution' => ['The explicitly selected disk can be resolved.', 'Check the selected filesystem declaration, driver installation and credentials.'],
        'storage_write' => ['A small private diagnostic object can be written.', 'Allow writes and private visibility in the diagnostic namespace on this disk.'],
        'storage_read' => ['Reading the diagnostic object returns the exact bytes written.', 'Check read permissions, storage integrity and read-after-write behavior on this disk.'],
        'storage_visibility' => ['The adapter reports private visibility for the diagnostic object.', 'Check private visibility support and storage policy; separately verify HTTP access controls.'],
        'storage_cleanup' => ['The exact diagnostic object is deleted and no longer exists.', 'Use the run ID to remove only its .fff-diagnostics/<runId>.probe object after correcting delete permissions.'],
        'lock_resolution' => ['The explicitly selected cache store supports the Laravel lock contract.', 'Select a store implementing LockProvider and install its driver.'],
        'lock_acquire' => ['The first diagnostic lock owner can acquire its key.', 'Check cache connectivity and lock write permissions; use a persistent shared store for multiple processes.'],
        'lock_exclusion' => ['A different owner cannot acquire the held diagnostic lock.', 'Use a cache backend with working atomic locks; do not rely on this store for media coordination until corrected.'],
        'lock_ownership' => ['A different owner cannot release the held diagnostic lock.', 'Use owner-aware Laravel locks; never repair a production lock by forcibly releasing an unknown owner.'],
        'lock_reacquire' => ['A different owner can acquire the key after the first owner releases it.', 'Check owner-aware release and subsequent acquisition on the selected cache store.'],
        'lock_cleanup' => ['Diagnostic lock owners have released their own keys.', 'Check cache connectivity and lock release permissions. Diagnostic keys have a 60-second TTL; never flush the cache.'],
        'image_source' => ['The bundled synthetic PNG can be prepared within the probe limits.', 'Enable fileinfo and PNG decoding support, and make PHP temporary storage writable.'],
        'image_jpg' => ['The bundled encoder converts the synthetic PNG into a valid 8 by 4 JPEG.', 'Enable JPEG encoding in GD and writable PHP temporary storage.'],
        'image_png' => ['The bundled encoder converts the synthetic PNG into a valid 8 by 4 PNG.', 'Enable PNG encoding in GD and writable PHP temporary storage.'],
        'image_webp' => ['The bundled encoder converts the synthetic PNG into a valid 8 by 4 WebP.', 'Enable WebP encoding in GD and writable PHP temporary storage.'],
        'image_avif' => ['The bundled encoder converts the synthetic PNG into a valid 8 by 4 AVIF.', 'Enable AVIF encoding in GD or select a supported conversion format.'],
        'image_cleanup' => ['Owned image snapshots have been closed.', 'Check PHP temporary storage and process cleanup; no application image is used by this probe.'],
    ];

    /** @var list<array{code: string, status: string, observation: string, action: string}> */
    private array $checks = [];

    public readonly string $runId;

    public function __construct(private readonly MediaProbeOptions $options)
    {
        $this->runId = bin2hex(random_bytes(16));
    }

    public function add(string $code, bool $passed): bool
    {
        if (! isset(self::FINDINGS[$code]) || count($this->checks) >= 32) {
            throw new LogicException('Invalid media probe finding.');
        }
        [$observation, $action] = self::FINDINGS[$code];
        $this->checks[] = ['code' => $code, 'status' => $passed ? 'passed' : 'failed', 'observation' => $observation, 'action' => $action];

        return $passed;
    }

    /** Backend exception text must not escape into the report or the application's exception reporter. */
    public function check(string $code, Closure $check): bool
    {
        try {
            $passed = $check() === true;
        } catch (Throwable) {
            $passed = false;
        }

        return $this->add($code, $passed);
    }

    /** @return array{format: int, scope: string, runId: string, passed: bool, readOnly: bool, operationallyVerified: bool, cleanupRequired: bool, counts: array<string, int>, profile: array<string, mixed>, checks: list<array{code: string, status: string, observation: string, action: string}>, limitations: list<string>, documentation: string} */
    public function toArray(): array
    {
        $failed = count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 'failed'));
        $cleanupRequired = count(array_filter($this->checks, static fn (array $check): bool => $check['status'] === 'failed' && str_ends_with($check['code'], '_cleanup'))) > 0;

        return ['format' => 1, 'scope' => 'media-active-probe', 'runId' => $this->runId,
            'passed' => $failed === 0 && $this->checks !== [], 'readOnly' => false, 'operationallyVerified' => false,
            'cleanupRequired' => $cleanupRequired, 'counts' => ['passed' => count($this->checks) - $failed, 'failed' => $failed],
            'profile' => ['disk' => $this->options->disk === null ? null : 'explicit',
                'lockStore' => $this->options->lockStore === null ? null : 'explicit', 'encoder' => $this->options->formats === [] ? null : 'bundled-gd',
                'formats' => $this->options->formats],
            'checks' => $this->checks,
            'limitations' => ['Results describe only the selected targets from this PHP process at this moment.',
                'Lock contention is tested between independent handles in this process; cross-host sharing and crash recovery are not established.',
                'A private visibility result is adapter metadata, not a test of public HTTP access.',
                'Workers, schedulers, custom encoders, Spatie conversions and application save/reopen authorization are not exercised.'],
            'documentation' => 'https://flex-fields.bjanczak.com/docs/media-os-diagnostics#active-probes'];
    }
}
