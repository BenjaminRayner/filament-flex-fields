<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\BarcodeCaptured;
use Bjanczak\FilamentFlexFields\Support\Media\Capture\CaptureIdentity;
use Bjanczak\FilamentFlexFields\Support\Media\Capture\RetentionSettings;
use Bjanczak\FilamentFlexFields\Support\Media\Capture\TranscriptionResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Compatibility\MediaTranslation;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaKind;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaPayload;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaStorageDriver;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Closure;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * @deprecated Compatibility registration facade. MediaOs owns all new media transactions.
 *
 * @phpstan-import-type Policies from RetentionSettings
 */
final class MediaCaptureOs implements MediaStorageContract
{
    /** @param (callable(string): bool|null)|null $callback */
    public static function registerVirusScanCallback(?callable $callback): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($callback): void {
            $state->virusScanCallback = $callback === null ? null : Closure::fromCallable($callback);
        });
    }

    /** @return (Closure(string): (bool|null))|null */
    public static function virusScanCallback(): ?Closure
    {
        return self::state()->virusScanCallback;
    }

    /** @param (callable(string, string, array<string, mixed>): string|null)|null $resolver */
    public static function registerSignedUploadUrlResolver(?callable $resolver): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($resolver): void {
            $state->signedUploadUrlResolver = $resolver === null ? null : Closure::fromCallable($resolver);
            $state->signedUrlConfigured = true;
        });
    }

    /** @return (Closure(string, string, array<string, mixed>): (string|null))|null */
    public static function signedUploadUrlResolver(): ?Closure
    {
        self::configureSignedUrls(self::configuration());

        return self::state()->signedUploadUrlResolver;
    }

    /** @param (callable(string): string|null)|null $callback */
    public static function registerTokenizeCreditCardCallback(?callable $callback): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($callback): void {
            $state->tokenizeCreditCardCallback = $callback === null ? null : Closure::fromCallable($callback);
        });
    }

    /** @return (Closure(string): (string|null))|null */
    public static function tokenizeCreditCardCallback(): ?Closure
    {
        return self::state()->tokenizeCreditCardCallback;
    }

    public static function registerTranscriptionInterface(?VoiceNoteTranscriptionInterface $interface): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($interface): void {
            $state->transcriptionInterface = $interface;
            $state->transcriptionFactory = null;
            $state->transcriptionConfigured = true;
        });
    }

    /** @param (Closure(): VoiceNoteTranscriptionInterface)|null $factory */
    public static function registerTranscriptionFactory(?Closure $factory): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($factory): void {
            $state->transcriptionInterface = null;
            $state->transcriptionFactory = $factory;
            $state->transcriptionConfigured = false;
        });
    }

    public static function transcriptionInterface(): ?VoiceNoteTranscriptionInterface
    {
        return app(TranscriptionResolver::class)->resolve();
    }

    /** @deprecated Permissive policy does not install a fake certifying scanner. */
    public static function bootSafeDefaults(): void {}

    public static function shouldRequireVirusScan(): bool
    {
        return (bool) config('filament-flex-fields.media_capture.require_virus_scan', false);
    }

    public static function shouldScanBeforePersist(): bool
    {
        return (bool) config('filament-flex-fields.media_capture.scan_before_persist', true);
    }

    public static function hasRegisteredVirusScanner(): bool
    {
        return self::virusScanCallback() !== null;
    }

    /** @param (callable(): string|null)|null $resolver */
    public static function registerLegalSignerIdResolver(?callable $resolver): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($resolver): void {
            $state->legalSignerIdResolver = $resolver === null ? null : Closure::fromCallable($resolver);
        });
    }

    /** @param (callable(): string|null)|null $resolver */
    public static function registerDocumentHashResolver(?callable $resolver): void
    {
        RequestState::configure(MediaCaptureState::class, static function (MediaCaptureState $state) use ($resolver): void {
            $state->documentHashResolver = $resolver === null ? null : Closure::fromCallable($resolver);
        });
    }

    public static function resolveLegalSignerId(): ?string
    {
        return app(CaptureIdentity::class)->signer();
    }

    public static function resolveDocumentHash(): ?string
    {
        return app(CaptureIdentity::class)->documentHash();
    }

    public static function passesVirusScanForTemporaryFile(TemporaryUploadedFile $file): bool
    {
        $context = new MediaContext(kind: MediaKind::Upload, driver: MediaStorageDriver::Disk);
        try {
            $prepared = app(MediaOs::class)->inspect(MediaTranslation::source(MediaPayload::fromTemporaryUploadedFile($file)), MediaTranslation::intent($context));
            $prepared->close();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function rejectStoredFile(string $diskName, string $storedPath): ?string
    {
        return MediaCaptureQuarantine::quarantineFromDisk($diskName, $storedPath);
    }

    /** @param array<string, mixed>|null $config */
    public static function bootFromConfig(?array $config = null): void
    {
        $config ??= self::configuration();
        app(RetentionSettings::class)->resolve($config);
        self::configureSignedUrls($config);
        app(TranscriptionResolver::class)->resolve($config);
    }

    /** @param array<string, mixed> $context */
    public static function recordBarcodeCapture(BarcodeValue $barcode, string $field, array $context = []): void
    {
        app(ActivityDispatcher::class)->dispatch(new BarcodeCaptured(
            $field, $barcode->value, $barcode->format, $context,
        ));
    }

    /** @return Policies */
    public static function retentionPolicies(): array
    {
        return app(RetentionSettings::class)->resolve();
    }

    /** @return Policies */
    public static function retentionPolicyDefaults(): array
    {
        return RetentionSettings::defaults();
    }

    public static function passesVirusScan(string $path): bool
    {
        $scanner = self::virusScanCallback();
        if ($scanner === null) {
            return ! self::shouldRequireVirusScan();
        }
        try {
            return $scanner($path) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function shouldRequireCreditCardTokenization(): bool
    {
        return (bool) config('filament-flex-fields.media_capture.pci.require_tokenization', false);
    }

    public static function shouldNeverStorePan(): bool
    {
        return (bool) config('filament-flex-fields.media_capture.pci.never_store_pan', true);
    }

    public static function tokenizePrimaryAccountNumber(string $pan): ?string
    {
        $callback = self::tokenizeCreditCardCallback();
        $token = $pan === '' || $callback === null ? null : $callback($pan);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function resetRuntimeState(): void
    {
        $state = self::state();
        $state->virusScanCallback = null;
        $state->signedUploadUrlResolver = null;
        $state->tokenizeCreditCardCallback = null;
        $state->transcriptionInterface = null;
        $state->transcriptionFactory = null;
        $state->retentionPolicies = null;
        $state->legalSignerIdResolver = null;
        $state->documentHashResolver = null;
        $state->signedUrlConfigured = false;
        $state->transcriptionConfigured = false;
    }

    private static function state(): MediaCaptureState
    {
        return RequestState::resolve(MediaCaptureState::class);
    }

    /** @return array<string, mixed> */
    private static function configuration(): array
    {
        $configuration = config('filament-flex-fields.media_capture', []);
        if (! is_array($configuration)) {
            throw new InvalidArgumentException('Media capture configuration must be an array.');
        }

        return $configuration;
    }

    /** @param array<string, mixed> $configuration */
    private static function configureSignedUrls(array $configuration): void
    {
        $state = self::state();
        if ($state->signedUrlConfigured) {
            return;
        }
        $state->signedUploadUrlResolver = ($configuration['auto_signed_urls'] ?? true) === true
            ? Closure::fromCallable(new LaravelStorageSignedUrlResolver) : null;
        $state->signedUrlConfigured = true;
    }
}
