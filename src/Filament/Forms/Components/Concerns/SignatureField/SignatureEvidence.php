<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Closure;

/** @mixin SignatureField */
trait SignatureEvidence
{
    public function legalPack(bool|Closure $condition = true): static
    {
        $this->legalPackEnabled = $condition;

        return $this;
    }

    public function timestampSeal(bool|Closure $condition = true): static
    {
        $this->timestampSealEnabled = $condition;

        return $this;
    }

    public function legalMetadataIn(string|Closure $statePath): static
    {
        $this->legalMetadataIn = $statePath;

        return $this;
    }

    public function requiresLegalInk(): bool
    {
        return (bool) $this->evaluate($this->legalPackEnabled);
    }

    public function shouldTimestampSeal(): bool
    {
        return (bool) $this->evaluate($this->timestampSealEnabled);
    }

    public function getLegalMetadataInPath(): ?string
    {
        $path = $this->evaluate($this->legalMetadataIn);

        return filled($path) ? (string) $path : null;
    }

    public function inkTrail(bool|Closure $condition = true): static
    {
        $this->inkTrailEnabled = $condition;

        return $this;
    }

    public function pdfPreview(bool|Closure $condition = true): static
    {
        $this->pdfPreviewEnabled = $condition;

        return $this;
    }

    public function isInkTrailEnabled(): bool
    {
        return (bool) $this->evaluate($this->inkTrailEnabled);
    }

    public function isPdfPreviewEnabled(): bool
    {
        return (bool) $this->evaluate($this->pdfPreviewEnabled);
    }
}
