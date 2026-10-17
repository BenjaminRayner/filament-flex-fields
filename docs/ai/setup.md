---
title: "Connect your AI assistant"
sidebarTitle: "Setup"
description: "Enable FLEX Fields guidelines and its Agent Skill, check the installed API and keep your application's own instructions."
icon: "plug"
---

Your assistant needs access to the project, the installed package and the relevant
instructions. Installing FLEX does not automatically give an unrelated chat or
model access to your application.

## With Laravel Boost

FLEX includes package guidelines and the `flex-fields-development` Agent Skill.
Use a compatible [Laravel Boost installation](https://laravel.com/docs/boost), then run:

```bash
php artisan boost:install
```

Select the FLEX guidelines and skill when offered. If you already use Boost,
rerun its installer after updating FLEX to pick up newly available materials.
Review generated instructions and keep your application's existing rules.

The skill loads references by task: fields, pickers, uploads, standalone Media OS,
calculations and extensions. It does not need to load the entire manual into
every conversation.

## Without Laravel Boost

Ask a project-aware assistant to read the installed package's
`resources/boost/skills/flex-fields-development/SKILL.md` and the reference relevant
to your task. The package is normally under
`vendor/janczakb/filament-flex-fields`; Composer path repositories can use another
location. Follow the editor's own skill/instruction setup if you want automatic
activation. FLEX does not configure every editor automatically.

Boost is optional. No AI provider, API key or model is required by FLEX in production.

## Verify the installed release

```bash
composer show janczakb/filament-flex-fields
php artisan fff:components:describe font-select --json
```

If the skill or command is missing, check the installed release before following
newer documentation. Component discovery is read-only and does not execute its
examples. Media OS services use their own PHP API rather than a component ID.

## A useful first prompt

```text
Read FLEX's installed development skill and the reference for this task.
Inspect our installed package version and actual public APIs before coding.
Preserve our models, authorization, panel registration and existing save flow.
Implement the requested workflow, run relevant tests, and report what was verified
and what remains untested. Do not guess methods or add optional integrations
unless the task needs them.
```

Continue with [components](/docs/ai/component-configuration), [standalone Media OS](/docs/ai/media-os)
or [File Upload](/docs/ai/file-upload).
