---
title: "Use Media OS without a form"
sidebarTitle: "Standalone Media OS"
description: "Guide your AI assistant to use FLEX Media OS directly in Laravel services, controllers, commands and jobs, with disk storage or Spatie."
icon: "hard-drive"
---

**Media OS can run directly in your PHP application.** A controller, service,
command or job can store files, create image variants and use staging without
rendering `FlexFileUpload` or a Filament form.

The shipped AI skill includes a dedicated `references/media-os.md` guide for this
workflow. Component discovery describes rendered controls; it is not the service
API reference.

## Choose the right entry point

| Requirement | API | Read first |
| --- | --- | --- |
| Store a local file, stream or bytes | `FlexMedia::store()` | [Storage API](/docs/media-os-api) |
| Inspect, verify, sign a URL or delete an owned object | `FlexMedia::os()` | [Policies and receipts](/docs/media-os-api) |
| Store an original and synchronous variants | `FlexMedia::images()->store()` | [Image conversions](/docs/media-os-images) |
| Generate variants in a worker | `FlexMedia::queuedImages()` | [Background processing](/docs/media-os-images#background-conversions) |
| Receive a file before its final record is accepted | `FlexMedia::staging()` | [Staging and confirmation](/docs/media-os-lifecycle) |
| Store in a Spatie collection | `MediaWrite` with the `spatie` adapter | [Spatie setup and storage](/docs/media-os-spatie) |
| Build your own capture interface | Public browser helpers plus the server API | [Browser integration](/docs/media-os-browser) |

## Minimal service-level storage

This example uses a trusted, existing local file and a configured private Laravel
disk. It can run inside your application service; it creates no form field.

```php
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;

$receipt = FlexMedia::store(
    MediaSource::path(storage_path('app/imports/contract.pdf'), 'contract.pdf'),
    new MediaWrite(
        disk: 'local',
        directory: 'contracts',
        policy: new MediaPolicy(
            maxBytes: 10 * 1024 * 1024,
            mimeTypes: ['application/pdf'],
            visibility: 'private',
        ),
    ),
);

// Persist the owned reference using your application's model and save flow.
$disk = $receipt->disk;
$path = $receipt->path;
```

Permanent storage inspects the actual content against the policy. Your application
still authorizes the operation and persists its own record reference. The receipt
does not automatically create a gallery row or grant permission to read the file.

For a normal HTTP request, validate the `UploadedFile` and use its valid local
path with `MediaSource::path()`. `MediaSource::upload()` specifically accepts a
Livewire temporary upload. See the [HTTP request example](/docs/media-os-api#http-request-upload).

## Give the assistant a complete task

```text
Use the installed FLEX Media OS directly in our Laravel import service, without
adding a Filament field or browser interface. Read the standalone Media OS skill
reference and storage API first.

Import an authorized local PDF to our configured private disk. Apply MIME and
size policy, persist the returned disk/path and required metadata in our existing
document model, and provide an authorized expiring URL where supported.
Keep our record policies. Explain the database-versus-storage failure handling.
Verify accepted and rejected content, persisted references and private URL failure.
```

## Images and Spatie

For disk-backed images, define named `ImageConversion` variants and choose
synchronous or queued processing. Retain the returned bundle/receipt information
needed by your application. A queued variant may not exist yet; configure a
fallback and a worker with supported encoders.

For Spatie, use the adapter with a persisted `HasMedia` model and `SpatieOptions`.
Keep Spatie's collections, conversions and queue configuration. An extra table
for every conversion is not normally required. The [Spatie guide](/docs/media-os-spatie)
shows direct service storage as well as field integration.

## When storage happens before the record save

Use staging when the workflow needs it. Stage, promote and confirm are distinct
operations; confirmation belongs after the durable record reference commits.
A database rollback does not roll back disk/S3 writes. Derive lease ownership
from trusted server context and follow the documented recovery contract for
ambiguous outcomes.

Read [temporary uploads and recovery](/docs/media-os-lifecycle) before implementing
this flow. Do not copy an upload field's completion hook into a service that does
not mount that field.

Pure server-side storage needs no browser asset initialization. A custom capture
interface has a separate browser lifecycle and still needs server validation.
