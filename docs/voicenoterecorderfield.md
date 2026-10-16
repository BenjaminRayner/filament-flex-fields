---
title: "Voice Note Recorder"
description: "Record, pause and review voice notes in Filament. Upload through Media OS to a Laravel disk or an optional Spatie Media Library collection."
icon: "microphone"
"canonical": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/voice-note-recorder-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "Voice Note Recorder in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/voice-note-recorder-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/voice-note-recorder-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/voice-note-recorder-field.webp"
"twitter:image:alt": "Voice Note Recorder in the FLEX Fields playground"
---

{/* flex-component-media:start */}
<script type="application/ld+json">
{JSON.stringify({
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://flex-fields.bjanczak.com/#organization",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "logo": "https://flex-fields.bjanczak.com/art/docs-logo-light.svg",
      "sameAs": [
        "https://github.com/janczakb/filament-flex-fields"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://flex-fields.bjanczak.com/#website",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "inLanguage": "en"
    },
    {
      "@type": "WebPage",
      "@id": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield",
      "name": "Voice Note Recorder",
      "description": "Record, pause and review voice notes in Filament. Upload through Media OS to a Laravel disk or an optional Spatie Media Library collection.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield#article",
      "url": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield",
      "headline": "Voice Note Recorder",
      "description": "Record, pause and review voice notes in Filament. Upload through Media OS to a Laravel disk or an optional Spatie Media Library collection.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/voice-note-recorder-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/voice-note-recorder-field-motion.mp4#video",
      "name": "Voice Note Recorder — FLEX Fields demonstration",
      "description": "A recorded demonstration of Voice Note Recorder in the FLEX Fields playground for Filament. Record, pause and review voice notes in Filament. Upload through Media OS to a Laravel disk or an optional Spatie Media Library collection.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/voice-note-recorder-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT19.3S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/voice-note-recorder-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/voicenoterecorderfield#article"
      }
    }
  ]
}).replace(/</g, '\u003c')}
</script>

<video
  autoPlay
  muted
  loop
  playsInline
  controls
  preload="metadata"
  className="w-full aspect-video rounded-xl"
  src="/art/previews/voice-note-recorder-field-motion.mp4"
  poster="/art/previews/voice-note-recorder-field.webp"
  width="1920"
  height="1080"
  aria-label="Voice Note Recorder — FLEX Fields demonstration"
>
  <a href="/art/previews/voice-note-recorder-field-motion.mp4">Watch the Voice Note Recorder demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

Record a voice message, pause when needed and listen back before uploading. The recorder shares its waveform player with [Audio Field](/docs/audiofield) and its storage pipeline with [File Upload](/docs/flexfileupload-and-fleximageupload).

<CardGroup cols={2}>
  <Card title="Laravel storage" icon="hard-drive">Store a relative file path on your configured disk. Spatie is optional.</Card>
  <Card title="Review before saving" icon="headphones">Preview locally, retry failed uploads and confirm deletion in a Filament modal.</Card>
</CardGroup>

## First recording

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;

VoiceNoteRecorderField::make('voice_note')
    ->label('Voice message')
    ->disk('public')
    ->directory('voice-notes')
    ->maxDuration(120)
    ->maxSize(10 * 1024)
    ->required();
```

`maxSize()` is in **kilobytes**. The example allows up to 10 MB. The default is 25 MB and one recording per field.

Your page must use Filament's normal form save lifecycle. On disk, the dehydrated value is a relative path such as `voice-notes/recording.webm`; use a nullable string column. Private disks need a supported temporary URL implementation for playback.

## Record, review, upload, save

<Steps>
  <Step title="Record">Start the microphone. Pause and resume without counting paused time toward the duration limit. Stop to finish; Cancel discards the current capture.</Step>
  <Step title="Review">Listen and scrub the waveform. Finishing a recording stops the microphone before waveform analysis begins.</Step>
  <Step title="Upload">The Upload recording button prepares a native Livewire temporary file. By default, submitting the form also uploads any pending recording before the form continues.</Step>
  <Step title="Save">The normal form save admits and persists the file through Media OS. Uploading alone does not save your database record.</Step>
</Steps>

### Immediate temporary upload

```php
VoiceNoteRecorderField::make('feedback_audio')
    ->disk('s3')
    ->directory('feedback/voice')
    ->visibility('private')
    ->uploadImmediately();
