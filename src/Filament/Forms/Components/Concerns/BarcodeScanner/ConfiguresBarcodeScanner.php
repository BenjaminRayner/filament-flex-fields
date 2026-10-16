<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BarcodeScanner;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresBarcodeScanner
{
    protected string|Closure $variant = 'primary';

    /**
     * @var list<string|BarcodeFormat>|Closure|null
     */
    protected array|Closure|null $supportedFormats = null;

    protected bool|Closure $continuous = false;

    protected bool|Closure $confirmScan = false;

    protected bool|Closure $beepOnScan = true;

    protected bool|Closure $autoSubmit = false;

    /** @var 'environment'|'user'|Closure */
    protected string|Closure $cameraFacing = 'environment';

    protected int|Closure $scanDelay = 750;

    protected int|Closure $scanInterval = 120;

    protected bool $scanIntervalFromFps = false;

    protected bool|Closure $allowCameraSwitch = true;

    protected string|Closure|null $preferredDeviceId = null;

    protected bool|Closure $storeDetectedFormat = false;

    protected bool|Closure $pauseWhenHidden = true;

    protected bool|Closure $allowManualInput = true;

    protected string|Closure $scanButtonLabel = '';

    protected string|Closure $modalHeading = '';

    protected bool|Closure $validateChecksum = false;

    protected string|BackedEnum|Htmlable|Closure|null $scanIcon = null;

    protected Closure|string|null $barcodeRule = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        if (! in_array($variant, ['primary', 'secondary', 'soft', 'flat', 'ghost'], true)) {
            throw new InvalidArgumentException("Invalid BarcodeScannerField variant [{$variant}].");
        }

        return $variant;
    }

    /**
     * @param  list<string|BarcodeFormat>|Closure|null  $formats
     */
    public function formats(array|Closure|null $formats): static
    {
        $this->supportedFormats = $formats;

        return $this;
    }

    /**
     * @param  list<string|BarcodeFormat>|Closure|null  $formats
     */
    public function supportedFormats(array|Closure|null $formats): static
    {
        return $this->formats($formats);
    }

    /**
     * @return list<BarcodeFormat>
     */
    public function getSupportedFormats(): array
    {
        $formats = $this->evaluate($this->supportedFormats);

        if ($formats === null) {
            return BarcodeFormat::cases();
        }

        if (! is_array($formats) || ! array_is_list($formats) || $formats === []) {
            throw new InvalidArgumentException('Barcode formats must be a non-empty list of supported symbologies.');
        }

        $normalized = BarcodeFormat::normalizeList($formats);

        if ($normalized === [] || count(array_filter($formats, fn (mixed $format): bool => ! ($format instanceof BarcodeFormat) && (! is_string($format) || BarcodeFormat::tryFrom($format) === null))) > 0) {
            throw new InvalidArgumentException('Barcode formats must be a non-empty list of supported symbologies.');
        }

        return $normalized;
    }

    public function continuous(bool|Closure $condition = true): static
    {
        $this->continuous = $condition;

        return $this;
    }

    public function isContinuous(): bool
    {
        return (bool) $this->evaluate($this->continuous);
    }

    public function confirmScan(bool|Closure $condition = true): static
    {
        $this->confirmScan = $condition;

        return $this;
    }

    public function shouldConfirmScan(): bool
    {
        return (bool) $this->evaluate($this->confirmScan);
    }

    public function beepOnScan(bool|Closure $condition = true): static
    {
        $this->beepOnScan = $condition;

        return $this;
    }

    public function shouldBeepOnScan(): bool
    {
        return (bool) $this->evaluate($this->beepOnScan);
    }

    public function autoSubmit(bool|Closure $condition = true): static
    {
        $this->autoSubmit = $condition;

        return $this;
    }

    public function shouldAutoSubmit(): bool
    {
        return (bool) $this->evaluate($this->autoSubmit);
    }

    /**
     * @param  'environment'|'user'|Closure  $facing
     */
    public function cameraFacing(string|Closure $facing): static
    {
        $this->cameraFacing = $facing;

        return $this;
    }

    /**
     * @return 'environment'|'user'
     */
    public function getCameraFacing(): string
    {
        $facing = $this->evaluate($this->cameraFacing);

        if (! in_array($facing, ['environment', 'user'], true)) {
            throw new InvalidArgumentException("Invalid BarcodeScannerField camera facing [{$facing}].");
        }

        return $facing;
    }

    public function scanDelay(int|Closure $milliseconds): static
    {
        $this->scanDelay = $milliseconds;

        return $this;
    }

    public function getScanDelay(): int
    {
        return max(0, min(60000, (int) $this->evaluate($this->scanDelay)));
    }

    public function scanInterval(int|Closure $milliseconds): static
    {
        $this->scanInterval = $milliseconds;
        $this->scanIntervalFromFps = false;

        return $this;
    }

    public function decodeFps(int|Closure $fps): static
    {
        $this->scanInterval = $fps;
        $this->scanIntervalFromFps = true;

        return $this;
    }

    public function getScanInterval(): int
    {
        if ($this->scanIntervalFromFps) {
            $fps = max(1, (int) $this->evaluate($this->scanInterval));

            return max(50, min(2000, (int) round(1000 / $fps)));
        }

        return max(50, min(2000, (int) $this->evaluate($this->scanInterval)));
    }

    public function allowCameraSwitch(bool|Closure $condition = true): static
    {
        $this->allowCameraSwitch = $condition;

        return $this;
    }

    public function allowsCameraSwitch(): bool
    {
        return (bool) $this->evaluate($this->allowCameraSwitch);
    }

    public function preferredDeviceId(string|Closure|null $deviceId): static
    {
        $this->preferredDeviceId = $deviceId;

        return $this;
    }

    public function getPreferredDeviceId(): ?string
    {
        $deviceId = $this->evaluate($this->preferredDeviceId);

        if (! is_string($deviceId)) {
            return null;
        }

        $trimmed = trim($deviceId);

        return $trimmed === '' ? null : $trimmed;
    }

    public function storeDetectedFormat(bool|Closure $condition = true): static
    {
        $this->storeDetectedFormat = $condition;

        return $this;
    }

    public function shouldStoreDetectedFormat(): bool
    {
        return (bool) $this->evaluate($this->storeDetectedFormat);
    }

    public function pauseWhenHidden(bool|Closure $condition = true): static
    {
        $this->pauseWhenHidden = $condition;

        return $this;
    }

    public function shouldPauseWhenHidden(): bool
    {
        return (bool) $this->evaluate($this->pauseWhenHidden);
    }

    public function allowManualInput(bool|Closure $condition = true): static
    {
        $this->allowManualInput = $condition;

        return $this;
    }

    public function allowsManualInput(): bool
    {
        return (bool) $this->evaluate($this->allowManualInput);
    }

    public function scanButtonLabel(string|Closure $label): static
    {
        $this->scanButtonLabel = $label;

        return $this;
    }

    public function getScanButtonLabel(): string
    {
        return $this->evaluate($this->scanButtonLabel);
    }

    public function modalHeading(string|Closure $heading): static
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function getModalHeading(): string
    {
        return $this->evaluate($this->modalHeading);
    }

    public function validateChecksum(bool|Closure $condition = true): static
    {
        $this->validateChecksum = $condition;

        return $this;
    }

    public function shouldValidateChecksum(): bool
    {
        return (bool) $this->evaluate($this->validateChecksum);
    }

    public function scanIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->scanIcon = $icon;

        return $this;
    }

    public function getScanIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->scanIcon) ?? FlexIcon::make('qr-code');
    }

    public function barcodeRule(string|Closure $rule): static
    {
        $this->barcodeRule = $rule;

        return $this;
    }

    public function getBarcodeRule(): Closure|string|null
    {
        return $this->barcodeRule;
    }
}
