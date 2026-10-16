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

## Inspect resource requirements

<CodeGroup>
```bash Published release
php artisan fff:assets:doctor --published --json
```
```bash Component dependencies
php artisan fff:assets:explain select-field
```
</CodeGroup>

The doctor checks published release consistency. The explain command describes declared resources and dependencies; it is not a browser timing benchmark. Use the component identifier from its registration, rather than guessing from its PHP class name.

Versioned asset identities support repeat visits and cache reuse. Preserve compatible releases while open documents or application nodes still refer to them; [Updating & deployment](/docs/updating) explains the release workflow.

For CDN origins, integrity and restrictive policies, use [asset delivery](/docs/extensions/asset-delivery). Normal forms should not include playground-only examples or catalog resources.



## Continue reading

[Asset Manager overview](/docs/asset-loading) · [Resource sharing](/docs/assets/sharing) · [Activation & cleanup](/docs/assets/lifecycle) · [Performance](/docs/assets/performance) · [Publish & diagnose](/docs/assets/deployment)
