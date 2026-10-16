<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Media;

use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Throwable;

trait UsesPrivateMediaUrls
{
    public function getImageUrl(?string $state = null): ?string
    {
        if ($this->getVisibility() !== 'private') {
            return parent::getImageUrl($state);
        }
        if ($state === null || $state === '') {
            return null;
        }
        try {
            MediaAddress::path($state);

            return FlexMedia::signedUrl($this->getDiskName(), $state, ['visibility' => 'private']);
        } catch (Throwable) {
            return null;
        }
    }
}
