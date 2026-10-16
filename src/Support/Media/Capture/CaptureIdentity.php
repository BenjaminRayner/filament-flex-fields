<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Capture;

use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureState;
use Illuminate\Support\Facades\Auth;

/** Identity metadata is evidence supplied by the host, not legal certification. */
final class CaptureIdentity
{
    public function __construct(private readonly MediaCaptureState $state) {}

    public function signer(): ?string
    {
        if ($this->state->legalSignerIdResolver !== null) {
            $identity = ($this->state->legalSignerIdResolver)();
            if (is_string($identity) && $identity !== '') {
                return $identity;
            }
        }
        $identity = Auth::user()?->getAuthIdentifier();

        return is_scalar($identity) ? (string) $identity : null;
    }

    public function documentHash(): ?string
    {
        $resolver = $this->state->documentHashResolver;
        $hash = $resolver === null ? null : $resolver();

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}
