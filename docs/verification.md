---
title: "Verify your integration"
sidebarTitle: "Verification & compatibility"
description: "Reproduce FLEX Fields installation, asset and application workflow checks and understand their limits."
icon: "clipboard-check"
---

A control opening in the playground does not prove that your application's value
persists, its access scope is correct or every target device is supported. Verify
the workflow you deploy with its actual versions, storage and policies.

## Framework support

FLEX v4 requires PHP `^8.3` and Filament `^5.6`. The repository CI declares PHP
8.3/8.4/8.5, historical Filament API fixtures for 5.6.0, 5.7.8 and 5.8.0, and
current stable 5.x. The 5.x development canary is non-blocking. A workflow's
configuration is not a passing result for a particular commit.

Check [the workflow runs](https://github.com/janczakb/filament-flex-fields/actions/workflows/flex-fields-ci.yml)
for the source revision you intend to use. Review the actual failed job and its
artifacts rather than assuming that a previous release's green run applies.
Use current patched dependencies in deployed applications; historical compatibility
fixtures do not make old upstream versions secure.

## In an installed application

```bash
composer show janczakb/filament-flex-fields
composer show filament/filament
composer show livewire/livewire
php artisan filament:assets
php artisan fff:assets:doctor
```

The doctor checks the asset installation. It does not submit your forms, certify
your record policies or start your queue worker. Follow the
[installation guide](/docs/installation) if publishing reports an error.

Publish native Filament scripts and FLEX assets from the **same application's
installed dependencies**. An isolated test fixture with another vendor version
must publish its own files; borrowing a development panel's public directory can
mix framework versions. The FLEX doctor checks FLEX artifacts, not the contents
of native Filament's JavaScript. Standalone layouts also need the
[explicit runtime layout phases](/docs/extensions/standalone).

Use [application recipes](/docs/recipes) for save/reopen checks. Test the configured
file disk, pending and failed conversions, unauthorized IDs, validation errors and
your own custom save actions. Keep sensitive form values out of diagnostic logs.

## Reproduce the repository checks

Use a development checkout of the exact Git revision. Tests and maintainer scripts
are excluded from Composer distributions; do not expect them under a production
application's `vendor` directory.

```bash
composer install
npm ci
npm run build
composer test
composer analyse
npm run test:js
npx playwright install chromium firefox webkit
node scripts/core/release-acceptance.mjs --phase=contracts
node scripts/core/release-acceptance.mjs --phase=browser --browser=chromium
node scripts/core/release-acceptance.mjs --phase=browser --browser=firefox
node scripts/core/release-acceptance.mjs --phase=browser --browser=webkit
node scripts/core/release-acceptance.mjs --phase=performance
```

Read each runner's receipt and per-check logs. Source hashes and a successful
probe identify the code and scenario checked; they do not certify every customer
page. Keep browser/runtime installation failures separate from failures after the
page starts. Never disable a failing engine or loosen a budget just to get green.

The isolated Composer composition/update/removal fixture is a separate check:

```bash
php scripts/compatibility/core-author-tooling.php --composer-install --composer-browser --filament-floor
```

## Measure the part you need

- Separate installation dependencies, base runtime, component resources and data queries.
- Compare native-only, FLEX enabled without a field, one field and repeated fields.
- Record cold and warm opens, request sizes, mounted option count and rapid-scroll behavior.
- Exercise inactive tabs, nested dialogs and actual removal. Closing a dialog is not always unmounting it.
- Keep recording decorations out of performance runs. CPU throttling is a documented test profile, not a prediction of every device.

The test `MinimalAdoptionCostTest` renders those four adoption cases in separate
PHP processes and writes its observations to `build/ai-adoption/minimal-cost.json`.
It measures PHP boot/render and HTML size in a Testbench fixture, not browser FPS,
production TTFB or total network transfer. Repeated fields retain distinct consumer
boundaries while declaring the same reusable resources.

For simple controls, native Filament may be the smaller choice. No general
time-saving percentage or superiority over AI-generated code is implied by these
checks. A fair comparison includes authorization, save/reopen, failure handling
and the cost of a subsequent requirement change.
