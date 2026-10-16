<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexFileUploadSpatieOptions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload\FlexSpatieMediaLibraryEnterpriseHooks;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\InteractsWithFlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Closure;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Spatie\MediaLibrary\HasMedia;

/**
 * Media conversions must be defined on the model via `registerMediaConversions()`.
 *
 * @see https://spatie.be/docs/laravel-medialibrary
 */
class FlexSpatieMediaLibraryFileUpload extends SpatieMediaLibraryFileUpload
{
    use FlexFileUploadSpatieOptions;
    use FlexSpatieMediaLibraryEnterpriseHooks;
    use HasControlSize;
    use HasFieldFocusOutline;
    use InteractsWithFlexFileUpload;

    protected string $view = 'filament-flex-fields::forms.components.flex-file-upload';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerFlexSpatieMediaLibraryEnterpriseHooks();
        $this->registerFileMetadataAction();
        $this->registerFileViewerAction();
    }

    public function withRecommendedDefaults(): static
    {
        $this->applyRecommendedSecurityDefaults();

        return $this->documentsOnly();
    }

    public function responsiveImages(bool|Closure $condition = true): static
    {
        parent::responsiveImages($condition);

        return $this;
    }

    public function conversion(string|Closure|null $conversion): static
    {
        parent::conversion($conversion);

        return $this;
    }

    public function conversionsDisk(string|Closure|null $disk): static
    {
        parent::conversionsDisk($disk);

        return $this;
    }

    public function deleteAbandonedFiles(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof HasMedia) {
            return;
        }
        $keep = array_values(array_filter($this->getRawState() ?? [], is_string(...)));
        $media = $record->getMedia($this->getCollection() ?? 'default');
        if ($this->hasMediaFilter()) {
            $media = $this->filterMedia($media);
        }
        foreach ($media as $item) {
            if (! in_array((string) $item->uuid, $keep, true)) {
                app(SpatieUploadBridge::class)->delete($this, (string) $item->uuid);
            }
        }
    }
}
