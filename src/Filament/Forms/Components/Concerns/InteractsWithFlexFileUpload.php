<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadAvatar;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadDisplay;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadEditorial;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadFileClick;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadImageEditor;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadMedia;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadSecurity;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadStorage;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadTransportEvents;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadViewer;
use Filament\Forms\Components\BaseFileUpload;

/**
 * @mixin BaseFileUpload
 */
trait InteractsWithFlexFileUpload
{
    use FlexFileUploadAvatar;
    use FlexFileUploadDisplay;
    use FlexFileUploadEditorial;
    use FlexFileUploadFileClick;
    use FlexFileUploadImageEditor;
    use FlexFileUploadMedia;
    use FlexFileUploadSecurity;
    use FlexFileUploadStorage;
    use FlexFileUploadTransportEvents;
    use FlexFileUploadViewer;
    use HasFlexFileUploadSources;

    public function withRecommendedDefaults(): static
    {
        return $this->applyRecommendedSecurityDefaults();
    }
}
