<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Security;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureState;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Throwable;

final class ScannerHub
{
    public function __construct(private readonly MediaRegistry $registry, private readonly MediaCaptureState $hooks) {}

    public function scan(PreparedMedia $file, MediaWrite $intent, MediaPolicy $policy, string $stage): void
    {
        if (($stage === 'before' && ! $policy->scanBeforeWrite) || ($stage === 'after' && ! $policy->scanAfterWrite)) {
            return;
        }
        $scanners = $this->registry->scanners();
        $callback = $this->hooks->virusScanCallback;
        if ($policy->requireScanner && $scanners === [] && $callback === null) {
            throw new MediaFailure('scanner_unavailable', 'A required media scanner is unavailable.');
        }
        try {
            foreach ($scanners as $scanner) {
                $scanner->scan($file, $intent, $stage);
            }
            if ($callback !== null && $callback($file->path) !== true) {
                throw new MediaFailure('scan_rejected', 'Media scanner did not certify clean bytes.');
            }
        } catch (MediaFailure $failure) {
            throw $failure;
        } catch (Throwable $failure) {
            throw new MediaFailure('scanner_failed', 'Media scanner failed.', $failure);
        }
    }
}
