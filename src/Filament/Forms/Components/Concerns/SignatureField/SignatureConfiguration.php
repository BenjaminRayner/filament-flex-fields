<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Closure;
use InvalidArgumentException;

/** @mixin SignatureField */
trait SignatureConfiguration
{
    /** @var list<string>|Closure */
    protected array|Closure $signaturePenColors = ['#18181b', '#2563eb', '#7c3aed'];

    /** @var list<float|int>|Closure */
    protected array|Closure $signaturePenWidths = [3.4, 5.6, 8.4];

    protected string|Closure|null $signatureSigner = null;

    protected bool|Closure $signatureReplay = true;

    /** @param list<string>|Closure $colors */
    public function penColors(array|Closure $colors): static
    {
        $this->signaturePenColors = $colors;

        return $this;
    }

    /** @param list<float|int>|Closure $widths */
    public function penWidths(array|Closure $widths): static
    {
        $this->signaturePenWidths = $widths;

        return $this;
    }

    public function signer(string|Closure|null $name): static
    {
        $this->signatureSigner = $name;

        return $this;
    }

    public function replayable(bool|Closure $condition = true): static
    {
        $this->signatureReplay = $condition;

        return $this;
    }

    /** @return list<string> */
    public function getPenColors(): array
    {
        $colors = $this->evaluate($this->signaturePenColors);
        if (! is_array($colors) || ! array_is_list($colors) || count($colors) > 12) {
            throw new InvalidArgumentException('Signature colors require a list of at most twelve hex colors.');
        }
        foreach ($colors as $color) {
            if (! is_string($color) || preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/iD', $color) !== 1) {
                throw new InvalidArgumentException('Signature colors must be hex colors.');
            }
        }

        return array_values(array_unique([$this->getPenColor(), ...array_map('strtolower', $colors)]));
    }

    /** @return list<float> */
    public function getPenWidths(): array
    {
        $widths = $this->evaluate($this->signaturePenWidths);
        if (! is_array($widths) || ! array_is_list($widths) || count($widths) > 8) {
            throw new InvalidArgumentException('Signature widths require a list of at most eight widths.');
        }
        foreach ($widths as $width) {
            if (! is_numeric($width) || ! is_finite((float) $width) || $width < .5 || $width > 12) {
                throw new InvalidArgumentException('Signature widths must be between 0.5 and 12.');
            }
        }
        $widths = array_values(array_unique([$this->getPenWidth(), ...array_map(floatval(...), $widths)]));
        sort($widths);

        return $widths;
    }

    public function getSigner(): ?string
    {
        $signer = $this->evaluate($this->signatureSigner);

        return is_string($signer) ? mb_substr($signer, 0, 200) : null;
    }

    public function isReplayEnabled(): bool
    {
        return (bool) $this->evaluate($this->signatureReplay);
    }

    public function penColor(string|Closure $color): static
    {
        $this->penColor = $color;

        return $this;
    }

    public function penWidth(float|Closure $width): static
    {
        $this->penWidth = $width;

        return $this;
    }

    public function backgroundColor(string|Closure|null $color): static
    {
        $this->backgroundColor = $color;

        return $this;
    }

    public function fullscreen(bool|Closure $condition = true): static
    {
        $this->fullscreen = $condition;

        return $this;
    }

    public function undoable(bool|Closure $condition = true): static
    {
        $this->undoable = $condition;

        return $this;
    }

    public function maxSizeKb(int|Closure $kilobytes): static
    {
        $this->maxSizeKb = $kilobytes;

        return $this;
    }

    public function minStrokes(int|Closure $strokes): static
    {
        $this->minStrokes = $strokes;

        return $this;
    }

    public function viewBox(int|Closure $width, int|Closure $height): static
    {
        $this->viewBoxWidth = $width;
        $this->viewBoxHeight = $height;

        return $this;
    }

    public function smoothing(bool|Closure $condition = true): static
    {
        $this->smoothing = $condition;

        return $this;
    }

    public function trackpadGlide(bool|Closure $condition = true): static
    {
        $this->trackpadGlide = $condition;

        return $this;
    }

    public function trackpadGlideKey(string|Closure $key): static
    {
        $this->trackpadGlideKey = $key;

        return $this;
    }

    public function guidelines(bool|Closure $condition = true): static
    {
        $this->guidelines = $condition;

        return $this;
    }

