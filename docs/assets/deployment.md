---
title: "Publish & diagnose assets"
description: "Publish the prebuilt FLEX assets and inspect release consistency and component dependency requirements."
icon: "package-check"
---

FLEX ships prebuilt CSS and JavaScript. Publish the files after installing or updating the package; a frontend build is not required for ordinary application use.

```bash
php artisan filament:assets
```

Deploy the PHP package and its matching browser assets together. Keep earlier release files while existing pages or application nodes still use them. See [Updating & deployment](/docs/updating) for the complete rollout.

## Verify a deployment or investigate a control

The included components declare their resource requirements for you. Use these
commands to verify a release during deployment or investigate a specific setup
issue; they are not steps you repeat when adding each field.

<CodeGroup>
```bash Published release
php artisan fff:assets:doctor --published --json
```
```bash Component dependencies
php artisan fff:assets:explain select-field
```
</CodeGroup>

**Doctor answers “Do these published files match the installed release?”**
**Explain answers “Why does this component need these resources?”** Use the
component identifier from its registration, such as `select-field` above.

For a particular open field or dialog, the [instance inspector](/docs/extensions/diagnostics)
shows retained resources and initialization states. Start with
[Troubleshooting](/docs/troubleshooting) if you need help choosing the right check.

Versioned asset identities support repeat visits and cache reuse. Preserve compatible releases while open documents or application nodes still refer to them; [Updating & deployment](/docs/updating) explains the release workflow.

For CDN origins, integrity and restrictive policies, use [asset delivery](/docs/extensions/asset-delivery).



## Continue reading

[Asset Manager overview](/docs/asset-loading) · [Resource sharing](/docs/assets/sharing) · [Activation & cleanup](/docs/assets/lifecycle) · [Performance](/docs/assets/performance) · [Publish & diagnose](/docs/assets/deployment)
