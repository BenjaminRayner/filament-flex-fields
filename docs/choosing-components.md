---
title: "Add FLEX to your existing Filament forms"
sidebarTitle: "Choose your approach"
description: "Start with one FLEX component, keep your existing Filament fields and models, and connect reusable controls, media workflows or custom field groups as needed."
icon: "git-branch"
---

**Add the behavior you need to the form you already have.** FLEX controls can sit
alongside native Filament fields in your existing Resource. Start with one
specialized input, then reuse its configuration and connected workflows wherever
your application needs them.

| What you want to build | Start here |
| --- | --- |
| A signature, schedule, searchable picker or other specialized input | Configure the matching FLEX control in your existing Resource |
| A form that already uses native inputs and selects | Keep those fields and add FLEX where it provides the behavior you need |
| A gallery stored in your existing related table | Follow the [relational gallery recipe](/docs/file-upload-gallery-recipe) |
| A domain control combining existing inputs | [Compose FLEX children](/docs/extensions/composition-patterns) and reuse their configuration, renderers and resources |
| Configurable product, CRM or tenant attributes | Use optional [managed field groups](/docs/flex-field-groups) |
| A completely bespoke interaction | Extend a control or create a custom field for that requirement |

## Reuse more than the appearance

FLEX connects configurable controls with a shared instance lifecycle and media
workflows. Repeated fields share resources, optional features follow each
instance, and changing tabs or nested dialogs retain the resources still in use.

This builds on Filament's existing form and extension capabilities, including
on-demand assets and optional Spatie integration. The benefit is a connected
implementation you can configure and reuse across your application's workflows.

## Configure with PHP or your AI assistant

Use the component's PHP examples directly, or give your assistant the
[optional FLEX skill and installed API discovery tools](/docs/ai-assisted-development).
They help it configure existing controls and integrations using the installed
package. Check generated configuration by saving, reopening and validating real
values, just as you would with manually written application code.

## Connect only the workflows you need

- **Existing models:** ordinary fields bind to their intended attributes. Managed
  JSON field groups are a separate, optional workflow.
- **Browser resources:** FLEX uses a shared runtime and base styles, then loads
  component dependencies according to active use. Built-in controls declare
  those requirements; you configure their features.
- **Media:** choose your storage and access rules. Enable image encoders, workers
  or Spatie packages for the processing and integration options you select.
- **Large catalogs:** combine virtualized rows with scoped, paginated providers
  to control both rendering and fetched results.
- **Updates:** follow the [deployment guide](/docs/updating) and verify the
  workflows your application uses when upgrading.

Start with [one field and a real saved value](/docs/first-component). For optional
services and commercial use, see [Requirements & integrations](/docs/requirements)
and [Licensing](/docs/licensing).
