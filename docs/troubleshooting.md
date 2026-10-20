---
title: "Troubleshoot FLEX Fields setup and saved values"
sidebarTitle: "Troubleshooting"
description: "Resolve FLEX Fields v4 asset loading, unsaved form values, optional integrations, pending image conversions and camera or microphone access."
icon: "life-buoy"
---

Start with the symptom, make one targeted check, then confirm the result. These steps distinguish a setup problem from a field configuration, storage or device problem.

## Find the right check

| You see | Start with |
| --- | --- |
| Missing styles or a control that will not open | [Panel and browser assets](#a-control-is-unstyled-or-does-not-open) |
| A value disappears after saving | [Database and save workflow](#the-value-does-not-save) |
| A missing Spatie class | [Selected integration](#an-optional-spatie-class-is-missing) |
| Only one page of Select results | [Data provider](#select-shows-only-the-first-results) |
| An original image or a pending thumbnail | [Conversion and preview configuration](#a-thumbnail-is-missing-or-stays-pending) |
| No editor button | [Opt-in feature settings](#an-editor-button-is-missing) |
| Camera or microphone unavailable | [Browser and device access](#camera-or-microphone-is-unavailable) |
| A setting appears unchanged | [Scope and configuration cache](#a-setting-change-has-no-effect) |

## A control is unstyled or does not open

**Check the panel first.** Confirm that its provider registers `FilamentFlexFieldsPlugin::make()`. Adding the plugin to a different panel does not configure the panel you are viewing.

Then check publication:

```bash
composer show janczakb/filament-flex-fields
php artisan fff:assets:doctor --published --json
```

If the report identifies missing or mismatched published files, publish the installed build:

```bash
php artisan filament:assets
```

Reload the affected page and try the control. The files must also be reachable by the browser: inspect failed network requests and the first relevant JavaScript error. A passing publication report cannot fix a web server returning 404, a blocked origin or an incompatible custom script.

The doctor explains the effect of a detected build or delivery problem and gives
a recommended next step. To attach its result to a support request, use:

```bash
php artisan fff:assets:doctor --published --share
```

This produces a redacted JSON report with the release identity, validation counts
and repair advice. It excludes raw exception messages, local paths, file URLs,
extension names and definition inventories. Keep the ordinary `--json` report
locally when you need the exact affected file. `--share` cannot be combined with
`--definitions` or `--locations`.

The check does not publish or repair files. A successful report covers the assets
and delivery settings it inspected; it does not confirm that queue workers,
storage credentials, media conversions or browser network access are working.

**Expected result:** the component has its styling and responds to interaction. If it still fails, include the failed resource URL and relevant error in your report. See [Installation](/docs/installation) and [asset delivery](/docs/extensions/asset-delivery).

## The value does not save

Check the complete path rather than changing the field's appearance:

1. The field name matches the attribute or relationship you intend to save.
2. The database has the appropriate column type.
3. The model's assignment policy allows the intended attribute.
4. Structured values have the appropriate cast; a default signature string does not have an array cast.
5. Your save flow reads `$this->form->getState()` rather than writing raw public state.
6. The field is not intentionally excluded from persistence, for example with `dehydrated(false)`.
7. A relationship uses its configured relationship save flow instead of an unrelated scalar column.

**Expected result:** after a successful save, reopening the record restores the same value. The [Saving & validation guide](/docs/data-and-validation) includes text, JSON, temporal values and a complete custom save example.

If validation prevents saving, resolve the reported rule. Do not bypass validation to make the write succeed.

## An optional Spatie class is missing

Compare the chosen field class or storage mode with [Requirements & integrations](/docs/requirements#optional-spatie-integrations).

- If you intend to use Spatie, install the compatible package and complete the model and field setup from its guide.
- If you intend to use plain arrays, JSON translations or disk storage, use the corresponding non-Spatie configuration.
- For Media Library uploads, check the matching Filament plugin as well as Media Library and the media-capable model.

**Expected result:** the configured mode opens and saves without a missing-class error. Installing every optional package is not a substitute for choosing the correct mode.

## Select shows only the first results

Check whether you supplied a fixed options array or a paginated provider. **Virtualization changes the rendered row window; it does not fetch another result page.**

For a provider, check the next-page cursor, search handling and selected-value resolution against the [Select guide](/docs/selectfield). Use consistent identifier types across returned options, saved values and model attributes.

**Expected result:** a larger result set offers subsequent results and reopening restores the selected label. Do not load an entire large catalog merely to conceal a paging error.

## A thumbnail is missing or stays pending

These situations have different fixes:

| Check | Action | Expected result |
| --- | --- | --- |
| The named preview conversion was never defined | Define it in native image configuration or the Spatie model | New eligible media can generate that named conversion |
| Processing is queued and no worker is running | Start the configured worker and check failed jobs | Pending jobs complete and their output becomes available |
| The job cannot read or write storage | Fix credentials, access and the disk used by the worker | The job can read its source and store the conversion |
| Preview selection uses a different conversion name | Match `previewConversion()` to the generated name | The configured variant is used when available |
| Old files predate the conversion | Use the relevant regeneration workflow deliberately | Those files obtain the newly configured variant |

Selecting `previewConversion('thumb')` does not create `thumb`. A pending or unavailable conversion may follow the configured fallback; seeing the original is not proof that the conversion job succeeded.

See [native image processing](/docs/media-os-images), [Spatie media](/docs/media-os-spatie) and [upload image previews](/docs/file-upload-images-and-sources).

### The upload finished but the record failed to save

Temporary transfer, permanent storage and record acceptance are separate stages. Resolve the form/database failure, then follow the configured staging and retry workflow. A custom save path needs its [completion hooks](/docs/file-upload-events-and-recovery).

Do not delete staging files indiscriminately while retries or record acceptance are pending. Review [Media OS lifecycle](/docs/media-os-lifecycle) for retention and cleanup.

## An editor button is missing

Image editing and information editing are separate opt-in features. Check:

- Whether `imageEditor()` or the information-editor configuration is enabled on **this** field.
- Whether the file type is eligible for the selected editor.
- Whether the field is editable in the current operation.
- Which information fields and languages you configured.

**Expected result:** an eligible editable file exposes the configured action. A button omitted for an unsupported file or disabled feature is intentional. See [image features](/docs/file-upload-images-and-sources) and [metadata configuration](/docs/file-upload-metadata).

## Camera or microphone is unavailable

Check these in order:

1. Use HTTPS or an allowed local origin.
2. Confirm that the browser supports the capture API used by the feature.
3. Check site and operating-system permissions.
4. Check that the device is available and not blocked by another application.
5. For recording, check supported output formats. For hardware settings, check the selected camera's detected capabilities.

**Expected result:** capture starts after permission is granted, and closing the capture interface releases its stream. Not every camera exposes torch, zoom, focus or ISO to the browser.

Speech recognition is a separate capability from microphone recording. See [browser requirements](/docs/requirements#browser-and-device-features) and the selected capture component's guide.

## A setting change has no effect

First check its scope: application default, panel option or explicit field setting. A field's explicit setting may replace an application default. Verify that you changed the panel that serves the page.

If you published and edited Laravel configuration, clear or rebuild the configuration cache according to your environment:

<CodeGroup>
```bash Local development
php artisan config:clear
```
```bash Deployment using cached configuration
php artisan config:cache
```
</CodeGroup>

Do not overwrite application configuration with `vendor:publish --force` to troubleshoot a single setting.

### Dates, languages or direction look wrong

Compare the interface locale, content locale list, display format, storage format, timezone and RTL setting separately. Changing one does not automatically change the others. Date/time `timeZone()` does not convert stored wall time.

**Expected result:** the field follows the selected configuration and restores the intended saved value. See [Configuration & styling](/docs/configuration#languages-dates-and-direction) and [date/time formats](/docs/date-time-configuration#formats-language-and-timezone).

## A problem appeared after deployment

Check that web nodes, published browser assets and long-running PHP processes use a consistent release. Retain old assets while documents still reference them, and follow [Updating & deployment](/docs/updating) for publication and restarts.

A browser document can retain evaluated code from an earlier release. Reloading that document may be necessary; arbitrary timestamp changes to asset URLs do not fix an inconsistent deployment.

## Report a reproducible issue

Include the package, PHP, Filament, Laravel and Livewire versions; the smallest field configuration that reproduces it; create/edit context; relevant integrations; browser/device; steps; and expected versus actual result.

Include relevant diagnostics and the first associated error. Remove credentials, private file URLs and customer data. Report the issue through [GitHub](https://github.com/janczakb/filament-flex-fields/issues).
