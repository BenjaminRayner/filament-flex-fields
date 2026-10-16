<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField;

use Bjanczak\FilamentFlexFields\Support\AudioWaveformGenerator;
use Closure;
use InvalidArgumentException;

trait ConfiguresWaveform
{
    /** @var list<int>|Closure|null */
    protected array|Closure|null $waveform = null;

    /** @param list<int>|Closure|null $waveform */
    public function waveform(array|Closure|null $waveform): static
    {
        $this->waveform = $waveform;

        return $this;
    }

    public function hasCustomWaveform(): bool
    {
        $value = $this->evaluate($this->waveform);

        return is_array($value) && $value !== [];
    }

    /** @return list<int> */
    public function getWaveform(): array
    {
        $value = $this->evaluate($this->waveform);
        if ($value === null || $value === []) {
            return AudioWaveformGenerator::placeholderWaveform();
        }
        if (! is_array($value) || ! array_is_list($value) || count($value) > 2048) {
            throw new InvalidArgumentException('Audio waveform requires a bounded list of at most 2048 peaks.');
        }
        $result = [];
        foreach ($value as $peak) {
            if (! is_numeric($peak) || ! is_finite((float) $peak)) {
                throw new InvalidArgumentException('Audio waveform peaks must be finite numeric values.');
            }
            $result[] = max(8, min(100, (int) $peak));
        }

        return $result;
    }

    /** @return list<int> */
    public function resolveWaveform(mixed $state): array
    {
        return $this->hasCustomWaveform() ? $this->getWaveform() : AudioWaveformGenerator::fromFingerprint($this->resolveAudioSrc($state) ?? '');
    }
}
