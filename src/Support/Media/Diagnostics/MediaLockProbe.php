<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Throwable;

final class MediaLockProbe
{
    public function __construct(private readonly CacheManager $cache) {}

    public function inspect(string $name, MediaProbeReport $report): void
    {
        try {
            $store = $this->cache->store($name)->getStore();
            if (! $store instanceof LockProvider) {
                $report->add('lock_resolution', false);

                return;
            }
            $key = 'fff:diagnostic:'.$report->runId;
            $primary = $store->lock($key, 60, bin2hex(random_bytes(16)));
            $contender = $store->lock($key, 60, bin2hex(random_bytes(16)));
        } catch (Throwable) {
            $report->add('lock_resolution', false);

            return;
        }
        $report->add('lock_resolution', true);
        $contenderAttempted = false;
        $primaryHeld = false;
        $contenderHeld = false;
        try {
            if (! $report->check('lock_acquire', static function () use ($primary, &$primaryHeld): bool {
                $primaryHeld = true;
                $acquired = $primary->get();
                $primaryHeld = $acquired !== false;

                return $acquired === true;
            })) {
                return;
            }
            $contenderAttempted = true;
            if (! $report->check('lock_exclusion', static function () use ($contender, &$contenderHeld): bool {
                $contenderHeld = true;
                $acquired = $contender->get();
                $contenderHeld = $acquired !== false;

                return $acquired === false;
            })) {
                return;
            }
            if (! $report->check('lock_ownership', static function () use ($contender, &$contenderHeld): bool {
                if ($contender->release() !== false) {
                    return false;
                }
                $contenderHeld = true;
                $acquired = $contender->get();
                $contenderHeld = $acquired !== false;

                return $acquired === false;
            })) {
                return;
            }
            if (! $report->check('lock_reacquire', static function () use ($primary, $contender, &$primaryHeld, &$contenderHeld): bool {
                if ($primary->release() !== true) {
                    return false;
                }
                $primaryHeld = false;
                $contenderHeld = true;
                $acquired = $contender->get();
                $contenderHeld = $acquired !== false;

                return $acquired === true;
            })) {
                return;
            }
        } finally {
            $primaryClean = $this->release($primary, $primaryHeld);
            $contenderClean = ! $contenderAttempted || $this->release($contender, $contenderHeld);
            $report->add('lock_cleanup', $primaryClean && $contenderClean);
        }
    }

    private function release(Lock $lock, bool $held): bool
    {
        try {
            return $lock->release() === true || ! $held;
        } catch (Throwable) {
            return false;
        }
    }
}