```

After capture and review preparation, the temporary upload starts automatically and displays actual transport progress. Permanent storage still follows the form save. `uploadOnSubmit()` restores deferred upload; `uploadOnSubmit(false)` enables immediate upload. If you call both methods, the last call sets the behavior.

A failed upload keeps the local recording available for retry. Reloading or closing the page discards recordings that have not been saved. The form cannot continue while microphone permission, recording, finalization or a required upload is pending.

## Optional Spatie Media Library

Install and configure `filament/spatie-laravel-media-library-plugin` and its model requirements before choosing this driver. Your record must implement Spatie's `HasMedia` contract and use `InteractsWithMedia`; register the collection on that model.

```php
VoiceNoteRecorderField::spatie('voice_note')
    ->collection('voice-notes')
    ->disk('s3')
    ->visibility('private')
    ->maxDuration(180)
    ->maxSize(10 * 1024)
    ->uploadImmediately();
```

The Spatie adapter uses Media OS admission and native Media Library ownership. Its field state uses media UUIDs; the relationship and media table store the attachment. Do not add a string column solely to store this field's UUID as though it were a disk path. Save through Filament's normal record and relationship lifecycle. On create forms, staging can retain an admitted file until a record exists.

The ordinary `VoiceNoteRecorderField::make()` driver does not require or load the optional Spatie field.

## Metadata and waveform

```php
use Filament\Forms\Components\Hidden;

VoiceNoteRecorderField::make('voice_note')
    ->directory('voice-notes')
    ->storeMetadataIn('voice_note_meta')
    ->storeWaveformIn('voice_note_meta');

Hidden::make('voice_note_meta')->default([]);
```

Add a JSON column for `voice_note_meta` and an `array` cast when saving it to an Eloquent record. The sidecar is separate from the audio path.

```php
protected function casts(): array
{
    return ['voice_note_meta' => 'array'];
}
```

The waveform payload contains `waveform.peaks`, `waveform.sample_count` and `waveform.duration`. Peaks contain at most 128 values. Browser-generated waveform and duration metadata are for display, not authoritative security checks.

Paths are **relative to the owning form container**, including repeater rows. For a form with `statePath('data')`, `storeWaveformIn('voice_note_meta')` resolves to `data.voice_note_meta`. Prefix `/` for an absolute state path, or use `../` to address a parent container. Every target must exist in your form state.

If `storeWaveformIn()` is omitted, waveform metadata uses the `storeMetadataIn()` path. Server transcription, when configured, adds a `transcript` value after persistence. See [Media OS](/docs/media-capture-os) for the transcription provider contract and registration.

## Configuration

Methods below accept closures as well as the displayed values. Configuration closures run on the server.

| Method | Default | Behavior |
| --- | --- | --- |
| `maxDuration(120)` | `120` | Recording limit in seconds; integer `1`–`3600`. |
| `audioBitsPerSecond(128000)` | `128000` | Requested encoder bitrate; integer `16000`–`512000`. Actual encoding depends on the browser. |
| `uploadImmediately()` | `false` | Start a temporary upload after finalization. |
| `uploadOnSubmit()` | `true` | Upload pending recordings before normal form submission. Inverse of immediate upload. |
| `confirmDeletion()` | `true` | Ask for confirmation through a Filament modal. `false` removes this step. |
| `storeWaveformIn('meta')` | metadata path | Destination for waveform metadata. |
| `storeMetadataIn('meta')` | `null` | Destination for persisted metadata and an optional server transcript. |
| `size('sm')` | `md` | `sm`, `md` or `lg`. |
| `rounding('md')` | global setting | `none`, `sm`, `md`, `lg`, `xl`, `full`. The default recorder surface uses a 24 px radius. |
| `disabled()` | `false` | Disable recording, uploading and deletion. |
| `microphoneIcon('hugeicons-mic-01')` | Hugeicons | Start-recording icon. |
| `stopIcon('hugeicons-stop')` | Hugeicons | Configurable stop icon. The review action uses the checkmark icon. |
| `checkmarkIcon(...)` | Hugeicons | Finish and review action icon. |
| `trashIcon(...)` | Hugeicons | Cancel and delete icon. |
| `playIcon(...)` | Hugeicons | Playback and resume icon. |
| `pauseIcon(...)` | Hugeicons | Playback and recording pause icon. |

Icons accept a registered icon name, a backed enum, HTMLable content or a closure. All defaults use Hugeicons.

### Storage and Media OS

Both drivers expose the shared upload API. See [File Upload](/docs/flexfileupload-and-fleximageupload) for the complete inherited reference and [Media OS](/docs/media-capture-os) for policies, processors, staging, events and recovery.

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;

VoiceNoteRecorderField::make('private_note')
    ->disk('s3')
    ->directory('support/voice')
    ->visibility('private')
    ->maxSize(5 * 1024)
    ->mediaPolicy(new MediaPolicy(
        maxBytes: 5 * 1024 * 1024,
        mimeTypes: ['audio/*'],
        visibility: 'private',
    ))
    ->mediaStaging()
    ->mediaAutoRecovery(false);
```

