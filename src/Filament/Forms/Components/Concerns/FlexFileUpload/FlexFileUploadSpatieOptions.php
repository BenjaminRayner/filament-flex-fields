<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;
use Closure;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @mixin FlexSpatieMediaLibraryFileUpload */
trait FlexFileUploadSpatieOptions
{
    protected ?Closure $flexSpatieOptions = null;

    /** Full native options, including queue, order, sanitizer and FileAdder configuration. */
    public function spatieOptionsUsing(?Closure $callback): static
    {
        $this->flexSpatieOptions = $callback;

        return $this;
    }

    public function getUploadSpatieOptions(?TemporaryUploadedFile $file = null): SpatieOptions
    {
        $defaults = new SpatieOptions(
            collection: $this->getCollection() ?? 'default', name: $file === null ? null : $this->getMediaName($file),
            conversionsDisk: $this->getConversionsDisk(), customProperties: $file === null ? [] : $this->getCustomProperties($file),
            manipulations: $this->getManipulations(), attributes: $this->getProperties(),
            headers: $this->getCustomHeaders(), responsiveImages: $this->hasResponsiveImages(),
        );
        $options = $this->flexSpatieOptions === null ? $defaults : $this->evaluate($this->flexSpatieOptions, ['options' => $defaults, 'file' => $file]);
        if (! $options instanceof SpatieOptions || $options->collection !== $defaults->collection) {
            throw new InvalidArgumentException('spatieOptionsUsing must return SpatieOptions for the field collection.');
        }

        return $options;
    }
}