    /**
     * @param  self::DOWNLOAD_SVG|self::DOWNLOAD_WEBP|Closure|null  $format
     */
    public function downloadable(string|Closure|null $format = self::DOWNLOAD_SVG): static
    {
        if ($format !== null && ! in_array($format, [self::DOWNLOAD_SVG, self::DOWNLOAD_WEBP], true)) {
            throw new InvalidArgumentException("Signature download format [{$format}] is not supported.");
        }

        $this->downloadFormat = $format;

        return $this;
    }

    public function downloadFilename(string|Closure $filename): static
    {
        $this->downloadFilename = $filename;

        return $this;
    }

    public function webpQuality(float|Closure $quality): static
    {
        $this->webpQuality = $quality;

        return $this;
    }

    public function getPenColor(): string
    {
        $color = (string) $this->evaluate($this->penColor);

        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            throw new InvalidArgumentException("Signature pen color [{$color}] must be a hex color.");
        }

        return strtolower($color);
    }

    public function getPenWidth(): float
    {
        return max(0.5, min(12, (float) $this->evaluate($this->penWidth)));
    }

    public function getBackgroundColor(): ?string
    {
        $color = $this->evaluate($this->backgroundColor);

        if ($color === null || $color === 'transparent') {
            return null;
        }

        $color = (string) $color;

        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            throw new InvalidArgumentException("Signature background color [{$color}] must be a hex color or null.");
        }

        return strtolower($color);
    }

    public function isFullscreenEnabled(): bool
    {
        return (bool) $this->evaluate($this->fullscreen);
    }

    public function isUndoable(): bool
    {
        return (bool) $this->evaluate($this->undoable);
    }

    public function getMaxSizeKb(): int
    {
        return max(1, (int) $this->evaluate($this->maxSizeKb));
    }

    public function getMinStrokes(): int
    {
        return max(1, (int) $this->evaluate($this->minStrokes));
    }

    public function getViewBoxWidth(): int
    {
        return max(100, (int) $this->evaluate($this->viewBoxWidth));
    }

    public function getViewBoxHeight(): int
    {
        return max(80, (int) $this->evaluate($this->viewBoxHeight));
    }

    public function isSmoothingEnabled(): bool
    {
        return (bool) $this->evaluate($this->smoothing);
    }

    public function isTrackpadGlideEnabled(): bool
    {
        return (bool) $this->evaluate($this->trackpadGlide);
    }

    public function getTrackpadGlideKey(): string
    {
        $key = strtolower(trim((string) $this->evaluate($this->trackpadGlideKey)));

        if (! preg_match('/^[a-z]$/', $key)) {
            throw new InvalidArgumentException("Signature trackpad glide key [{$key}] must be a single letter from a to z.");
        }

        return $key;
    }

    public function isGuidelinesEnabled(): bool
    {
        return (bool) $this->evaluate($this->guidelines);
    }

    public function getDownloadFormat(): ?string
    {
        $format = $this->evaluate($this->downloadFormat);

        return in_array($format, [self::DOWNLOAD_SVG, self::DOWNLOAD_WEBP], true) ? $format : null;
    }

    public function getDownloadFilename(): string
    {
        $filename = trim((string) $this->evaluate($this->downloadFilename));

        return $filename !== '' ? $filename : 'signature';
    }

    public function getWebpQuality(): float
    {
        return max(0.1, min(1, (float) $this->evaluate($this->webpQuality)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlpineConfiguration(bool $isRequired = false): array
    {
        return [
            'minStrokes' => $this->getMinStrokes(),
            'maxSizeKb' => $this->getMaxSizeKb(),
            'required' => $isRequired,
            'validationMessages' => [
                'required' => __('filament-flex-fields::default.signature.ui.required'),
                'too_few_strokes' => __('filament-flex-fields::default.signature.ui.too_few_strokes', [
                    'min' => $this->getMinStrokes(),
                ]),
                'too_large' => __('filament-flex-fields::default.signature.ui.too_large', [
                    'max' => $this->getMaxSizeKb(),
                ]),
            ],
            'inkTrailEnabled' => $this->isInkTrailEnabled(),
            'pdfPreviewEnabled' => $this->isPdfPreviewEnabled(),
            'legalAcknowledgment' => $this->requiresLegalInk()
                ? __('filament-flex-fields::default.signature.legal_acknowledgment')
                : null,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-signature-field' => true,
        ];
    }
}
