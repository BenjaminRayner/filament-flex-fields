---
title: "Media OS temporary uploads"
description: "Stage files, promote them after model saves, recover interrupted operations and clean up abandoned uploads."
icon: "refresh-cw"
---

Use temporary staging when a file arrives before its final model exists. A lease binds private temporary bytes to a trusted owner, destination, expiry and content checksum. FLEX upload fields use this lifecycle automatically when staging is enabled.

## Stage a file

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;

$owner = 'user:'.$user->getKey().':form:'.$serverGeneratedFormId;
$intent = new MediaWrite(disk: 'local', directory: 'contracts', field: 'contract');
$lease = FlexMedia::staging()->stage(MediaSource::path($sourcePath), $intent, $owner);
```

Derive `$owner` from authenticated server context. A lease ID is not sufficient permission to read, promote or delete a file.

## Promote after validation

```php
$receipt = FlexMedia::staging()->commit($lease->id, $owner, $intent);
$record->contract = $receipt->identifier;
$record->save();
FlexMedia::staging()->confirm($lease->id, $owner);
```

`confirm()` belongs after your durable model reference is accepted. If you save inside a database transaction, confirm after its commit. A repeated successful promotion returns the same receipt instead of intentionally creating another file.

## Create a Spatie attachment

```php
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;

$pending = new MediaWrite(
    adapter: 'spatie', field: 'contract',
    spatie: new SpatieOptions(collection: 'contracts'),
);
$lease = FlexMedia::staging()->stage($source, $pending, $owner);
// Validate and persist your HasMedia record.
$destination = new MediaWrite(
    adapter: 'spatie', field: 'contract', record: $savedRecord,
    spatie: new SpatieOptions(collection: 'contracts'),
);
$receipt = FlexMedia::staging()->commit($lease->id, $owner, $destination);
```

The destination must retain the lease's configured target and policy. Bind the newly saved record when promoting a create-form lease.

## Check status

```php
$status = FlexMedia::staging()->status($lease->id, $owner);
```

Use this in an authorized application flow. An ordinary form validation failure should leave pending data available for a corrected submission.

## Abandon an unsaved file

```php
FlexMedia::staging()->abandon($lease->id, $owner);
```

Use explicit cancellation for abandonment. It does not delete an already committed permanent object. Browser close/unload is not relied on for cleanup; pending leases also have an expiry.

## Read staged content in your service

```php
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;

FlexMedia::staging()->withSource(
    $lease->id, $owner, $intent,
    function (MediaSource $source): void {
        // Consume the source synchronously in your application service.
        app(DocumentInspection::class)->inspect($source);
    },
);
```

`DocumentInspection` is an application-provided service. Do not retain a staged source beyond this callback.

## Recover an interrupted promotion

If a promotion already has a durable receipt, retry can verify and complete that operation. If the outcome is ambiguous, locate the persisted object independently and reconcile it server-side:

```php
$receipt = FlexMedia::staging()->recover(
    $leaseId, $owner, $originalIntent, $independentlyLocatedReceipt,
);
```

Recovery checks the owner, operation, target, record and content. Do not reconstruct a receipt from untrusted browser input. A missing receipt is not proof that a write never happened.

## Rollback in a custom persistence flow

```php
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;

FlexMedia::staging()->rollback(
    $leaseId, $owner, $intent,
    function (MediaReceipt $receipt) use ($intent): bool {
        return FlexMedia::os()->compensate($receipt, $intent);
    },
    unbindRecord: true,
);
```

Use this only when your save flow has genuinely rolled back the new attachment. `unbindRecord: true` lets a failed create attempt later bind a different saved record. FLEX fields provide their own rollback integration on connections supporting rollback callbacks.

## Cleanup commands

```bash
php artisan flex-fields:prune-media-staging --dry-run
```

```bash
php artisan flex-fields:prune-media-staging
```

The janitor removes expired/abandoned temporary data and old receipt metadata within its mutation budget. It preserves uncertain promotions for reconciliation. Livewire temporary uploads retain their own cleanup configuration.

A programmatic sweep is also available:

```php
$report = FlexMedia::staging()->prune(dryRun: true);
```

Configure TTL, private disk, lock lifetime and batch size in [Media OS configuration](/docs/media-os-configuration#temporary-staging).

## Form fields

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;

FlexFileUpload::make('contract')
    ->mediaStaging()
    ->mediaAutoRecovery(false);
```

This keeps staging while disabling the field's automatic retry recovery. [Field save/recovery guide](/docs/file-upload-events-and-recovery).
