<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Data;

use RuntimeException;
use Throwable;

final class MediaFailure extends RuntimeException
{
    public bool $cleanupFailed = false;

    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
