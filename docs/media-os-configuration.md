---
title: "Media OS configuration"
description: "Configure disks, validation, security, temporary storage, conversion workers and cleanup."
icon: "settings-2"
---

Media OS reads `media_capture` in `config/filament-flex-fields.php`. The examples below are entries inside that existing array; merge them into your published configuration rather than replacing unrelated settings.

After configuring your workflow, run `php artisan fff:media:doctor` for storage
and recovery settings, or add `--queued` for native background image conversions.
The [diagnostics guide](/docs/media-os-diagnostics) explains the profiles and
distinguishes configuration checks from worker and storage verification.

## Default storage

```php
'disk' => 'local',
'visibility' => 'private',
'max_bytes' => 20 * 1024 * 1024,
'min_bytes' => 0,
'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
'verify_integrity' => true,
```

Global limits use **bytes**. FLEX `maxSize()`, `minSize()` and `maxTotalSizeKb()` use **KiB**. A per-operation `MediaPolicy` overrides the engine's global policy; an upload field still enforces its own restrictions independently.

## Public image storage

```php
'disk' => 'public',
'visibility' => 'public',
'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
```

For Laravel's local public disk, create the normal public storage link:

```bash
php artisan storage:link
```

## S3 and other Laravel disks

Configure the disk in Laravel first. Then select it by name:

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;

$intent = new MediaWrite(disk: 's3', directory: 'customer-assets');
```

Credentials, bucket permissions, temporary URLs and browser CORS are configured on the Laravel disk/storage provider. A disk name alone does not provision those services. Source and conversion files can use separate disks.

## Tenant storage

```php
'tenant' => [
    'disk' => 'tenant-media',
    'directory_prefix' => 'organizations',
    'auto_disk' => true,
],
```

Tenant defaults are opt-in. Explicit operation/field disks take precedence; Spatie collection disks also participate in its disk selection. Use a trusted tenant resolver and record authorization in your application. A directory prefix alone does not enforce tenant access.

For field-level tenant directories:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;

FlexFileUpload::make('attachments')->scopedDirectory('uploads');
```

`scopedDirectory()` resolves the directory using the field's storage context. For a custom business partition, use `directory(fn ($record) => ...)` with trusted record values.

### Resolve storage for each tenant

For application-specific tenant selection, register trusted server callbacks:

```php
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureTenantDiskResolver;

MediaCaptureTenantDiskResolver::registerDiskResolver(
    fn (array $context): ?string => app(CurrentTenantMedia::class)->disk(),
);
MediaCaptureTenantDiskResolver::registerDirectoryPrefixResolver(
    fn (array $context): ?string => app(CurrentTenantMedia::class)->directoryPrefix(),
);
```

`CurrentTenantMedia` is your application service. Return configured disk names and safe relative prefixes. These compatibility hooks feed the same destination service; workers need the same tenant bootstrap. Explicit disks still take precedence.

## Antivirus requirements

```php
'require_virus_scan' => true,
'scan_before_persist' => true,
'scan_after_persist' => true,
'quarantine_disk' => 'quarantine',
```

