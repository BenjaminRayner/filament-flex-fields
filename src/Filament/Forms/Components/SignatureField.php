<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField\SignatureConfiguration;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField\SignatureEvidence;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField\SignatureIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField\SignaturePersistence;
use Bjanczak\FilamentFlexFields\Support\SignatureSvg;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Support\Htmlable;

class SignatureField extends Field
{
    use CanBeReadOnly;
    use ResolvesConfiguredIcons;
    use SignatureConfiguration;
    use SignatureEvidence;
    use SignatureIcons;
    use SignaturePersistence;

    public const string STORE_SVG = 'svg';

    public const string DOWNLOAD_SVG = 'svg';

    public const string DOWNLOAD_WEBP = 'webp';

    protected string $view = 'filament-flex-fields::forms.components.signature-field';

    protected string|Closure $penColor = '#18181b';

    protected float|Closure $penWidth = 5.6;

    protected string|Closure|null $backgroundColor = '#ffffff';

    protected bool|Closure $fullscreen = true;

    protected bool|Closure $undoable = true;

    protected int|Closure $maxSizeKb = 48;

    protected int|Closure $minStrokes = 1;

    protected int|Closure $viewBoxWidth = SignatureSvg::VIEWBOX_WIDTH;

    protected int|Closure $viewBoxHeight = SignatureSvg::VIEWBOX_HEIGHT;

    protected bool|Closure $smoothing = true;

    protected bool|Closure $trackpadGlide = false;

    protected string|Closure $trackpadGlideKey = 'd';

    protected bool|Closure $guidelines = false;

    protected string|Closure|null $downloadFormat = null;

    protected string|Closure $downloadFilename = 'signature';

    protected float|Closure $webpQuality = 0.9;

    protected string|BackedEnum|Htmlable|Closure|null $undoIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $clearIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $downloadIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $fullscreenIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $pdfPreviewIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $closeIcon = null;

    protected ?Closure $resolveStoredSvgUsing = null;

    protected bool|Closure $storeToDiskEnabled = false;

    protected string|Closure $storageDirectory = 'signatures';

    protected string|Closure|null $storageDisk = null;

    protected bool|Closure $spatieSinkEnabled = false;

    protected string|Closure $spatieSinkCollection = 'signatures';

    protected string|Closure|null $spatieSinkDisk = null;

    protected string|Closure|null $spatieSinkUuidIn = null;

    protected bool|Closure $legalPackEnabled = false;

    protected bool|Closure $timestampSealEnabled = false;

    protected string|Closure|null $legalMetadataIn = null;

    protected bool|Closure $inkTrailEnabled = false;

    protected bool|Closure $pdfPreviewEnabled = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSignaturePersistence();
    }
}
