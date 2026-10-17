---
title: "Native Filament, custom code or FLEX?"
sidebarTitle: "Choose your approach"
description: "Decide when to keep native Filament, configure a FLEX component or build a custom control based on integration, persistence and maintenance requirements."
icon: "git-branch"
---

Start with the requirement, not the catalog size. A simple, one-off control may
need only native Filament or a small custom field. A reusable interaction with
media, large catalogs or dynamic surfaces has more integration work to consider.

| Situation | A useful starting point |
| --- | --- |
| A standard input, select or upload already meets your needs | Keep the native Filament component |
| A small application-specific interaction has no reusable workflow | A custom field, including AI-assisted implementation |
| A FLEX control already covers the required behavior | Configure that field in the existing Resource |
| A domain layout combines existing controls | Compose real children before copying their implementations |
| Users need configurable custom attributes | Evaluate optional managed field groups separately from ordinary attributes |

## Compare the complete workflow

For each approach, include the work needed to validate, save, reopen, authorize
and change the result. A screenshot proves appearance, not persistence or the
behavior of repeated instances inside a modal.

Filament already provides custom fields, on-demand asset loading, image editing
and an official Spatie Media Library integration. FLEX's relevant addition is
the behavior and configuration of its particular controls, shared instance
lifecycle and connected media workflows. Choose based on the part you actually
need, not a claim that every underlying feature is exclusive to this package.

## AI can configure either approach

An assistant can build a custom field or configure an existing one. Give it the
same complete requirements and use actual save/reopen and invalid-input checks.
When using FLEX, its [optional skill and discovery commands](/docs/ai-assisted-development)
help the assistant find the installed API rather than copy the runtime into your
application. This does not guarantee that every generated integration is correct.

## Understand the costs you retain

- Installing FLEX adds its required Composer packages and shared browser runtime.
  Selective loading does not make that baseline zero.
- Media workflows still need configured storage, access policies and, where
  selected, image encoders and queue workers.
- Virtualization limits DOM rows; it does not make an unbounded database query cheap.
- A reusable implementation reduces private copies to maintain. You still need
  to review updates and test your application's workflows.
- Source availability and commercial rights are separate. Read the applicable
  [license](/docs/licensing) for your use, and the [requirements](/docs/requirements)
  for the features you enable.

Try [one field with a real saved value](/docs/first-component) or the
[relational gallery recipe](/docs/file-upload-gallery-recipe) before adopting a
larger part of the library. You can keep unrelated native fields and existing
application models.
