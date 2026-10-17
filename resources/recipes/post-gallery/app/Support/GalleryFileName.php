<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class GalleryFileName
{
    public function __invoke(TemporaryUploadedFile $file, string $attribute): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                $attribute => 'Upload a JPEG, PNG or WebP image.',
            ]),
        };

        $stem = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $stem = substr($stem === '' ? 'image' : $stem, 0, 60);

        return 'photo-'.$stem.'-'.Str::uuid().'.'.$extension;
    }
}
