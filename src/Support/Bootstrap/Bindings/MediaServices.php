<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Bootstrap\Bindings;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadLeaseRepository;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\ImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Images\GdImageEncoder;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionJournal;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantState;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaIngress;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaExtensionState;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageRepository;
use Bjanczak\FilamentFlexFields\Support\Media\WhisperRuntimeState;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentManifestRepository;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorSpatieMediaRepository;
use Illuminate\Contracts\Foundation\Application;

final class MediaServices
{
    public static function register(Application $app): void
    {
        $app->scoped(MediaIngress::class);
        $app->scoped(MediaExtensionState::class);
        $app->scoped(MediaRegistry::class);
        $app->scoped(MediaOs::class);
        $app->scoped(UploadManifestRepository::class);
        $app->scoped(UploadLeaseRepository::class);
        $app->scoped(UploadRecordLifecycle::class);
        if (! $app->bound(ImageEncoder::class)) {
            $app->scoped(ImageEncoder::class, GdImageEncoder::class);
        }
        $app->scoped(ImageMedia::class);
        $app->scoped(ConversionJournal::class);
        $app->scoped(QueuedImageConversions::class);
        $app->scoped(StageOptions::class, static fn (): StageOptions => StageOptions::fromConfig());
        $app->scoped(StageRepository::class);
        $app->scoped(MediaStaging::class);
        $app->scoped(MediaCaptureTenantState::class);
        $app->scoped(WhisperRuntimeState::class);
        $app->scoped(RichEditorAttachmentManifestRepository::class);
        $app->scoped(RichEditorSpatieMediaRepository::class);
    }
}
