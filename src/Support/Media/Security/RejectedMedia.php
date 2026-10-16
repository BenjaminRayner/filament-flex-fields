<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Security;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaRejection;
use Throwable;

/** Quarantine failure never prevents an attempt to remove the rejected original. */
final class RejectedMedia
{
    public function __construct(private readonly Quarantine $quarantine) {}

    public function remove(MediaAdapter $adapter, MediaReceipt $receipt, ?string $quarantineDisk): MediaRejection
    {
        $path = null;
        $failed = false;
        if ($quarantineDisk !== null) {
            try {
                $path = $this->quarantine->copy($adapter, $receipt, $quarantineDisk);
            } catch (Throwable $error) {
                $failed = true;
                $this->report($error);
            }
        }
        try {
            $adapter->delete($receipt);
        } catch (Throwable $error) {
            $failed = true;
            $this->report($error);
        }

        return new MediaRejection($path, $failed);
    }

    private function report(Throwable $error): void
    {
        try {
            report($error);
        } catch (Throwable) {
            // Reporting has no authority over compensation.
        }
    }
}
