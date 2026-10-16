<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Capture\RetentionPruner;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @deprecated Compatibility names delegate to native upload and retention services. */
final class SpatieMediaCaptureAdapter
{
    public static function saveUploadedFile(FlexSpatieMediaLibraryFileUpload $component, TemporaryUploadedFile $file, ?Model $record = null): ?string
    {
        try {
            // This legacy server API has no native form lifecycle or temporary-upload lease contract.
            // Native fields call the bridge directly and retain transactional staging by default.
            return app(SpatieUploadBridge::class)->save($component, $file, $record, stage: false);
        } catch (MediaFailure) {
            return null;
        }
    }

    /** @return array{name: string, size: int, type: string, url: string|null}|null */
    public static function resolveUploadedFilePayload(FlexSpatieMediaLibraryFileUpload $component, string $file): ?array
    {
        return app(SpatieUploadBridge::class)->payload($component, $file);
    }

    public static function findMediaByUuid(FlexSpatieMediaLibraryFileUpload $component, string $uuid): ?object
    {
        return app(SpatieUploadBridge::class)->find($component, $uuid);
    }

    /** @return array<string, mixed> */
    public static function enterpriseCustomProperties(FlexSpatieMediaLibraryFileUpload $component): array
    {
        return ['flex_capture' => ['field' => $component->getName(), 'collection' => $component->getCollection() ?? 'default']];
    }

    /** @param list<string> $directories
     * @return list<string>
     */
    public static function pruneDiskDirectories(string $diskName, array $directories, int $maxAgeDays, bool $dryRun = false): array
    {
        return app(RetentionPruner::class)->disk($diskName, $directories, $maxAgeDays, $dryRun);
    }

    /** @return list<string> */
    public static function pruneSpatieMedia(int $maxAgeDays, ?string $collection = null, bool $dryRun = false, ?string $kind = null): array
    {
        return app(RetentionPruner::class)->spatie($maxAgeDays, $collection, $dryRun, $kind);
    }

    public static function hasFlexCaptureStamp(object $media): bool
    {
        return app(RetentionPruner::class)->hasStamp($media);
    }
}
