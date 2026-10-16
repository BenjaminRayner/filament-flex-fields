<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events;

use Throwable;

final class UploadNotifications
{
    public static function report(Throwable $error): void
    {
        try {
            report($error);
        } catch (Throwable) {
            // Reporting infrastructure must not replace an operation result.
        }
    }
}
