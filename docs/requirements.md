---
title: "FLEX Fields requirements and Spatie integrations"
sidebarTitle: "Requirements & integrations"
description: "Check FLEX Fields v4 server requirements, required Composer dependencies, optional Spatie packages, queue processing and browser device capabilities."
icon: "plug-zap"
---

Install the dependencies required by the package, then prepare only the services used by the features you enable. A default signature pad and a queued, Spatie-backed image workflow have different requirements.

## Server requirements

| Dependency | Package constraint or setup |
| --- | --- |
| PHP | `^8.3` |
| Filament | `^5.0`; verified stable baseline 5.9.0 |
| Laravel and Livewire | Compatible with the installed Filament release |
| FLEX Fields | The v4 release line for these guides |
| Node.js in production | Not required for normal use of prebuilt FLEX assets |

The declared Composer constraint and the verified baseline are different. A constraint permitting a version is not evidence that every permitted framework version and configuration has been exercised. For a new installation, use the verified baseline or a later compatible stable Filament release.

Composer checks constraints and resolves your application's dependencies. Keep its security advisory checks enabled. [Installation](/docs/installation) includes the v4 constraint and panel setup.

### Packages Composer installs

| Required package | Purpose |
| --- | --- |
| `filament/filament` | Panel, schemas and form integration |
| `janczakb/blade-hugeicons` | The icon integration used by FLEX |
| `giggsey/libphonenumber-for-php` | Server-side phone-number handling |
| `prodstarter/filament-notification-center` | Notification-center integration |

These are package dependencies even if a particular form does not use the associated feature. Browser resource selection is a separate mechanism; it does not remove required PHP packages from Composer installation.

## Optional Spatie integrations

| You want to use | Install and prepare | Guide |
| --- | --- | --- |
| Media Library-backed uploads | `spatie/laravel-medialibrary`, the matching Filament Media Library plugin, a media-capable model, collections and conversions | [Media OS with Spatie](/docs/media-os-spatie) |
| Related tags | `spatie/laravel-tags`, the model's tag trait and field relationship configuration | [Tags Field](/docs/tags-field) |
| Spatie-backed translated attributes | `spatie/laravel-translatable`, the model trait and translated attribute list | [TranslatableFields](/docs/translatablefields) |
| Model-driven slug generation | `spatie/laravel-sluggable`, the model trait and slug options | [Slug fields](/docs/slugfield-and-titleslugfield) |

Use versions compatible with your application and the selected integration guide. Installing a package alone does not configure the model or activate the field integration.

Plain tag arrays, JSON translation maps, ordinary slug controls and disk-backed media have their own modes that do not require those Spatie packages. If you opt into Spatie mode, complete its setup before using that mode.

## Feature-specific infrastructure

| Feature | Additional requirement | Start here |
| --- | --- | --- |
| Disk-backed files | Configured Laravel disk, credentials where needed, visibility and URL/access policy | [Media OS configuration](/docs/media-os-configuration) |
| S3 storage | A working S3-compatible disk and its filesystem adapter/configuration | [Upload storage](/docs/file-upload-storage-and-validation) |
| Native queued image conversions | Queue connection, running worker and access to source/output storage | [Native image processing](/docs/media-os-images) |
| Spatie queued conversions | Model conversion configuration and a worker for its configured queue | [Spatie conversions](/docs/media-os-spatie) |
| Image encoding | Server image-processing support for your chosen formats | [Image versions](/docs/media-os-images) |
| Staging and journal cleanup | Laravel's scheduler running the registered cleanup tasks | [Media lifecycle](/docs/media-os-lifecycle) |
| Browser Whisper transcription | Separately installed, compatible speech runtime | [Audio runtime setup](/docs/audiofield#installing-the-whisper-runtime) |
| Maps and geocoding | Selected provider and its required credentials | [Map Picker](/docs/mappickerfield) |

Synchronous processing does not need a queue worker. Selecting queued processing does not start one. The worker must reach the configured files, including private or remote storage where applicable.

## Browser and device features

| Feature | What determines availability |
| --- | --- |
| Camera and microphone capture | Secure context, browser permission and an available device |
| Advanced camera controls | The selected track's capabilities and browser APIs; zoom, torch and focus are hardware-dependent |
| Voice recording | Recording API support and supported output formats |
| Speech dictation | Browser recognition API, language and service availability |
| Barcode scanning | Native detector support or the configured ZXing fallback |
| Image-editor export | Browser encoding support for the chosen output format |

FLEX camera controls follow detected capabilities. A phone having a camera does not guarantee that its browser exposes torch, focus, ISO or optical zoom. Test the actual capture workflow on the devices your users rely on.

Use HTTPS or an allowed local origin and request the required permission. A denied permission is different from an unsupported API or an unavailable device. [Troubleshooting](/docs/troubleshooting#camera-or-microphone-is-unavailable) separates those cases.

The core loading workflow has been exercised in Chromium, Firefox and WebKit. That does not certify every optional control, custom theme and physical device combination.

## Delivery policies and your own extensions

For CDN delivery or restrictive content security policies, follow [asset delivery](/docs/extensions/asset-delivery). Framework scripts, permitted origins and integrity settings must work together; one FLEX setting is not a whole-application CSP guarantee.

Extension authors should also follow the [package dependency](/docs/extensions/package-dependencies) and [asset compatibility](/docs/extensions/compatibility-assets) contracts.

<CardGroup cols={2}>
  <Card title="Install the package" icon="package-plus" href="/docs/installation">Configure Composer, your panel and publication.</Card>
  <Card title="Understand the page cost" icon="network" href="/docs/asset-loading">Separate installation, download, initialization and data work.</Card>
</CardGroup>
