<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\VoiceNote;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField\ConfiguresAudioIcons;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use League\Flysystem\UnableToRetrieveMetadata;

trait VoiceNoteConfiguration
{
    use ConfiguresAudioIcons;
    use HasFieldRounding;

    protected int|Closure $maxDuration = 120;

    protected int|Closure $audioBitsPerSecond = 128000;

    protected bool|Closure $uploadImmediately = false;

    protected bool|Closure $confirmDeletion = true;

    protected string|Closure|null $storeWaveformIn = null;

    protected string|BackedEnum|Htmlable|Closure|null $microphoneIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $stopIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $trashIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $checkmarkIcon = null;

    public function maxDuration(int|Closure $seconds): static
    {
        $this->maxDuration = $seconds;

        return $this;
    }

    public function audioBitsPerSecond(int|Closure $bits): static
    {
        $this->audioBitsPerSecond = $bits;

        return $this;
    }

    public function confirmDeletion(bool|Closure $condition = true): static
    {
        $this->confirmDeletion = $condition;

        return $this;
    }

    public function uploadImmediately(bool|Closure $condition = true): static
    {
        $this->uploadImmediately = $condition;

        return $this;
    }

    public function uploadOnSubmit(bool|Closure $condition = true): static
    {
        $this->uploadImmediately = fn (): bool => ! $this->evaluate($condition);

        return $this;
    }

    public function storeWaveformIn(string|Closure $statePath): static
    {
        $this->storeWaveformIn = $statePath;

        return $this;
    }

    public function microphoneIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->microphoneIcon = $icon;

        return $this;
    }

    public function stopIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->stopIcon = $icon;

        return $this;
    }

    public function trashIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->trashIcon = $icon;

        return $this;
    }

    public function checkmarkIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->checkmarkIcon = $icon;

        return $this;
    }

    public function getMicrophoneIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->microphoneIcon, 'microphone_icon', FlexIcon::Microphone);
    }

    public function getStopIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->stopIcon, 'stop_icon', 'hugeicons-stop');
    }

    public function getTrashIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->trashIcon, 'trash_icon', FlexIcon::TrashBin);
    }

    public function getCheckmarkIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->checkmarkIcon, 'checkmark_icon', FlexIcon::Check);
    }

    public function shouldUploadImmediately(): bool
    {
        return (bool) $this->evaluate($this->uploadImmediately);
    }

    public function shouldConfirmDeletion(): bool
    {
        return (bool) $this->evaluate($this->confirmDeletion);
    }

    public function getMaxDuration(): int
    {
        $value = $this->evaluate($this->maxDuration);
        if (! is_int($value) || $value < 1 || $value > 3600) {
            throw new InvalidArgumentException('Voice recording duration must be between 1 and 3600 seconds.');
        }

        return $value;
    }

    public function getAudioBitsPerSecond(): int
    {
        $value = $this->evaluate($this->audioBitsPerSecond);
        if (! is_int($value) || $value < 16000 || $value > 512000) {
            throw new InvalidArgumentException('Voice recording bitrate must be between 16000 and 512000 bits per second.');
        }

        return $value;
    }

    public function getStoreWaveformInPath(): ?string
    {
        $value = $this->evaluate($this->storeWaveformIn);

        return filled($value) ? $value : $this->getStoreMetadataInPath();
    }

    public function getInitialAudioUrl(): ?string
    {
        try {
            $files = $this->getUploadedFiles() ?? [];
        } catch (UnableToRetrieveMetadata) {
            return null;
        }
        foreach ($files as $file) {
            if (is_array($file) && is_string($file['url'] ?? null) && filled($file['url'])) {
                return $file['url'];
            }
        }

        return null;
    }

    /** @return list<float|int>|null */
    public function getInitialAudioWaveform(): ?array
    {
        $path = $this->getStoreWaveformInPath();
        if (blank($path)) {
            return null;
        }
        $metadata = ($this->getGetCallback())($path);
        $waveform = is_array($metadata) ? ($metadata['waveform'] ?? null) : null;
        $values = is_array($waveform) ? ($waveform['peaks'] ?? $waveform) : null;
        if (! is_array($values) || ! array_is_list($values) || count($values) < 1 || count($values) > 128) {
            return null;
        }
        foreach ($values as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0 || $value > 100) {
                return null;
            }
        }

        return $values;
    }
}
