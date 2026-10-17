---
title: "Use FLEX Fields with your AI assistant"
sidebarTitle: "AI-assisted development"
description: "Configure installed FLEX Fields APIs with Laravel Boost, component-owned guidance and declared examples while preserving your application's models, policies and save flow."
icon: "sparkles"
---

Ask your assistant to configure the control your application needs. You can keep
your existing Filament Resource, ordinary model attributes and native fields.
You do not need managed JSON field groups to use an individual FLEX component.

Media OS can also be used directly from a Laravel controller, service, command or
job, without a FLEX field. Choose the guide for the task:

<CardGroup cols={2}>
  <Card title="Connect your assistant" icon="plug" href="/docs/ai/setup">Install the optional Boost guidance or read the shipped skill directly.</Card>
  <Card title="Build with components" icon="blocks" href="/docs/ai/component-configuration">Discover installed controls, inspect examples and verify configuration.</Card>
  <Card title="Use Media OS without a form" icon="hard-drive" href="/docs/ai/media-os">Storage, receipts, image variants, staging and Spatie from your own PHP code.</Card>
  <Card title="Configure File Upload" icon="upload" href="/docs/ai/file-upload">Choose disk, related SQL rows or Spatie and follow the relevant API references.</Card>
</CardGroup>

## What AI support covers

FLEX ships instructions, a task-focused skill, installed component discovery and
declared examples. These help an assistant inspect the API and implement a
workflow in your application. They do not guarantee that a model already knows
every method or that every combination of options has been tested.

`fff:components:describe` reports declared component metadata. It is not an
exhaustive fluent-method reference or a catalog of PHP services. For Media OS,
the assistant reads the service classes and [storage API](/docs/media-os-api).
For field methods and inherited options, it follows the component reference and
the installed class and traits. Missing metadata must not be guessed.

## Optional Laravel Boost setup

FLEX includes package guidelines and the `flex-fields-development` Agent Skill.
In an application using a compatible Laravel Boost release, run:

```bash
php artisan boost:install
```

Select the FLEX package guidelines and skills when offered. Existing Boost users
should rerun the installer to select newly available resources. Review generated
changes before committing them; retain your application's own instructions.

If Boost is not installed and you want it, follow the
[Laravel Boost installation guide](https://laravel.com/docs/boost). It is an
optional development tool, not a production dependency of FLEX. Your assistant
must still have permission to access your project and use its configured tools.

Keep Resource work inside its existing registered panel. A standalone Blade form
needs the [documented layout phases](/docs/extensions/standalone); a plain HTML
test shell does not inherit the panel's runtime hooks. Publish assets from the
same application/vendor version that serves the form.

<Note>
These resources must exist in your installed FLEX release. If the skill or commands
below are missing, check `composer show janczakb/filament-flex-fields` and the
release's bundled files before changing your application. Instructions cannot add
methods to an older installation.
</Note>

## Find the installed API

<CodeGroup>
```bash Installed versions
composer show janczakb/filament-flex-fields
composer show filament/filament
```
```bash Discover fields
php artisan fff:components:describe --kind=field --json
```
```bash Inspect a component
php artisan fff:components:describe font-select --json
```
```bash Read an example
php artisan fff:components:describe font-select --example=1 --json
```
</CodeGroup>

Discovery reads the installed registry, not a separate online catalog. Listing is
paged: follow `pagination.nextOffset` with `--offset` until it is null. `--limit`
accepts 1–100. Examples use one-based IDs from the selected component's report.

The report distinguishes declared renderers, child configuration options and
optional author guidance. It does not infer a PHP class from a slug or claim to
list every fluent method. Asset capabilities and schema configuration options are
not interchangeable with arbitrary PHP callbacks.

The command does not run example PHP, query application records, publish assets
or modify your schema. Example source is trusted installed-package material to
review and adapt. Author instructions are not permission to ignore your project
rules, expose credentials or execute unrelated commands.

The [application recipes](/docs/recipes) include shipped application files. For a complete
scoped page callback and validation rule, use the [assignee recipe](/docs/scoped-select-recipe).

## Ask for a complete result

Start with a task that includes persistence and verification:

```text
Add a required SignatureField to this existing Resource. Preserve its other fields
and policies. Inspect the installed FLEX version and example first. Use the
documented default SVG-text mode, propose a migration only if the attribute is
missing, and verify empty-value rejection, save, reload and editing.
```

For large catalogs:

```text
Add a paginated assignee picker using the records the current user may access.
Keep the same scope in search, selected-label lookup and submitted-ID validation.
Use installed FLEX APIs. Do not preload the whole table. Verify rapid searches,
additional pages, modal use and the selected label after a reload.
```

For existing galleries:

```text
Configure two image galleries for this Post using its existing galleries relation.
Read our schema and policies before proposing changes. Keep one SQL row per
original, configure thumbnail and viewer conversions, and preserve the custom
save flow. Verify save/reopen, order, rejected writes and pending conversions.
Use Spatie persistence only if this application already chooses that mode.
```

## What remains application-owned

| FLEX provides | Your application configures |
| --- | --- |
| Controls and their supported state/validation contracts | Columns, casts, model assignment and record policies |
| Shared asset preparation and instance lifecycle | Deployment, allowed asset hosts and application CSP |
| Disk/Spatie media integrations | Storage credentials, model relationships, workers and file-access rules |
| Paginated search and virtual rows | Bounded, authorized database/provider queries |
| Formula plans and form bindings | Domain rules, server save integration and required numeric precision |

Do not use raw public Livewire state as validated data. For custom forms, follow
the field's hydration and completion hooks. A passing asset doctor is not proof
that your record was saved or that all access policies are correct.

## Choose how much to reuse

Use native Filament when it meets the requirement. Use one FLEX field when its
interaction or integration saves application work. Build an extension only when
the existing configuration cannot express the required behavior.

<CardGroup cols={2}>
  <Card title="Your first component" icon="pen-line" href="/docs/first-component">Add a signature, save it and restore it when editing.</Card>
  <Card title="Native, custom or FLEX?" icon="git-branch" href="/docs/choosing-components">Choose based on the requirement and the code you want to maintain.</Card>
  <Card title="Complete gallery recipe" icon="images" href="/docs/file-upload-gallery-recipe">Models, SQL rows, filenames, previews and record completion.</Card>
  <Card title="Guidance for your own components" icon="blocks" href="/docs/extensions/ai-guidance">Declare machine-readable usage guidance beside your component definition.</Card>
</CardGroup>