The write kind is `voice_note`, including Spatie writes. Optional retention uses `media_capture.retention.voice_notes`; enable the policy and schedule the documented capture-pruning command if required. Retention is not enabled by adding a recorder.

## Validation and access

- `required()`, `maxSize()` and the shared upload rules run on the server.
- Media OS inspects file bytes before permanent storage. Audio-only WebM track declarations are recognized even when the system MIME detector reports `video/webm`; a file with video tracks is not accepted by the recorder's audio policy.
- `maxDuration()` controls browser capture. For a strict security limit on duration, validate decoded duration with a trusted server processor. Client metadata can be forged.
- MIME and byte admission do not replace application authorization or malware scanning. Configure the shared Media OS policies for those requirements.
- Deletion uses the owning field's native temporary/permanent file API. An unsaved local recording is cleared without deleting another field's data.

## Appearance examples

```php
VoiceNoteRecorderField::make('short_note')
    ->size('sm')
    ->maxDuration(30)
    ->audioBitsPerSecond(64000)
    ->directory('voice-notes');

VoiceNoteRecorderField::make('interview')
    ->size('lg')
    ->maxDuration(600)
    ->maxSize(25 * 1024)
    ->directory('interviews');

VoiceNoteRecorderField::make('quick_note')
    ->confirmDeletion(false)
    ->uploadImmediately()
    ->directory('quick-notes');
```

## Browser support and performance

Microphone capture requires HTTPS or localhost, browser permission, a working microphone and `MediaRecorder`. The browser chooses a supported WebM/Opus, MP4 or Ogg format; filenames follow the actual container. A requested bitrate is a preference, not a guarantee of file size.

The recorder uses an active-only canvas animation; idle fields do not run a continuous animation. Playback, waveform rendering and storage transport are shared modules. Tabs and modals follow the FLEX asset lifecycle. Leaving an active capture scope stops recording; removing the field disposes its streams, timers, listeners and object URLs. Scrolling below the fold does not deactivate recording.

Waveform analysis has time and memory budgets. If decoding is unavailable or exceeds the budget, a display waveform keeps the player usable. That fallback is a visual aid rather than an exact amplitude measurement.

| Problem | Check |
| --- | --- |
| Microphone does not start | HTTPS, permission, available input device and whether another app is using it. |
| Upload stays pending or fails | Livewire temporary-upload rules, request size limits, storage permissions and network response. |
| Audio uploads but record has no saved path | Run the normal Filament form save; temporary upload alone does not persist the record. |
| Stored audio will not play | Storage visibility, temporary URL support and audio format support. |
| Metadata property not found | Declare the sidecar in form state and use a relative path, rather than manually constructing an incomplete Livewire path. |
| Spatie collection is empty | Model media contract, collection, record save and relationship save lifecycle. |

<CardGroup cols={2}>
  <Card title="Audio Field" icon="headphones" href="/docs/audiofield">Playback and optional browser transcription.</Card>
  <Card title="Media OS" icon="folder-open" href="/docs/media-capture-os">Shared storage, admission and optional integrations.</Card>
</CardGroup>