Register your scanner before requiring scans. Required scanning rejects files when no scanner is configured or the scanner cannot certify them. Both boundaries must remain enabled when `require_virus_scan` is true. [Scanner example](/docs/media-os-extensions#antivirus-scanner).

Quarantine is a private disk for rejected persisted content. Files rejected before a write do not have a stored object to quarantine. Quarantine contents need their own access and retention policy.

## URL settings

```php
'auto_signed_urls' => true,
'signed_url_minutes' => 15,
```

Capture/field URL helpers use these settings where applicable. An explicit `os()->url(..., $expiresAt)` uses the supplied expiry. Signing helper expiry is bounded to 1–10,080 minutes.

### Custom signed URLs

Register a resolver in your service provider when your private delivery service needs custom signing. This callback returns a URL or `null`:

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;

FlexMedia::registerSignedUploadUrlResolver(
    fn (string $disk, string $path, array $context): ?string =>
        app(PrivateMediaDelivery::class)->temporaryUrl($disk, $path, $context),
);
```

`PrivateMediaDelivery` is your application's service. The callback is a compatibility configuration hook; new custom adapters can implement their own `url()` method. Return no URL on signing failure rather than exposing private files publicly.

Use the configured helper for a relative stored path:

```php
$url = FlexMedia::signedUrl('s3', 'contracts/signed.pdf', ['purpose' => 'download']);
```

Authorize access in your application before requesting that link.

## Temporary staging

```php
'staging' => [
    'disk' => 'local',
    'directory' => 'media-os-staging',
    'ttl_seconds' => 86400,
    'receipt_ttl_seconds' => 604800,
    'lock_seconds' => 300,
    'lock_wait_seconds' => 10,
    'prune_batch_size' => 1000,
    'schedule_enabled' => true,
],
```

Pending files expire after the configured TTL. Successful promotion receipts are retained separately for retries. Use private shared storage and a cache with atomic locks when web requests and workers run on different hosts. Set the lock duration above your longest permitted storage operation.

You can construct validated settings with `StageOptions`; the staging service uses the configuration-derived settings by default:

```php
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;

$options = new StageOptions(
    disk: 'private-shared', ttlSeconds: 3600,
    receiptTtlSeconds: 604800, lockSeconds: 300, pruneBatchSize: 500,
);
```

Use these values in `media_capture.staging` for the standard service. See [temporary upload usage](/docs/media-os-lifecycle).

## Upload receipts and recovery

```php
'uploads' => [
    'staging' => true,
    'auto_recovery' => true,
    'manifest_disk' => 'private-shared',
    'manifest_directory' => 'media-os-upload-receipts',
    'lease_directory' => 'media-os-upload-leases',
],
```

Staging and automatic retry recovery are separate switches. Fields can override either switch. Recovery resumes recognized storage operations; it does not restore an entire unsaved form after the browser closes. Keep private receipts, media backups and the application encryption key together.

## Native image limits

```php
'images' => [
    'source_pixels' => 16_000_000,
    'output_pixels' => 16_000_000,
    'variants' => 16,
    'working_bytes' => 256 * 1024 * 1024,
    'queue' => [
        'journal_disk' => 'private-shared',
        'journal_directory' => 'media-os-conversions',
        'prune_schedule_enabled' => true,
    ],
],
```

Byte limits and pixel limits are independent. Image encoding must also fit PHP's available memory. Use [ImageLimits](/docs/media-os-images#resource-limits) for a per-operation override.

## Conversion worker

```php
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;

$options = new ConversionQueueOptions(
    connection: 'database', queue: 'media-conversions', tries: 3, timeout: 60,
);
```

```bash
php artisan queue:work database --queue=media-conversions
```

Use a durable queue connection. Native background conversions reject `sync`, `null`, `deferred` and `background` connections. Configure queue `retry_after` or the provider visibility timeout above the job timeout. Web and workers need the same source/conversion disks, private journal disk, encryption key and lock store. Initialize your tenant access context in workers when required by your policies.

Spatie conversions use `config/media-library.php` and native Spatie workers instead. [Spatie queue configuration](/docs/media-os-spatie#queued-conversions).

## Scheduled cleanup

```bash
php artisan flex-fields:prune-media-staging --dry-run
php artisan flex-fields:prune-media-staging
php artisan flex-fields:prune-media-conversions --days=7 --limit=1000
```

The normal Laravel scheduler must run for the hourly staging cleanup and daily cancelled-conversion-journal cleanup. Cancelled journals are removed only after their owned files are absent. Active, failed and awaiting-save conversion plans are retained for recovery.

## Retention

```php
'retention' => [
    'schedule_enabled' => true,
    'schedule' => 'daily',
    'uploads' => ['enabled' => false, 'days' => null],
    'voice_notes' => ['enabled' => true, 'days' => 365],
    'signatures' => ['enabled' => false, 'days' => null],
    'temp_captures' => ['enabled' => true, 'days' => 7],
],
'directories' => [
    'uploads' => ['uploads'],
    'voice_notes' => ['voice-notes'],
    'signatures' => ['signatures'],
    'temp_captures' => ['livewire-tmp'],
],
```

Retention is deliberate age-based deletion, separate from abandoned staging cleanup. Use dedicated directories; age-based disk retention does not check whether a model still references a path. [Retention usage](/docs/media-os-capture#retention).

## Environment variables

| Variable | Configuration |
| --- | --- |
| `FLEX_FIELDS_MEDIA_DISK` | Default media disk |
| `FLEX_FIELDS_MEDIA_STAGING_DISK` | Staging disk |
| `FLEX_FIELDS_UPLOAD_MANIFEST_DISK` | Upload receipt disk |
| `FLEX_FIELDS_CONVERSION_JOURNAL_DISK` | Background conversion journal disk |
| `FLEX_FIELDS_REQUIRE_VIRUS_SCAN` | Require scanners |
| `FLEX_FIELDS_SCAN_BEFORE_PERSIST`, `FLEX_FIELDS_SCAN_AFTER_PERSIST` | Scan boundaries |
| `FLEX_FIELDS_QUARANTINE_DISK` | Quarantine disk |
| `FLEX_FIELDS_MEDIA_AUTO_SIGNED_URLS`, `FLEX_FIELDS_MEDIA_SIGNED_URL_MINUTES` | URL helper defaults |
| `FLEX_FIELDS_MEDIA_TENANT_DISK`, `FLEX_FIELDS_MEDIA_TENANT_DIRECTORY_PREFIX`, `FLEX_FIELDS_MEDIA_TENANT_AUTO_DISK` | Tenant storage |
| `FLEX_FIELDS_RETENTION_SCHEDULE`, `FLEX_FIELDS_RETENTION_SCHEDULE_EXPRESSION` | Retention schedule |
| `FLEX_FIELDS_RETENTION_UPLOADS`, `FLEX_FIELDS_RETENTION_UPLOADS_DAYS` | Upload retention |
| `FLEX_FIELDS_RETENTION_VOICE_NOTES`, `FLEX_FIELDS_RETENTION_VOICE_NOTES_DAYS` | Voice retention |
| `FLEX_FIELDS_RETENTION_SIGNATURES`, `FLEX_FIELDS_RETENTION_SIGNATURES_DAYS` | Signature retention |
| `FLEX_FIELDS_RETENTION_TEMP_CAPTURES`, `FLEX_FIELDS_RETENTION_TEMP_CAPTURES_DAYS` | Temporary capture retention |

Transcription environment options are described in [capture services](/docs/media-os-capture). Configuration caching follows Laravel's normal deployment workflow.
