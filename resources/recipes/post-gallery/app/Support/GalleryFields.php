<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Shared application configuration for a page or an existing Resource. */
final class GalleryFields
{
    public static function make(string $state, string $collection): FlexImageUpload
    {
        return FlexImageUpload::make($state)->key($state)
            ->multiple()->maxFiles(12)->maxSize(5120)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->uploadLayout('grid')->reorderable()->appendFiles()
            ->imageEditor($collection === 'main')
            ->disk('public')->visibility('public')
            ->directory('posts/'.Auth::id().'/'.$collection)
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => app(GalleryFileName::class)($file, 'data.'.$state),
            )
            ->storeFileNamesIn($state.'__names')
            ->imageConversions([
                new ImageConversion('thumb', 320, 320, fit: ImageFit::Cover),
                new ImageConversion('large', width: 1600, quality: 85),
            ])
            ->previewConversion('thumb')
            ->fileViewer()->fileViewerConversion('large')
            ->preventFilePathTampering(allowFilePathUsing: fn (?Post $record, string $file): bool => $record?->exists
                    && $record->galleries()->where('collection', $collection)
                        ->where('disk', 'public')->where('path', $file)->exists())
            ->mediaReferenceUsing(fn (Post $record, string $identifier): bool => $record->galleries()->where('collection', $collection)
                ->where('disk', 'public')->where('path', $identifier)->exists())
            ->deleteFileOnRemove(false)->deleteReplacedFiles(false)
            ->pruneOrphanedOnSave(false);
    }
}
