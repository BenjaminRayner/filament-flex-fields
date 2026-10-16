---
title: "Media OS capture and retention"
description: "Configure voice transcription, capture evidence, retention and the capture compatibility API."
icon: "aperture"
---

Media OS shares storage with capture components, while each component owns its input interface. Use [VoiceNoteRecorderField](/docs/voicenoterecorderfield) for recording and [SignatureField](/docs/signaturefield) for signatures. Ordinary File Upload does not automatically transcribe audio or create signature evidence.

## Voice transcription provider

Implement `VoiceNoteTranscriptionInterface` with your chosen transcription service:

```php
namespace App\Media;

use Bjanczak\FilamentFlexFields\Support\Media\VoiceNoteTranscriptionInterface;

final class VoiceTranscription implements VoiceNoteTranscriptionInterface
{
    public function __construct(private SpeechClient $client) {}

    public function transcribe(string $disk, string $path, array $context = []): ?string
    {
        return $this->client->transcribeStoredFile($disk, $path, $context);
    }
}
```

`SpeechClient` is your application/provider client. Return plain text or `null` when no transcript is produced.

Select it in `media_capture`:

```php
'transcription' => \App\Media\VoiceTranscription::class,
```

Or register an application factory:

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;

FlexMedia::registerTranscriptionFactory(fn () => app(\App\Media\VoiceTranscription::class));
```

The factory controls its provider and any resilience wrapper. Configured class providers can use the built-in circuit breaker:

```php
'transcription_circuit_breaker' => [
    'enabled' => true,
    'failure_threshold' => 3,
    'open_seconds' => 30,
    'cache_scope' => null,
    'probe_seconds' => 60,
],
```

Environment equivalents: `FLEX_FIELDS_MEDIA_TRANSCRIPTION`, `FLEX_FIELDS_TRANSCRIPTION_CIRCUIT_BREAKER`, `FLEX_FIELDS_TRANSCRIPTION_CB_THRESHOLD`, `FLEX_FIELDS_TRANSCRIPTION_CB_OPEN_SECONDS`.

Use the resolver from an application service:

```php
use Bjanczak\FilamentFlexFields\Support\Media\Capture\TranscriptionResolver;

$provider = app(TranscriptionResolver::class)->resolve();
$text = $provider?->transcribe('local', 'voice-notes/meeting.webm', ['language' => 'en']);
```

## Signature evidence

```php
use Bjanczak\FilamentFlexFields\Support\Media\Capture\SignatureEvidence;

$evidence = app(SignatureEvidence::class);
$hasInk = $evidence->hasInk($signatureState);
$sealed = $evidence->seal($signatureState);
$timestamp = $evidence->timestamp();
```

The service captures configured signer/document information and request/timestamp facts. Configure the signer and document through your signature workflow. Evidence is not identity verification or a guarantee of legal validity.

### Configure signer and document context

Register trusted server resolvers in your service provider:

```php
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;

MediaCaptureOs::registerLegalSignerIdResolver(fn (): ?string =>
    auth()->id() === null ? null : (string) auth()->id(),
);
MediaCaptureOs::registerDocumentHashResolver(fn (): ?string =>
    app(CurrentSigningDocument::class)->sha256(),
);
```

`CurrentSigningDocument` is your application service for the document actually being signed. Do not accept an arbitrary client hash as evidence of that document. `clientIp()` and `userAgent()` expose the captured request facts if your application needs them separately.

## Retention

Age-based retention is independently configurable for uploads, voice notes, signatures and temporary captures. Upload/signature retention is off by default. Limit disk retention to dedicated directories that your application is allowed to expire.

Preview the configured retention sweep:

```bash
php artisan flex-fields:prune-capture-media --category=voice_notes --dry-run
```

Run the same configured sweep:

```bash
php artisan flex-fields:prune-capture-media --category=voice_notes
```

For a programmatic, bounded disk sweep:

```php
use Bjanczak\FilamentFlexFields\Support\Media\Capture\RetentionPruner;

$paths = app(RetentionPruner::class)->disk(
    'local', ['voice-notes'], days: 365, dryRun: true, limit: 500,
);
```

Disk retention compares file modification age and does not inspect model references. It differs from staging cleanup, which preserves uncertain permanent writes.

## Spatie retention

```php
$uuids = app(RetentionPruner::class)->spatie(
    days: 365, collection: 'recordings', dryRun: true, kind: 'voice_note', limit: 500,
);
```

This only selects media bearing the package's capture provenance. Configure `media_capture.spatie.prune_collections` for the command:

```php
'spatie' => ['prune_collections' => ['recordings']],
```

```bash
php artisan flex-fields:prune-capture-media --category=spatie --dry-run
```

Set `media_capture.retention.uploads.days` for that command category. [Full retention configuration](/docs/media-os-configuration#retention).

## Capture persistence API

Existing capture integrations can use `FlexMedia::persist()`. It translates typed capture context into the same storage service:

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaKind;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaPayload;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaStorageDriver;

$reference = FlexMedia::persist(
    MediaPayload::fromPath($recordingPath, 'meeting.webm'),
    new MediaContext(
        kind: MediaKind::VoiceNote, driver: MediaStorageDriver::Disk,
        disk: 'local', directory: 'voice-notes', field: 'recording',
    ),
);
```

Payload factories include `fromTemporaryUploadedFile`, `fromPath`, `fromStream` and `fromInlineString`. Context supports disk/directory/filename, record/collection, display name, conversion disk, custom properties, manipulations, storage headers, responsive images and correlation ID. The compatibility API may return `null` for policy rejection; prefer `FlexMedia::store()` with typed `MediaFailure` handling for new storage integrations.

## Related configuration

`media_capture.pci.never_store_pan` and `require_tokenization` belong to [CreditCardField](/docs/creditcardfield), not file conversion or upload metadata. Keep those settings in their payment workflow. [Capture configuration](/docs/media-os-configuration).
