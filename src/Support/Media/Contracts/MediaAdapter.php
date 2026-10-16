<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Contracts;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use DateTimeInterface;

interface MediaAdapter
{
    /** On a failed write the adapter must compensate any resource created before its receipt exists. */
    public function write(PreparedMedia $file, MediaWrite $intent): MediaReceipt;

    /** @return resource Caller owns/ closes the returned fresh readable stream. */
    public function read(MediaReceipt $receipt): mixed;

    public function delete(MediaReceipt $receipt): void;

    /** Apply final publication policy only after verification; native adapter side effects remain documented. */
    public function commit(MediaReceipt $receipt, MediaWrite $intent): void;

    /** Private URL failure must never fall back to an unsigned public URL. */
    public function url(MediaReceipt $receipt, DateTimeInterface $expiresAt, string $conversion = ''): ?string;
}
