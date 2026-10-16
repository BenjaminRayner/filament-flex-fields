<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;

/** Every URL is access-policy authorized; private signing never falls back to public. */
final class UploadUrls
{
    public function __construct(private readonly MediaOs $media) {}

    public function resolve(MediaReceipt $receipt, MediaWrite $intent): ?string
    {
        $expires = now()->addMinutes((int) config('filament-flex-fields.media_capture.signed_url_minutes', 15));
        $url = $this->media->url($receipt, $intent, $expires);
        if ($url !== null || $receipt->adapter !== 'disk') {
            return $url;
        }
        $signed = FlexMedia::signedUrl($receipt->disk, $receipt->path, ['visibility' => $intent->policy?->visibility,
            'field' => $intent->field]);
        if ($signed !== null) {
            return $signed;
        }

        $disk = Storage::disk($receipt->disk);

        return $intent->policy?->visibility === 'public' && $disk instanceof Cloud ? $disk->url($receipt->path) : null;
    }
}
