---
title: "Configure File Upload with AI"
sidebarTitle: "File Upload & Spatie"
description: "Give your assistant the correct FLEX upload references for disk storage, relational galleries, Spatie, conversions, metadata and custom save flows."
icon: "upload"
---

Start with where the files belong. The upload interface and the application's
database representation are separate decisions.

| Your application's storage | Ask the assistant to use |
| --- | --- |
| Model attribute containing disk paths | Disk-backed field with the matching attribute and cast |
| One related SQL row per original | Gallery hydration and save mapping for the existing relation |
| Spatie Media Library | FLEX's Spatie field adapter, model collections and native conversions |
| Import or API with no form | [Standalone Media OS](/docs/ai/media-os) |

## Find the requested configuration

The AI skill's media reference points to these guides. For a specific method,
check the installed class and traits, inherited Filament API and optional Spatie
version. The component discovery report is not a complete upload API listing.

| Configure | Reference |
| --- | --- |
| Method groups, defaults and inherited options | [Upload API](/docs/file-upload-api) |
| Disks, filenames, sizes and allowed content | [Storage and validation](/docs/file-upload-storage-and-validation) |
| Image sources, editor, conversions and previews | [Images and sources](/docs/file-upload-images-and-sources) |
| Alt text, captions, tags and locales | [Metadata](/docs/file-upload-metadata) |
| Existing models and older upload data | [Models and migration](/docs/file-upload-models-and-migration) |
| Posts, galleries, migrations and custom filenames | [Complete gallery recipe](/docs/file-upload-gallery-recipe) |
| Native Resource create/edit pages | [Resource recipe](/docs/file-upload-resource-recipe) |
| Website/API URLs and optional conversion rows | [Gallery API](/docs/file-upload-gallery-api) |
| Rejected saves, staging and record acceptance | [Events and recovery](/docs/file-upload-events-and-recovery) |
| Collections, conversions, responsive images and options | [Spatie integration](/docs/media-os-spatie) |

## Example: an existing relational gallery

```text
Configure FLEX File Upload for our existing Post -> galleries relationship.
Keep one SQL row per original and do not add a JSON upload column to Post.
Read the complete gallery and Resource recipes before editing.

Use our configured disk, a safe collision-resistant filename callback, a thumb
preview and a separate large viewer variant. Preserve our policies and transaction
flow. Restore existing files when editing and keep ordering after save/reload.
Verify uploads, invalid submitted paths, rejected saves and pending conversions.
```

## Example: Spatie collections

```text
Configure FLEX's Spatie upload adapter for our existing HasMedia model.
Use the photos collection and the model's thumb conversion. Keep the existing
custom Media model, path generator and Spatie queue settings.

Inspect the installed Spatie/FLEX APIs and document any missing setup. Do not
enable native disk conversions over the same files or create a duplicate gallery
table. Verify create/edit timing, collection ownership, ordering, pending previews
and the existing authorization policy.
```

## Check the complete save flow

Selecting `previewConversion()` does not define that conversion. Disk-backed
native conversions and Spatie conversions have different configuration. A custom
basename callback does not replace the configured directory. Match each method
to the chosen integration rather than transferring options blindly.

For custom disk-backed saves, follow the mounted field's completion contract
after a successful commit. Native Resource pages and Spatie relationships have
their own integration; avoid adding a second acceptance or persistence step.

AI can inspect these references and implement the requested setup. Every upload
option and provider combination is not automatically verified by enabling the
skill; test the exact configuration used by your application.
