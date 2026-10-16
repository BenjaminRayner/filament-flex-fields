<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Contracts;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

interface MediaAccessPolicy
{
    /** Throw on denied access. Absence of a custom policy is not application authorization. */
    public function authorize(string $action, MediaWrite $intent, ?MediaReceipt $receipt): void;
}
