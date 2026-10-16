---
title: "Update and deploy FLEX Fields v4"
sidebarTitle: "Updating & deployment"
description: "Update FLEX Fields v4 with Composer, publish matching prebuilt assets, preserve configuration and deploy with the workers and migrations your application uses."
icon: "refresh-cw"
---

An update needs a compatible package version **and matching browser assets**. FLEX includes prebuilt CSS and JavaScript, so a normal application update does not require rebuilding the package with Node.js.

## Choose your update path

| Your deployment | Asset publication |
| --- | --- |
| Composer scripts run `filament:upgrade` or `filament:assets` | Keep that existing hook; do not add a duplicate |
| No Filament publication hook | Run `php artisan filament:assets` after updating |
| Composer runs with `--no-scripts` | Publish explicitly in the deployment workflow |
| A separate public release store | Publish and verify the selected public root |

## Update within v4

<Steps>
  <Step title="Read the release notes">
    Check [Changelog](/docs/changelog) for requirements, configuration changes and migrations. Keep the application's Composer constraint on the intended v4 release line.
  </Step>
  <Step title="Resolve the new package version">

```bash
composer update janczakb/filament-flex-fields
```

Test and commit the resulting lockfile through your normal release process. Production deployments generally install that reviewed lockfile with `composer install`, rather than resolve a different dependency set on each node.

  </Step>
  <Step title="Publish matching assets">

If publication is not already handled by the application's Composer hook:

```bash
php artisan filament:assets
```

FLEX publishes its resources after successful Filament asset publication. Normal page requests and unrelated commands do not republish package files.

  </Step>
  <Step title="Finish your application's deployment">
    Apply planned migrations, rebuild the caches your application uses and restart relevant long-running processes. Then verify the release and a representative create/edit workflow.
  </Step>
</Steps>

## Set up automatic publication once

Composer runs scripts from **your application's** `composer.json`, not from installed dependency packages. If `filament:upgrade` is already present, it includes Filament asset publication.

If neither publication command is configured, merge this entry into the application's existing `post-autoload-dump` list:

```json composer.json
{
  "scripts": {
    "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
      "@php artisan package:discover --ansi",
      "@php artisan filament:assets --ansi"
    ]
  }
}
```

Retain your other script entries. [Composer's script documentation](https://getcomposer.org/doc/articles/scripts.md) explains application-owned hooks.

<Note>
  `composer install --no-scripts` and `composer update --no-scripts` skip these hooks. Include explicit publication when your deployment uses either option.
</Note>

## Preserve your settings

Your published configuration and translations remain application-owned. Compare new settings with the release notes and add only the overrides you need. Do not routinely overwrite those files with `vendor:publish --force`.

After changing published configuration, rebuild configuration cache through your normal Laravel deployment. Keep component configuration and model storage contracts aligned when a release changes a value format.

## Plan database changes

Individual fields use application attributes; they do not require managed field-group storage. However, FLEX registers package migrations, and your application's migration step includes pending package migrations even if the field-group resource is disabled.

Review pending migrations and the release notes before running the normal production migration step:

```bash
php artisan migrate --force
```

Composer does not itself migrate the database or silently rewrite saved custom-component values. Use an explicitly planned value migration when a storage contract changes. See [database setup](/docs/installation#database-setup) and [extension value migrations](/docs/extensions/migrations).

## Restart only the processes you use

<CodeGroup>
```bash Queue workers
php artisan queue:restart
```
```bash Laravel Octane
php artisan octane:reload
```
</CodeGroup>

Use the appropriate command for the processes your application actually runs. A worker executing old PHP must not continue processing new jobs indefinitely after deployment. Ensure queue workers and web nodes can reach the same configured media storage.

## Keep PHP and asset releases together

Deploy the reviewed code, lockfile and matching published assets as one release. In a multi-node deployment, each active node needs a consistent package and asset set.

Retain older immutable asset releases while open browser documents or application nodes still reference them. Pruning is explicit. An open page can retain already evaluated JavaScript; a reload may be needed after a runtime change.

Do not add cache-busting timestamps to URLs or delete old releases indiscriminately. For release stores and delivery policies, use [extension deployment](/docs/extensions/deployment) and [asset delivery](/docs/extensions/asset-delivery).

## Verify the deployed release

```bash
composer show janczakb/filament-flex-fields
php artisan fff:assets:doctor --published --json
```

Check for missing or mismatched files, then open an actual form. Create a record, reopen it, edit it and try a relevant validation failure. If you use queued media, verify that its conversion jobs finish too.

<Accordion title="I publish to a separate public asset store">

```bash
php artisan fff:assets:publish --public-root=/path/to/public
php artisan fff:assets:doctor --published --public-root=/path/to/public
```

Use the public root for the release you are deploying. Verify that the web server or CDN serves that same published release.
</Accordion>

<Accordion title="I use the optional Whisper runtime">

```bash
php artisan fff:whisper:install --check
```

If the release changes the pinned runtime, install it with `php artisan fff:whisper:install`. Ordinary playback does not require transcription. See [AudioField runtime setup](/docs/audiofield#installing-the-whisper-runtime).
</Accordion>

## Prepare a rollback

Keep the previous application release and its matching assets available. Restoring code alone is not enough if its browser files have been removed.

Database migrations and changed saved-value contracts need their own rollback or forward-recovery plan. Restoring the package version does not automatically reverse database or media changes.

<CardGroup cols={2}>
  <Card title="Changelog" icon="history" href="/docs/changelog">Review the changes for your installed version.</Card>
  <Card title="Troubleshooting" icon="life-buoy" href="/docs/troubleshooting">Diagnose publication, configuration and processing failures.</Card>
</CardGroup>
